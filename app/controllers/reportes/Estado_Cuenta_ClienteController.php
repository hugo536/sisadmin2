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
        $esVistaProducto = ($f['vista'] === 'PRODUCTO');
        
        // --- LÓGICA CENTRAL DE EXPORTACIÓN ---
        if (in_array($accion, ['exportar_excel_estado_cuenta', 'exportar_csv_estado_cuenta', 'imprimir_estado_cuenta'])) {
            
            // Siempre consultamos el historial general porque necesitamos los totales (el resumen)
            $detalleBase = $this->tesoreria->historialEstadoCuenta($f, 1, 999999);
            $resumenExport = $detalleBase['resumen'] ?? ['saldo_inicial' => 0, 'total_facturado' => 0, 'total_pagado' => 0, 'total_saldo' => 0];
            
            // Decidimos qué datos enviar dependiendo de la vista
            if ($esVistaProducto) {
                $dataExportar = $this->tesoreria->estadoCuentaPorProducto($f, 5000); // Límite alto para exportar todo
            } else {
                $dataExportar = $detalleBase['rows'] ?? [];
            }

            // -------------------------------------------------------------
            // 1. EXPORTAR CSV
            // -------------------------------------------------------------
            if ($accion === 'exportar_csv_estado_cuenta') {
                $filename = ($esVistaProducto ? 'Resumen_Productos_Clientes_' : 'Estado_Cuenta_Clientes_') . date('Ymd_His') . '.csv'; 
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filename);
                echo "\xEF\xBB\xBF"; 
                
                $output = fopen('php://output', 'w');
                
                if ($esVistaProducto) {
                    fputcsv($output, ['Producto', 'Cantidad Vendida', 'Total Facturado', 'Deuda Pendiente'], ',');
                    foreach ($dataExportar as $row) {
                        fputcsv($output, [
                            $row['producto'] ?? '',
                            number_format((float)($row['total_cantidad'] ?? 0), 2, '.', ''),
                            number_format((float)($row['total_facturado'] ?? 0), 2, '.', ''),
                            number_format((float)($row['total_saldo'] ?? 0), 2, '.', '')
                        ], ',');
                    }
                } else {
                    fputcsv($output, ['Fecha', 'Cliente/Distribuidor', 'Documento', 'Concepto', 'Tipo', 'Monto'], ',');
                    foreach ($dataExportar as $row) {
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
                }
                fclose($output);
                exit;
            }

            // -------------------------------------------------------------
            // 2. EXPORTAR EXCEL (.xlsx)
            // -------------------------------------------------------------
            if ($accion === 'exportar_excel_estado_cuenta') {
                require_once BASE_PATH . '/vendor/autoload.php';
                require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
                
                $empresaModel = new EmpresaModel();
                $config = $empresaModel->obtener();
                
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($esVistaProducto ? 'Resumen Productos' : 'Estado de Cuenta');
                $sheet->setShowGridlines(false); 
                $sheet->getColumnDimension('A')->setWidth(3); 

                // --- 2. TÍTULOS Y DATOS DE LA EMPRESA DINÁMICOS ---
                $sheet->getRowDimension(1)->setRowHeight(50);
                $nombreEmpresa = mb_strtoupper((string)($config['nombre_empresa'] ?? 'NUESTRA EMPRESA'));
                $tituloGeneral = $esVistaProducto ? 'RESUMEN DE PRODUCTOS VENDIDOS' : 'ESTADO DE CUENTA CLIENTES';
                
                $columnaFin = $esVistaProducto ? 'E' : 'G'; 
                // TRUCO: Fusionamos el título una columna ANTES del final para dejarle el espacio exclusivo al logo
                $columnaMergeTitulo = $esVistaProducto ? 'D' : 'F';

                $sheet->setCellValue('B1', $nombreEmpresa . ' - ' . $tituloGeneral);
                $sheet->mergeCells('B1:' . $columnaMergeTitulo . '1');
                // Bajamos un punto la fuente a 14 para que encaje holgadamente
                $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF0B5ED7');
                $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

                $fechaTexto = 'Periodo del reporte: ' . date('d/m/Y', strtotime($f['fecha_desde'])) . ' al ' . date('d/m/Y', strtotime($f['fecha_hasta']));
                $sheet->setCellValue('B2', $fechaTexto);
                $sheet->mergeCells('B2:C2');
                $sheet->getStyle('B2')->getFont()->setItalic(true)->getColor()->setARGB('FF555555');

                $clienteTexto = !empty($f['cliente']) ? 'Cliente filtrado: ' . $f['cliente'] : 'Todos los clientes';
                $sheet->setCellValue('B3', $clienteTexto);
                $sheet->mergeCells('B3:C3');
                $sheet->getStyle('B3')->getFont()->setBold(true);

                // --- 3. INSERTAR LOGO DINÁMICO ---
                $rutaLogo = $config['ruta_logo'] ?? '';
                if (!empty($rutaLogo)) {
                    $rutaLimpia = ltrim($rutaLogo, '/\\');
                    $logoPath = (strpos($rutaLimpia, 'public/') === 0) ? BASE_PATH . '/' . $rutaLimpia : BASE_PATH . '/public/' . $rutaLimpia;
                    $logoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $logoPath);
                    
                    if (file_exists($logoPath)) {
                        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                            $drawing->setName('Logo Empresa');
                            $drawing->setPath($logoPath);
                            $drawing->setHeight(55); // Ajustado para que encaje mejor sin salirse de la fila
                            
                            $drawing->setCoordinates($columnaFin . '1'); 
                            // Empujamos el logo ligeramente a la derecha si es la tabla pequeña
                            $drawing->setOffsetX($esVistaProducto ? 30 : 10); 
                            $drawing->setOffsetY(5);
                            $drawing->setWorksheet($sheet);
                        }
                    }
                }

                // Encabezados de Tabla
                $filaInicioTabla = 5; 
                $sheet->getRowDimension($filaInicioTabla)->setRowHeight(25);
                
                if ($esVistaProducto) {
                    $cabeceras = ['B' => 'Producto', 'C' => 'Cantidad Vendida', 'D' => 'Total Facturado', 'E' => 'Deuda Pendiente'];
                } else {
                    $cabeceras = ['B' => 'Fecha', 'C' => 'Cliente / Distribuidor', 'D' => 'Documento', 'E' => 'Concepto', 'F' => 'Tipo', 'G' => 'Monto Transacción'];
                }

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
                    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]]
                ];
                $sheet->getStyle('B' . $filaInicioTabla . ':' . $columnaFin . $filaInicioTabla)->applyFromArray($estiloEncabezado);
                $sheet->freezePane('A' . ($filaInicioTabla + 1));
                $sheet->setAutoFilter('B' . $filaInicioTabla . ':' . $columnaFin . $filaInicioTabla);

                $fila = $filaInicioTabla + 1;

                // --- LLENADO DE DATOS: PRODUCTO ---
                if ($esVistaProducto) {
                    foreach ($dataExportar as $row) {
                        $sheet->setCellValue('B' . $fila, $row['producto'] ?? '');
                        $sheet->setCellValue('C' . $fila, (float)($row['total_cantidad'] ?? 0));
                        $sheet->setCellValue('D' . $fila, (float)($row['total_facturado'] ?? 0));
                        $sheet->setCellValue('E' . $fila, (float)($row['total_saldo'] ?? 0));
                        
                        $sheet->getStyle('C' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
                        $sheet->getStyle('D' . $fila . ':E' . $fila)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                        
                        $sheet->getStyle('B' . $fila . ':E' . $fila)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                        $sheet->getStyle('B' . $fila . ':E' . $fila)->getBorders()->getAllBorders()->getColor()->setARGB('FFCCCCCC'); 

                        if ($fila % 2 == 0) {
                            $sheet->getStyle('B' . $fila . ':E' . $fila)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7F7F7'); 
                        }
                        $fila++;
                    }

                    // Fila Totales
                    $filaTotal = $fila;
                    $sheet->setCellValue('B' . $filaTotal, 'TOTALES:');
                    $sheet->getStyle('B' . $filaTotal)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                    $sheet->mergeCells('B' . $filaTotal . ':C' . $filaTotal);
                    
                    $sheet->setCellValue('D' . $filaTotal, (float)($resumenExport['total_facturado'] ?? 0));
                    $sheet->setCellValue('E' . $filaTotal, (float)($resumenExport['total_saldo'] ?? 0));
                    
                    $sheet->getStyle('B' . $filaTotal . ':E' . $filaTotal)->getFont()->setBold(true);
                    $sheet->getStyle('D' . $filaTotal . ':E' . $filaTotal)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                    $sheet->getStyle('B' . $filaTotal . ':E' . $filaTotal)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAEAEA');
                    $sheet->getStyle('B' . $filaTotal . ':E' . $filaTotal)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                    $sheet->getColumnDimension('B')->setWidth(45);
                    foreach (range('C', 'E') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

                } 
                // --- LLENADO DE DATOS: DETALLE ---
                else {
                    $saldoInicial = (float)($resumenExport['saldo_inicial'] ?? 0);
                    
                    $sheet->setCellValue('B' . $fila, '-');
                    $sheet->setCellValue('C' . $fila, '-');
                    $sheet->setCellValue('D' . $fila, 'SALDO ANTERIOR AL ' . date('d/m/Y', strtotime($f['fecha_desde'])));
                    $sheet->setCellValue('E' . $fila, '-');
                    $sheet->setCellValue('F' . $fila, '-');
                    $sheet->setCellValue('G' . $fila, $saldoInicial);
                    
                    $sheet->getStyle('G' . $fila)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                    $sheet->getStyle('B' . $fila . ':G' . $fila)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                    $sheet->getStyle('B' . $fila . ':G' . $fila)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAEAEA');
                    $sheet->getStyle('B' . $fila . ':G' . $fila)->getFont()->setBold(true);
                    $sheet->getStyle('B' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    
                    $filaInicialSuma = $fila; 
                    $fila++;

                    foreach ($dataExportar as $row) {
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
                            $sheet->getStyle('B' . $fila . ':G' . $fila)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7F7F7'); 
                        }
                        $fila++;
                    }

                    $filaTotal = $fila;
                    $sheet->setCellValue('F' . $filaTotal, 'SALDO PENDIENTE FINAL:');
                    $sheet->getStyle('F' . $filaTotal)->getFont()->setBold(true);
                    $sheet->getStyle('F' . $filaTotal)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                    $sheet->setCellValue('G' . $filaTotal, '=SUM(G' . $filaInicialSuma . ':G' . ($filaTotal - 1) . ')');
                    $sheet->getStyle('G' . $filaTotal)->getFont()->setBold(true);
                    $sheet->getStyle('G' . $filaTotal)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                    $sheet->getStyle('F' . $filaTotal . ':G' . $filaTotal)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAEAEA');
                    $sheet->getStyle('F' . $filaTotal . ':G' . $filaTotal)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                    foreach (range('B', 'G') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
                    $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(35);
                    $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(45);
                }

                // Salida de Archivo
                $filename = ($esVistaProducto ? 'Resumen_Productos_' : 'Estado_Cuenta_Clientes_') . date('Ymd_His') . '.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="' . $filename . '"');
                header('Cache-Control: max-age=0');

                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;
            }

            // -------------------------------------------------------------
            // 3. EXPORTAR PDF
            // -------------------------------------------------------------
            if ($accion === 'imprimir_estado_cuenta') {
                require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
                require_once BASE_PATH . '/vendor/autoload.php';

                $empresaModel = new EmpresaModel();
                $config = $empresaModel->obtener();

                $filtros = $f;
                $resumen = $resumenExport;
                
                // Definimos las variables y la vista que se usará para el PDF
                if ($esVistaProducto) {
                    $porProducto = $dataExportar;
                    $vistaPdf = BASE_PATH . '/app/views/reportes/pdf_estado_cuenta_producto.php';
                } else {
                    $movimientos = $dataExportar;
                    $vistaPdf = BASE_PATH . '/app/views/reportes/pdf_estado_cuenta.php';
                }

                ob_start();
                require $vistaPdf;
                $html = ob_get_clean();

                $dompdf = new \Dompdf\Dompdf();
                $options = $dompdf->getOptions();
                $options->set(['isRemoteEnabled' => true]);
                $dompdf->setOptions($options);

                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $dompdf->stream(($esVistaProducto ? 'Resumen_Productos.pdf' : 'Estado_Cuenta.pdf'), ['Attachment' => false]);
                return;
            }
        }

        // --- RENDERIZADO DE LA VISTA WEB NORMAL ---
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