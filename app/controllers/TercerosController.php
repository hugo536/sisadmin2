<?php
// Si tu proyecto usa namespaces, descomenta la siguiente línea:
// namespace App\Controllers;

// Usamos rutas relativas para evitar problemas con BASE_PATH
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/TercerosModel.php';

class TercerosController extends Controlador
{
    private $tercerosModel;

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
                    $estado = (int) ($_POST['estado'] ?? 0);
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
                    
                    $id = $this->tercerosModel->guardarCargo($nombre);
                    json_response(['ok' => true, 'id' => $id, 'nombre' => $nombre, 'mensaje' => 'Cargo guardado']);
                    return;
                }

                if (es_ajax() && $accion === 'editar_cargo') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($id <= 0 || $nombre === '') throw new Exception('Datos inválidos');
                    
                    $this->tercerosModel->actualizarCargo($id, $nombre);
                    json_response(['ok' => true, 'mensaje' => 'Cargo actualizado']);
                    return;
                }

                if (es_ajax() && $accion === 'eliminar_cargo') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido');
                    
                    $this->tercerosModel->eliminarCargo($id);
                    json_response(['ok' => true, 'mensaje' => 'Cargo desactivado']);
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
                    
                    $id = $this->tercerosModel->guardarArea($nombre);
                    json_response(['ok' => true, 'id' => $id, 'nombre' => $nombre, 'mensaje' => 'Área guardada']);
                    return;
                }

                if (es_ajax() && $accion === 'editar_area') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($id <= 0 || $nombre === '') throw new Exception('Datos inválidos');
                    
                    $this->tercerosModel->actualizarArea($id, $nombre);
                    json_response(['ok' => true, 'mensaje' => 'Área actualizada']);
                    return;
                }

                if (es_ajax() && $accion === 'eliminar_area') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new Exception('ID inválido');
                    
                    $this->tercerosModel->eliminarArea($id);
                    json_response(['ok' => true, 'mensaje' => 'Área desactivada']);
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
                    // Usamos una ruta relativa desde public si BASE_PATH falla, pero idealmente BASE_PATH debe estar definido
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

        $this->render('terceros_perfil', [
            'tercero' => $tercero,
            'documentos' => $documentos,
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

        $numeroDigits = preg_replace('/\D/', '', $numeroRaw);
        $numero       = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $numeroRaw));

        // --- UBIGEO: Resolver Nombres ---
        $departamentoId = !empty($data['departamento_id'] ?? $data['departamento']) ? (string) ($data['departamento_id'] ?? $data['departamento']) : '';
        $provinciaId    = !empty($data['provincia_id'] ?? $data['provincia'])    ? (string) ($data['provincia_id'] ?? $data['provincia'])    : '';
        $distritoId     = !empty($data['distrito_id'] ?? $data['distrito'])     ? (string) ($data['distrito_id'] ?? $data['distrito'])     : '';

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

        // --- CUENTAS ---
        $cuentasTipo            = $data['cuenta_tipo']            ?? [];
        $cuentasEntidad         = $data['cuenta_entidad']         ?? [];
        $cuentasTipoCta         = $data['cuenta_tipo_cuenta']     ?? $data['cuenta_tipo_cta'] ?? [];
        $cuentasNumero          = $data['cuenta_numero']          ?? [];
        $cuentasCci             = $data['cuenta_cci']             ?? [];  
        $cuentasAlias           = $data['cuenta_alias']           ?? [];
        $cuentasMoneda          = $data['cuenta_moneda']          ?? [];
        $cuentasPrincipal       = $data['cuenta_principal']       ?? [];
        $cuentasBilletera       = $data['cuenta_billetera']       ?? [];
        $cuentasObservaciones   = $data['cuenta_observaciones']   ?? [];

        $toArray = static function ($value): array {
            return is_array($value) ? $value : [$value];
        };

        $cuentasTipo          = $toArray($cuentasTipo);
        $cuentasEntidad       = $toArray($cuentasEntidad);
        $cuentasTipoCta       = $toArray($cuentasTipoCta);
        $cuentasNumero        = $toArray($cuentasNumero);
        $cuentasCci           = $toArray($cuentasCci);
        $cuentasAlias         = $toArray($cuentasAlias);
        $cuentasMoneda        = $toArray($cuentasMoneda);
        $cuentasPrincipal     = $toArray($cuentasPrincipal);
        $cuentasBilletera     = $toArray($cuentasBilletera);
        $cuentasObservaciones = $toArray($cuentasObservaciones);

        $cuentasNormalizadas = [];
        $maxIndex = max(count($cuentasEntidad), count($cuentasCci), count($cuentasAlias), count($cuentasNumero));

        for ($i = 0; $i < $maxIndex; $i++) {
            $entidad   = trim((string) ($cuentasEntidad[$i] ?? ''));
            $cci       = trim((string) ($cuentasCci[$i] ?? ''));
            $alias     = trim((string) ($cuentasAlias[$i] ?? ''));
            $numero    = trim((string) ($cuentasNumero[$i] ?? ''));

            if ($entidad === '' && $cci === '' && $alias === '' && $numero === '') continue;

            if ($entidad === '') {
                throw new Exception("Indique la entidad/billetera en la cuenta #" . ($i + 1));
            }

            $cuentasNormalizadas[] = [
                'tipo'              => trim((string) ($cuentasTipo[$i] ?? '')),
                'entidad'           => $entidad,
                'tipo_cta'          => trim((string) ($cuentasTipoCta[$i] ?? '')),
                'tipo_cuenta'       => trim((string) ($cuentasTipoCta[$i] ?? '')),
                'numero_cuenta'     => $numero,
                'cci'               => $cci,                              
                'alias'             => $alias,
                'moneda'            => in_array($cuentasMoneda[$i] ?? 'PEN', ['PEN', 'USD']) ? $cuentasMoneda[$i] : 'PEN',
                'principal'         => !empty($cuentasPrincipal[$i]) ? 1 : 0,
                'billetera_digital' => !empty($cuentasBilletera[$i]) ? 1 : 0,
                'observaciones'     => trim((string) ($cuentasObservaciones[$i] ?? ''))
            ];
        }

        if (!empty($data['es_distribuidor'])) {
            $data['es_cliente'] = 1;
        }

        // --- VALIDACIONES BÁSICAS ---
        $roles = [
            !empty($data['es_cliente']),
            !empty($data['es_proveedor']),
            !empty($data['es_empleado']),
            !empty($data['es_distribuidor']),
        ];

        if ($tipoPersona === '' || $tipoDoc === '' || $numero === '' || $nombre === '') {
            throw new Exception('Tipo de persona, documento y nombre son obligatorios.');
        }

        $representanteLegal = trim((string) ($data['representante_legal'] ?? ''));
        if ($tipoPersona === 'JURIDICA' && $representanteLegal === '') {
            throw new Exception('Representante legal es obligatorio para empresas.');
        }

        if ($tipoDoc === 'RUC' && strlen($numeroDigits) !== 11) {
            throw new Exception('El RUC debe tener 11 dígitos.');
        }
        if ($tipoDoc === 'DNI' && strlen($numeroDigits) !== 8) {
            throw new Exception('El DNI debe tener 8 dígitos.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El email no tiene un formato válido.');
        }

        if (!in_array(true, $roles, true)) {
            throw new Exception('Seleccione al menos un rol (Cliente, Proveedor, Empleado o Distribuidor).');
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

        // --- PREPARAR PAYLOAD ---
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

        return $prepared;
    }
}
