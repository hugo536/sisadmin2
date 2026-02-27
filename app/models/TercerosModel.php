<?php
declare(strict_types=1);

require_once __DIR__ . '/terceros/TercerosClientesModel.php';
require_once __DIR__ . '/terceros/TercerosProveedoresModel.php';
require_once __DIR__ . '/terceros/TercerosEmpleadosModel.php';
require_once __DIR__ . '/terceros/DistribuidoresModel.php';

class TercerosModel extends Modelo
{
    private TercerosClientesModel $clientesModel;
    private TercerosProveedoresModel $proveedoresModel;
    private TercerosEmpleadosModel $empleadosModel;
    private DistribuidoresModel $distribuidoresModel;

    /** @var array<string,bool> */
    private array $columnCache = [];

    public function __construct()
    {
        parent::__construct();
        $this->clientesModel = new TercerosClientesModel();
        $this->proveedoresModel = new TercerosProveedoresModel();
        $this->empleadosModel = new TercerosEmpleadosModel();
        $this->distribuidoresModel = new DistribuidoresModel();
    }

    // ==========================================
    // SECCIÓN 1: LECTURA DE TERCEROS (Listar/Obtener)
    // ==========================================

    public function listar(): array
    {
        $sql = "SELECT t.id, t.tipo_persona, t.tipo_documento, t.numero_documento, t.nombre_completo,
                       t.direccion, t.telefono, t.email, t.representante_legal,
                       t.departamento, t.provincia, t.distrito,
                       dep.id AS departamento_id, prov.id AS provincia_id, dist.id AS distrito_id,
                       t.rubro_sector, t.observaciones, t.es_cliente, t.es_proveedor, t.es_empleado, t.estado,
                       0 AS es_distribuidor
                FROM terceros t
                LEFT JOIN terceros_clientes tc ON t.id = tc.id_tercero
                LEFT JOIN terceros_proveedores tp ON t.id = tp.id_tercero
                LEFT JOIN terceros_empleados te ON t.id = te.id_tercero
                LEFT JOIN departamentos dep ON dep.nombre COLLATE utf8mb4_unicode_ci = t.departamento COLLATE utf8mb4_unicode_ci
                LEFT JOIN provincias prov ON prov.nombre COLLATE utf8mb4_unicode_ci = t.provincia COLLATE utf8mb4_unicode_ci AND prov.departamento_id = dep.id
                LEFT JOIN distritos dist ON dist.nombre COLLATE utf8mb4_unicode_ci = t.distrito COLLATE utf8mb4_unicode_ci AND dist.provincia_id = prov.id
                LEFT JOIN distribuidores d ON t.id = d.id_tercero AND d.deleted_at IS NULL
                WHERE t.deleted_at IS NULL
                ORDER BY COALESCE(t.updated_at, t.created_at) DESC, t.id DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [];
        }

        $ids = array_map('intval', array_column($rows, 'id'));
        $telefonos = $this->obtenerTelefonosPorTerceros($ids);
        $cuentas   = $this->obtenerCuentasPorTerceros($ids);
        $zonas     = $this->distribuidoresModel->obtenerZonasPorTerceros($ids);
        $clientes = $this->obtenerClientesPorTerceros($ids);
        $proveedores = $this->obtenerProveedoresPorTerceros($ids);
        $empleados = $this->obtenerEmpleadosPorTerceros($ids);
        $distribuidores = $this->obtenerDistribuidoresPorTerceros($ids);

        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $bloqueo = $this->obtenerBloqueoEliminacion($id);
            $row = array_merge($row, $clientes[$id] ?? [], $proveedores[$id] ?? [], $empleados[$id] ?? []);
            $row['es_distribuidor'] = isset($distribuidores[$id]) ? 1 : 0;
            $row['telefonos']         = $telefonos[$id] ?? [];
            $row['cuentas_bancarias'] = $cuentas[$id]   ?? [];
            $row['zonas_exclusivas']  = $zonas[$id] ?? [];
            $row['zonas_exclusivas_resumen'] = implode(', ', array_filter(array_column($row['zonas_exclusivas'], 'label')));
            $row['puede_eliminar'] = $bloqueo['puede_eliminar'] ? 1 : 0;
            $row['motivo_no_eliminar'] = $bloqueo['motivo'];
            
            // Helpers de ubicación (compatibilidad visual)
            $row['departamento_nombre'] = $row['departamento'];
            $row['provincia_nombre']    = $row['provincia'];
            $row['distrito_nombre']     = $row['distrito'];
        }
        unset($row);

        return $rows;
    }

    public function obtenerZonasDistribuidor(int $idTercero): array
    {
        return $this->distribuidoresModel->obtenerZonasPorTercero($idTercero);
    }

    public function obtenerConflictosZonasDistribuidor(array $zonas, int $excludeDistribuidorId = 0): array
    {
        return $this->distribuidoresModel->obtenerConflictosZonas($zonas, $excludeDistribuidorId);
    }

    public function listarCatalogoCajasBancosActivos(): array
    {
        if (!$this->hasColumn('configuracion_cajas_bancos', 'id')) {
            return [];
        }

        $sql = "SELECT id, codigo, nombre, tipo, entidad, tipo_cuenta, moneda
                FROM configuracion_cajas_bancos
                WHERE estado = 1 AND deleted_at IS NULL
                ORDER BY COALESCE(updated_at, created_at) DESC, id DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarHijosEmpleado(int $idTercero): array
    {
        return $this->empleadosModel->listarHijos($idTercero);
    }

    public function obtener(int $id): array
    {
        $selectCumple = ($this->hasColumn('terceros_empleados', 'recordar_cumpleanos') && $this->hasColumn('terceros_empleados', 'fecha_nacimiento'))
            ? 'te.recordar_cumpleanos, te.fecha_nacimiento,'
            : '0 AS recordar_cumpleanos, NULL AS fecha_nacimiento,';
        $selectPerfilEmpleado = $this->hasColumn('terceros_empleados', 'genero')
            ? 'te.genero, te.estado_civil, te.nivel_educativo, te.contacto_emergencia_nombre, te.contacto_emergencia_telf, te.tipo_sangre,'
            : 'NULL AS genero, NULL AS estado_civil, NULL AS nivel_educativo, NULL AS contacto_emergencia_nombre, NULL AS contacto_emergencia_telf, NULL AS tipo_sangre,';

        $sql = "SELECT t.*, 
                       dep.id AS departamento_id, prov.id AS provincia_id, dist.id AS distrito_id,
                       -- Cliente
                       tc.dias_credito AS cliente_dias_credito,
                       tc.limite_credito AS cliente_limite_credito,
                       tc.condicion_pago AS cliente_condicion_pago,
                       
                       -- Proveedor
                       tp.dias_credito AS proveedor_dias_credito,
                       tp.condicion_pago AS proveedor_condicion_pago,
                       tp.forma_pago AS proveedor_forma_pago,
                       
                       -- Empleado
                       te.cargo, te.area, te.fecha_ingreso, te.estado_laboral, 
                       te.sueldo_basico, te.moneda, te.asignacion_familiar,
                       te.tipo_pago, te.pago_diario, te.tipo_contrato, te.fecha_cese,
                       te.regimen_pensionario, te.tipo_comision_afp, te.cuspp, te.essalud,
                       {$selectCumple}
                       {$selectPerfilEmpleado}

                       -- Distribuidor
                       CASE WHEN d.id_tercero IS NULL THEN 0 ELSE 1 END AS es_distribuidor
                FROM terceros t
                LEFT JOIN terceros_clientes tc ON t.id = tc.id_tercero
                LEFT JOIN terceros_proveedores tp ON t.id = tp.id_tercero
                LEFT JOIN terceros_empleados te ON t.id = te.id_tercero
                LEFT JOIN departamentos dep ON dep.nombre COLLATE utf8mb4_unicode_ci = t.departamento COLLATE utf8mb4_unicode_ci
                LEFT JOIN provincias prov ON prov.nombre COLLATE utf8mb4_unicode_ci = t.provincia COLLATE utf8mb4_unicode_ci AND prov.departamento_id = dep.id
                LEFT JOIN distritos dist ON dist.nombre COLLATE utf8mb4_unicode_ci = t.distrito COLLATE utf8mb4_unicode_ci AND dist.provincia_id = prov.id
                LEFT JOIN distribuidores d ON t.id = d.id_tercero AND d.deleted_at IS NULL
                WHERE t.id = :id AND t.deleted_at IS NULL LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [];
        }

        $row = array_merge(
            $row,
            $this->obtenerClientesPorTerceros([$id])[$id] ?? [],
            $this->obtenerProveedoresPorTerceros([$id])[$id] ?? [],
            $this->obtenerEmpleadosPorTerceros([$id])[$id] ?? []
        );
        $row['es_distribuidor'] = isset($this->obtenerDistribuidoresPorTerceros([$id])[$id]) ? 1 : 0;
        $row['telefonos']         = $this->obtenerTelefonosPorTerceros([$id])[$id] ?? [];
        $row['cuentas_bancarias'] = $this->obtenerCuentasPorTerceros([$id])[$id]   ?? [];
        $row['zonas_exclusivas'] = $this->distribuidoresModel->obtenerZonasPorTerceros([$id])[$id] ?? [];
        $row['zonas_exclusivas_resumen'] = implode(', ', array_filter(array_column($row['zonas_exclusivas'], 'label')));

        return $row;
    }

    private function obtenerClientesPorTerceros(array $ids): array
    {
        if (empty($ids)) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id_tercero, dias_credito AS cliente_dias_credito, limite_credito AS cliente_limite_credito,
                       condicion_pago AS cliente_condicion_pago
                FROM terceros_clientes
                WHERE id_tercero IN ($in)";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($ids);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int) $row['id_tercero']] = [
                'cliente_dias_credito' => (int) ($row['cliente_dias_credito'] ?? 0),
                'cliente_limite_credito' => $row['cliente_limite_credito'],
                'cliente_condicion_pago' => (string) ($row['cliente_condicion_pago'] ?? ''),
            ];
        }
        return $result;
    }

    private function obtenerProveedoresPorTerceros(array $ids): array
    {
        if (empty($ids)) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id_tercero, dias_credito AS proveedor_dias_credito,
                       condicion_pago AS proveedor_condicion_pago,
                       forma_pago AS proveedor_forma_pago
                FROM terceros_proveedores
                WHERE id_tercero IN ($in)";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($ids);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int) $row['id_tercero']] = [
                'proveedor_dias_credito' => (int) ($row['proveedor_dias_credito'] ?? 0),
                'proveedor_condicion_pago' => (string) ($row['proveedor_condicion_pago'] ?? ''),
                'proveedor_forma_pago' => (string) ($row['proveedor_forma_pago'] ?? ''),
            ];
        }
        return $result;
    }

    private function obtenerEmpleadosPorTerceros(array $ids): array
    {
        if (empty($ids)) return [];

        $selectCumple = ($this->hasColumn('terceros_empleados', 'recordar_cumpleanos') && $this->hasColumn('terceros_empleados', 'fecha_nacimiento'))
            ? 'recordar_cumpleanos, fecha_nacimiento,'
            : '0 AS recordar_cumpleanos, NULL AS fecha_nacimiento,';
        $selectPerfilEmpleado = $this->hasColumn('terceros_empleados', 'genero')
            ? 'genero, estado_civil, nivel_educativo, contacto_emergencia_nombre, contacto_emergencia_telf, tipo_sangre,'
            : 'NULL AS genero, NULL AS estado_civil, NULL AS nivel_educativo, NULL AS contacto_emergencia_nombre, NULL AS contacto_emergencia_telf, NULL AS tipo_sangre,';

        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id_tercero,
                       cargo, area, fecha_ingreso, estado_laboral,
                       sueldo_basico, moneda, asignacion_familiar,
                       tipo_pago, pago_diario, tipo_contrato, fecha_cese,
                       regimen_pensionario, tipo_comision_afp, cuspp, essalud,
                       {$selectCumple}
                       {$selectPerfilEmpleado}
                       id_tercero AS _id_ref
                FROM terceros_empleados
                WHERE id_tercero IN ($in)";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($ids);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id_tercero'] ?? 0);
            unset($row['_id_ref']);
            $result[$id] = $row;
        }
        return $result;
    }

    private function obtenerDistribuidoresPorTerceros(array $ids): array
    {
        if (empty($ids)) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id_tercero FROM distribuidores WHERE deleted_at IS NULL AND id_tercero IN ($in)";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($ids);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int) $row['id_tercero']] = true;
        }
        return $result;
    }

    // ==========================================
    // SECCIÓN 2: ESCRITURA DE TERCEROS (Crear/Actualizar/Eliminar)
    // ==========================================

    public function crear(array $data, int $userId): int
    {
        $payload = $this->mapPayload($data);
        
        try {
            $this->db()->beginTransaction();

            $existente = $this->buscarPorDocumento($payload['tipo_documento'], $payload['numero_documento']);
            $idTercero = 0;

            if (!empty($existente)) {
                $idTercero = (int) $existente['id'];
                
                $sql = "UPDATE terceros SET 
                            tipo_persona = :tipo_persona, 
                            nombre_completo = :nombre_completo, 
                            direccion = :direccion,
                            representante_legal = :representante_legal,
                            telefono = :telefono, 
                            email = :email, 
                            departamento = :departamento, provincia = :provincia, distrito = :distrito,
                            rubro_sector = :rubro_sector, observaciones = :observaciones,
                            es_cliente = :es_cliente, es_proveedor = :es_proveedor, es_empleado = :es_empleado,
                            estado = :estado, updated_by = :updated_by, deleted_at = NULL
                        WHERE id = :id";
                
                $params = $this->filtrarParamsTercero($payload);
                $params['id'] = $idTercero;
                $params['updated_by'] = $userId;
                unset($params['tipo_documento'], $params['numero_documento'], $params['created_by']);

                $this->db()->prepare($sql)->execute($params);
            } else {
                $sql = "INSERT INTO terceros (
                            tipo_persona, tipo_documento, numero_documento, nombre_completo,
                            direccion, representante_legal, telefono, email, departamento, provincia, distrito,
                            rubro_sector, observaciones, es_cliente, es_proveedor, es_empleado,
                            estado, created_by, updated_by
                        ) VALUES (
                            :tipo_persona, :tipo_documento, :numero_documento, :nombre_completo,
                            :direccion, :representante_legal, :telefono, :email, :departamento, :provincia, :distrito,
                            :rubro_sector, :observaciones, :es_cliente, :es_proveedor, :es_empleado,
                            :estado, :created_by, :updated_by
                        )";
                
                $params = $this->filtrarParamsTercero($payload);
                $params['created_by'] = $userId;
                $params['updated_by'] = $userId;

                $this->db()->prepare($sql)->execute($params);
                $idTercero = (int) $this->db()->lastInsertId();
            }

            if (!empty($payload['es_empleado'])) {
                $this->empleadosModel->guardar($idTercero, $payload, $userId);
            }
            if (!empty($payload['es_cliente'])) {
                $this->clientesModel->guardar($idTercero, $payload, $userId);
            }
            if (!empty($payload['es_proveedor'])) {
                $this->proveedoresModel->guardar($idTercero, $payload, $userId);
            }
            if (!empty($payload['es_distribuidor'])) {
                $this->distribuidoresModel->guardar($idTercero, $payload, $userId);
            } else {
                $this->distribuidoresModel->eliminar($idTercero, $userId);
            }

            $this->sincronizarTelefonos($idTercero, $payload['telefonos'] ?? [], $userId);
            $this->sincronizarCuentasBancarias($idTercero, $payload['cuentas_bancarias'] ?? [], $userId);

            $this->db()->commit();
            return $idTercero;

        } catch (Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data, int $userId): bool
    {
        $payload = $this->mapPayload($data);
        
        try {
            $this->db()->beginTransaction();

            $sql = "UPDATE terceros SET 
                        tipo_persona = :tipo_persona, 
                        tipo_documento = :tipo_documento, 
                        numero_documento = :numero_documento,
                        nombre_completo = :nombre_completo, 
                        direccion = :direccion,
                        representante_legal = :representante_legal,
                        telefono = :telefono,
                        email = :email,
                        departamento = :departamento, provincia = :provincia, distrito = :distrito,
                        rubro_sector = :rubro_sector, observaciones = :observaciones,
                        es_cliente = :es_cliente, es_proveedor = :es_proveedor, es_empleado = :es_empleado,
                        estado = :estado, updated_by = :updated_by
                    WHERE id = :id AND deleted_at IS NULL";
            
            $params = $this->filtrarParamsTercero($payload);
            $params['id'] = $id;
            $params['updated_by'] = $userId;
            unset($params['created_by']);

            $this->db()->prepare($sql)->execute($params);

            if (!empty($payload['es_empleado'])) {
                $this->empleadosModel->guardar($id, $payload, $userId);
            }
            if (!empty($payload['es_cliente'])) {
                $this->clientesModel->guardar($id, $payload, $userId);
            }
            if (!empty($payload['es_proveedor'])) {
                $this->proveedoresModel->guardar($id, $payload, $userId);
            }
            if (!empty($payload['es_distribuidor'])) {
                $this->distribuidoresModel->guardar($id, $payload, $userId);
            } else {
                $this->distribuidoresModel->eliminar($id, $userId);
            }

            $this->sincronizarTelefonos($id, $payload['telefonos'] ?? [], $userId);
            $this->sincronizarCuentasBancarias($id, $payload['cuentas_bancarias'] ?? [], $userId);

            $this->db()->commit();
            return true;
        } catch (Throwable $e) {
            $this->db()->rollBack();
            return false;
        }
    }

    public function eliminar(int $id, int $userId): bool
    {
        $bloqueo = $this->obtenerBloqueoEliminacion($id);
        if (!$bloqueo['puede_eliminar']) {
            throw new RuntimeException($bloqueo['motivo']);
        }

        $sql = "UPDATE terceros SET estado = 0, deleted_at = NOW(), deleted_by = :deleted_by WHERE id = :id";
        return $this->db()->prepare($sql)->execute(['id' => $id, 'deleted_by' => $userId]);
    }

    public function obtenerBloqueoEliminacion(int $id): array
    {
        $referencias = [];

        $ventas = $this->contarReferenciasActivas('ventas_documentos', 'id_cliente', $id);
        if ($ventas > 0) {
            $referencias[] = $ventas . ' venta(s)';
        }

        $compras = $this->contarReferenciasActivas('compras_ordenes', 'id_proveedor', $id);
        if ($compras > 0) {
            $referencias[] = $compras . ' compra(s)';
        }

        if ($referencias === []) {
            return ['puede_eliminar' => true, 'motivo' => ''];
        }

        return [
            'puede_eliminar' => false,
            'motivo' => 'No se puede eliminar: el tercero tiene movimientos activos (' . implode(', ', $referencias) . '). Puedes desactivarlo.',
        ];
    }

    // ==========================================
    // SECCIÓN 3: GESTIÓN DE CARGOS Y ÁREAS
    // ==========================================

    public function listarCargos(): array
    {
        $sql = "SELECT id, nombre, estado FROM cargos WHERE deleted_at IS NULL ORDER BY nombre ASC";
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAreas(): array
    {
        $sql = "SELECT id, nombre, estado FROM areas WHERE deleted_at IS NULL ORDER BY nombre ASC";
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarCargo(string $nombre, int $userId = 0): int
    {
        $stmt = $this->db()->prepare("SELECT id FROM cargos WHERE nombre = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([trim($nombre)]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) return (int) $row['id'];

        $stmt = $this->db()->prepare("INSERT INTO cargos (nombre, estado, created_by) VALUES (?, 1, ?)");
        $stmt->execute([trim($nombre), $userId > 0 ? $userId : null]);
        return (int) $this->db()->lastInsertId();
    }

    public function actualizarCargo(int $id, string $nombre, int $userId = 0): bool
    {
        return $this->db()->prepare("UPDATE cargos SET nombre = ?, updated_at = NOW(), updated_by = ? WHERE id = ? AND deleted_at IS NULL")->execute([trim($nombre), $userId > 0 ? $userId : null, $id]);
    }

    public function eliminarCargo(int $id, int $userId = 0): bool
    {
        return $this->db()->prepare("UPDATE cargos SET deleted_at = NOW(), deleted_by = ?, estado = 0 WHERE id = ? AND deleted_at IS NULL")->execute([$userId > 0 ? $userId : null, $id]);
    }

    public function cambiarEstadoCargo(int $id, int $estado, int $userId = 0): bool
    {
        return $this->db()->prepare("UPDATE cargos SET estado = ?, updated_at = NOW(), updated_by = ? WHERE id = ? AND deleted_at IS NULL")->execute([$estado === 1 ? 1 : 0, $userId > 0 ? $userId : null, $id]);
    }

    public function guardarArea(string $nombre, int $userId = 0): int
    {
        $stmt = $this->db()->prepare("SELECT id FROM areas WHERE nombre = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([trim($nombre)]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) return (int) $row['id'];

        $stmt = $this->db()->prepare("INSERT INTO areas (nombre, estado, created_by) VALUES (?, 1, ?)");
        $stmt->execute([trim($nombre), $userId > 0 ? $userId : null]);
        return (int) $this->db()->lastInsertId();
    }

    public function actualizarArea(int $id, string $nombre, int $userId = 0): bool
    {
        return $this->db()->prepare("UPDATE areas SET nombre = ?, updated_at = NOW(), updated_by = ? WHERE id = ? AND deleted_at IS NULL")->execute([trim($nombre), $userId > 0 ? $userId : null, $id]);
    }

    public function eliminarArea(int $id, int $userId = 0): bool
    {
        return $this->db()->prepare("UPDATE areas SET deleted_at = NOW(), deleted_by = ?, estado = 0 WHERE id = ? AND deleted_at IS NULL")->execute([$userId > 0 ? $userId : null, $id]);
    }

    public function cambiarEstadoArea(int $id, int $estado, int $userId = 0): bool
    {
        return $this->db()->prepare("UPDATE areas SET estado = ?, updated_at = NOW(), updated_by = ? WHERE id = ? AND deleted_at IS NULL")->execute([$estado === 1 ? 1 : 0, $userId > 0 ? $userId : null, $id]);
    }

    // ==========================================
    // SECCIÓN 4: GESTIÓN DOCUMENTAL
    // ==========================================

    public function listarDocumentos(int $terceroId): array
    {
        $sql = "SELECT * FROM terceros_documentos WHERE id_tercero = ? ORDER BY fecha_subida DESC";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([$terceroId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarDocumento(array $docData): bool
    {
        $sql = "INSERT INTO terceros_documentos (id_tercero, tipo_documento, nombre_archivo, ruta_archivo, extension, observaciones) 
                VALUES (:id_tercero, :tipo_documento, :nombre_archivo, :ruta_archivo, :extension, :observaciones)";
        return $this->db()->prepare($sql)->execute($docData);
    }

    public function actualizarDocumento(int $id, string $tipo, string $observaciones): bool
    {
        $sql = "UPDATE terceros_documentos SET tipo_documento = :tipo, observaciones = :obs WHERE id = :id";
        return $this->db()->prepare($sql)->execute([
            'tipo' => $tipo, 
            'obs' => $observaciones, 
            'id' => $id
        ]);
    }

    public function eliminarDocumento(int $docId): bool
    {
        $stmt = $this->db()->prepare("SELECT ruta_archivo FROM terceros_documentos WHERE id = ?");
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && file_exists($row['ruta_archivo'])) {
            unlink($row['ruta_archivo']);
        }

        return $this->db()->prepare("DELETE FROM terceros_documentos WHERE id = ?")->execute([$docId]);
    }

    // ==========================================
    // SECCIÓN 5: UBIGEO
    // ==========================================

    public function obtenerDepartamentos(): array
    {
        return $this->db()->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerProvincias(string $departamentoId): array
    {
        $stmt = $this->db()->prepare("SELECT id, nombre FROM provincias WHERE departamento_id = ? ORDER BY nombre ASC");
        $stmt->execute([$departamentoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDistritos(string $provinciaId): array
    {
        $stmt = $this->db()->prepare("SELECT id, nombre FROM distritos WHERE provincia_id = ? ORDER BY nombre ASC");
        $stmt->execute([$provinciaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // MÉTODOS PRIVADOS Y HELPERS
    // ==========================================

    private function filtrarParamsTercero(array $payload): array
    {
        return [
            'tipo_persona'     => $payload['tipo_persona'],
            'tipo_documento'   => $payload['tipo_documento'],
            'numero_documento' => $payload['numero_documento'],
            'nombre_completo'  => $payload['nombre_completo'],
            'direccion'        => $payload['direccion'],
            'representante_legal' => $payload['representante_legal'],
            'telefono'         => $payload['telefono_principal'],
            'email'            => $payload['email'],
            'departamento'     => $payload['departamento'],
            'provincia'        => $payload['provincia'],
            'distrito'         => $payload['distrito'],
            'rubro_sector'     => $payload['rubro_sector'],
            'observaciones'    => $payload['observaciones'],
            'es_cliente'       => $payload['es_cliente'],
            'es_proveedor'     => $payload['es_proveedor'],
            'es_empleado'      => $payload['es_empleado'],
            'estado'           => $payload['estado'],
            'created_by'       => $payload['created_by'] ?? null,
        ];
    }

    private function mapPayload(array $data): array
    {
        $numeroDocumento = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($data['numero_documento'] ?? '')));
        
        $telefonoPrincipal = null;
        $listaTelefonos = $data['telefonos'] ?? $data['telefonos_list'] ?? [];
        if (!empty($listaTelefonos) && is_array($listaTelefonos)) {
            foreach ($listaTelefonos as $tel) {
                if (!empty($tel['telefono'])) {
                    $telefonoPrincipal = trim($tel['telefono']);
                    break;
                }
            }
        }

        // --- SOLUCIÓN DEL ERROR AQUÍ ---
        // Usamos filter_var para convertir textos como "false" enviados por JS a un booleano real false.
        $esDistribuidor = filter_var($data['es_distribuidor'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $esCliente = filter_var($data['es_cliente'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        if ($esDistribuidor) {
            $esCliente = 1;
        }

        $recordarCumpleanos = filter_var($data['recordar_cumpleanos'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'tipo_persona'     => strtoupper(trim((string)($data['tipo_persona'] ?? 'NATURAL'))),
            'tipo_documento'   => trim((string)($data['tipo_documento'] ?? '')),
            'numero_documento' => $numeroDocumento,
            'nombre_completo'  => trim((string)($data['nombre_completo'] ?? '')),
            'direccion'        => trim((string)($data['direccion'] ?? '')),
            'representante_legal' => strtoupper(trim((string)($data['tipo_persona'] ?? 'NATURAL'))) === 'JURIDICA'
                ? trim((string)($data['representante_legal'] ?? ''))
                : null,
            'telefono_principal' => $telefonoPrincipal,
            'email'            => trim((string)($data['email'] ?? '')),
            'departamento'     => !empty($data['departamento']) ? (string)$data['departamento'] : null,
            'provincia'        => !empty($data['provincia'])    ? (string)$data['provincia']    : null,
            'distrito'         => !empty($data['distrito'])     ? (string)$data['distrito']     : null,
            'rubro_sector'     => trim((string)($data['rubro_sector'] ?? '')),
            'observaciones'    => trim((string)($data['observaciones'] ?? '')),
            'es_cliente'       => $esCliente,
            'es_proveedor'     => filter_var($data['es_proveedor'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'es_empleado'      => filter_var($data['es_empleado'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'es_distribuidor'  => $esDistribuidor,
            'estado'           => filter_var($data['estado'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            
            // Campos cliente
            'cliente_dias_credito'     => (int)($data['cliente_dias_credito'] ?? 0),
            'cliente_limite_credito'   => (float)($data['cliente_limite_credito'] ?? 0),
            'cliente_condicion_pago'   => trim((string)($data['cliente_condicion_pago'] ?? '')),

            // Campos proveedor
            'proveedor_dias_credito'   => (int)($data['proveedor_dias_credito'] ?? 0),
            'proveedor_condicion_pago' => trim((string)($data['proveedor_condicion_pago'] ?? '')),
            'proveedor_forma_pago'     => trim((string)($data['proveedor_forma_pago'] ?? '')),

            // Empleado
            'cargo'           => trim((string)($data['cargo'] ?? '')),
            'area'            => trim((string)($data['area'] ?? '')),
            'fecha_ingreso'   => !empty($data['fecha_ingreso']) ? $data['fecha_ingreso'] : null,
            'fecha_cese'      => !empty($data['fecha_cese']) ? $data['fecha_cese'] : null,
            'estado_laboral'  => $data['estado_laboral'] ?? 'activo',
            'tipo_contrato'   => $data['tipo_contrato'] ?? null,
            'sueldo_basico'   => (float)($data['sueldo_basico'] ?? 0),
            'moneda'          => $data['moneda'] ?? 'PEN',
            'asignacion_familiar' => filter_var($data['asignacion_familiar'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'tipo_pago'       => $data['tipo_pago'] ?? null,
            'pago_diario'     => (float)($data['pago_diario'] ?? 0),
            'regimen_pensionario' => $data['regimen_pensionario'] ?? null,
            'tipo_comision_afp'   => $data['tipo_comision_afp'] ?? null,
            'cuspp'           => $data['cuspp'] ?? null,
            'essalud'         => filter_var($data['essalud'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'genero'          => !empty($data['genero']) ? trim((string) $data['genero']) : null,
            'estado_civil'    => !empty($data['estado_civil']) ? trim((string) $data['estado_civil']) : null,
            'nivel_educativo' => !empty($data['nivel_educativo']) ? trim((string) $data['nivel_educativo']) : null,
            'contacto_emergencia_nombre' => !empty($data['contacto_emergencia_nombre']) ? trim((string) $data['contacto_emergencia_nombre']) : null,
            'contacto_emergencia_telf'   => !empty($data['contacto_emergencia_telf']) ? trim((string) $data['contacto_emergencia_telf']) : null,
            'tipo_sangre'     => !empty($data['tipo_sangre']) ? trim((string) $data['tipo_sangre']) : null,
            
            // --- CORRECCIÓN FINAL DE CUMPLEAÑOS ---
            'recordar_cumpleanos' => $recordarCumpleanos ? 1 : 0,
            'fecha_nacimiento' => ($recordarCumpleanos && !empty($data['fecha_nacimiento'])) ? $data['fecha_nacimiento'] : null,

            // Distribuidores
            'zonas_exclusivas' => $this->normalizarZonasExclusivas($data['zonas_exclusivas'] ?? []),
            
            'telefonos'         => $listaTelefonos,
            'cuentas_bancarias' => $data['cuentas_bancarias'] ?? $data['cuentas_bancarias_list'] ?? []
        ];
    }

    private function normalizarZonasExclusivas($zonasRaw): array
    {
        if (!is_array($zonasRaw)) return [];
        $normalizadas = [];
        foreach ($zonasRaw as $zona) {
            if (is_string($zona)) {
                $valor = trim($zona);
                if ($valor !== '') $normalizadas[] = $valor;
                continue;
            }
            if (!is_array($zona)) continue;
            $dep = trim((string)($zona['dep'] ?? $zona['departamento_id'] ?? ''));
            $prov = trim((string)($zona['prov'] ?? $zona['provincia_id'] ?? ''));
            $dist = trim((string)($zona['dist'] ?? $zona['distrito_id'] ?? ''));
            if ($dep === '') continue;
            $normalizadas[] = $dep . '|' . $prov . '|' . $dist;
        }
        return array_values(array_unique($normalizadas));
    }

    private function obtenerTelefonosPorTerceros(array $ids): array
    {
        if (empty($ids)) return [];
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT tercero_id, telefono, tipo FROM terceros_telefonos WHERE tercero_id IN ($inQuery)";
        if ($this->hasColumn('terceros_telefonos', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' ORDER BY id ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($ids);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['tercero_id']][] = $row;
        }
        return $result;
    }

    private function obtenerCuentasPorTerceros(array $ids): array
    {
        if (empty($ids)) return [];
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        
        $columnas = ['tercero_id'];
        if ($this->hasColumn('terceros_cuentas_bancarias', 'config_banco_id')) {
            $columnas[] = 'config_banco_id';
        } else {
            $columnas[] = 'NULL AS config_banco_id';
        }
        
        $columnas = array_merge($columnas, [
            'tipo_entidad', 'entidad', 'tipo_cuenta', 'numero_cuenta', 'cci',
            'titular', 'moneda', 'principal', 'billetera_digital', 'observaciones'
        ]);

        $sql = 'SELECT ' . implode(', ', $columnas) . " 
                FROM terceros_cuentas_bancarias 
                WHERE tercero_id IN ($inQuery)";

        if ($this->hasColumn('terceros_cuentas_bancarias', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= " ORDER BY id ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($ids);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['tercero_id']][] = $row;
        }
        return $result;
    }

    private function sincronizarTelefonos(int $terceroId, array $telefonos, int $userId): void
    {
        if ($this->hasColumn('terceros_telefonos', 'deleted_at')) {
            $set = ['deleted_at = NOW()'];
            $params = ['tercero_id' => $terceroId];
            if ($this->hasColumn('terceros_telefonos', 'deleted_by')) {
                $set[] = 'deleted_by = :deleted_by';
                $params['deleted_by'] = $userId;
            }
            if ($this->hasColumn('terceros_telefonos', 'updated_by')) {
                $set[] = 'updated_by = :updated_by';
                $params['updated_by'] = $userId;
            }
            $sql = 'UPDATE terceros_telefonos SET ' . implode(', ', $set) . ' WHERE tercero_id = :tercero_id';
            $this->db()->prepare($sql)->execute($params);
        } else {
            $this->db()->prepare("DELETE FROM terceros_telefonos WHERE tercero_id = ?")->execute([$terceroId]);
        }
        if (empty($telefonos)) return;

        $columnas = ['tercero_id', 'telefono', 'tipo'];
        $placeholders = ['?', '?', '?'];
        if ($this->hasColumn('terceros_telefonos', 'created_by')) {
            $columnas[] = 'created_by';
            $placeholders[] = '?';
        }
        if ($this->hasColumn('terceros_telefonos', 'updated_by')) {
            $columnas[] = 'updated_by';
            $placeholders[] = '?';
        }
        $sql = 'INSERT INTO terceros_telefonos (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db()->prepare($sql);
        foreach ($telefonos as $tel) {
            if (!empty($tel['telefono'])) {
                $params = [$terceroId, trim($tel['telefono']), $tel['tipo'] ?? 'Móvil'];
                if ($this->hasColumn('terceros_telefonos', 'created_by')) $params[] = $userId;
                if ($this->hasColumn('terceros_telefonos', 'updated_by')) $params[] = $userId;
                $stmt->execute($params);
            }
        }
    }

    private function sincronizarCuentasBancarias(int $terceroId, array $cuentas, int $userId): void
    {
        if ($this->hasColumn('terceros_cuentas_bancarias', 'deleted_at')) {
            $set = ['deleted_at = NOW()'];
            $params = ['tercero_id' => $terceroId];
            if ($this->hasColumn('terceros_cuentas_bancarias', 'deleted_by')) {
                $set[] = 'deleted_by = :deleted_by';
                $params['deleted_by'] = $userId;
            }
            if ($this->hasColumn('terceros_cuentas_bancarias', 'updated_by')) {
                $set[] = 'updated_by = :updated_by';
                $params['updated_by'] = $userId;
            }
            $sql = 'UPDATE terceros_cuentas_bancarias SET ' . implode(', ', $set) . ' WHERE tercero_id = :tercero_id';
            $this->db()->prepare($sql)->execute($params);
        } else {
            $this->db()->prepare("DELETE FROM terceros_cuentas_bancarias WHERE tercero_id = ?")->execute([$terceroId]);
        }
        
        if (empty($cuentas)) return;

        $columnas = ['tercero_id'];
        if ($this->hasColumn('terceros_cuentas_bancarias', 'config_banco_id')) {
            $columnas[] = 'config_banco_id';
        }
        
        $columnas = array_merge($columnas, [
            'tipo_entidad', 'entidad', 'tipo_cuenta', 'numero_cuenta', 'cci', 'titular',
            'moneda', 'estado', 'principal', 'billetera_digital', 'observaciones'
        ]);
        
        if ($this->hasColumn('terceros_cuentas_bancarias', 'created_by')) $columnas[] = 'created_by';
        if ($this->hasColumn('terceros_cuentas_bancarias', 'updated_by')) $columnas[] = 'updated_by';

        $placeholders = implode(', ', array_fill(0, count($columnas), '?'));
        $sql = 'INSERT INTO terceros_cuentas_bancarias (' . implode(', ', $columnas) . ') VALUES (' . $placeholders . ')';

        $stmt = $this->db()->prepare($sql);
        
        foreach ($cuentas as $cta) {
            if (empty($cta['config_banco_id']) && empty($cta['entidad']) && empty($cta['cci']) && empty($cta['numero_cuenta'])) continue;

            $params = [$terceroId];
            if ($this->hasColumn('terceros_cuentas_bancarias', 'config_banco_id')) {
                $configBancoId = isset($cta['config_banco_id']) ? (int)$cta['config_banco_id'] : 0;
                $params[] = $configBancoId > 0 ? $configBancoId : null;
            }
            
            $params = array_merge($params, [
                $cta['tipo_entidad'] ?? null,
                $cta['entidad'] ?? '',
                $cta['tipo_cuenta'] ?? null,
                trim((string)($cta['numero_cuenta'] ?? '')),
                trim((string)($cta['cci'] ?? '')),
                trim((string)($cta['titular'] ?? '')),
                $cta['moneda'] ?? 'PEN',
                1,
                !empty($cta['principal']) ? 1 : 0,
                !empty($cta['billetera_digital']) ? 1 : 0,
                $cta['observaciones'] ?? null,
            ]);
            
            if ($this->hasColumn('terceros_cuentas_bancarias', 'created_by')) $params[] = $userId;
            if ($this->hasColumn('terceros_cuentas_bancarias', 'updated_by')) $params[] = $userId;
            
            $stmt->execute($params);
        }
    }

    public function documentoExiste(string $tipo, string $numero, ?int $excludeId = null): bool
    {
        $numeroNormalizado = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $numero));
        $sql = 'SELECT 1 FROM terceros WHERE tipo_documento = :tipo AND numero_documento = :numero AND deleted_at IS NULL';
        $params = ['tipo' => $tipo, 'numero' => $numeroNormalizado];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function actualizarEstado(int $id, int $estado, int $userId): bool
    {
        $sql = 'UPDATE terceros SET estado = :estado, updated_by = :updated_by WHERE id = :id AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute(['id' => $id, 'estado' => $estado === 1 ? 1 : 0, 'updated_by' => $userId]);
    }

    private function buscarPorDocumento(string $tipo, string $numero): array
    {
        $stmt = $this->db()->prepare("SELECT id FROM terceros WHERE tipo_documento = ? AND numero_documento = ? LIMIT 1");
        $stmt->execute([$tipo, $numero]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function hasColumn(string $tableName, string $columnName): bool
    {
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        $stmt = $this->db()->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        $this->columnCache[$cacheKey] = (bool) $stmt->fetchColumn();
        return $this->columnCache[$cacheKey];
    }

    private function contarReferenciasActivas(string $tableName, string $foreignKey, int $id): int
    {
        if (!$this->hasColumn($tableName, $foreignKey)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM {$tableName} WHERE {$foreignKey} = :id";
        if ($this->hasColumn($tableName, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }
}