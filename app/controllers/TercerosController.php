<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/TercerosModel.php';

class TercerosController extends Controlador
{
    private TercerosModel $tercerosModel;

    public function __construct()
    {
        $this->tercerosModel = new TercerosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                // Validación AJAX de documento duplicado
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

                // Cambio de estado AJAX (Switch)
                if (es_ajax() && $accion === 'toggle_estado') {
                    require_permiso('terceros.editar');
                    $id     = (int) ($_POST['id'] ?? 0);
                    $estado = (int) ($_POST['estado'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');
                    
                    $this->tercerosModel->actualizarEstado($id, $estado, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Estado actualizado.']);
                    return;
                }

                // Cargar Ubigeo vía AJAX
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

                // ==========================================
                // ACCIONES: CARGOS (CRUD)
                // ==========================================

                if (es_ajax() && $accion === 'listar_cargos') {
                    $data = $this->tercerosModel->listarCargos();
                    json_response(['ok' => true, 'data' => $data]);
                    return;
                }

                if (es_ajax() && $accion === 'guardar_cargo') {
                    require_permiso('configuracion.editar');
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($nombre === '') throw new RuntimeException('El nombre del cargo es obligatorio');
                    
                    $id = $this->tercerosModel->guardarCargo($nombre);
                    json_response(['ok' => true, 'id' => $id, 'nombre' => $nombre, 'mensaje' => 'Cargo guardado']);
                    return;
                }

                if (es_ajax() && $accion === 'editar_cargo') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($id <= 0 || $nombre === '') throw new RuntimeException('Datos inválidos');
                    
                    $this->tercerosModel->actualizarCargo($id, $nombre);
                    json_response(['ok' => true, 'mensaje' => 'Cargo actualizado']);
                    return;
                }

                if (es_ajax() && $accion === 'eliminar_cargo') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido');
                    
                    $this->tercerosModel->eliminarCargo($id);
                    json_response(['ok' => true, 'mensaje' => 'Cargo desactivado']);
                    return;
                }

                // ==========================================
                // ACCIONES: ÁREAS (CRUD)
                // ==========================================

                if (es_ajax() && $accion === 'listar_areas') {
                    $data = $this->tercerosModel->listarAreas();
                    json_response(['ok' => true, 'data' => $data]);
                    return;
                }

                if (es_ajax() && $accion === 'guardar_area') {
                    require_permiso('configuracion.editar');
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($nombre === '') throw new RuntimeException('El nombre del área es obligatorio');
                    
                    $id = $this->tercerosModel->guardarArea($nombre);
                    json_response(['ok' => true, 'id' => $id, 'nombre' => $nombre, 'mensaje' => 'Área guardada']);
                    return;
                }

                if (es_ajax() && $accion === 'editar_area') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    $nombre = trim((string)($_POST['nombre'] ?? ''));
                    if ($id <= 0 || $nombre === '') throw new RuntimeException('Datos inválidos');
                    
                    $this->tercerosModel->actualizarArea($id, $nombre);
                    json_response(['ok' => true, 'mensaje' => 'Área actualizada']);
                    return;
                }

                if (es_ajax() && $accion === 'eliminar_area') {
                    require_permiso('configuracion.editar');
                    $id = (int)($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido');
                    
                    $this->tercerosModel->eliminarArea($id);
                    json_response(['ok' => true, 'mensaje' => 'Área desactivada']);
                    return;
                }

                // ==========================================
                // ACCIONES: DOCUMENTOS
                // ==========================================

                if ($accion === 'subir_documento') {
                    require_permiso('terceros.editar');
                    $idTercero = (int)($_POST['id_tercero'] ?? 0);
                    $tipoDoc   = trim((string)($_POST['tipo_documento'] ?? 'OTRO'));
                    $obs       = trim((string)($_POST['observaciones'] ?? ''));

                    if ($idTercero <= 0) throw new RuntimeException('ID de tercero inválido');
                    if (empty($_FILES['archivo']['name'])) throw new RuntimeException('No se ha seleccionado ningún archivo');

                    $file = $_FILES['archivo'];
                    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx'];
                    
                    if (!in_array($ext, $allowed)) throw new RuntimeException('Formato de archivo no permitido');

                    // Crear directorio si no existe
                    $uploadDir = BASE_PATH . '/public/uploads/terceros/' . $idTercero . '/';
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
                        // Redirigir al perfil
                        header("Location: ?ruta=terceros/perfil&id=$idTercero&tab=documentos");
                        exit;
                    } else {
                        throw new RuntimeException('Error al mover el archivo al servidor');
                    }
                }

                if ($accion === 'editar_documento') {
                    require_permiso('terceros.editar');
                    $docId = (int)($_POST['id_documento'] ?? 0);
                    $idTercero = (int)($_POST['id_tercero'] ?? 0);
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
                    $idTercero = (int)($_POST['id_tercero'] ?? 0);
                    
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
                        throw new RuntimeException('El documento ya se encuentra registrado.');
                    }
                    
                    $id = $this->tercerosModel->crear($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero registrado correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero registrado correctamente.'];
                }

                if ($accion === 'editar') {
                    require_permiso('terceros.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');
                    
                    $data = $this->validarTercero($_POST);
                    
                    if ($this->tercerosModel->documentoExiste($data['tipo_documento'], $data['numero_documento'], $id)) {
                        throw new RuntimeException('El documento ya se encuentra registrado.');
                    }
                    
                    $this->tercerosModel->actualizar($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    require_permiso('terceros.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');
                    
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
                        throw new RuntimeException('RUC inválido.');
                    }
                    $simulados = [
                        '20123456789' => ['razon_social' => 'Embotelladora Andina S.A.', 'direccion' => 'Av. Principal 123, Lima'],
                        '20600000001' => ['razon_social' => 'Distribuidora Ejemplo SAC', 'direccion' => 'Jr. Comercio 456, Huanuco'],
                    ];
                    $respuestaSunat = $simulados[$ruc] ?? ['razon_social' => '', 'direccion' => ''];
                    json_response(['ok' => true] + $respuestaSunat);
                    return;
                }

            } catch (Throwable $e) {
                if (es_ajax()) {
                    json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
                    return;
                }
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
                
                // Si falla subida, redirigir back
                if (in_array($accion, ['subir_documento', 'editar_documento', 'eliminar_documento'])) {
                     $idTercero = (int)($_POST['id_tercero'] ?? 0);
                     if ($idTercero > 0) {
                        header("Location: ?ruta=terceros/perfil&id=$idTercero&tab=documentos&error=" . urlencode($e->getMessage()));
                        exit;
                     }
                }
            }
        }

        // Vista principal
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
    public function perfil(): void
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

    private function validarTercero(array $data): array
    {
        $tipoPersona   = trim((string) ($data['tipo_persona'] ?? ''));
        $tipoDoc       = trim((string) ($data['tipo_documento'] ?? ''));
        $numeroRaw     = trim((string) ($data['numero_documento'] ?? ''));
        $nombre        = trim((string) ($data['nombre_completo'] ?? ''));
        $email         = trim((string) ($data['email'] ?? ''));

        $numeroDigits = preg_replace('/\D/', '', $numeroRaw);
        $numero       = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $numeroRaw));

        $departamentoId = !empty($data['departamento']) ? (string) $data['departamento'] : '';
        $provinciaId    = !empty($data['provincia'])    ? (string) $data['provincia']    : '';
        $distritoId     = !empty($data['distrito'])     ? (string) $data['distrito']     : '';

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

        $cuentasTipo            = $data['cuenta_tipo']            ?? [];
        $cuentasEntidad         = $data['cuenta_entidad']         ?? [];
        $cuentasTipoCta         = $data['cuenta_tipo_cta']        ?? [];
        $cuentasNumero          = $data['cuenta_numero']          ?? [];
        $cuentasCci             = $data['cuenta_cci']             ?? [];  
        $cuentasAlias           = $data['cuenta_alias']           ?? [];
        $cuentasMoneda          = $data['cuenta_moneda']          ?? [];
        $cuentasPrincipal       = $data['cuenta_principal']       ?? [];
        $cuentasBilletera       = $data['cuenta_billetera']       ?? [];
        $cuentasObservaciones   = $data['cuenta_observaciones']   ?? [];

        $keys = ['tipo', 'entidad', 'tipo_cta', 'numero', 'cci', 'alias', 'moneda', 'principal', 'billetera', 'observaciones'];
        foreach ($keys as $k) {
            $var = "cuentas" . ucfirst($k);
            if (!is_array($$var)) $$var = [$$var];
        }

        $cuentasNormalizadas = [];
        $maxIndex = max(count($cuentasEntidad), count($cuentasCci), count($cuentasAlias), count($cuentasNumero));

        for ($i = 0; $i < $maxIndex; $i++) {
            $entidad   = trim((string) ($cuentasEntidad[$i] ?? ''));
            $cci       = trim((string) ($cuentasCci[$i] ?? ''));
            $alias     = trim((string) ($cuentasAlias[$i] ?? ''));
            $numero    = trim((string) ($cuentasNumero[$i] ?? ''));

            if ($entidad === '' && $cci === '' && $alias === '' && $numero === '') continue;

            if ($entidad === '') {
                throw new RuntimeException("Indique la entidad/billetera en la cuenta #" . ($i + 1));
            }

            $cuentasNormalizadas[] = [
                'tipo'              => trim((string) ($cuentasTipo[$i] ?? '')),
                'entidad'           => $entidad,
                'tipo_cta'          => trim((string) ($cuentasTipoCta[$i] ?? '')),
                'numero_cuenta'     => $numero,
                'cci'               => $cci,                              
                'alias'             => $alias,
                'moneda'            => in_array($cuentasMoneda[$i] ?? 'PEN', ['PEN', 'USD']) ? $cuentasMoneda[$i] : 'PEN',
                'principal'         => !empty($cuentasPrincipal[$i]) ? 1 : 0,
                'billetera_digital' => !empty($cuentasBilletera[$i]) ? 1 : 0,
                'observaciones'     => trim((string) ($cuentasObservaciones[$i] ?? ''))
            ];
        }

        $roles = [
            !empty($data['es_cliente']),
            !empty($data['es_proveedor']),
            !empty($data['es_empleado']),
        ];

        if ($tipoPersona === '' || $tipoDoc === '' || $numero === '' || $nombre === '') {
            throw new RuntimeException('Tipo de persona, documento y nombre son obligatorios.');
        }

        if ($tipoDoc === 'RUC' && strlen($numeroDigits) !== 11) {
            throw new RuntimeException('El RUC debe tener 11 dígitos.');
        }
        if ($tipoDoc === 'DNI' && strlen($numeroDigits) !== 8) {
            throw new RuntimeException('El DNI debe tener 8 dígitos.');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El email no tiene un formato válido.');
        }

        if (!in_array(true, $roles, true)) {
            throw new RuntimeException('Seleccione al menos un rol (Cliente, Proveedor o Empleado).');
        }

        // Validación empleado
        if (!empty($data['es_empleado'])) {
            // Nota: Se han relajado validaciones estrictas aquí porque 
            // el controller ya no debe validar "tipo_pago" obligatorio si solo se está creando un borrador
            // pero mantenemos lo básico si se envían.
        }

        $prepared = $data;
        $prepared['numero_documento']      = $numero;
        $prepared['telefono']              = $telefonoPrincipal;
        $prepared['telefonos']             = $telefonosNormalizados; 
        $prepared['cuentas_bancarias']     = $cuentasNormalizadas;   
        
        $prepared['departamento']          = $departamentoNombre;
        $prepared['provincia']             = $provinciaNombre;
        $prepared['distrito']              = $distritoNombre;

        return $prepared;
    }
}