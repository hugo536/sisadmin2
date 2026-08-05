<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class CxcController extends Controlador
{
    private ReporteTesoreriaModel $tesoreria;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        parent::__construct();
        $this->tesoreria = new ReporteTesoreriaModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.tesoreria.ver');
        $this->registrarAuditoria('reporte_global_cxc');

        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['cliente'] = trim((string) ($_GET['cliente'] ?? ''));
        $f['estado_factura'] = trim((string) ($_GET['estado_factura'] ?? 'todos'));
        
        // --- AGREGAR ESTO: Capturar el tipo de tercero ---
        $f['tipo_tercero'] = trim((string) ($_GET['tipo_tercero'] ?? 'todos'));
        if (!in_array($f['tipo_tercero'], ['todos', 'cliente', 'distribuidor'], true)) {
            $f['tipo_tercero'] = 'todos';
        }

        // ---> LÍNEAS SUGERIDAS PARA MANTENER LA SIMETRÍA CON CXP
        $f['pagina'] = $pagina;
        $f['tamano'] = $tamano;

        if (!in_array($f['estado_factura'], ['todos', 'vencida', 'corriente'], true)) {
            $f['estado_factura'] = 'todos';
        }

        $accion = $_GET['accion'] ?? $_GET['exportar'] ?? '';

        // --- 1. PROCESAR DATOS BASE DESDE EL MODELO ---
        $datos = $this->tesoreria->obtenerCarteraMacroCxC($f);

        // --- 2. CÁLCULO DE KPIs GLOBALES EN TIEMPO REAL ---
        $total_cartera = 0;
        $total_vencido = 0;
        $total_por_vencer = 0;

        foreach ($datos['agrupados'] as $r) {
            $total_cartera += (float)$r['total_deuda'];
            $total_por_vencer += (float)$r['por_vencer'];
            $total_vencido += ((float)$r['mora_30'] + (float)$r['mora_60'] + (float)$r['mora_mas_60']);
        }

        $resumen = [
            'total_cartera'      => $total_cartera,
            'total_vencido'      => $total_vencido,
            'total_por_vencer'   => $total_por_vencer,
            'clientes_con_deuda' => count($datos['agrupados'])
        ];

        // --- 3. EXPORTAR CSV (Usando lista detallada) ---
        if ($accion === 'exportar_csv_cxc' || $accion === 'csv') {
            $filename = 'Reporte_Global_CXC_' . date('Ymd_His') . '.csv'; 
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            echo "\xEF\xBB\xBF"; 
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Cliente / Distribuidor', 'Documento Ref.', 'Emisión', 'Vencimiento', 'Total Emitido', 'Saldo Pendiente', 'Estado'], ',');
            
            foreach ($datos['detallados'] as $row) {
                fputcsv($output, [
                    $row['cliente'],
                    $row['documento_referencia'],
                    $row['fecha_emision'],
                    $row['fecha_vencimiento'],
                    number_format((float)$row['monto_total'], 2, '.', ''),
                    number_format((float)$row['saldo'], 2, '.', ''),
                    $row['estado']
                ], ',');
            }
            fclose($output);
            exit;
        }

        // --- 4. EXPORTAR EXCEL NATIVO (.xlsx) (Usando lista detallada) ---
        if ($accion === 'exportar_excel_cxc' || $accion === 'excel') {
            require_once BASE_PATH . '/vendor/autoload.php';
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            
            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Cuentas por Cobrar');

            // Configuración general
            $sheet->setShowGridlines(true); 
            $sheet->getColumnDimension('A')->setWidth(3); 

            // Títulos
            $sheet->getRowDimension(1)->setRowHeight(40);
            $nombreEmpresa = mb_strtoupper((string)($config['nombre_empresa'] ?? 'EMPRESA'));
            $sheet->setCellValue('B1', $nombreEmpresa . ' - REPORTE GLOBAL DE CUENTAS POR COBRAR (CXC)');
            $sheet->mergeCells('B1:H1');
            $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF0B5ED7');
            $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $sheet->setCellValue('B2', 'Periodo: ' . date('d/m/Y', strtotime($f['fecha_desde'])) . ' al ' . date('d/m/Y', strtotime($f['fecha_hasta'])));
            $sheet->mergeCells('B2:D2');
            $sheet->getStyle('B2')->getFont()->setItalic(true)->getColor()->setARGB('FF555555');

            // Cabecera de la tabla
            $filaInicioTabla = 4; 
            $cabeceras = [
                'B' => 'Cliente / Distribuidor', 
                'C' => 'Documento Ref.', 
                'D' => 'Emisión', 
                'E' => 'Vencimiento', 
                'F' => 'Total Emitido', 
                'G' => 'Saldo Pendiente', 
                'H' => 'Estado'
            ];
            
            $sheet->getRowDimension($filaInicioTabla)->setRowHeight(25);
            foreach ($cabeceras as $col => $texto) {
                $sheet->setCellValue($col . $filaInicioTabla, $texto);
            }

            $estiloEncabezado = [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1A1A1A']], 
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]
                ]
            ];
            $sheet->getStyle('B' . $filaInicioTabla . ':H' . $filaInicioTabla)->applyFromArray($estiloEncabezado);

            $sheet->freezePane('A' . ($filaInicioTabla + 1));
            $sheet->setAutoFilter('B' . $filaInicioTabla . ':H' . $filaInicioTabla);

            // Llenado de datos
            $fila = $filaInicioTabla + 1;
            foreach ($datos['detallados'] as $row) {
                $sheet->setCellValue('B' . $fila, $row['cliente']);
                $sheet->setCellValue('C' . $fila, $row['documento_referencia']);
                $sheet->setCellValue('D' . $fila, !empty($row['fecha_emision']) ? date('d/m/Y', strtotime($row['fecha_emision'])) : '');
                $sheet->setCellValue('E' . $fila, !empty($row['fecha_vencimiento']) ? date('d/m/Y', strtotime($row['fecha_vencimiento'])) : '');
                $sheet->setCellValue('F' . $fila, (float)$row['monto_total']);
                $sheet->setCellValue('G' . $fila, (float)$row['saldo']);
                $sheet->setCellValue('H' . $fila, $row['estado']);

                $sheet->getStyle('F' . $fila . ':G' . $fila)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                $sheet->getStyle('B' . $fila . ':H' . $fila)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('B' . $fila . ':H' . $fila)->getBorders()->getAllBorders()->getColor()->setARGB('FFCCCCCC'); 

                $sheet->getStyle('D' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('H' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                if ($fila % 2 == 0) {
                    $sheet->getStyle('B' . $fila . ':H' . $fila)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF7F7F7'); 
                }
                $fila++;
            }

            // Fila de Totales
            $filaTotal = $fila;
            $sheet->setCellValue('E' . $filaTotal, 'TOTALES CARTERA:');
            $sheet->getStyle('E' . $filaTotal)->getFont()->setBold(true);
            $sheet->getStyle('E' . $filaTotal)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue('F' . $filaTotal, '=SUM(F' . ($filaInicioTabla + 1) . ':F' . ($filaTotal - 1) . ')');
            $sheet->setCellValue('G' . $filaTotal, '=SUM(G' . ($filaInicioTabla + 1) . ':G' . ($filaTotal - 1) . ')');
            
            $sheet->getStyle('F' . $filaTotal . ':G' . $filaTotal)->getFont()->setBold(true);
            $sheet->getStyle('F' . $filaTotal . ':G' . $filaTotal)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            
            $sheet->getStyle('B' . $filaTotal . ':H' . $filaTotal)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAEAEA');
            $sheet->getStyle('B' . $filaTotal . ':H' . $filaTotal)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Ajuste de columnas
            foreach (range('B', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getColumnDimension('B')->setAutoSize(false);
            $sheet->getColumnDimension('B')->setWidth(32);

            $filename = 'Reporte_Global_CXC_' . date('Ymd_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }

        // --- 5. RENDERIZAR VISTA WEB ---
        $this->render('reportes/tesoreria_cxc', [
            'ruta_actual' => 'reportes/cxc',
            'filtros' => $f,
            'registros' => $datos['agrupados'], // Pasamos los datos listos para el Aging
            'resumen' => $resumen,
            'clientesEstadoCuenta' => $this->tesoreria->listarClientesEstadoCuenta(),
        ]);
    }

    private function filtrosPeriodo(): array
    {
        $fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));

        if ($fechaDesde === '' || $fechaHasta === '') {
            $fechaDesde = date('Y-m-01');
            $fechaHasta = date('Y-m-t');
        }
        if ($fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        return ['fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta];
    }

    private function registrarAuditoria(string $reporte): void
    {
        try {
            $this->usuariosModel->insertar_bitacora(
                (int) ($_SESSION['id'] ?? 0),
                'REPORTES_VER',
                'Consulta reporte: ' . $reporte,
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
        } catch (Throwable $e) {}
    }

    private function paginacion(): array
    {
        $pagina = max(1, (int) ($_GET['page'] ?? 1));
        $tamano = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        return [$pagina, $tamano];
    }
}