<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/rrhh/PlanillasModel.php';
require_once BASE_PATH . '/app/models/terceros/TercerosModel.php'; 

// Cargar librerías de Composer (DomPDF) si existe el archivo
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

class PlanillasController extends Controlador
{
    private PlanillasModel $planillasModel;
    private TercerosModel $tercerosModel;

    public function __construct()
    {
        parent::__construct();
        $this->planillasModel = new PlanillasModel();
        $this->tercerosModel = new TercerosModel();
    }

    /**
     * ========================================================================
     * 1. VISTA PRINCIPAL (Dashboard de Lotes)
     * ========================================================================
     */
    public function index(): void
    {
        AuthMiddleware::handle();

        $lotesRecientes = $this->planillasModel->obtenerLotesRecientes(10);
        $loteActual = null;
        $detallesNomina = [];

        $idLote = (int) ($_GET['id_lote'] ?? 0);
        
        if ($idLote === 0 && !empty($lotesRecientes)) {
            $idLote = (int) $lotesRecientes[0]['id'];
        }

        if ($idLote > 0) {
            $loteActual = $this->planillasModel->obtenerLotePorId($idLote);
            if ($loteActual) {
                $estadoLote = strtoupper(trim((string)$loteActual['estado']));
                
                if (in_array($estadoLote, ['PENDIENTE', 'BORRADOR', 'CREADO'])) {
                    $detallesNomina = $this->planillasModel->calcularNominaEnMemoria($loteActual);
                } else {
                    $detallesNomina = $this->planillasModel->obtenerDetallesLote($idLote);
                }
            }
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'movimientos_detalle') {
            $idDetalle = (int) ($_GET['id_detalle'] ?? 0);
            if ($idDetalle <= 0) {
                json_response(['ok' => false, 'mensaje' => 'Detalle inválido.'], 400);
                return;
            }

            json_response([
                'ok' => true,
                'movimientos' => $this->planillasModel->obtenerMovimientosManualesDetalle($idDetalle),
            ]);
            return;
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $cuentasTesoreria = $this->planillasModel->obtenerCuentasTesoreria();

        $this->render('rrhh/planillas', [
            'ruta_actual' => 'planillas',
            'lotes_recientes' => $lotesRecientes,
            'lote_actual' => $loteActual,
            'detalles_nomina' => $detallesNomina,
            'cuentas' => $cuentasTesoreria,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }

    /**
     * ========================================================================
     * 2. CREACIÓN DEL LOTE
     * ========================================================================
     */
    public function generar(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('planillas');
            return;
        }

        try {
            $userId = AuthMiddleware::getUserId();
            $idLoteNuevo = $this->planillasModel->generarLoteNomina($_POST, $userId);
            
            redirect("planillas?id_lote={$idLoteNuevo}&ok=" . urlencode('Lote generado correctamente.'));
        } catch (Exception $e) {
            $msgError = urlencode($e->getMessage());
            redirect("planillas?error={$msgError}");
        }
    }

    /**
     * ========================================================================
     * 3. AJUSTES MANUALES
     * ========================================================================
     */
    public function agregar_concepto(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $exito = $this->planillasModel->agregarConceptoManual($_POST);
            $referer = $_SERVER['HTTP_REFERER'] ?? 'planillas';
            
            if ($exito) {
                redirect($referer); 
            } else {
                redirect($referer . "&error=" . urlencode('No se pudo aplicar el ajuste. Es posible que el lote ya esté cerrado.'));
            }
        }
    }

    /**
     * ========================================================================
     * 4. CONGELAMIENTO (Cerrar Planilla)
     * ========================================================================
     */
    public function cerrar(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $idLote = (int) ($_POST['id_lote'] ?? 0);
            
            if ($idLote > 0) {
                $lote = $this->planillasModel->obtenerLotePorId($idLote);
                if ($lote) {
                    $nominaCalculada = $this->planillasModel->calcularNominaEnMemoria($lote);
                    
                    foreach ($nominaCalculada as $row) {
                        if (!empty($row['tiene_conflicto'])) {
                            redirect("planillas?id_lote={$idLote}&error=" . urlencode('No se puede cerrar: hay empleados con asistencia incompleta.'));
                            return;
                        }
                    }
                }

                $exito = $this->planillasModel->aprobarLote($idLote);
                
                if (!$exito) {
                    $errorMsg = $this->planillasModel->ultimoError ?? 'Error al cerrar el lote.';
                    redirect("planillas?id_lote={$idLote}&error=" . urlencode($errorMsg));
                    return;
                }
            }
            redirect("planillas?id_lote={$idLote}&ok=" . urlencode('Planilla cerrada con éxito.'));
        }
    }

    /**
     * ========================================================================
     * 5. REPORTES: Imprimir Boleta PDF Individual
     * ========================================================================
     */
    public function imprimir_boleta(): void
    {
        AuthMiddleware::handle();
        
        $idDetalle = (int) ($_GET['id'] ?? 0);
        if ($idDetalle <= 0) die("Recibo no especificado.");

        $boleta = $this->planillasModel->obtenerDatosBoletaPdf($idDetalle);
        if (!$boleta) die("El recibo no existe o la planilla no está cerrada.");

        ob_start();
        $this->render('rrhh/planillas_boleta_pdf', [
            'boletas' => [$boleta],
            'empresa' => [
                'nombre' => 'Tu Empresa S.A.C.',
                'ruc' => '20123456789',
                'direccion' => 'Av. Principal 123, Ciudad'
            ]
        ], true); 
        $html = ob_get_clean();

        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set(['isRemoteEnabled' => true]);
        $dompdf->setOptions($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        if (ob_get_length()) ob_end_clean(); // <-- PROTECCIÓN CONTRA CORRUPCIÓN PDF

        $nombreArchivo = 'Boleta_' . str_replace(' ', '_', $boleta['nombre_completo']) . '.pdf';
        $dompdf->stream($nombreArchivo, ["Attachment" => 0]);
        exit;
    }

    /**
     * ========================================================================
     * 6. REPORTES: Imprimir MASIVO (Boletas)
     * ========================================================================
     */
    public function imprimir_masivo()
    {
        AuthMiddleware::handle();

        $idLote = (int) ($_GET['id_lote'] ?? 0);
        if ($idLote <= 0) die("ID de lote inválido.");

        $boletas = $this->planillasModel->obtenerBoletasMasivasPdf($idLote);

        if (empty($boletas)) {
            echo '<script>alert("No se encontraron recibos"); window.close();</script>';
            exit;
        }
        
        // 1. Declarar variables
        $empresa = [
            'nombre' => 'Agua Belén',
            'ruc' => '20123456789',
            'direccion' => 'Av. Principal 123, Ciudad'
        ];

        // 2. Extraer y requerir la vista directamente (SIN el layout)
        ob_start();
        extract(compact('boletas', 'empresa'));
        require BASE_PATH . '/app/views/rrhh/planillas_boleta_pdf.php';
        $html = ob_get_clean();

        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set(['isRemoteEnabled' => true]);
        $dompdf->setOptions($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait'); 
        $dompdf->render();

        if (ob_get_length()) ob_end_clean(); // <-- PROTECCIÓN CONTRA CORRUPCIÓN PDF

        $nombreArchivo = 'Boletas_Masivas_Lote_' . $idLote . '.pdf';
        $dompdf->stream($nombreArchivo, ["Attachment" => 0]);
        exit;
    }

    /**
     * ========================================================================
     * 7. REPORTES: Exportar Planilla (PDF General)
     * ========================================================================
     */
    public function imprimir_reporte_planilla(): void
    {
        AuthMiddleware::handle();

        $idLote = (int) ($_GET['id_lote'] ?? 0);
        if ($idLote <= 0) die("ID de lote inválido.");

        $loteActual = $this->planillasModel->obtenerLotePorId($idLote);
        if (!$loteActual) die("El lote solicitado no existe.");

        $detallesNomina = $this->planillasModel->obtenerDetallesLote($idLote);

        // 1. Declarar variables
        $boletas = $detallesNomina; 
        $lote = $loteActual;
        $empresa = [
            'nombre' => 'Agua Belén',
            'ruc' => '20123456789',
            'direccion' => 'Av. Principal 123, Ciudad'
        ];

        // 2. Extraer y requerir la vista directamente (SIN el layout)
        ob_start();
        extract(compact('boletas', 'lote', 'empresa'));
        require BASE_PATH . '/app/views/rrhh/planillas_boleta_pdf.php';
        $html = ob_get_clean();

        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set(['isRemoteEnabled' => true]);
        $dompdf->setOptions($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        if (ob_get_length()) ob_end_clean(); // <-- PROTECCIÓN CONTRA CORRUPCIÓN PDF

        $nombreArchivo = 'Reporte_Planilla_' . $loteActual['referencia'] . '.pdf';
        $dompdf->stream($nombreArchivo, ["Attachment" => 0]);
        exit;
    }

    /**
     * ========================================================================
     * 8. PAGAR PLANILLA (Egreso desde Tesorería)
     * ========================================================================
     */
    public function pagar(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $idLote = (int) ($_POST['id_lote'] ?? 0);
            
            if ($idLote > 0) {
                $exito = $this->planillasModel->registrarPagoLote($idLote, $_POST, AuthMiddleware::getUserId());
                
                if ($exito) {
                    redirect("planillas?id_lote={$idLote}&ok=" . urlencode('Planilla pagada.'));
                } else {
                    $errorMsg = $this->planillasModel->ultimoError ?? 'Error al pagar.';
                    redirect("planillas?id_lote={$idLote}&error=" . urlencode($errorMsg));
                }
            }
        }
    }

    /**
     * ========================================================================
     * 9. EXPORTAR A CSV (Súper rápido y liviano)
     * ========================================================================
     */
    public function exportar_csv(): void
    {
        AuthMiddleware::handle();
        $idLote = (int) ($_GET['id_lote'] ?? 0);
        if ($idLote <= 0) die("ID de lote inválido.");

        $lote = $this->planillasModel->obtenerLotePorId($idLote);
        $detalles = $this->planillasModel->obtenerDetallesLote($idLote);

        if (!$lote || empty($detalles)) die("No hay datos para exportar.");

        $nombreArchivo = "Planilla_" . $lote['referencia'] . ".csv";

        // Forzar la descarga del archivo en el navegador
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        
        // Limpiar buffer por si hay espacios en blanco
        if (ob_get_length()) ob_end_clean();

        $salida = fopen('php://output', 'w');
        
        // Agregar BOM para que Excel reconozca los tildes y ñ (UTF-8) correctamente
        fprintf($salida, chr(0xEF).chr(0xBB).chr(0xBF));

        // Encabezados de las columnas
        fputcsv($salida, ['DNI', 'EMPLEADO', 'CARGO', 'ASISTENCIA (Días)', 'INGRESOS (S/)', 'DEDUCCIONES (S/)', 'NETO A PAGAR (S/)']);

        // Imprimir cada fila de datos
        foreach ($detalles as $row) {
            fputcsv($salida, [
                $row['numero_documento'] ?? 'No registrado',
                $row['nombre_completo'],
                $row['cargo'],
                $row['dias_pagados'],
                number_format((float)$row['total_percepciones'], 2, '.', ''),
                number_format((float)$row['total_deducciones'], 2, '.', ''),
                number_format((float)$row['neto_a_pagar'], 2, '.', '')
            ]);
        }

        fclose($salida);
        exit;
    }

    /**
     * ========================================================================
     * 10. EXPORTAR A EXCEL (.xlsx) - FORMATO BOLETAS INDIVIDUALES
     * ========================================================================
     */
    public function exportar_excel(): void
    {
        AuthMiddleware::handle();
        $idLote = (int) ($_GET['id_lote'] ?? 0);
        if ($idLote <= 0) die("ID de lote inválido.");

        $lote = $this->planillasModel->obtenerLotePorId($idLote);
        $detalles = $this->planillasModel->obtenerDetallesLote($idLote);

        if (!$lote || empty($detalles)) die("No hay datos para exportar.");

        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            die("Falta instalar PhpSpreadsheet.");
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Boletas de Pago');

        // Ocultar líneas de cuadrícula para que parezca un documento en blanco limpio
        $sheet->setShowGridlines(false);

        $fila = 2; // Empezamos en la fila 2 para dejar un margen superior

        foreach ($detalles as $row) {
            // --- 1. ENCABEZADO DE LA BOLETA (Azul con letras blancas) ---
            $sheet->setCellValue('B' . $fila, 'BOLETA DE PAGO - ' . $lote['referencia']);
            $sheet->mergeCells("B{$fila}:E{$fila}"); // Unir celdas
            $sheet->getStyle("B{$fila}")->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("B{$fila}:E{$fila}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6EFD'); 
            $sheet->getStyle("B{$fila}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $fila++;

            // --- 2. DATOS DEL EMPLEADO ---
            $sheet->setCellValue('B' . $fila, 'EMPLEADO:');
            $sheet->setCellValue('C' . $fila, $row['nombre_completo']);
            $sheet->setCellValue('D' . $fila, 'DNI:');
            $sheet->setCellValueExplicit('E' . $fila, $row['numero_documento'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle("B{$fila}")->getFont()->setBold(true);
            $sheet->getStyle("D{$fila}")->getFont()->setBold(true);
            $fila++;

            $sheet->setCellValue('B' . $fila, 'CARGO:');
            $sheet->setCellValue('C' . $fila, $row['cargo']);
            $sheet->setCellValue('D' . $fila, 'ASISTENCIA:');
            $sheet->setCellValue('E' . $fila, $row['dias_pagados'] . ' días');
            $sheet->getStyle("B{$fila}")->getFont()->setBold(true);
            $sheet->getStyle("D{$fila}")->getFont()->setBold(true);
            $fila++;

            $sheet->setCellValue('B' . $fila, 'PERIODO:');
            $sheet->setCellValue('C' . $fila, date('d/m/Y', strtotime($lote['fecha_inicio'])) . ' al ' . date('d/m/Y', strtotime($lote['fecha_fin'])));
            $sheet->getStyle("B{$fila}")->getFont()->setBold(true);
            $fila += 2; // Salto de línea

            // --- 3. ENCABEZADOS DE LA MINI TABLA ---
            $sheet->setCellValue('B' . $fila, 'CONCEPTO');
            $sheet->setCellValue('D' . $fila, 'INGRESOS');
            $sheet->setCellValue('E' . $fila, 'DEDUCCIONES');
            
            $sheet->getStyle("B{$fila}:E{$fila}")->getFont()->setBold(true);
            $sheet->getStyle("B{$fila}:E{$fila}")->getBorders()->getBottom()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle("B{$fila}:E{$fila}")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F6FB'); // Fondo gris claro
            $fila++;

            // --- 4. DETALLE DE MONTOS ---
            $sheet->setCellValue('B' . $fila, 'Total Remuneraciones / Ingresos');
            $sheet->setCellValue('D' . $fila, (float)$row['total_percepciones']);
            $sheet->getStyle("D{$fila}")->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            $fila++;

            $sheet->setCellValue('B' . $fila, 'Total Descuentos / Deducciones');
            $sheet->setCellValue('E' . $fila, (float)$row['total_deducciones']);
            $sheet->getStyle("E{$fila}")->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            $fila++;

            // --- 5. NETO A PAGAR ---
            $sheet->setCellValue('B' . $fila, 'NETO A PAGAR');
            $sheet->setCellValue('E' . $fila, (float)$row['neto_a_pagar']);
            $sheet->getStyle("B{$fila}:E{$fila}")->getFont()->setBold(true);
            $sheet->getStyle("B{$fila}:E{$fila}")->getBorders()->getTop()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle("E{$fila}")->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            
            // Dibujar un borde exterior alrededor de toda esta boleta
            $inicioBoleta = $fila - 8;
            $sheet->getStyle("B{$inicioBoleta}:E{$fila}")->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

            // Dejar 4 filas de espacio para la boleta del siguiente trabajador
            $fila += 4;
        }

        // Anchos de columna predefinidos para que encaje perfecto
        $sheet->getColumnDimension('B')->setWidth(35);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(18);

        $nombreArchivo = "Boletas_Planilla_" . $lote['referencia'] . ".xlsx";

        if (ob_get_length()) ob_end_clean();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . urlencode($nombreArchivo) . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}