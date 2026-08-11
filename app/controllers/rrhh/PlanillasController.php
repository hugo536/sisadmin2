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

        // Cambiamos el límite a 10 lotes
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
                    // Motor dinámico (Tiempo real)
                    $detallesNomina = $this->planillasModel->calcularNominaEnMemoria($loteActual);
                } else {
                    // Datos fijos de la BD (Cerrados)
                    $detallesNomina = $this->planillasModel->obtenerDetallesLote($idLote);
                }
            }
        }

        // Endpoint AJAX para cargar movimientos manuales
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

        // Generar token CSRF para seguridad
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->render('rrhh/planillas', [
            'ruta_actual' => 'planillas',
            'lotes_recientes' => $lotesRecientes,
            'lote_actual' => $loteActual,
            'detalles_nomina' => $detallesNomina,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }

    /**
     * ========================================================================
     * 2. CREACIÓN DEL LOTE (Solo crea el encabezado del lote)
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
     * 3. AJUSTES MANUALES (Agregar Bonos / Deducciones extras)
     * ========================================================================
     */
    public function agregar_concepto(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $exito = $this->planillasModel->agregarConceptoManual($_POST);
            
            // Redirige a la página desde la que se envió el formulario
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
     * 4. CONGELAMIENTO (Cerrar Planilla y GUARDAR CÁLCULOS)
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
                    
                    // Doble validación de seguridad por si vulneran el frontend
                    foreach ($nominaCalculada as $row) {
                        if (!empty($row['tiene_conflicto'])) {
                            redirect("planillas?id_lote={$idLote}&error=" . urlencode('No se puede cerrar: hay empleados con asistencia incompleta. Corrige los registros antes de continuar.'));
                            return;
                        }
                    }
                }

                // Intentamos cerrar y capturamos si hay éxito o fallo
                $exito = $this->planillasModel->aprobarLote($idLote);
                
                if (!$exito) {
                    $errorMsg = $this->planillasModel->ultimoError ?? 'Ocurrió un error en la base de datos al cerrar el lote.';
                    redirect("planillas?id_lote={$idLote}&error=" . urlencode($errorMsg));
                    return;
                }
            }
            
            redirect("planillas?id_lote={$idLote}&ok=" . urlencode('Planilla cerrada, calculada y guardada con éxito.'));
        }
    }

    /**
     * ========================================================================
     * 5. REPORTES (Imprimir Boleta PDF)
     * ========================================================================
     */
    public function imprimir_boleta(): void
    {
        AuthMiddleware::handle();
        
        $idDetalle = (int) ($_GET['id'] ?? 0);
        if ($idDetalle <= 0) {
            die("Recibo no especificado.");
        }

        $boleta = $this->planillasModel->obtenerDatosBoletaPdf($idDetalle);

        if (!$boleta) {
            die("El recibo solicitado no existe o la planilla aún no ha sido cerrada.");
        }

        // Renderizamos la vista del PDF de forma oculta para capturar su HTML
        ob_start();
        $this->render('rrhh/planillas_boleta_pdf', [
            'boleta' => $boleta,
            'empresa' => [
                'nombre' => 'Tu Empresa S.A.C.',
                'ruc' => '20123456789',
                'direccion' => 'Av. Principal 123, Ciudad'
            ]
        ], true); 
        $html = ob_get_clean();

        // Inicializar DomPDF
        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set(array('isRemoteEnabled' => true));
        $dompdf->setOptions($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $nombreArchivo = 'Boleta_' . str_replace(' ', '_', $boleta['nombre_completo']) . '.pdf';

        $dompdf->stream($nombreArchivo, ["Attachment" => 0]);
        exit;
    }

    public function imprimir_masivo()
    {
        AuthMiddleware::handle();

        $idLote = (int) ($_GET['id_lote'] ?? 0);
        if ($idLote <= 0) {
            die("ID de lote inválido.");
        }

        $boletas = $this->planillasModel->obtenerBoletasMasivasPdf($idLote);

        if (empty($boletas)) {
            // Renderizamos una alerta elegante en la nueva pestaña y luego la cerramos
            echo '<!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>Aviso de Sistema</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                <style>body { background-color: #f3f6fb; font-family: sans-serif; }</style>
            </head>
            <body>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        Swal.fire({
                            icon: "info",
                            title: "Lote sin pagos",
                            text: "No se encontraron recibos con montos mayores a S/ 0.00 en este lote.",
                            confirmButtonText: "Cerrar pestaña",
                            confirmButtonColor: "#0d6efd",
                            allowOutsideClick: false,
                            allowEscapeKey: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.close();
                            }
                        });
                    });
                </script>
            </body>
            </html>';
            exit;
        }

        // Aquí llamas a tu librería PDF (DOMPDF, mPDF, etc)
        // Ejemplo usando solo HTML para imprimir con el navegador:
        $vistaBoletas = BASE_PATH . '/app/views/rrhh/planillas_boleta_pdf.php';

        if (!file_exists($vistaBoletas)) {
            throw new RuntimeException('No se encontró la vista de impresión masiva de boletas.');
        }

        require_once $vistaBoletas;
    }

    /**
     * ========================================================================
     * 6. REPORTES (Imprimir Reporte General de Planilla)
     * ========================================================================
     */
    public function imprimir_reporte_planilla(): void
    {
        AuthMiddleware::handle();

        $idLote = (int) ($_GET['id_lote'] ?? 0);
        if ($idLote <= 0) {
            die("ID de lote inválido.");
        }

        // 1. Obtener la información del lote
        $loteActual = $this->planillasModel->obtenerLotePorId($idLote);
        if (!$loteActual) {
            die("El lote solicitado no existe.");
        }

        // 2. Obtener los detalles de la nómina para ese lote
        // (Asumiendo que esta función trae a todos los empleados del lote)
        $detallesNomina = $this->planillasModel->obtenerDetallesLote($idLote);

        // 3. Generar el PDF (Similar a tu método imprimir_boleta)
        ob_start();
        
        // AQUÍ ES DONDE LLAMAMOS A LA VISTA DEL REPORTE
        $this->render('rrhh/planillas_reporte_general_pdf', [
            'lote' => $loteActual,
            'detalles' => $detallesNomina,
            'empresa' => [
                'nombre' => 'Tu Empresa S.A.C.',
                'ruc' => '20123456789',
                'direccion' => 'Av. Principal 123, Ciudad'
            ]
        ], true); 
        
        $html = ob_get_clean();

        // Inicializar DomPDF
        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->set(['isRemoteEnabled' => true]);
        $dompdf->setOptions($options);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape'); // 'landscape' suele ser mejor para reportes anchos
        $dompdf->render();

        $nombreArchivo = 'Reporte_Planilla_' . $loteActual['referencia'] . '.pdf';

        $dompdf->stream($nombreArchivo, ["Attachment" => 0]);
        exit;
    }
}