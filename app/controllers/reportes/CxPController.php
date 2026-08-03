<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class CxpController extends Controlador
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
        $this->registrarAuditoria('reporte_global_cxp');

        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['proveedor'] = trim((string) ($_GET['proveedor'] ?? ''));
        $f['estado_factura'] = trim((string) ($_GET['estado_factura'] ?? 'todos'));

        if (!in_array($f['estado_factura'], ['todos', 'vencida', 'corriente'], true)) {
            $f['estado_factura'] = 'todos';
        }

        $accion = $_GET['accion'] ?? $_GET['exportar'] ?? '';

        // --- 1. PROCESAR DATOS BASE DESDE EL MODELO ---
        $registrosBrutos = $this->tesoreria->historialEstadoCuentaProveedores($f, 1, 999999);
        $filasCompletas = $registrosBrutos['rows'] ?? [];

        // Filtramos y calculamos KPIs de pasivos en tiempo real
        $registros = [];
        $total_pasivo = 0;
        $total_vencido = 0;
        $total_por_vencer = 0;
        $proveedoresUnicos = [];
        $hoy = time();

        foreach ($filasCompletas as $r) {
            // Solo procesamos transacciones de tipo CARGO (obligaciones vivas de CxP)
            if (($r['tipo_transaccion'] ?? 'CARGO') !== 'CARGO') {
                continue;
            }

            $saldo = (float)($r['monto_transaccion'] ?? 0);
            if ($saldo <= 0) continue;

            $estadoStr = strtoupper(trim((string)($r['estado'] ?? 'PENDIENTE')));
            $esDeudaActiva = in_array($estadoStr, ['PENDIENTE', 'PARCIAL', 'VENCIDA', 'ABIERTA'], true);
            
            if (!$esDeudaActiva) continue;

            $fechaVencTime = isset($r['fecha_vencimiento']) ? strtotime((string)$r['fecha_vencimiento']) : 0;
            $estaVencida = ($fechaVencTime && $fechaVencTime < $hoy);

            if ($f['estado_factura'] === 'vencida' && !($estaVencida || $estadoStr === 'VENCIDA')) continue;
            if ($f['estado_factura'] === 'corriente' && ($estaVencida || $estadoStr === 'VENCIDA')) continue;

            $total_pasivo += $saldo;
            if ($estaVencida || $estadoStr === 'VENCIDA') {
                $total_vencido += $saldo;
            } else {
                $total_por_vencer += $saldo;
            }

            if (!empty($r['proveedor'])) {
                $proveedoresUnicos[$r['proveedor']] = true;
            }

            $registros[] = [
                'proveedor' => $r['proveedor'] ?? '',
                'documento_referencia' => $r['documento'] ?? '',
                'fecha_emision' => $r['fecha_atencion'] ?? '',
                'fecha_vencimiento' => $r['fecha_vencimiento'] ?? $r['fecha_atencion'] ?? '',
                'monto_total' => $saldo,
                'saldo' => $saldo,
                'estado' => $estadoStr
            ];
        }

        $resumen = [
            'total_pasivo'          => $total_pasivo,
            'total_vencido'         => $total_vencido,
            'total_por_vencer'      => $total_por_vencer,
            'proveedores_con_deuda' => count($proveedoresUnicos)
        ];

        // --- 2. EXPORTAR CSV ---
        if ($accion === 'exportar_csv_cxp' || $accion === 'csv') {
            $filename = 'Reporte_Global_CXP_' . date('Ymd_His') . '.csv'; 
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            echo "\xEF\xBB\xBF"; 
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Proveedor', 'Documento Ref.', 'Emisión', 'Vencimiento', 'Total Emitido', 'Saldo Pendiente', 'Estado'], ',');
            
            foreach ($registros as $row) {
                fputcsv($output, [
                    $row['proveedor'],
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

        // --- 3. EXPORTAR EXCEL NATIVO (.xlsx) CON PHPSpreadsheet ---
        if ($accion === 'exportar_excel_cxp' || $accion === 'excel') {
            require_once BASE_PATH . '/vendor/autoload.php';
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            
            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Cuentas por Pagar');

            $sheet->setShowGridlines(true); 
            $sheet->getColumnDimension('A')->setWidth(3); 

            $sheet->getRowDimension(1)->setRowHeight(40);
            $nombreEmpresa = mb_strtoupper((string)($config['nombre_empresa'] ?? 'EMPRESA'));
            $sheet->setCellValue('B1', $nombreEmpresa . ' - REPORTE GLOBAL DE CUENTAS POR PAGAR (CXP)');
            $sheet->mergeCells('B1:H1');
            $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFDC3545');
            $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $sheet->setCellValue('B2', 'Periodo: ' . date('d/m/Y', strtotime($f['fecha_desde'])) . ' al ' . date('d/m/Y', strtotime($f['fecha_hasta'])));
            $sheet->mergeCells('B2:D2');
            $sheet->getStyle('B2')->getFont()->setItalic(true)->getColor()->setARGB('FF555555');

            $filaInicioTabla = 4; 
            $cabeceras = [
                'B' => 'Proveedor', 
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

            $fila = $filaInicioTabla + 1;
            foreach ($registros as $row) {
                $sheet->setCellValue('B' . $fila, $row['proveedor']);
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

            $filaTotal = $fila;
            $sheet->setCellValue('E' . $filaTotal, 'TOTALES PASIVO:');
            $sheet->getStyle('E' . $filaTotal)->getFont()->setBold(true);
            $sheet->getStyle('E' . $filaTotal)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue('F' . $filaTotal, '=SUM(F' . ($filaInicioTabla + 1) . ':F' . ($filaTotal - 1) . ')');
            $sheet->setCellValue('G' . $filaTotal, '=SUM(G' . ($filaInicioTabla + 1) . ':G' . ($filaTotal - 1) . ')');
            
            $sheet->getStyle('F' . $filaTotal . ':G' . $filaTotal)->getFont()->setBold(true);
            $sheet->getStyle('F' . $filaTotal . ':G' . $filaTotal)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            
            $sheet->getStyle('B' . $filaTotal . ':H' . $filaTotal)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAEAEA');
            $sheet->getStyle('B' . $filaTotal . ':H' . $filaTotal)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            foreach (range('B', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getColumnDimension('B')->setAutoSize(false);
            $sheet->getColumnDimension('B')->setWidth(32);

            $filename = 'Reporte_Global_CXP_' . date('Ymd_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }

        // --- 4. RENDERIZAR VISTA WEB ---
        $this->render('reportes/tesoreria_cxp', [
            'ruta_actual' => 'reportes/cxp',
            'filtros' => $f,
            'registros' => $registros,
            'resumen' => $resumen,
            'proveedoresEstadoCuenta' => $this->tesoreria->listarProveedoresEstadoCuenta(),
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