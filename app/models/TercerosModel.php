<?php
declare(strict_types=1);

class TercerosModel extends Modelo
{
    public function listar(): array
    {
        // CORREGIDO: Hacemos LEFT JOIN con terceros_empleados para traer los datos si es empleado
        // y seleccionamos los campos específicos de cliente/proveedor
        $sql = 'SELECT t.id, t.tipo_persona, t.tipo_documento, t.numero_documento, t.nombre_completo,
                       t.direccion, t.telefono, t.email, t.es_cliente, t.es_proveedor, t.es_empleado, t.estado,
                       t.cliente_condicion_pago, t.cliente_dias_credito, t.cliente_limite_credito,
                       t.proveedor_condicion_pago, t.proveedor_dias_credito, t.proveedor_forma_pago,
                       te.cargo, te.area, te.fecha_ingreso, te.estado_laboral, te.sueldo_basico
                FROM terceros t
                LEFT JOIN terceros_empleados te ON t.id = te.id_tercero
                WHERE t.deleted_at IS NULL
                ORDER BY t.id DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): array
    {
        $sql = 'SELECT t.id, t.tipo_persona, t.tipo_documento, t.numero_documento, t.nombre_completo,
                       t.direccion, t.telefono, t.email, t.es_cliente, t.es_proveedor, t.es_empleado, t.estado,
                       t.cliente_condicion_pago, t.cliente_dias_credito, t.cliente_limite_credito,
                       t.proveedor_condicion_pago, t.proveedor_dias_credito, t.proveedor_forma_pago,
                       te.cargo, te.area, te.fecha_ingreso, te.estado_laboral, te.sueldo_basico
                FROM terceros t
                LEFT JOIN terceros_empleados te ON t.id = te.id_tercero
                WHERE t.id = :id
                  AND t.deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function crear(array $data, int $userId): int
    {
        $payload = $this->mapPayload($data);
        
        // CORREGIDO: Usamos transacciones porque vamos a tocar 2 tablas
        try {
            $this->db()->beginTransaction();

            // 1. Verificar si existe (lógica de reactivación)
            $existente = $this->buscarPorDocumento($payload['tipo_documento'], $payload['numero_documento']);
            $idTercero = 0;

            if ($existente !== []) {
                $idTercero = (int) $existente['id'];
                // Actualizar flags y datos principales
                $sql = 'UPDATE terceros
                        SET tipo_persona = :tipo_persona,
                            nombre_completo = :nombre_completo,
                            direccion = :direccion,
                            telefono = :telefono,
                            email = :email,
                            es_cliente = :es_cliente,
                            es_proveedor = :es_proveedor,
                            es_empleado = :es_empleado,
                            cliente_condicion_pago = :cliente_condicion_pago,
                            cliente_dias_credito = :cliente_dias_credito,
                            cliente_limite_credito = :cliente_limite_credito,
                            proveedor_condicion_pago = :proveedor_condicion_pago,
                            proveedor_dias_credito = :proveedor_dias_credito,
                            proveedor_forma_pago = :proveedor_forma_pago,
                            estado = :estado,
                            updated_by = :updated_by,
                            deleted_at = NULL
                        WHERE id = :id';
                
                $params = $payload; // Copiamos payload
                $params['id'] = $idTercero;
                // Quitamos datos de empleados del array para este insert/update
                unset($params['cargo'], $params['area'], $params['fecha_ingreso'], $params['estado_laboral'], $params['sueldo_basico'], $params['tipo_documento'], $params['numero_documento']);
                
                $this->db()->prepare($sql)->execute($params);

            } else {
                // Insertar nuevo Tercero
                $sql = 'INSERT INTO terceros (tipo_persona, tipo_documento, numero_documento, nombre_completo,
                                              direccion, telefono, email, es_cliente, es_proveedor, es_empleado,
                                              cliente_condicion_pago, cliente_dias_credito, cliente_limite_credito,
                                              proveedor_condicion_pago, proveedor_dias_credito, proveedor_forma_pago,
                                              estado, created_by, updated_by)
                        VALUES (:tipo_persona, :tipo_documento, :numero_documento, :nombre_completo,
                                :direccion, :telefono, :email, :es_cliente, :es_proveedor, :es_empleado,
                                :cliente_condicion_pago, :cliente_dias_credito, :cliente_limite_credito,
                                :proveedor_condicion_pago, :proveedor_dias_credito, :proveedor_forma_pago,
                                :estado, :created_by, :updated_by)';
                
                $params = $payload;
                unset($params['cargo'], $params['area'], $params['fecha_ingreso'], $params['estado_laboral'], $params['sueldo_basico']);
                $params['created_by'] = $userId;
                $params['updated_by'] = $userId;
                
                $stmt = $this->db()->prepare($sql);
                $stmt->execute($params);
                $idTercero = (int) $this->db()->lastInsertId();
            }

            // 2. Gestionar la tabla de Empleados
            if (!empty($payload['es_empleado'])) {
                $this->guardarEmpleado($idTercero, $payload, $userId);
            }

            $this->db()->commit();
            return $idTercero;

        } catch (Throwable $e) {
            $this->db()->rollBack();
            throw $e; // O manejar el error según tu framework
        }
    }

    public function actualizar(int $id, array $data, int $userId): bool
    {
        $payload = $this->mapPayload($data);
        $payload['updated_by'] = $userId;

        try {
            $this->db()->beginTransaction();

            $sql = 'UPDATE terceros
                    SET tipo_persona = :tipo_persona,
                        tipo_documento = :tipo_documento,
                        numero_documento = :numero_documento,
                        nombre_completo = :nombre_completo,
                        direccion = :direccion,
                        telefono = :telefono,
                        email = :email,
                        es_cliente = :es_cliente,
                        es_proveedor = :es_proveedor,
                        es_empleado = :es_empleado,
                        cliente_condicion_pago = :cliente_condicion_pago,
                        cliente_dias_credito = :cliente_dias_credito,
                        cliente_limite_credito = :cliente_limite_credito,
                        proveedor_condicion_pago = :proveedor_condicion_pago,
                        proveedor_dias_credito = :proveedor_dias_credito,
                        proveedor_forma_pago = :proveedor_forma_pago,
                        estado = :estado,
                        updated_by = :updated_by
                    WHERE id = :id
                      AND deleted_at IS NULL';
            
            $params = $payload;
            $params['id'] = $id;
            // Quitamos campos de empleado para este update
            unset($params['cargo'], $params['area'], $params['fecha_ingreso'], $params['estado_laboral'], $params['sueldo_basico']);

            $this->db()->prepare($sql)->execute($params);

            // Gestionar tabla empleados
            if (!empty($payload['es_empleado'])) {
                $this->guardarEmpleado($id, $payload, $userId);
            }

            $this->db()->commit();
            return true;

        } catch (Throwable $e) {
            $this->db()->rollBack();
            return false;
        }
    }

    // Método auxiliar privado para manejar la tabla 'terceros_empleados'
    private function guardarEmpleado(int $idTercero, array $data, int $userId): void
    {
        // Usamos ON DUPLICATE KEY UPDATE para manejar insert o update automáticamente
        $sql = 'INSERT INTO terceros_empleados (id_tercero, cargo, area, fecha_ingreso, estado_laboral, sueldo_basico, updated_by, updated_at)
                VALUES (:id_tercero, :cargo, :area, :fecha_ingreso, :estado_laboral, :sueldo_basico, :updated_by, NOW())
                ON DUPLICATE KEY UPDATE
                    cargo = VALUES(cargo),
                    area = VALUES(area),
                    fecha_ingreso = VALUES(fecha_ingreso),
                    estado_laboral = VALUES(estado_laboral),
                    sueldo_basico = VALUES(sueldo_basico),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()';
        
        $this->db()->prepare($sql)->execute([
            'id_tercero' => $idTercero,
            'cargo' => $data['cargo'] ?? null,
            'area' => $data['area'] ?? null,
            'fecha_ingreso' => !empty($data['fecha_ingreso']) ? $data['fecha_ingreso'] : null,
            'estado_laboral' => $data['estado_laboral'] ?? 'activo',
            'sueldo_basico' => $data['sueldo_basico'] ?? 0.00,
            'updated_by' => $userId
        ]);
    }

    public function eliminar(int $id, int $userId): bool
    {
        $sql = 'UPDATE terceros
                SET estado = 0,
                    deleted_at = NOW(),
                    updated_by = :updated_by,
                    deleted_by = :deleted_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'updated_by' => $userId,
            'deleted_by' => $userId,
        ]);
    }

    public function actualizarEstado(int $id, int $estado, int $userId): bool
    {
        $sql = 'UPDATE terceros
                SET estado = :estado,
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'estado' => $estado,
            'updated_by' => $userId,
        ]);
    }

    public function documentoExiste(string $tipo, string $numero, ?int $excludeId = null): bool
    {
        $numeroNormalizado = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $numero));
        $sql = 'SELECT 1 FROM terceros WHERE tipo_documento = :tipo AND numero_documento = :numero AND deleted_at IS NULL';
        $params = [
            'tipo' => $tipo,
            'numero' => $numeroNormalizado,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function buscarPorDocumento(string $tipo, string $numero): array
    {
        $sql = 'SELECT id, es_cliente, es_proveedor, es_empleado
                FROM terceros
                WHERE tipo_documento = :tipo
                  AND numero_documento = :numero
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'tipo' => $tipo,
            'numero' => $numero,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    private function mapPayload(array $data): array
    {
        $numeroDocumento = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['numero_documento'] ?? '')));
        
        return [
            'tipo_persona' => trim((string) ($data['tipo_persona'] ?? 'NATURAL')),
            'tipo_documento' => trim((string) ($data['tipo_documento'] ?? '')),
            'numero_documento' => $numeroDocumento,
            'nombre_completo' => trim((string) ($data['nombre_completo'] ?? '')),
            'direccion' => trim((string) ($data['direccion'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'es_cliente' => !empty($data['es_cliente']) ? 1 : 0,
            'es_proveedor' => !empty($data['es_proveedor']) ? 1 : 0,
            'es_empleado' => !empty($data['es_empleado']) ? 1 : 0,
            
            // CORREGIDO: Mapeo a las columnas con prefijos correctos de la BD
            'cliente_condicion_pago' => trim((string) ($data['cliente_condicion_pago'] ?? '')),
            'cliente_dias_credito' => (int) ($data['cliente_dias_credito'] ?? 0),
            'cliente_limite_credito' => (float) ($data['cliente_limite_credito'] ?? 0),
            
            'proveedor_condicion_pago' => trim((string) ($data['proveedor_condicion_pago'] ?? '')),
            'proveedor_dias_credito' => (int) ($data['proveedor_dias_credito'] ?? 0),
            'proveedor_forma_pago' => trim((string) ($data['proveedor_forma_pago'] ?? '')),

            // Campos para la tabla empleados (se separarán luego)
            'cargo' => trim((string) ($data['cargo'] ?? '')),
            'area' => trim((string) ($data['area'] ?? '')),
            'fecha_ingreso' => trim((string) ($data['fecha_ingreso'] ?? '')),
            'estado_laboral' => trim((string) ($data['estado_laboral'] ?? 'activo')),
            'sueldo_basico' => (float) ($data['sueldo_basico'] ?? 0),
            
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];
    }
}