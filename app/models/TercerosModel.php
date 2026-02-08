<?php
declare(strict_types=1);

class TercerosModel extends Modelo
{
    // ==========================================
    // SECCIÓN 1: LECTURA DE TERCEROS (Listar/Obtener)
    // ==========================================

    public function listar(): array
    {
        $sql = "SELECT t.id, t.tipo_persona, t.tipo_documento, t.numero_documento, t.nombre_completo,
                    t.direccion, t.email, 
                    t.departamento, t.provincia, t.distrito,
                    t.rubro_sector, t.observaciones, t.es_cliente, t.es_proveedor, t.es_empleado, t.estado,
                    t.condicion_pago, t.dias_credito, t.limite_credito,
                    t.cliente_dias_credito, t.cliente_limite_credito, 
                    t.proveedor_condicion_pago, t.proveedor_dias_credito,
                    
                    te.cargo, te.area, te.fecha_ingreso, te.estado_laboral, 
                    te.sueldo_basico, te.moneda, te.asignacion_familiar,
                    te.tipo_pago, te.pago_diario, te.regimen_pensionario, 
                    te.tipo_comision_afp, te.cuspp, te.essalud, te.fecha_cese, te.tipo_contrato
                FROM terceros t
                LEFT JOIN terceros_empleados te ON t.id = te.id_tercero
                WHERE t.deleted_at IS NULL
                ORDER BY t.id DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [];
        }

        $ids = array_column($rows, 'id');
        $telefonos = $this->obtenerTelefonosPorTerceros($ids);
        $cuentas   = $this->obtenerCuentasPorTerceros($ids);

        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row['telefonos']         = $telefonos[$id] ?? [];
            $row['cuentas_bancarias'] = $cuentas[$id]   ?? [];
            
            // Helpers de ubicación (compatibilidad)
            $row['departamento_nombre'] = $row['departamento'];
            $row['provincia_nombre']    = $row['provincia'];
            $row['distrito_nombre']     = $row['distrito'];
        }
        unset($row);

        return $rows;
    }

    public function obtener(int $id): array
    {
        $sql = "SELECT t.*, 
                       te.cargo, te.area, te.fecha_ingreso, te.estado_laboral, 
                       te.sueldo_basico, te.moneda, te.asignacion_familiar,
                       te.tipo_pago, te.pago_diario, te.tipo_contrato, te.fecha_cese,
                       te.regimen_pensionario, te.tipo_comision_afp, te.cuspp, te.essalud
                FROM terceros t
                LEFT JOIN terceros_empleados te ON t.id = te.id_tercero
                WHERE t.id = :id AND t.deleted_at IS NULL LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [];
        }

        $row['telefonos']         = $this->obtenerTelefonosPorTerceros([$id])[$id] ?? [];
        $row['cuentas_bancarias'] = $this->obtenerCuentasPorTerceros([$id])[$id]   ?? [];

        return $row;
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
                            tipo_persona = :tipo_persona, nombre_completo = :nombre_completo, direccion = :direccion,
                            email = :email, 
                            departamento = :departamento, provincia = :provincia, distrito = :distrito,
                            rubro_sector = :rubro_sector, observaciones = :observaciones,
                            es_cliente = :es_cliente, es_proveedor = :es_proveedor, es_empleado = :es_empleado,
                            condicion_pago = :condicion_pago, dias_credito = :dias_credito, limite_credito = :limite_credito,
                            cliente_dias_credito = :cliente_dias_credito, 
                            cliente_limite_credito = :cliente_limite_credito,
                            proveedor_condicion_pago = :proveedor_condicion_pago,
                            proveedor_dias_credito = :proveedor_dias_credito,
                            estado = :estado, updated_by = :updated_by, deleted_at = NULL
                        WHERE id = :id";
                
                $params = $this->filtrarParamsTercero($payload);
                $params['id'] = $idTercero;
                $params['updated_by'] = $userId;
                unset($params['tipo_documento'], $params['numero_documento'], $params['created_by']);

                $this->db()->prepare($sql)->execute($params);
            } else {
                $sql = "INSERT INTO terceros (tipo_persona, tipo_documento, numero_documento, nombre_completo,
                                            direccion, email, departamento, provincia, distrito,
                                            rubro_sector, observaciones, es_cliente, es_proveedor, es_empleado,
                                            condicion_pago, dias_credito, limite_credito,
                                            cliente_dias_credito, cliente_limite_credito,
                                            proveedor_condicion_pago, proveedor_dias_credito,
                                            estado, created_by, updated_by)
                        VALUES (:tipo_persona, :tipo_documento, :numero_documento, :nombre_completo,
                                :direccion, :email, :departamento, :provincia, :distrito,
                                :rubro_sector, :observaciones, :es_cliente, :es_proveedor, :es_empleado,
                                :condicion_pago, :dias_credito, :limite_credito,
                                :cliente_dias_credito, :cliente_limite_credito,
                                :proveedor_condicion_pago, :proveedor_dias_credito,
                                :estado, :created_by, :updated_by)";
                
                $params = $this->filtrarParamsTercero($payload);
                $params['created_by'] = $userId;
                $params['updated_by'] = $userId;

                $this->db()->prepare($sql)->execute($params);
                $idTercero = (int) $this->db()->lastInsertId();
            }

            if (!empty($payload['es_empleado'])) {
                $this->guardarEmpleado($idTercero, $payload, $userId);
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
                        tipo_persona = :tipo_persona, tipo_documento = :tipo_documento, numero_documento = :numero_documento,
                        nombre_completo = :nombre_completo, direccion = :direccion, email = :email,
                        departamento = :departamento, provincia = :provincia, distrito = :distrito,
                        rubro_sector = :rubro_sector, observaciones = :observaciones,
                        es_cliente = :es_cliente, es_proveedor = :es_proveedor, es_empleado = :es_empleado,
                        condicion_pago = :condicion_pago, dias_credito = :dias_credito, limite_credito = :limite_credito,
                        cliente_dias_credito = :cliente_dias_credito, cliente_limite_credito = :cliente_limite_credito,
                        proveedor_condicion_pago = :proveedor_condicion_pago, proveedor_dias_credito = :proveedor_dias_credito,
                        estado = :estado, updated_by = :updated_by
                    WHERE id = :id AND deleted_at IS NULL";
            
            $params = $this->filtrarParamsTercero($payload);
            $params['id'] = $id;
            $params['updated_by'] = $userId;
            unset($params['created_by']);

            $this->db()->prepare($sql)->execute($params);

            if (!empty($payload['es_empleado'])) {
                $this->guardarEmpleado($id, $payload, $userId);
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
        $sql = "UPDATE terceros SET estado = 0, deleted_at = NOW(), deleted_by = :deleted_by WHERE id = :id";
        return $this->db()->prepare($sql)->execute(['id' => $id, 'deleted_by' => $userId]);
    }

    // ==========================================
    // SECCIÓN 3: GESTIÓN DE CARGOS Y ÁREAS
    // ==========================================

    public function listarCargos(): array
    {
        $sql = "SELECT id, nombre FROM cargos WHERE estado = 1 ORDER BY nombre ASC";
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAreas(): array
    {
        $sql = "SELECT id, nombre FROM areas WHERE estado = 1 ORDER BY nombre ASC";
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarCargo(string $nombre): int
    {
        // Verifica si ya existe
        $stmt = $this->db()->prepare("SELECT id FROM cargos WHERE nombre = ? LIMIT 1");
        $stmt->execute([trim($nombre)]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) return (int) $row['id'];

        // Inserta
        $stmt = $this->db()->prepare("INSERT INTO cargos (nombre, estado) VALUES (?, 1)");
        $stmt->execute([trim($nombre)]);
        return (int) $this->db()->lastInsertId();
    }

    public function guardarArea(string $nombre): int
    {
        $stmt = $this->db()->prepare("SELECT id FROM areas WHERE nombre = ? LIMIT 1");
        $stmt->execute([trim($nombre)]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) return (int) $row['id'];

        $stmt = $this->db()->prepare("INSERT INTO areas (nombre, estado) VALUES (?, 1)");
        $stmt->execute([trim($nombre)]);
        return (int) $this->db()->lastInsertId();
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

    // MÉTODO AGREGADO: Necesario para la edición
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
        // Primero obtenemos la ruta para borrar el archivo físico
        $stmt = $this->db()->prepare("SELECT ruta_archivo FROM terceros_documentos WHERE id = ?");
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && file_exists($row['ruta_archivo'])) {
            unlink($row['ruta_archivo']); // Borra archivo del servidor
        }

        return $this->db()->prepare("DELETE FROM terceros_documentos WHERE id = ?")->execute([$docId]);
    }

    // ==========================================
    // SECCIÓN 5: HELPERS Y PRIVADOS
    // ==========================================

    private function filtrarParamsTercero(array $payload): array
    {
        return [
            'tipo_persona'     => $payload['tipo_persona'],
            'tipo_documento'   => $payload['tipo_documento'],
            'numero_documento' => $payload['numero_documento'],
            'nombre_completo'  => $payload['nombre_completo'],
            'direccion'        => $payload['direccion'],
            'email'            => $payload['email'],
            'departamento'     => $payload['departamento'],
            'provincia'        => $payload['provincia'],
            'distrito'         => $payload['distrito'],
            'rubro_sector'     => $payload['rubro_sector'],
            'observaciones'    => $payload['observaciones'],
            'es_cliente'       => $payload['es_cliente'],
            'es_proveedor'     => $payload['es_proveedor'],
            'es_empleado'      => $payload['es_empleado'],
            'condicion_pago'   => $payload['condicion_pago'],
            'dias_credito'     => $payload['dias_credito'],
            'limite_credito'   => $payload['limite_credito'],
            'cliente_dias_credito'     => $payload['cliente_dias_credito'],
            'cliente_limite_credito'   => $payload['cliente_limite_credito'],
            'proveedor_condicion_pago' => $payload['proveedor_condicion_pago'],
            'proveedor_dias_credito'   => $payload['proveedor_dias_credito'],
            'estado'           => $payload['estado'],
            'created_by'       => $payload['created_by'] ?? null,
        ];
    }

    private function guardarEmpleado(int $idTercero, array $data, int $userId): void
    {
        $sql = "INSERT INTO terceros_empleados (id_tercero, cargo, area, fecha_ingreso, fecha_cese, estado_laboral, 
                                            tipo_contrato, sueldo_basico, moneda, asignacion_familiar,
                                            tipo_pago, pago_diario, regimen_pensionario, tipo_comision_afp, cuspp, essalud, updated_by)
                VALUES (:id_tercero, :cargo, :area, :fecha_ingreso, :fecha_cese, :estado_laboral, 
                        :tipo_contrato, :sueldo_basico, :moneda, :asignacion_familiar,
                        :tipo_pago, :pago_diario, :regimen_pensionario, :tipo_comision_afp, :cuspp, :essalud, :updated_by)
                ON DUPLICATE KEY UPDATE
                    cargo = VALUES(cargo), area = VALUES(area), 
                    fecha_ingreso = VALUES(fecha_ingreso), fecha_cese = VALUES(fecha_cese),
                    estado_laboral = VALUES(estado_laboral), tipo_contrato = VALUES(tipo_contrato),
                    sueldo_basico = VALUES(sueldo_basico), moneda = VALUES(moneda), asignacion_familiar = VALUES(asignacion_familiar),
                    tipo_pago = VALUES(tipo_pago), pago_diario = VALUES(pago_diario),
                    regimen_pensionario = VALUES(regimen_pensionario), tipo_comision_afp = VALUES(tipo_comision_afp),
                    cuspp = VALUES(cuspp), essalud = VALUES(essalud),
                    updated_by = VALUES(updated_by), updated_at = NOW()";
        
        $this->db()->prepare($sql)->execute([
            'id_tercero'      => $idTercero,
            'cargo'           => $data['cargo'] ?? null,
            'area'            => $data['area'] ?? null,
            'fecha_ingreso'   => $data['fecha_ingreso'] ?? null,
            'fecha_cese'      => $data['fecha_cese'] ?? null,
            'estado_laboral'  => $data['estado_laboral'] ?? 'activo',
            'tipo_contrato'   => $data['tipo_contrato'] ?? null,
            'sueldo_basico'   => (float)($data['sueldo_basico'] ?? 0),
            'moneda'          => $data['moneda'] ?? 'PEN',
            'asignacion_familiar' => !empty($data['asignacion_familiar']) ? 1 : 0,
            'tipo_pago'       => $data['tipo_pago'] ?? null,
            'pago_diario'     => (float)($data['pago_diario'] ?? 0),
            'regimen_pensionario' => $data['regimen_pensionario'] ?? null,
            'tipo_comision_afp'   => $data['tipo_comision_afp'] ?? null,
            'cuspp'           => $data['cuspp'] ?? null,
            'essalud'         => !empty($data['essalud']) ? 1 : 0,
            'updated_by'      => $userId
        ]);
    }

    private function mapPayload(array $data): array
    {
        $numeroDocumento = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($data['numero_documento'] ?? '')));
        return [
            'tipo_persona'     => trim((string)($data['tipo_persona'] ?? 'NATURAL')),
            'tipo_documento'   => trim((string)($data['tipo_documento'] ?? '')),
            'numero_documento' => $numeroDocumento,
            'nombre_completo'  => trim((string)($data['nombre_completo'] ?? '')),
            'direccion'        => trim((string)($data['direccion'] ?? '')),
            'email'            => trim((string)($data['email'] ?? '')),
            'departamento'     => !empty($data['departamento']) ? (string)$data['departamento'] : null,
            'provincia'        => !empty($data['provincia'])    ? (string)$data['provincia']    : null,
            'distrito'         => !empty($data['distrito'])     ? (string)$data['distrito']     : null,
            'rubro_sector'     => trim((string)($data['rubro_sector'] ?? '')),
            'observaciones'    => trim((string)($data['observaciones'] ?? '')),
            'es_cliente'       => !empty($data['es_cliente'])   ? 1 : 0,
            'es_proveedor'     => !empty($data['es_proveedor']) ? 1 : 0,
            'es_empleado'      => !empty($data['es_empleado'])  ? 1 : 0,
            'condicion_pago'   => !empty($data['condicion_pago']) ? trim($data['condicion_pago']) : null,
            'dias_credito'     => (int)($data['dias_credito'] ?? 0),
            'limite_credito'   => (float)($data['limite_credito'] ?? 0),
            'cliente_dias_credito'     => (int)($data['cliente_dias_credito'] ?? 0),
            'cliente_limite_credito'   => (float)($data['cliente_limite_credito'] ?? 0),
            'proveedor_condicion_pago' => !empty($data['proveedor_condicion_pago']) ? trim($data['proveedor_condicion_pago']) : null,
            'proveedor_dias_credito'   => (int)($data['proveedor_dias_credito'] ?? 0),
            'estado'           => isset($data['estado']) ? (int)$data['estado'] : 1,
            
            // Empleado Mapping Nuevo
            'cargo'           => trim((string)($data['cargo'] ?? '')),
            'area'            => trim((string)($data['area'] ?? '')),
            'fecha_ingreso'   => !empty($data['fecha_ingreso']) ? $data['fecha_ingreso'] : null,
            'fecha_cese'      => !empty($data['fecha_cese']) ? $data['fecha_cese'] : null,
            'estado_laboral'  => $data['estado_laboral'] ?? 'activo',
            'tipo_contrato'   => $data['tipo_contrato'] ?? null,
            'sueldo_basico'   => (float)($data['sueldo_basico'] ?? 0),
            'moneda'          => $data['moneda'] ?? 'PEN',
            'asignacion_familiar' => !empty($data['asignacion_familiar']) ? 1 : 0,
            'tipo_pago'       => $data['tipo_pago'] ?? null,
            'pago_diario'     => (float)($data['pago_diario'] ?? 0),
            'regimen_pensionario' => $data['regimen_pensionario'] ?? null,
            'tipo_comision_afp'   => $data['tipo_comision_afp'] ?? null,
            'cuspp'           => $data['cuspp'] ?? null,
            'essalud'         => !empty($data['essalud']) ? 1 : 0,
            
            'telefonos'         => $data['telefonos']         ?? $data['telefonos_list']         ?? [],
            'cuentas_bancarias' => $data['cuentas_bancarias'] ?? $data['cuentas_bancarias_list'] ?? []
        ];
    }
    
    // (Mantén los métodos obtenerDepartamentos, obtenerProvincias, obtenerDistritos, 
    // documentoExiste, actualizarEstado, buscarPorDocumento, sincronizarTelefonos, sincronizarCuentasBancarias, etc.
    // que YA estaban en tu código anterior si no los ves aquí explícitamente, pero el bloque de arriba 
    // cubre toda la lógica principal nueva).
    
    public function obtenerDepartamentos(): array
    {
        $stmt = $this->db()->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function documentoExiste(string $tipo, string $numero, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM terceros WHERE tipo_documento = :tipo AND numero_documento = :numero AND deleted_at IS NULL';
        $params = ['tipo' => $tipo, 'numero' => $numero];
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
        return $this->db()->prepare($sql)->execute(['id' => $id, 'estado' => $estado, 'updated_by' => $userId]);
    }

    private function buscarPorDocumento(string $tipo, string $numero): array
    {
        $stmt = $this->db()->prepare("SELECT id FROM terceros WHERE tipo_documento = ? AND numero_documento = ? LIMIT 1");
        $stmt->execute([$tipo, $numero]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    private function sincronizarTelefonos(int $terceroId, array $telefonos, int $userId): void
    {
        $this->db()->prepare("DELETE FROM terceros_telefonos WHERE tercero_id = ?")->execute([$terceroId]);
        if (empty($telefonos)) return;

        $sql = "INSERT INTO terceros_telefonos (tercero_id, telefono, tipo, created_by, updated_by) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db()->prepare($sql);
        foreach ($telefonos as $tel) {
            if (!empty($tel['telefono'])) {
                $stmt->execute([$terceroId, trim($tel['telefono']), $tel['tipo'] ?? 'Móvil', $userId, $userId]);
            }
        }
    }

    private function sincronizarCuentasBancarias(int $terceroId, array $cuentas, int $userId): void
    {
        $this->db()->prepare("DELETE FROM terceros_cuentas_bancarias WHERE tercero_id = ?")->execute([$terceroId]);
        if (empty($cuentas)) return;

        $sql = "INSERT INTO terceros_cuentas_bancarias (tercero_id, tipo, entidad, tipo_cta, numero_cuenta, cci, alias, moneda, principal, billetera_digital, observaciones, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db()->prepare($sql);
        foreach ($cuentas as $cta) {
            if (empty($cta['entidad']) && empty($cta['cci']) && empty($cta['alias'])) continue;
            $stmt->execute([
                $terceroId, $cta['tipo'] ?? null, $cta['entidad'] ?? '', $cta['tipo_cta'] ?? null,
                trim($cta['numero_cuenta'] ?? ''), trim($cta['cci'] ?? ''), trim($cta['alias'] ?? ''),
                $cta['moneda'] ?? 'PEN', !empty($cta['principal']) ? 1 : 0, !empty($cta['billetera_digital']) ? 1 : 0,
                $cta['observaciones'] ?? null, $userId, $userId
            ]);
        }
    }
    
    private function obtenerTelefonosPorTerceros(array $ids): array
    {
        if (empty($ids)) return [];
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db()->prepare("SELECT tercero_id, telefono, tipo FROM terceros_telefonos WHERE tercero_id IN ($inQuery) ORDER BY id ASC");
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
        $stmt = $this->db()->prepare("SELECT tercero_id, tipo, entidad, tipo_cta, numero_cuenta, cci, alias, moneda, principal, billetera_digital, observaciones FROM terceros_cuentas_bancarias WHERE tercero_id IN ($inQuery) ORDER BY id ASC");
        $stmt->execute($ids);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['tercero_id']][] = $row;
        }
        return $result;
    }
}