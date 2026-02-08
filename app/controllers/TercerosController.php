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

                // Cargar Ubigeo vía AJAX (departamentos, provincias, distritos)
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

                // ACCIÓN: CREAR
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

                // ACCIÓN: EDITAR
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

                // ACCIÓN: ELIMINAR
                if ($accion === 'eliminar') {
                    require_permiso('terceros.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');
                    
                    $this->tercerosModel->eliminar($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero eliminado correctamente.'];
                }

                // Respuesta AJAX general
                if (isset($respuesta) && es_ajax()) {
                    json_response($respuesta);
                    return;
                }

                // Consulta SUNAT (simulada o real)
                if (es_ajax() && $accion === 'consultar_sunat') {
                    require_permiso('terceros.crear');
                    $ruc = preg_replace('/\D/', '', (string) ($_POST['ruc'] ?? ''));
                    if (strlen($ruc) !== 11) {
                        throw new RuntimeException('RUC inválido.');
                    }

                    // Aquí iría tu integración real con API SUNAT
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
            }
        }

        // Vista principal
        $departamentos = $this->tercerosModel->obtenerDepartamentos();

        $this->render('terceros', [
            'terceros'           => $this->tercerosModel->listar(),
            'flash'              => $flash,
            'ruta_actual'        => 'tercero',
            'departamentos_list' => $departamentos
        ]);
    }

    /**
     * Valida y normaliza todos los datos del formulario de tercero
     */
    private function validarTercero(array $data): array
    {
        $tipoPersona   = trim((string) ($data['tipo_persona'] ?? ''));
        $tipoDoc       = trim((string) ($data['tipo_documento'] ?? ''));
        $numeroRaw     = trim((string) ($data['numero_documento'] ?? ''));
        $nombre        = trim((string) ($data['nombre_completo'] ?? ''));
        $direccion     = trim((string) ($data['direccion'] ?? ''));
        $email         = trim((string) ($data['email'] ?? ''));

        // Normalizar número de documento
        $numeroDigits = preg_replace('/\D/', '', $numeroRaw);
        $numero       = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $numeroRaw));

        // --- CORRECCIÓN UBIGEO ---
        // El frontend envía IDs (ej. "10", "1001"), pero la BD espera Nombres (ej. "HUANUCO").
        // Buscamos el nombre correspondiente al ID seleccionado.
        
        $departamentoId = !empty($data['departamento']) ? (string) $data['departamento'] : '';
        $provinciaId    = !empty($data['provincia'])    ? (string) $data['provincia']    : '';
        $distritoId     = !empty($data['distrito'])     ? (string) $data['distrito']     : '';

        $departamentoNombre = null;
        $provinciaNombre    = null;
        $distritoNombre     = null;

        // 1. Resolver Nombre Departamento
        if ($departamentoId !== '') {
            $deps = $this->tercerosModel->obtenerDepartamentos();
            foreach ($deps as $d) {
                if ((string)$d['id'] === $departamentoId) {
                    $departamentoNombre = $d['nombre'];
                    break;
                }
            }
        }

        // 2. Resolver Nombre Provincia (si hay depto)
        if ($departamentoId !== '' && $provinciaId !== '') {
            $provs = $this->tercerosModel->obtenerProvincias($departamentoId);
            foreach ($provs as $p) {
                if ((string)$p['id'] === $provinciaId) {
                    $provinciaNombre = $p['nombre'];
                    break;
                }
            }
        }

        // 3. Resolver Nombre Distrito (si hay provincia)
        if ($provinciaId !== '' && $distritoId !== '') {
            $dists = $this->tercerosModel->obtenerDistritos($provinciaId);
            foreach ($dists as $dt) {
                if ((string)$dt['id'] === $distritoId) {
                    $distritoNombre = $dt['nombre'];
                    break;
                }
            }
        }

        // TELÉFONOS
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

        // CUENTAS BANCARIAS / BILLETERAS DIGITALES
        $cuentasTipo            = $data['cuenta_tipo']            ?? [];
        $cuentasEntidad         = $data['cuenta_entidad']         ?? [];
        $cuentasTipoCta         = $data['cuenta_tipo_cta']        ?? [];
        $cuentasNumero          = $data['cuenta_numero']          ?? [];
        $cuentasCci             = $data['cuenta_cci']             ?? [];  // ← Aquí va número Yape/Plin o CCI
        $cuentasAlias           = $data['cuenta_alias']           ?? [];
        $cuentasMoneda          = $data['cuenta_moneda']          ?? [];
        $cuentasPrincipal       = $data['cuenta_principal']       ?? [];
        $cuentasBilletera       = $data['cuenta_billetera']       ?? [];
        $cuentasObservaciones   = $data['cuenta_observaciones']   ?? [];

        // Normalizar arrays
        $keys = ['tipo', 'entidad', 'tipo_cta', 'numero', 'cci', 'alias', 'moneda', 'principal', 'billetera', 'observaciones'];
        foreach ($keys as $k) {
            $var = "cuentas" . ucfirst($k);
            if (!is_array($$var)) $$var = [$$var];
        }

        $cuentasNormalizadas = [];
        $maxIndex = max(
            count($cuentasEntidad),
            count($cuentasCci),
            count($cuentasAlias),
            count($cuentasNumero)
        );

        for ($i = 0; $i < $maxIndex; $i++) {
            $entidad   = trim((string) ($cuentasEntidad[$i] ?? ''));
            $cci       = trim((string) ($cuentasCci[$i] ?? ''));
            $alias     = trim((string) ($cuentasAlias[$i] ?? ''));
            $numero    = trim((string) ($cuentasNumero[$i] ?? ''));

            // Si no hay entidad + (cci o alias o numero) → fila vacía
            if ($entidad === '' && $cci === '' && $alias === '' && $numero === '') {
                continue;
            }

            // Validación básica
            if ($entidad === '') {
                throw new RuntimeException("Indique la entidad/billetera en la cuenta #" . ($i + 1));
            }

            $cuentasNormalizadas[] = [
                'tipo'              => trim((string) ($cuentasTipo[$i] ?? '')),
                'entidad'           => $entidad,
                'tipo_cta'          => trim((string) ($cuentasTipoCta[$i] ?? '')),
                'numero_cuenta'     => $numero,
                'cci'               => $cci,                  // Número Yape/Plin o CCI real
                'alias'             => $alias,
                'moneda'            => in_array($cuentasMoneda[$i] ?? 'PEN', ['PEN', 'USD']) ? $cuentasMoneda[$i] : 'PEN',
                'principal'         => !empty($cuentasPrincipal[$i]) ? 1 : 0,
                'billetera_digital' => !empty($cuentasBilletera[$i]) ? 1 : 0,
                'observaciones'     => trim((string) ($cuentasObservaciones[$i] ?? ''))
            ];
        }

        // Validaciones generales
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

        // Validación empleado (sin cambios)
        if (!empty($data['es_empleado'])) {
            $tipoPago = trim((string) ($data['tipo_pago'] ?? ''));
            if ($tipoPago === '') throw new RuntimeException('Seleccione el tipo de pago del empleado.');
            
            if ($tipoPago === 'DIARIO' && (float) ($data['pago_diario'] ?? 0) <= 0) {
                throw new RuntimeException('El pago diario debe ser mayor a 0.');
            }
            if ($tipoPago === 'SUELDO' && (float) ($data['sueldo_basico'] ?? 0) <= 0) {
                throw new RuntimeException('El sueldo básico debe ser mayor a 0.');
            }
        }

        // Preparar payload para el modelo
        $prepared = $data;
        $prepared['numero_documento']      = $numero;
        $prepared['telefono']              = $telefonoPrincipal;
        $prepared['telefonos']             = $telefonosNormalizados;          // el modelo usa 'telefonos'
        $prepared['cuentas_bancarias']     = $cuentasNormalizadas;           // el modelo usa 'cuentas_bancarias'
        
        // Pasamos los NOMBRES en lugar de los IDs
        $prepared['departamento']          = $departamentoNombre;
        $prepared['provincia']             = $provinciaNombre;
        $prepared['distrito']              = $distritoNombre;

        return $prepared;
    }
}