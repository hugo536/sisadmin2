<?php
// Si tu proyecto usa namespaces, descomenta la siguiente línea:
// namespace App\Controllers;

// Usamos rutas relativas para evitar problemas con BASE_PATH
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/TercerosModel.php';
// ELIMINADO: require_once __DIR__ . '/../models/terceros/TercerosEmpleadosHijosModel.php';

class TercerosController extends Controlador
{
    private $tercerosModel;
    // ELIMINADO: private $hijosEmpleadoModel;

    public function __construct()
    {
        // Validación de seguridad para la clase padre
        if (!class_exists('Controlador')) {
            // Intenta cargar la clase Controlador si no existe (ajusta la ruta si es necesario)
            if (file_exists(__DIR__ . '/../core/Controlador.php')) {
                require_once __DIR__ . '/../core/Controlador.php';
            } elseif (file_exists(__DIR__ . '/../libs/Controlador.php')) {
                require_once __DIR__ . '/../libs/Controlador.php';
            }
        }
        
        $this->tercerosModel = new TercerosModel();
        // ELIMINADO: $this->hijosEmpleadoModel = new TercerosEmpleadosHijosModel();
    }

    public function index()
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                // ==========================================
                // VALIDACIONES AJAX
                // ==========================================
                if (es_ajax() && $accion === 'validar_documento') {
                    require_permiso('terceros.crear');
                    $tipoDoc   = trim((string) ($_POST['tipo_documento'] ?? ''));
                    $numeroDoc = trim((string) ($_POST['numero_documento'] ?? ''));
                    $excludeId = isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : null;
                    
                    $existe = $tipoDoc !== '' && $numeroDoc !== ''
                        ? $this->tercerosModel->documentoExiste($tipoDoc, $numeroDoc, $excludeId)
                        : false;
                    
                    json_response(['ok' => true, 'existe' => $existe]);
                    return;
                }

                if (es_ajax() && $accion === 'toggle_estado') {
                    require_permiso('terceros.editar');
                    $id     = (int) ($_POST['id'] ?? 0);
                    $estado = ((int) ($_POST['estado'] ?? 0) === 1) ? 1 : 0;
                    if ($id <= 0) throw new Exception('ID inválido.');
                    
                    $this->tercerosModel->actualizarEstado($id, $estado, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Estado actualizado.']);
                    return;
                }

                if (es_ajax() && $accion === 'cargar_ubigeo') {
                    $tipo    = (string)($_POST['tipo'] ?? '');
                    $padreId = (string)($_POST['padre_id'] ?? '');
                    
                    $data = [];
                    switch ($tipo) {
                        case 'departamentos':
                            $data = $this->tercerosModel->obtenerDepartamentos();
                            break;
                        case 'provincias':
                            if ($padreId !== '') $data = $this->tercerosModel->obtenerProvincias($padreId);
                            break;
                        case 'distritos':
                            if ($padreId !== '') $data = $this->tercerosModel->obtenerDistritos($padreId);
                            break;
                    }
                    
                    json_response(['ok' => true, 'data' => $data]);
                    return;
                }

                if (es_ajax() && $accion === 'cargar_zonas_distribuidor') {
                    $idDistribuidor = $this->postInt(['tercero_id', 'distribuidor_id']);
                    if ($idDistribuidor <= 0) {
                        json_response(['ok' => true, 'data' => []]);
                        return;
                    }

                    $zonas = $this->tercerosModel->obtenerZonasDistribuidor($idDistribuidor);
                    json_response(['ok' => true, 'data' => $zonas]);
                    return;
                }

                if (es_ajax() && $accion === 'validar_conflictos_zonas') {
                    $excludeDistribuidorId = $this->postInt(['tercero_id', 'distribuidor_id']);
                    $zonas = $_POST['zonas'] ?? [];
                    if (!is_array($zonas)) {
                        $zonas = [$zonas];
                    }

                    $conflictos = $this->tercerosModel->obtenerConflictosZonasDistribuidor($zonas, $excludeDistribuidorId);
                    json_response(['ok' => true, 'conflictos' => $conflictos]);
                    return;
                }

                // ==========================================
                // GESTIÓN DE MAESTROS (CARGOS Y ÁREAS)
                // ==========================================

                // --- CARGOS ---
                if (es_ajax() && $accion === 'listar_cargos') {
                    $data = $this->tercerosModel->listarCargos();
                    json_response(['ok' => true, 'data' => $data]);
                    return;
                }

                if (es_ajax() && $accion === 'guardar_cargo') {
                    require_permiso('configuracion.editar');
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($nombre === '') throw new Exception('El nombre del cargo es obligatorio');
                    
                    $id = $this->tercerosModel->guardarCargo($nombre, $userId);
                    json_response(['ok' => true, 'id' => $id, 'nombre' => $nombre, 'mensaje' => 'Cargo guardado']);
                    return;
                }

                if (es_ajax() && $accion === 'editar_cargo') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($id <= 0 || $nombre === '') throw new Exception('Datos inválidos');
                    
                    $this->tercerosModel->actualizarCargo($id, $nombre, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Cargo actualizado']);
                    return;
                }

                if (es_ajax() && $accion === 'eliminar_cargo') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido');
                    
                    $this->tercerosModel->eliminarCargo($id, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Cargo desactivado']);
                    return;
                }

                if (es_ajax() && $accion === 'toggle_estado_cargo') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    $estado = ((int)($_POST['estado'] ?? 0) === 1) ? 1 : 0;
                    if ($id <= 0) throw new Exception('ID inválido');
                    $this->tercerosModel->cambiarEstadoCargo($id, $estado, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Estado de cargo actualizado']);
                    return;
                }

                // --- ÁREAS ---
                if (es_ajax() && $accion === 'listar_areas') {
                    $data = $this->tercerosModel->listarAreas();
                    json_response(['ok' => true, 'data' => $data]);
                    return;
                }

                if (es_ajax() && $accion === 'guardar_area') {
                    require_permiso('configuracion.editar');
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($nombre === '') throw new Exception('El nombre del área es obligatorio');
                    
                    $id = $this->tercerosModel->guardarArea($nombre, $userId);
                    json_response(['ok' => true, 'id' => $id, 'nombre' => $nombre, 'mensaje' => 'Área guardada']);
                    return;
                }

                if (es_ajax() && $accion === 'editar_area') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($id <= 0 || $nombre === '') throw new Exception('Datos inválidos');
                    
                    $this->tercerosModel->actualizarArea($id, $nombre, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Área actualizada']);
                    return;
                }

                if (es_ajax() && $accion === 'eliminar_area') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido');
                    
                    $this->tercerosModel->eliminarArea($id, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Área desactivada']);
                    return;
                }

                if (es_ajax() && $accion === 'toggle_estado_area') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    $estado = ((int)($_POST['estado'] ?? 0) === 1) ? 1 : 0;
                    if ($id <= 0) throw new Exception('ID inválido');
                    $this->tercerosModel->cambiarEstadoArea($id, $estado, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Estado de área actualizado']);
                    return;
                }

                // ==========================================
                // GESTIÓN DE DOCUMENTOS
                // ==========================================

                if ($accion === 'subir_documento') {
                    require_permiso('terceros.editar');
                    $idTercero = $this->postInt(['tercero_id', 'id_tercero']);
                    $tipoDoc   = trim((string)($_POST['tipo_documento'] ?? 'OTRO'));
                    $obs       = trim((string)($_POST['observaciones'] ?? ''));

                    if ($idTercero <= 0) throw new Exception('ID de tercero inválido');
                    if (empty($_FILES['archivo']['name'])) throw new Exception('No se ha seleccionado ningún archivo');

                    $file = $_FILES['archivo'];
                    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx'];
                    
                    if (!in_array($ext, $allowed)) throw new Exception('Formato de archivo no permitido');

                    // Crear directorio si no existe
                    $baseUploads = defined('BASE_PATH') ? BASE_PATH . '/public/uploads/terceros/' : __DIR__ . '/../../public/uploads/terceros/';
                    $uploadDir = $baseUploads . $idTercero . '/';
                    
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
                    $targetPath = $uploadDir . $fileName;
                    $publicPath = 'uploads/terceros/' . $idTercero . '/' . $fileName;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $this->tercerosModel->guardarDocumento([
                            'id_tercero' => $idTercero,
                            'tipo_documento' => $tipoDoc,
                            'nombre_archivo' => $file['name'],
                            'ruta_archivo' => $publicPath,
                            'extension' => $ext,
                            'observaciones' => $obs
                        ]);
                        
                        $flash = ['tipo' => 'success', 'texto' => 'Documento subido correctamente'];
                        header("Location: ?ruta=terceros/perfil&id=$idTercero&tab=documentos");
                        exit;
                    } else {
                        throw new Exception('Error al mover el archivo al servidor');
                    }
                }

                if ($accion === 'editar_documento') {
                    require_permiso('terceros.editar');
                    $docId = (int)($_POST['id_documento'] ?? 0);
                    $idTercero = $this->postInt(['tercero_id', 'id_tercero']);
                    $tipoDoc = trim((string)($_POST['tipo_documento'] ?? ''));
                    $obs = trim((string)($_POST['observaciones'] ?? ''));

                    if ($docId > 0) {
                        $this->tercerosModel->actualizarDocumento($docId, $tipoDoc, $obs);
                        $flash = ['tipo' => 'success', 'texto' => 'Detalles del documento actualizados'];
                    }
                    header("Location: ?ruta=terceros/perfil&id=$idTercero&tab=documentos");
                    exit;
                }

                if ($accion === 'eliminar_documento') {
                    require_permiso('terceros.editar');
                    $docId = (int)($_POST['id_documento'] ?? 0);
                    $idTercero = $this->postInt(['tercero_id', 'id_tercero']);
                    
                    if ($docId > 0) {
                        $this->tercerosModel->eliminarDocumento($docId);
                        $flash = ['tipo' => 'success', 'texto' => 'Documento eliminado'];
                    }
                    header("Location: ?ruta=terceros/perfil&id=$idTercero&tab=documentos");
                    exit;
                }

                // ==========================================
                // ACCIONES CRUD TERCEROS
                // ==========================================

                if ($accion === 'crear') {
                    require_permiso('terceros.crear');
                    $data = $this->validarTercero($_POST);
                    
                    if ($this->tercerosModel->documentoExiste($data['tipo_documento'], $data['numero_documento'])) {
                        throw new Exception('El documento ya se encuentra registrado.');
                    }

                    $conflictos = $this->tercerosModel->obtenerConflictosZonasDistribuidor($data['zonas_exclusivas'] ?? [], 0);
                    if (!empty($conflictos)) {
                        $zona = $conflictos[0];
                        throw new Exception('La zona exclusiva ' . ($zona['label'] ?: $zona['valor']) . ' ya pertenece al distribuidor ' . ($zona['distribuidor_nombre'] ?: ('#' . ($zona['tercero_id'] ?? $zona['distribuidor_id']))) . '.');
                    }
                    
                    $id = $this->tercerosModel->crear($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero registrado correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero registrado correctamente.'];
                }

                if ($accion === 'editar') {
                    require_permiso('terceros.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido.');
                    
                    $data = $this->validarTercero($_POST);
                    
                    if ($this->tercerosModel->documentoExiste($data['tipo_documento'], $data['numero_documento'], $id)) {
                        throw new Exception('El documento ya se encuentra registrado.');
                    }

                    $conflictos = $this->tercerosModel->obtenerConflictosZonasDistribuidor($data['zonas_exclusivas'] ?? [], $id);
                    if (!empty($conflictos)) {
                        $zona = $conflictos[0];
                        throw new Exception('La zona exclusiva ' . ($zona['label'] ?: $zona['valor']) . ' ya pertenece al distribuidor ' . ($zona['distribuidor_nombre'] ?: ('#' . ($zona['tercero_id'] ?? $zona['distribuidor_id']))) . '.');
                    }
                    
                    $this->tercerosModel->actualizar($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    require_permiso('terceros.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido.');
                    
                    $this->tercerosModel->eliminar($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero eliminado correctamente.'];
                }

                if (isset($respuesta) && es_ajax()) {
                    json_response($respuesta);
                    return;
                }

                if (es_ajax() && $accion === 'consultar_sunat') {
                    require_permiso('terceros.crear');
                    $ruc = preg_replace('/\D/', '', (string) ($_POST['ruc'] ?? ''));
                    if (strlen($ruc) !== 11) {
                        throw new Exception('RUC inválido.');
                    }
                    // Simulación (puedes reemplazar con API real más adelante)
                    $simulados = [
                        '20123456789' => ['razon_social' => 'Embotelladora Andina S.A.', 'direccion' => 'Av. Principal 123, Lima'],
                    ];
                    $respuestaSunat = $simulados[$ruc] ?? ['razon_social' => '', 'direccion' => ''];
                    json_response(['ok' => true] + $respuestaSunat);
                    return;
                }

            } catch (Exception $e) {
                if (es_ajax()) {
                    json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
                    return;
                }
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
                
                // Redirigir si falla documento
                if (in_array($accion, ['subir_documento', 'editar_documento', 'eliminar_documento'])) {
                    $idTercero = $this->postInt(['tercero_id', 'id_tercero']);
                    if ($idTercero > 0) {
                        header("Location: ?ruta=terceros/perfil&id=$idTercero&tab=documentos&error=" . urlencode($e->getMessage()));
                        exit;
                    }
                }
            } catch (Throwable $e) {
                 if (es_ajax()) {
                    json_response(['ok' => false, 'mensaje' => $e->getMessage()], 500);
                    return;
                }
                $flash = ['tipo' => 'error', 'texto' => "Error del sistema: " . $e->getMessage()];
            }
        }

        // Cargar datos para la vista principal
        $departamentos = $this->tercerosModel->obtenerDepartamentos();
        $cargos = $this->tercerosModel->listarCargos();
        $areas = $this->tercerosModel->listarAreas();

        $this->render('terceros', [
            'terceros'       => $this->tercerosModel->listar(),
            'flash'          => $flash,
            'ruta_actual'    => 'tercero',
            'departamentos_list' => $departamentos,
            'cargos_list'    => $cargos,
            'areas_list'     => $areas
        ]);
    }

    /**
     * Nueva Vista: Perfil del Tercero
     */
    public function perfil()
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ?ruta=terceros');
            exit;
        }

        $tercero = $this->tercerosModel->obtener($id);
        if (empty($tercero)) {
            header('Location: ?ruta=terceros');
            exit;
        }

        $documentos = $this->tercerosModel->listarDocumentos($id);
        $departamentos = $this->tercerosModel->obtenerDepartamentos();
        $hijosEmpleado = !empty($tercero['es_empleado']) ? $this->tercerosModel->listarHijosEmpleado($id) : [];

        $this->render('terceros_perfil', [
            'tercero' => $tercero,
            'documentos' => $documentos,
            'hijos_empleado' => $hijosEmpleado,
            'departamentos_list' => $departamentos,
            'ruta_actual' => 'tercero'
        ]);
    }

    private function postInt(array $keys, int $default = 0): int
    {
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                return (int) $_POST[$key];
            }
        }

        return $default;
    }

    private function validarTercero($data)
    {
        $tipoPersona   = strtoupper(trim((string) ($data['tipo_persona'] ?? '')));
        $tipoDoc       = trim((string) ($data['tipo_documento'] ?? ''));
        $numeroRaw     = trim((string) ($data['numero_documento'] ?? ''));
        $nombre        = trim((string) ($data['nombre_completo'] ?? ''));
        $email         = trim((string) ($data['email'] ?? ''));

        $numeroDocumentoDigits = preg_replace('/\D/', '', $numeroRaw);
        $numero       = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $numeroRaw));

        // --- UBIGEO: Resolver Nombres ---
        $departamentoId = $data['departamento_id'] ?? $data['departamento'] ?? '';
        $provinciaId    = $data['provincia_id']    ?? $data['provincia']    ?? '';
        $distritoId     = $data['distrito_id']     ?? $data['distrito']     ?? '';

        $departamentoNombre = null;
        $provinciaNombre    = null;
        $distritoNombre     = null;

        if ($departamentoId !== '') {
            $deps = $this->tercerosModel->obtenerDepartamentos();
            foreach ($deps as $d) {
                if ((string)$d['id'] === $departamentoId) {
                    $departamentoNombre = $d['nombre'];
                    break;
                }
            }
        }

        if ($departamentoId !== '' && $provinciaId !== '') {
            $provs = $this->tercerosModel->obtenerProvincias($departamentoId);
            foreach ($provs as $p) {
                if ((string)$p['id'] === $provinciaId) {
                    $provinciaNombre = $p['nombre'];
                    break;
                }
            }
        }

        if ($provinciaId !== '' && $distritoId !== '') {
            $dists = $this->tercerosModel->obtenerDistritos($provinciaId);
            foreach ($dists as $dt) {
                if ((string)$dt['id'] === $distritoId) {
                    $distritoNombre = $dt['nombre'];
                    break;
                }
            }
        }

        // --- TELÉFONOS ---
        $telefonos      = $data['telefonos']      ?? [];
        $telefonoTipos  = $data['telefono_tipos'] ?? [];
        
        if (!is_array($telefonos))     $telefonos     = [$telefonos];
        if (!is_array($telefonoTipos)) $telefonoTipos = [$telefonoTipos];

        $telefonosNormalizados = [];
        foreach ($telefonos as $i => $telRaw) {
            $tel = trim((string) $telRaw);
            if ($tel === '') continue;
            $telefonosNormalizados[] = [
                'telefono' => $tel,
                'tipo'     => trim((string) ($telefonoTipos[$i] ?? 'Móvil'))
            ];
        }
        $telefonoPrincipal = $telefonosNormalizados[0]['telefono'] ?? '';

        // --- CUENTAS BANCARIAS ---
        $cuentasTipoEntidad = $data['cuenta_tipo']             ?? [];
        $cuentasEntidad     = $data['cuenta_entidad']          ?? [];
        $cuentasTipoCuenta  = $data['cuenta_tipo_cta']         ?? $data['cuenta_tipo_cuenta'] ?? [];
        $cuentasNumero      = $data['cuenta_numero']           ?? [];
        $cuentasCci         = $data['cuenta_cci']              ?? [];
        $cuentasTitular     = $data['cuenta_titular']          ?? $data['cuenta_alias'] ?? [];
        $cuentasMoneda      = $data['cuenta_moneda']           ?? [];
        $cuentasPrincipal   = $data['cuenta_principal']        ?? [];
        $cuentasBilletera   = $data['cuenta_billetera']        ?? [];
        $cuentasObs         = $data['cuenta_observaciones']    ?? [];

        // Helper para asegurar arrays
        $toArray = static function ($value) { return is_array($value) ? $value : [$value]; };

        $cuentasTipoEntidad = $toArray($cuentasTipoEntidad);
        $cuentasEntidad     = $toArray($cuentasEntidad);
        $cuentasTipoCuenta  = $toArray($cuentasTipoCuenta);
        $cuentasNumero      = $toArray($cuentasNumero);
        $cuentasCci         = $toArray($cuentasCci);
        $cuentasTitular     = $toArray($cuentasTitular);
        $cuentasMoneda      = $toArray($cuentasMoneda);
        $cuentasPrincipal   = $toArray($cuentasPrincipal);
        $cuentasBilletera   = $toArray($cuentasBilletera);
        $cuentasObs         = $toArray($cuentasObs);

        $cuentasNormalizadas = [];
        $maxIndex = max(count($cuentasEntidad), count($cuentasNumero), count($cuentasCci));

        $normalizarTipoEntidad = static function ($val) {
            $v = mb_strtolower(trim((string)$val));
            if (in_array($v, ['banco', 'caja', 'billetera digital', 'otros'])) return ucwords($v);
            if ($v === 'billetera') return 'Billetera Digital';
            return 'Banco'; 
        };

        for ($i = 0; $i < $maxIndex; $i++) {
            $entidad = trim((string)($cuentasEntidad[$i] ?? ''));
            // Si la fila está vacía visualmente, la saltamos
            if ($entidad === '' && empty($cuentasNumero[$i]) && empty($cuentasCci[$i])) continue;

            $tipoEntidad = $normalizarTipoEntidad($cuentasTipoEntidad[$i] ?? 'Banco');
            $esBilletera = ($tipoEntidad === 'Billetera Digital' || !empty($cuentasBilletera[$i]));
            
            $numeroVal = trim((string)($cuentasNumero[$i] ?? ''));
            $cciVal    = trim((string)($cuentasCci[$i] ?? ''));
            
            if ($esBilletera) {
                $digits = preg_replace('/\D+/', '', $cciVal ?: $numeroVal);
                $cciVal = $digits; 
                $numeroVal = ''; 
            } else {
                if ($cciVal !== '' && strlen($cciVal) !== 20 && strlen($cciVal) < 6) {
                    throw new Exception("Cuenta #" . ($i + 1) . ": el CCI debe tener 20 dígitos o use número de cuenta.");
                }
            }

            $tipoCuentaVal = trim((string)($cuentasTipoCuenta[$i] ?? ''));
            if ($esBilletera && $tipoCuentaVal === '') $tipoCuentaVal = 'N/A';

            $titularVal = trim((string)($cuentasTitular[$i] ?? ''));
            if ($titularVal === '') {
                throw new Exception("Cuenta #" . ($i + 1) . ": el titular de la cuenta es obligatorio.");
            }

            $cuentasNormalizadas[] = [
                'tercero_id'        => null, 
                'tipo_entidad'      => $tipoEntidad,      
                'entidad'           => $entidad,
                'tipo_cuenta'       => $tipoCuentaVal,    
                'numero_cuenta'     => $numeroVal,
                'cci'               => $cciVal,
                'titular'           => $titularVal,
                'moneda'            => $cuentasMoneda[$i] ?? 'PEN',
                'principal'         => !empty($cuentasPrincipal[$i]) ? 1 : 0,
                'billetera_digital' => $esBilletera ? 1 : 0,
                'observaciones'     => trim((string)($cuentasObs[$i] ?? ''))
            ];
        }

        $esEmpleado = !empty($data['es_empleado']);

        // ==========================================
        // LÓGICA PARA HIJOS (ASIGNACIÓN FAMILIAR)
        // ==========================================
        $asignacionFamiliar = $esEmpleado && !empty($data['asignacion_familiar']);
        
        $hijosIds          = $data['hijo_id'] ?? [];
        $hijosNombres      = $data['hijo_nombre'] ?? [];
        $hijosNacimientos  = $data['hijo_fecha_nacimiento'] ?? [];
        // Se espera que los inputs "estudia" y "discapacidad" envíen 1 o 0 explícitamente (ej: vía Select)
        $hijosEstudios     = $data['hijo_esta_estudiando'] ?? []; 
        $hijosDiscapacidad = $data['hijo_discapacidad'] ?? [];

        // Asegurar arrays
        $hijosIds          = $toArray($hijosIds);
        $hijosNombres      = $toArray($hijosNombres);
        $hijosNacimientos  = $toArray($hijosNacimientos);
        $hijosEstudios     = $toArray($hijosEstudios);
        $hijosDiscapacidad = $toArray($hijosDiscapacidad);

        $hijosNormalizados = [];

        // Solo procesamos hijos si la asignación familiar está activa
        if ($asignacionFamiliar) {
            foreach ($hijosNombres as $i => $nombreHijo) {
                $nombreHijo = trim((string)$nombreHijo);
                
                // Si no hay nombre, ignoramos la fila (fila vacía)
                if ($nombreHijo === '') continue;

                $fechaNac = trim((string)($hijosNacimientos[$i] ?? ''));
                
                // Validación básica de fecha
                if ($fechaNac === '') {
                     throw new Exception("El hijo #".($i+1)." (" . htmlspecialchars($nombreHijo) . ") debe tener fecha de nacimiento.");
                }

                $hijosNormalizados[] = [
                    'id'               => (int)($hijosIds[$i] ?? 0), // ID para editar si ya existe
                    'nombre_completo'  => $nombreHijo,
                    'fecha_nacimiento' => $fechaNac,
                    'esta_estudiando'  => !empty($hijosEstudios[$i]) ? 1 : 0,
                    'discapacidad'     => !empty($hijosDiscapacidad[$i]) ? 1 : 0,
                ];
            }
        }

        if (!empty($data['es_distribuidor'])) {
            $data['es_cliente'] = 1;
        }

        // --- VALIDACIONES BÁSICAS ---
        $roles = [
            !empty($data['es_cliente']),
            !empty($data['es_proveedor']),
            $esEmpleado,
            !empty($data['es_distribuidor']),
        ];

        if ($tipoPersona === '' || $tipoDoc === '' || $numero === '' || $nombre === '') {
            throw new Exception('Tipo de persona, documento y nombre son obligatorios.');
        }

        $representanteLegal = trim((string) ($data['representante_legal'] ?? ''));
        if ($tipoPersona === 'JURIDICA' && $representanteLegal === '') {
            throw new Exception('Representante legal es obligatorio para empresas.');
        }

        if ($tipoDoc === 'RUC' && strlen($numeroDocumentoDigits) !== 11) {
            throw new Exception('El RUC debe tener 11 dígitos.');
        }
        if ($tipoDoc === 'DNI' && strlen($numeroDocumentoDigits) !== 8) {
            throw new Exception('El DNI debe tener 8 dígitos.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El email no tiene un formato válido.');
        }

        if (!in_array(true, $roles, true)) {
            throw new Exception('Seleccione al menos un rol (Cliente, Proveedor, Empleado o Distribuidor).');
        }

        if ($esEmpleado) {
            $cargo = trim((string) ($data['cargo'] ?? ''));
            $area = trim((string) ($data['area'] ?? ''));
            $fechaIngresoRaw = trim((string) ($data['fecha_ingreso'] ?? ''));
            $sueldoBasicoRaw = trim((string) ($data['sueldo_basico'] ?? ''));

            if ($cargo === '' || $area === '' || $fechaIngresoRaw === '' || $sueldoBasicoRaw === '') {
                throw new Exception('Para el rol Empleado, cargo, área, fecha de ingreso y sueldo básico son obligatorios.');
            }

            if (!is_numeric($sueldoBasicoRaw) || (float) $sueldoBasicoRaw < 0) {
                throw new Exception('El sueldo básico del empleado debe ser un número válido mayor o igual a 0.');
            }

            $fechaIngreso = DateTimeImmutable::createFromFormat('Y-m-d', $fechaIngresoRaw);
            if (!$fechaIngreso || $fechaIngreso->format('Y-m-d') !== $fechaIngresoRaw) {
                throw new Exception('La fecha de ingreso del empleado no tiene un formato válido.');
            }
        }

        $recordarCumpleanos = $esEmpleado && !empty($data['recordar_cumpleanos']);
        $fechaNacimientoRaw = trim((string) ($data['fecha_nacimiento'] ?? ''));
        $fechaNacimientoNormalizada = null;

        if ($recordarCumpleanos) {
            if ($fechaNacimientoRaw === '') {
                throw new Exception('Si activa recordar cumpleaños, debe registrar la fecha de nacimiento.');
            }

            $fechaNacimiento = DateTimeImmutable::createFromFormat('Y-m-d', $fechaNacimientoRaw);
            $hoy = new DateTimeImmutable('today');
            if (!$fechaNacimiento || $fechaNacimiento->format('Y-m-d') !== $fechaNacimientoRaw) {
                throw new Exception('La fecha de nacimiento no tiene un formato válido.');
            }
            if ($fechaNacimiento > $hoy) {
                throw new Exception('La fecha de nacimiento no puede ser mayor a la fecha actual.');
            }

            $fechaNacimientoNormalizada = $fechaNacimiento->format('Y-m-d');
        }

        $zonas = $data['zonas_exclusivas'] ?? [];
        if (!is_array($zonas)) {
            $zonas = [$zonas];
        }
        $zonasLimpias = [];
        foreach ($zonas as $zona) {
            $zona = trim((string)$zona);
            if ($zona === '' || !preg_match('/^[^|]+\|[^|]*\|[^|]*$/', $zona)) {
                continue;
            }
            $zonasLimpias[] = $zona;
        }

        // --- PREPARAR PAYLOAD FINAL ---
        $prepared = $data;
        $prepared['tipo_persona']          = $tipoPersona;
        $prepared['numero_documento']      = $numero;
        $prepared['representante_legal']   = $tipoPersona === 'JURIDICA' ? $representanteLegal : null;
        $prepared['telefono']              = $telefonoPrincipal;
        $prepared['telefonos']             = $telefonosNormalizados; 
        $prepared['cuentas_bancarias']     = $cuentasNormalizadas;   
        
        $prepared['departamento']          = $departamentoNombre;
        $prepared['provincia']             = $provinciaNombre;
        $prepared['distrito']              = $distritoNombre;
        $prepared['departamento_id']       = $departamentoId !== '' ? $departamentoId : null;
        $prepared['provincia_id']          = $provinciaId !== '' ? $provinciaId : null;
        $prepared['distrito_id']           = $distritoId !== '' ? $distritoId : null;
        $prepared['zonas_exclusivas']      = $zonasLimpias;
        $prepared['recordar_cumpleanos']    = $recordarCumpleanos ? 1 : 0;
        $prepared['fecha_nacimiento']        = $fechaNacimientoNormalizada;
        $prepared['genero']                  = $esEmpleado ? trim((string) ($data['genero'] ?? '')) : '';
        $prepared['estado_civil']            = $esEmpleado ? trim((string) ($data['estado_civil'] ?? '')) : '';
        $prepared['nivel_educativo']         = $esEmpleado ? trim((string) ($data['nivel_educativo'] ?? '')) : '';
        $prepared['contacto_emergencia_nombre'] = $esEmpleado ? trim((string) ($data['contacto_emergencia_nombre'] ?? '')) : '';
        $prepared['contacto_emergencia_telf']   = $esEmpleado ? trim((string) ($data['contacto_emergencia_telf'] ?? '')) : '';
        $prepared['tipo_sangre']                = $esEmpleado ? strtoupper(trim((string) ($data['tipo_sangre'] ?? ''))) : '';

        // Agregamos la información de Asignación Familiar e Hijos al Payload
        $prepared['asignacion_familiar'] = $asignacionFamiliar ? 1 : 0;
        $prepared['hijos_lista']         = $hijosNormalizados;

        $tiposSangreValidos = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        if (!in_array($prepared['tipo_sangre'], $tiposSangreValidos, true)) {
            throw new Exception('El tipo de sangre seleccionado no es válido.');
        }

        return $prepared;
    }
}
