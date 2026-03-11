<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/rrhh/PlanillasModel.php';
require_once BASE_PATH . '/app/models/TercerosModel.php'; 
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';

// Cargar librerías de Composer (DomPDF) si existe el archivo
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

class PlanillasController extends Controlador
{
    private PlanillasModel $planillasModel;
    private TercerosModel $tercerosModel;
    private TesoreriaCuentaModel $cuentasModel;

    public function __construct()
    {
        parent::__construct();
        $this->planillasModel = new PlanillasModel();
        $this->tercerosModel = new TercerosModel();
        $this->cuentasModel = new TesoreriaCuentaModel();
    }

    /**
     * ========================================================================
     * 1. VISTA PRINCIPAL (Dashboard de Lotes)
     * ========================================================================
     */
    public function index(): void
    {
        AuthMiddleware::handle();

        $lotesRecientes = $this->planillasModel->obtenerLotesRecientes(15);
        $loteActual = null;
        $detallesNomina = [];

        $idLote = (int) ($_GET['id_lote'] ?? 0);
        
        if ($idLote === 0 && !empty($lotesRecientes)) {
            $idLote = (int) $lotesRecientes[0]['id'];
        }

        if ($idLote > 0) {
            $loteActual = $this->planillasModel->obtenerLotePorId($idLote);
            if ($loteActual) {
                // ¡AQUÍ ESTABA EL ERROR! Ahora forzamos a MAYÚSCULAS para que siempre coincida con 'BORRADOR'
                $estadoLote = strtoupper((string) $loteActual['estado']);
                
                if (in_array($estadoLote, ['PENDIENTE', 'BORRADOR', 'CREADO'])) {
                    // Motor dinámico
                    $detallesNomina = $this->planillasModel->calcularNominaEnMemoria($loteActual);
                } else {
                    // Datos fijos de la BD
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

        $this->render('rrhh/planillas', [
            'ruta_actual' => 'planillas',
            'lotes_recientes' => $lotesRecientes,
            'lote_actual' => $loteActual,
            'detalles_nomina' => $detallesNomina,
            'cuentas' => $this->cuentasModel->listarActivas(),
            'metodos' => $this->listarMetodosPago()
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
        }

        try {
            $userId = AuthMiddleware::getUserId();
            // Ahora este método solo debe crear la fila en la tabla maestra (Lotes), no los detalles
            $idLoteNuevo = $this->planillasModel->generarLoteNomina($_POST, $userId);
            
            redirect("planillas?id_lote={$idLoteNuevo}&success=" . urlencode('Lote generado correctamente.'));
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
            // Esto se guardará en una tabla de novedades/conceptos extras, no en la tabla de cálculo final.
            $exito = $this->planillasModel->agregarConceptoManual($_POST);
            
            $referer = $_SERVER['HTTP_REFERER'] ?? 'planillas';
            
            if ($exito) {
                redirect($referer); 
            } else {
                redirect($referer . "&error=" . urlencode('No se pudo aplicar el ajuste.'));
            }
        }
    }

    /**
     * ========================================================================
     * 4. CONGELAMIENTO (Aprobar Lote y GUARDAR CÁLCULOS)
     * ========================================================================
     */
    public function aprobar(): void
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
                            redirect("planillas?id_lote={$idLote}&error=" . urlencode('No se puede aprobar: hay empleados con asistencia incompleta. Corrige los registros antes de continuar.'));
                            return;
                        }
                    }
                }

                // Al aprobar, el modelo deberá llamar a calcularNominaEnMemoria() una última vez
                // y ahí sí, hacer todos los INSERT en la base de datos final.
                $this->planillasModel->aprobarLote($idLote);
            }
            
            redirect("planillas?id_lote={$idLote}&success=" . urlencode('Lote aprobado, calculado y guardado con éxito.'));
        }
    }

    // EL MÉTODO recalcular() FUE ELIMINADO CON ÉXITO YA QUE SERÁ AUTOMÁTICO

    /**
     * ========================================================================
     * 5. TESORERÍA (Pagar todo el bloque)
     * ========================================================================
     */
    public function pagar_lote(): void
    {
        AuthMiddleware::handle();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('planillas');
        }

        $idLote = (int) ($_POST['id_lote'] ?? 0);
        $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);

        if ($idLote <= 0 || $idCuenta <= 0) {
            redirect("planillas?id_lote={$idLote}&error=" . urlencode('Datos incompletos para procesar la dispersión.'));
            return;
        }

        try {
            $userId = AuthMiddleware::getUserId();
            $this->planillasModel->pagarLoteNomina($_POST, $userId);
            
            redirect("planillas?id_lote={$idLote}&success=" . urlencode('Salida de dinero registrada con éxito. Lote PAGADO.'));
        } catch (Exception $e) {
            $msgError = urlencode($e->getMessage());
            redirect("planillas?id_lote={$idLote}&error={$msgError}");
        }
    }

    /**
     * ========================================================================
     * 6. REPORTES (Imprimir Boleta PDF)
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
            die("El recibo solicitado no existe o el lote aún no ha sido aprobado.");
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

    /**
     * ========================================================================
     * UTILIDADES
     * ========================================================================
     */
    private function listarMetodosPago(): array
    {
        $sql = 'SELECT id, nombre
                FROM tesoreria_metodos_pago
                WHERE estado = 1 AND deleted_at IS NULL
                ORDER BY nombre ASC';

        return Conexion::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function pagar_lote_mixto()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $idLote = (int) ($_POST['id_lote'] ?? 0);
                if ($idLote <= 0) {
                    throw new Exception('ID de lote inválido.');
                }
                
                // Aquí usa la variable de sesión donde guardas el ID del usuario actual en tu ERP
                $userId = $_SESSION['usuario_id'] ?? 1; 

                $modelo = new PlanillasModel();
                // Llamamos a la nueva función maestra
                $resultado = $modelo->pagarLoteNominaMixto($_POST, $userId);

                if ($resultado) {
                    // Redirigir de vuelta con mensaje de éxito
                    header('Location: ?ruta=planillas&id_lote=' . $idLote . '&ok=Lote pagado y dispersado correctamente');
                    exit;
                } else {
                    throw new Exception('No se pudo procesar el pago mixto.');
                }
            } catch (Exception $e) {
                // Redirigir de vuelta con mensaje de error
                header('Location: ?ruta=planillas&id_lote=' . ($_POST['id_lote'] ?? '') . '&error=' . urlencode($e->getMessage()));
                exit;
            }
        }
    }
}
