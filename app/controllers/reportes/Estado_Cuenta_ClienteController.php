<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class Estado_Cuenta_ClienteController extends Controlador
{
    private ReporteTesoreriaModel $tesoreria;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        $this->tesoreria = new ReporteTesoreriaModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.tesoreria.ver');
        $this->registrarAuditoria('estado_cuenta_cliente');

        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['cliente'] = trim((string) ($_GET['cliente'] ?? ''));
        $f['producto'] = trim((string) ($_GET['producto'] ?? ''));
        $f['estado'] = strtoupper(trim((string) ($_GET['estado'] ?? '')));
        $f['vista'] = trim((string) ($_GET['vista'] ?? 'DETALLE'));
        
        if (!in_array($f['estado'], ['', 'PENDIENTE', 'PARCIAL', 'PAGADA', 'VENCIDA', 'ANULADA'], true)) {
            $f['estado'] = '';
        }
        if (!in_array($f['vista'], ['DETALLE', 'PRODUCTO'], true)) {
            $f['vista'] = 'DETALLE';
        }

        $accion = $_GET['accion'] ?? '';
        
        // --- EXPORTAR EXCEL NATIVO Y CSV ---
        if ($accion === 'exportar_excel_estado_cuenta' || $accion === 'exportar_csv_estado_cuenta') {
            
            $detalle = $this->tesoreria->historialEstadoCuenta($f, 1, 999999);
            $movimientos = $detalle['rows'] ?? [];

            if ($accion === 'exportar_csv_estado_cuenta') {
                $filename = 'Estado_Cuenta_Clientes_' . date('Ymd_His') . '.csv'; 
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filename);
                echo "\xEF\xBB\xBF"; 
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Fecha', 'Cliente/Distribuidor', 'Documento', 'Concepto', 'Tipo', 'Monto'], ',');
                
                foreach ($movimientos as $row) {
                    $esCargo = ($row['tipo_transaccion'] ?? 'CARGO') === 'CARGO';
                    $signo = $esCargo ? '+' : '-';
                    fputcsv($output, [
                        !empty($row['fecha_atencion']) ? date('d-m-Y', strtotime($row['fecha_atencion'])) : '',
                        $row['cliente'] ?? '',
                        $row['documento'] ?? '',
                        $row['producto'] ?? '',
                        $esCargo ? 'Deuda/Cargo' : 'Pago/Abono',
                        $signo . number_format((float)($row['monto_transaccion'] ?? 0), 2, '.', '')
                    ], ',');
                }
                fclose($output);
                exit;
            }

            // Si es EXCEL (.xlsx), usamos PhpSpreadsheet con Diseño Personalizado
            if ($accion === 'exportar_excel_estado_cuenta') {
                require_once BASE_PATH . '/vendor/autoload.php';
                require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
                
                $empresaModel = new EmpresaModel();
                $config = $empresaModel->obtener();
                
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Estado de Cuenta');

                // --- 1. CONFIGURACIÓN GENERAL DE LA HOJA ---
                $sheet->setShowGridlines(false); 
                $sheet->getColumnDimension('A')->setWidth(3); 

                // --- 2. TÍTULOS Y DATOS DE LA EMPRESA DINÁMICOS ---
                $sheet->getRowDimension(1)->setRowHeight(50); // <-- ESTA ES LA LÍNEA NUEVA (Puedes cambiar el 50 por el alto que prefieras)
                $nombreEmpresa = mb_strtoupper((string)($config['nombre_empresa'] ?? 'NUESTRA EMPRESA'));
                $sheet->setCellValue('B1', $nombreEmpresa . ' - ESTADO DE CUENTA CLIENTES');
                // Título abarca toda la tabla (B a G) y va centrado
                $sheet->mergeCells('B1:G1');
                $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF0B5ED7');
                $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                $fechaTexto = 'Periodo del reporte: ' . date('d/m/Y', strtotime($f['fecha_desde'])) . ' al ' . date('d/m/Y', strtotime($f['fecha_hasta']));
                $sheet->setCellValue('B2', $fechaTexto);
                // Subtítulos abarcan solo columnas B y C
                $sheet->mergeCells('B2:C2');
                $sheet->getStyle('B2')->getFont()->setItalic(true)->getColor()->setARGB('FF555555');

                $clienteTexto = !empty($f['cliente']) ? 'Cliente filtrado: ' . $f['cliente'] : 'Todos los clientes';
                $sheet->setCellValue('B3', $clienteTexto);
                // Subtítulos abarcan solo columnas B y C
                $sheet->mergeCells('B3:C3');
                $sheet->getStyle('B3')->getFont()->setBold(true);

                // --- 3. INSERTAR LOGO DINÁMICO (Encima de Columna G) ---
                $rutaLogo = $config['ruta_logo'] ?? '';
                if (!empty($rutaLogo)) {
                    $rutaLimpia = ltrim($rutaLogo, '/\\');
                    
                    if (strpos($rutaLimpia, 'public/') === 0) {
                        $logoPath = BASE_PATH . '/' . $rutaLimpia;
                    } else {
                        $logoPath = BASE_PATH . '/public/' . $rutaLimpia;
                    }

                    $logoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logoPath);
                    
                    if (file_exists($logoPath)) {
                        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                        
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                            $drawing->setName('Logo Empresa');
                            $drawing->setDescription('Logo');
                            $drawing->setPath($logoPath);
                            $drawing->setHeight(60); 
                            $drawing->setCoordinates('G1'); // Mover a la columna G
                            $drawing->setOffsetX(10); 
                            $drawing->setOffsetY(5);
                            $drawing->setWorksheet($sheet);
                        }
                    }
                }

                // --- 4. CONFIGURACIÓN DEL ENCABEZADO DE TABLA ---
                $filaInicioTabla = 5; 
                $cabeceras = ['B' => 'Fecha', 'C' => 'Cliente / Distribuidor', 'D' => 'Documento', 'E' => 'Concepto', 'F' => 'Tipo', 'G' => 'Monto Transacción'];
                
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
                $sheet->getStyle('B' . $filaInicioTabla . ':G' . $filaInicioTabla)->applyFromArray($estiloEncabezado);

                // --- 5. CONGELAR PANELES Y ACTIVAR AUTOFILTRO NATIVO ---
                // Usamos 'A' para no congelar ninguna columna, y la fila de inicio + 1 para congelar las filas superiores
                $sheet->freezePane('A' . ($filaInicioTabla + 1));
                $sheet->setAutoFilter('B' . $filaInicioTabla . ':G' . $filaInicioTabla);

                // --- 6. LLENADO DE DATOS (Fila 7 en adelante) ---
                $fila = $filaInicioTabla + 1;
                foreach ($movimientos as $row) {
                    $esCargo = ($row['tipo_transaccion'] ?? 'CARGO') === 'CARGO';
                    $multiplicador = $esCargo ? 1 : -1; 
                    $montoMatematico = (float)($row['monto_transaccion'] ?? 0) * $multiplicador;

                    $sheet->setCellValue('B' . $fila, !empty($row['fecha_atencion']) ? date('d/m/Y', strtotime($row['fecha_atencion'])) : '');
                    $sheet->setCellValue('C' . $fila, $row['cliente'] ?? '');
                    $sheet->setCellValue('D' . $fila, $row['documento'] ?? '');
                    $sheet->setCellValue('E' . $fila, $row['producto'] ?? '');
                    $sheet->setCellValue('F' . $fila, $esCargo ? 'Deuda (Cargo)' : 'Pago (Abono)');
                    $sheet->setCellValue('G' . $fila, $montoMatematico);
                    
                    $sheet->getStyle('G' . $fila)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                    
                    $sheet->getStyle('B' . $fila . ':G' . $fila)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                    $sheet->getStyle('B' . $fila . ':G' . $fila)->getBorders()->getAllBorders()->getColor()->setARGB('FFCCCCCC'); 

                    $sheet->getStyle('B' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                    if ($fila % 2 == 0) {
                        $sheet->getStyle('B' . $fila . ':G' . $fila)->getFill()
                              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                              ->getStartColor()->setARGB('FFF7F7F7'); 
                    }
                    $fila++;
                }

                // --- 7. FILA DE TOTALES DINÁMICA ---
                $filaTotal = $fila;
                $sheet->setCellValue('F' . $filaTotal, 'TOTAL NETO:');
                $sheet->getStyle('F' . $filaTotal)->getFont()->setBold(true);
                $sheet->getStyle('F' . $filaTotal)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                $sheet->setCellValue('G' . $filaTotal, '=SUM(G7:G' . ($filaTotal - 1) . ')');
                $sheet->getStyle('G' . $filaTotal)->getFont()->setBold(true);
                $sheet->getStyle('G' . $filaTotal)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                
                $sheet->getStyle('F' . $filaTotal . ':G' . $filaTotal)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAEAEA');
                $sheet->getStyle('F' . $filaTotal . ':G' . $filaTotal)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                // --- 8. AUTOAJUSTAR ANCHO DE COLUMNAS ---
                foreach (range('B', 'G') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getColumnDimension('C')->setAutoSize(false);
                $sheet->getColumnDimension('C')->setWidth(35);
                $sheet->getColumnDimension('E')->setAutoSize(false);
                $sheet->getColumnDimension('E')->setWidth(45);

                // --- 9. SALIDA DEL ARCHIVO ---
                $filename = 'Estado_Cuenta_Clientes_' . date('Ymd_His') . '.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="' . $filename . '"');
                header('Cache-Control: max-age=0');

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;
            }
        }

        // --- EXPORTAR PDF (EXISTENTE) ---
        if ($accion === 'imprimir_estado_cuenta') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();

            $detalle = $this->tesoreria->historialEstadoCuenta($f, 1, 999999);

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_estado_cuenta.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(['isRemoteEnabled' => true]);
            $dompdf->setOptions($options);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream('Estado_Cuenta.pdf', ['Attachment' => false]);
            return;
        }

        if (empty($f['cliente'])) {
            $detalle = [
                'rows' => [], 
                'total' => 0, 
                'resumen' => [
                    'saldo_inicial' => 0, 'total_facturado' => 0, 'total_pagado' => 0, 'total_saldo' => 0
                ]
            ];
            $porProducto = [];
        } else {
            $detalle = $this->tesoreria->historialEstadoCuenta($f, $pagina, $tamano);
            $porProducto = $this->tesoreria->estadoCuentaPorProducto($f, 200);
        }

        $this->render('reportes/estado_cuenta', [
            'ruta_actual' => 'reportes/estado_cuenta',
            'filtros' => $f,
            'detalle' => $detalle,
            'porProducto' => $porProducto,
            'clientesEstadoCuenta' => $this->tesoreria->listarClientesEstadoCuenta(),
            'pagina' => $pagina,
            'tamano' => $tamano,
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