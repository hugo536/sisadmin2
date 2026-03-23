<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/produccion/ProduccionRecetasModel.php';

class ProduccionRecetasController extends Controlador
{
    private ProduccionRecetasModel $produccionRecetasModel;

    public function __construct()
    {
        $this->produccionRecetasModel = new ProduccionRecetasModel();
    }

    private function parseDecimal($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalizado = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalizado) ? (float) $normalizado : 0.0;
    }

    public function recetas(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        if (es_ajax() && ($_GET['accion'] ?? '') === 'buscar_insumos_ajax') {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            $termino = trim((string) ($_GET['q'] ?? ''));
            
            // NUEVO: Capturamos el parámetro que configuramos en el JavaScript
            $soloConBom = (int) ($_GET['solo_con_bom'] ?? 0) === 1;

            // Le pasamos el parámetro al modelo
            $resultados = $termino !== ''
                ? $this->produccionRecetasModel->buscarInsumosStockeables($termino, $soloConBom)
                : [];
                
            echo json_encode(['success' => true, 'data' => $resultados]);
            exit;
        }

        // NUEVA ACCIÓN: Obtener el siguiente código autogenerado para una nueva receta
        if (es_ajax() && ($_GET['accion'] ?? '') === 'obtener_siguiente_codigo_ajax') {
            if (ob_get_level() > 0) {
                ob_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            try {
                $nuevoCodigo = $this->produccionRecetasModel->obtenerSiguienteCodigoReceta();
                echo json_encode(['success' => true, 'codigo' => $nuevoCodigo]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Error al generar código: ' . $e->getMessage()]);
            }
            exit;
        }

        $flash = $this->obtenerFlash();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);
            $esAjaxPost = es_ajax() || str_contains($accion, 'ajax');

            try {
                if ($accion === 'obtener_datos_nueva_version_ajax') {
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    header('Content-Type: application/json; charset=utf-8');
                    $idRecetaBase = (int) ($_POST['id_receta_base'] ?? 0);

                    if ($idRecetaBase <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Receta base inválida.']);
                        exit;
                    }

                    $datos = $this->produccionRecetasModel->obtenerDatosParaNuevaVersion($idRecetaBase);
                    echo json_encode(['success' => true, 'data' => $datos]);
                    exit;
                }

                if ($accion === 'listar_versiones_receta_ajax') {
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    header('Content-Type: application/json; charset=utf-8');
                    $idRecetaBase = (int) ($_POST['id_receta_base'] ?? 0);
                    if ($idRecetaBase <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Receta base inválida.']);
                        exit;
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $this->produccionRecetasModel->listarVersionesReceta($idRecetaBase),
                    ]);
                    exit;
                }

                if ($accion === 'obtener_receta_version_ajax') {
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    header('Content-Type: application/json; charset=utf-8');
                    $idReceta = (int) ($_POST['id_receta'] ?? 0);
                    if ($idReceta <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Receta inválida.']);
                        exit;
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $this->produccionRecetasModel->obtenerRecetaVersionParaEdicion($idReceta),
                    ]);
                    exit;
                }

                if ($accion === 'crear_receta' || $accion === 'guardar_receta_ajax') {
                    $detalles = [];
                    $insumos = $_POST['insumo_id'] ?? [];
                    $cantidades = $_POST['insumo_cantidad'] ?? [];
                    $mermas = $_POST['insumo_merma'] ?? [];
                    $costos = $_POST['insumo_costo'] ?? [];
                    $etapas = $_POST['insumo_etapa'] ?? [];

                    foreach ((array) $insumos as $idx => $idInsumo) {
                        if (empty($idInsumo)) {
                            continue;
                        }
                        $detalles[] = [
                            'id_insumo' => (int) $idInsumo,
                            'etapa' => (string) ($etapas[$idx] ?? 'General'),
                            'cantidad_por_unidad' => $this->parseDecimal($cantidades[$idx] ?? 0),
                            'merma_porcentaje' => $this->parseDecimal($mermas[$idx] ?? 0),
                            'costo_unitario' => $this->parseDecimal($costos[$idx] ?? 0),
                        ];
                    }

                    $parametros = [];
                    $paramIds = $_POST['parametro_id'] ?? [];
                    $paramValores = $_POST['parametro_valor'] ?? [];
                    foreach ((array) $paramIds as $idx => $idParam) {
                        if (empty($idParam)) {
                            continue;
                        }
                        $valor = trim((string) ($paramValores[$idx] ?? ''));
                        if ($valor !== '') {
                            $parametros[] = [
                                'id_parametro' => (int) $idParam,
                                'valor_objetivo' => (float) $valor,
                            ];
                        }
                    }

                    $manoObra = [];
                    $modPerfiles = $_POST['mod_perfil_puesto'] ?? [];
                    $modCostoHora = $_POST['mod_costo_hora_estimado'] ?? [];
                    $tiempoProduccionHoras = $this->parseDecimal($_POST['tiempo_produccion_horas'] ?? 0);
                    foreach ((array) $modPerfiles as $idx => $perfil) {
                        $perfilTexto = trim((string) $perfil);
                        $costoHora = $this->parseDecimal($modCostoHora[$idx] ?? 0);
                        if ($perfilTexto === '' || $tiempoProduccionHoras <= 0 || $costoHora <= 0) {
                            continue;
                        }
                        $manoObra[] = [
                            'perfil_puesto' => $perfilTexto,
                            'horas_estimadas' => $tiempoProduccionHoras,
                            'costo_hora_estimado' => $costoHora,
                        ];
                    }

                    $cif = [];
                    $cifActivos = $_POST['cif_id_activo'] ?? [];
                    $cifConceptos = $_POST['cif_concepto'] ?? [];
                    $cifCostos = $_POST['cif_costo_estimado'] ?? [];
                    foreach ((array) $cifConceptos as $idx => $concepto) {
                        $conceptoTexto = trim((string) $concepto);
                        $costo = $this->parseDecimal($cifCostos[$idx] ?? 0);
                        if ($conceptoTexto === '' || $costo <= 0) {
                            continue;
                        }
                        $cif[] = [
                            'id_activo' => (int) ($cifActivos[$idx] ?? 0),
                            'concepto' => $conceptoTexto,
                            'costo_estimado' => $costo,
                        ];
                    }

                    $codigoIngresado = trim((string) ($_POST['codigo'] ?? ''));
                    $idProd = (int) ($_POST['id_producto'] ?? 0);
                    if ($codigoIngresado === '') {
                        $codigoIngresado = 'REC-ITEM-' . str_pad((string) $idProd, 6, '0', STR_PAD_LEFT);
                    }

                    $payloadReceta = [
                        'id_producto' => $idProd,
                        'codigo' => $codigoIngresado,
                        'version' => (int) ($_POST['version'] ?? 1),
                        'descripcion' => (string) ($_POST['descripcion'] ?? ''),
                        'rendimiento_base' => $this->parseDecimal($_POST['rendimiento_base'] ?? 1),
                        'unidad_rendimiento' => (string) ($_POST['unidad_rendimiento'] ?? 'UND'),
                        'tiempo_produccion_horas' => $tiempoProduccionHoras > 0 ? $tiempoProduccionHoras : 1,
                        'detalles' => $detalles,
                        'parametros' => $parametros,
                        'mano_obra' => $manoObra,
                        'cif' => $cif,
                    ];

                    $idRecetaBase = (int) ($_POST['id_receta_base'] ?? 0);

                    if ($idRecetaBase > 0) {
                        $this->produccionRecetasModel->crearNuevaVersionDesdePayload($idRecetaBase, $payloadReceta, $userId);
                        $mensajeExito = 'Nueva versión creada y activada correctamente.';
                    } else {
                        $this->produccionRecetasModel->crearReceta($payloadReceta, $userId);
                        $mensajeExito = 'Receta creada correctamente.';
                    }

                    if ($esAjaxPost) {
                        if (ob_get_level() > 0) {
                            ob_clean();
                        }
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'message' => $mensajeExito]);
                        exit;
                    }

                    $this->setFlash('success', $mensajeExito);
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'nueva_version') {
                    $this->produccionRecetasModel->crearNuevaVersion((int) ($_POST['id_receta_base'] ?? 0), $userId);
                    $this->setFlash('success', 'Nueva versión creada y activada correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'crear_parametro_catalogo') {
                    $this->produccionRecetasModel->crearParametroCatalogo([
                        'nombre' => (string) ($_POST['nombre'] ?? ''),
                        'unidad_medida' => (string) ($_POST['unidad_medida'] ?? ''),
                        'descripcion' => (string) ($_POST['descripcion'] ?? ''),
                    ]);
                    $this->setFlash('success', 'Parámetro creado correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'editar_parametro_catalogo') {
                    $this->produccionRecetasModel->actualizarParametroCatalogo((int) ($_POST['id_parametro_catalogo'] ?? 0), [
                        'nombre' => (string) ($_POST['nombre'] ?? ''),
                        'unidad_medida' => (string) ($_POST['unidad_medida'] ?? ''),
                        'descripcion' => (string) ($_POST['descripcion'] ?? ''),
                    ]);
                    $this->setFlash('success', 'Parámetro actualizado correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'eliminar_parametro_catalogo') {
                    $this->produccionRecetasModel->eliminarParametroCatalogo((int) ($_POST['id_parametro_catalogo'] ?? 0));
                    $this->setFlash('success', 'Parámetro eliminado correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }
            } catch (Throwable $e) {
                if ($esAjaxPost) {
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('produccion/produccion_recetas', [
            'flash' => $flash,
            'recetas' => $this->produccionRecetasModel->listarRecetas(),
            'items_stockeables' => [],
            'parametros_catalogo' => $this->produccionRecetasModel->listarParametrosCatalogo(),
            
            // Reemplazamos los activos fijos por los nuevos Conceptos Operativos (MOD y CIF)
            'conceptos_operativos' => $this->produccionRecetasModel->listarConceptosOperativos(),
            
            'ruta_actual' => 'produccion/recetas',
        ]);
    }

    private function setFlash(string $tipo, string $texto): void
    {
        $_SESSION['produccion_flash'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function obtenerFlash(): array
    {
        $flash = ['tipo' => '', 'texto' => ''];
        if (isset($_SESSION['produccion_flash']) && is_array($_SESSION['produccion_flash'])) {
            $flash = [
                'tipo' => (string) ($_SESSION['produccion_flash']['tipo'] ?? ''),
                'texto' => (string) ($_SESSION['produccion_flash']['texto'] ?? ''),
            ];
            unset($_SESSION['produccion_flash']);
        }

        return $flash;
    }
}
?>