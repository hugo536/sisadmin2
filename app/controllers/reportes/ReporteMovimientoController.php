<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
// Asegúrate de que esta ruta coincida con la ubicación real de tu modelo
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaMovimientoModel.php'; 
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class ReporteMovimientoController extends Controlador
{
    private ReporteTesoreriaMovimientoModel $tesoreria;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        parent::__construct();
        $this->tesoreria = new ReporteTesoreriaMovimientoModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.tesoreria.ver');
        $this->registrarAuditoria('reporte_movimientos_caja');

        // Paginación estándar para la vista web
        $pagina = max(1, (int) ($_GET['page'] ?? 1));
        $tamano = min(100, max(10, (int) ($_GET['per_page'] ?? 10)));

        $f = $this->filtrosPeriodo();
        $f['id_cuenta'] = $_GET['id_cuenta'] ?? [];
        $f['id_metodo_pago'] = $_GET['id_metodo_pago'] ?? [];
        $f['origen'] = $_GET['origen'] ?? [];
        $f['busqueda'] = trim((string) ($_GET['busqueda'] ?? ''));

        // Asegurar que sean arrays para la lógica de dropdowns múltiples
        if (!is_array($f['id_cuenta'])) $f['id_cuenta'] = [$f['id_cuenta']];
        if (!is_array($f['id_metodo_pago'])) $f['id_metodo_pago'] = [$f['id_metodo_pago']];
        if (!is_array($f['origen'])) $f['origen'] = [$f['origen']];

        // Limpiar elementos vacíos
        $f['id_cuenta'] = array_filter($f['id_cuenta']);
        $f['id_metodo_pago'] = array_filter($f['id_metodo_pago']);
        $f['origen'] = array_filter($f['origen']);

        $accion = $_GET['exportar_csv'] ?? $_GET['exportar_pdf'] ?? '';

        // Si el usuario quiere descargar Excel o PDF, traemos TODOS los registros (limit 100,000)
        if ($accion == '1') {
            $pagina = 1;
            $tamano = 100000;
        }

        // --- 1. PROCESAR DATOS BASE DESDE EL MODELO ---
        $resultado = $this->tesoreria->listarMovimientos($f, $pagina, $tamano);
        $movimientos = $resultado['rows'] ?? [];
        
        $resumenCuentas = $this->tesoreria->listarCuentas();
        $metodos = $this->tesoreria->listarMetodosPago();

        // --- 2. EXPORTAR EXCEL NATIVO (.xlsx) ---
        if (!empty($_GET['exportar_excel']) && $_GET['exportar_excel'] == '1') {
            require_once BASE_PATH . '/vendor/autoload.php';
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            
            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Flujo de Caja');

            // Configuración general
            $sheet->setShowGridlines(true); 
            $sheet->getColumnDimension('A')->setWidth(3); 

            // Títulos
            $sheet->getRowDimension(1)->setRowHeight(40);
            $nombreEmpresa = mb_strtoupper((string)($config['nombre_empresa'] ?? 'EMPRESA'));
            $sheet->setCellValue('B1', $nombreEmpresa . ' - REPORTE DE FLUJO DE CAJA (MOVIMIENTOS)');
            $sheet->mergeCells('B1:I1');
            $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF0B5ED7');
            $sheet->getStyle('B1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B1')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $sheet->setCellValue('B2', 'Periodo: ' . date('d/m/Y', strtotime($f['fecha_desde'])) . ' al ' . date('d/m/Y', strtotime($f['fecha_hasta'])));
            $sheet->mergeCells('B2:D2');
            $sheet->getStyle('B2')->getFont()->setItalic(true)->getColor()->setARGB('FF555555');

            // Cabecera de la tabla
            $filaInicioTabla = 4; 
            $cabeceras = [
                'B' => 'Fecha', 
                'C' => 'Tipo', 
                'D' => 'Tercero / Entidad', 
                'E' => 'Ref. Origen', 
                'F' => 'Cuenta / Método', 
                'G' => 'Ingresos (+)', 
                'H' => 'Egresos (-)', 
                'I' => 'Estado'
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
            $sheet->getStyle('B' . $filaInicioTabla . ':I' . $filaInicioTabla)->applyFromArray($estiloEncabezado);
            $sheet->freezePane('A' . ($filaInicioTabla + 1));
            $sheet->setAutoFilter('B' . $filaInicioTabla . ':I' . $filaInicioTabla);

            // Llenado de datos
            $fila = $filaInicioTabla + 1;
            foreach ($movimientos as $m) {
                $tipo = (string)($m['tipo'] ?? '');
                $origen = strtoupper((string)($m['origen'] ?? ''));
                $tercero = ($origen === 'TRANSFERENCIA') ? 'Cuentas Propias (Interno)' : ($m['tercero_nombre'] ?? ('#' . ($m['id_tercero'] ?? 0)));
                $nombreCuenta = (string) ($m['cuenta_nombre'] ?? '');
                $metodoPago = (string) ($m['metodo'] ?? $m['metodo_pago'] ?? '');
                $cuenta = $nombreCuenta . ($metodoPago !== '' && $metodoPago !== 'N/D' ? ' - ' . $metodoPago : '');
                
                $montoValor = (float)($m['monto'] ?? 0);
                
                $ingreso = ($tipo === 'COBRO') ? $montoValor : '';
                $egreso = ($tipo !== 'COBRO') ? $montoValor : '';

                $sheet->setCellValue('B' . $fila, !empty($m['fecha']) ? date('d/m/Y', strtotime($m['fecha'])) : '');
                $sheet->setCellValue('C' . $fila, $tipo);
                $sheet->setCellValue('D' . $fila, $tercero);
                $sheet->setCellValue('E' . $fila, $origen . ' #' . ($m['id_origen'] ?? ''));
                $sheet->setCellValue('F' . $fila, $cuenta);
                $sheet->setCellValue('G' . $fila, $ingreso);
                $sheet->setCellValue('H' . $fila, $egreso);
                $sheet->setCellValue('I' . $fila, $m['estado'] ?? '');

                $sheet->getStyle('G' . $fila . ':H' . $fila)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
                $sheet->getStyle('B' . $fila . ':I' . $fila)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                $sheet->getStyle('B' . $fila . ':I' . $fila)->getBorders()->getAllBorders()->getColor()->setARGB('FFCCCCCC'); 

                $sheet->getStyle('B' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('C' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('I' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                if ($tipo === 'COBRO') {
                    $sheet->getStyle('C' . $fila)->getFont()->getColor()->setARGB('FF198754'); // Verde
                } else {
                    $sheet->getStyle('C' . $fila)->getFont()->getColor()->setARGB('FFDC3545'); // Rojo
                }

                if ($fila % 2 == 0) {
                    $sheet->getStyle('B' . $fila . ':I' . $fila)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7F7F7'); 
                }
                $fila++;
            }

            // Fila de Totales
            $filaTotal = $fila;
            $sheet->setCellValue('F' . $filaTotal, 'FLUJO NETO:');
            $sheet->getStyle('F' . $filaTotal)->getFont()->setBold(true);
            $sheet->getStyle('F' . $filaTotal)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sheet->setCellValue('G' . $filaTotal, '=SUM(G' . ($filaInicioTabla + 1) . ':G' . ($filaTotal - 1) . ')');
            $sheet->setCellValue('H' . $filaTotal, '=SUM(H' . ($filaInicioTabla + 1) . ':H' . ($filaTotal - 1) . ')');
            
            $sheet->getStyle('G' . $filaTotal . ':H' . $filaTotal)->getFont()->setBold(true);
            $sheet->getStyle('G' . $filaTotal . ':H' . $filaTotal)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            $sheet->getStyle('B' . $filaTotal . ':I' . $filaTotal)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAEAEA');
            $sheet->getStyle('B' . $filaTotal . ':I' . $filaTotal)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            // Ajuste automático de columnas
            foreach (range('B', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $filename = 'Flujo_Caja_Palavilla_' . date('Ymd_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }

        // --- 3. EXPORTAR PDF ---
        if (!empty($_GET['exportar_pdf']) && $_GET['exportar_pdf'] == '1') {
             // Aquí mantienes tu lógica actual de generación de PDF.
             exit;
        }

        // --- 4. RENDERIZAR VISTA WEB ---
        $this->render('reportes/tesoreria_movimiento', [
            'ruta_actual' => 'reportes/tesoreria_movimientos',
            'filtros' => $f,
            'movimientos' => $movimientos,
            'resumenCuentas' => $resumenCuentas,
            'metodos' => $metodos,
            'totalRegistros' => $resultado['total'] ?? 0
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
}