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
    private ?bool $hasRepresentanteLegalColumn = null;

    public function __construct()
    {
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
        $representanteLegalSelect = $this->hasRepresentanteLegalColumn()
            ? 't.representante_legal'
            : "'' AS representante_legal";

        // Agregado t.telefono para tener el número principal disponible en la lista
        $sql = "SELECT t.id, t.tipo_persona, t.tipo_documento, t.numero_documento, t.nombre_completo,
                       t.direccion, t.telefono, t.email, {$representanteLegalSelect},
                       t.departamento, t.provincia, t.distrito,
                       t.rubro_sector, t.observaciones, t.es_cliente, t.es_proveedor, t.es_empleado, t.estado,
                       
                       -- Datos cliente desde tabla hija
                       tc.dias_credito AS cliente_dias_credito,
                       tc.limite_credito AS cliente_limite_credito,
                       tc.condicion_pago AS cliente_condicion_pago,
                       
                       -- Datos proveedor desde tabla hija
                       tp.dias_credito AS proveedor_dias_credito,
                       tp.condicion_pago AS proveedor_condicion_pago,
                       tp.forma_pago AS proveedor_forma_pago,
                       
                       -- Datos empleado
                       te.cargo, te.area, te.fecha_ingreso, te.estado_laboral, 
                       te.sueldo_basico, te.moneda, te.asignacion_familiar,
                       te.tipo_pago, te.pago_diario, te.regimen_pensionario, 
                       te.tipo_comision_afp, te.cuspp, te.essalud, te.fecha_cese, te.tipo_contrato,

                       -- Datos distribuidor
                       CASE WHEN d.id_tercero IS NULL THEN 0 ELSE 1 END AS es_distribuidor
                FROM terceros t
                LEFT JOIN terceros_clientes tc ON t.id = tc.id_tercero
                LEFT JOIN terceros_proveedores tp ON t.id = tp.id_tercero
                LEFT JOIN terceros_empleados te ON t.id = te.id_tercero
                LEFT JOIN distribuidores d ON t.id = d.id_tercero AND d.deleted_at IS NULL
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
        $zonas     = $this->distribuidoresModel->obtenerZonasPorTerceros(array_map('intval', $ids));

        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row['telefonos']         = $telefonos[$id] ?? [];
            $row['cuentas_bancarias'] = $cuentas[$id]   ?? [];
            $row['zonas_exclusivas'] = $zonas[$id] ?? [];
            $row['zonas_exclusivas_resumen'] = implode(', ', array_filter(array_column($row['zonas_exclusivas'], 'label')));
            
            // Helpers de ubicación (compatibilidad visual)
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

                       -- Distribuidor
                       CASE WHEN d.id_tercero IS NULL THEN 0 ELSE 1 END AS es_distribuidor
                FROM terceros t
                LEFT JOIN terceros_clientes tc ON t.id = tc.id_tercero
                LEFT JOIN terceros_proveedores tp ON t.id = tp.id_tercero
                LEFT JOIN terceros_empleados te ON t.id = te.id_tercero
                LEFT JOIN distribuidores d ON t.id = d.id_tercero AND d.deleted_at IS NULL
                WHERE t.id = :id AND t.deleted_at IS NULL LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [];
        }

        $row['telefonos']         = $this->obtenerTelefonosPorTerceros([$id])[$id] ?? [];
        $row['cuentas_bancarias'] = $this->obtenerCuentasPorTerceros([$id])[$id]   ?? [];
        $row['zonas_exclusivas'] = $this->distribuidoresModel->obtenerZonasPorTerceros([$id])[$id] ?? [];
        $row['zonas_exclusivas_resumen'] = implode(', ', array_filter(array_column($row['zonas_exclusivas'], 'label')));

        return $row;
    }

    // ==========================================
    // SECCIÓN 2: ESCRITURA DE TERCEROS (Crear/Actualizar/Eliminar)
    // ==========================================

    public function crear(array $data, int $userId): int
    {
        $payload = $this->mapPayload($data);
        $hasRepresentanteLegalColumn = $this->hasRepresentanteLegalColumn();
        
        try {
            $this->db()->beginTransaction();

            $existente = $this->buscarPorDocumento($payload['tipo_documento'], $payload['numero_documento']);
            $idTercero = 0;

            // Se agregó la columna 'telefono' en ambos queries para mantener consistencia con la tabla padre
            if (!empty($existente)) {
                $idTercero = (int) $existente['id'];
                $sql = "UPDATE terceros SET 
                            tipo_persona = :tipo_persona, 
                            nombre_completo = :nombre_completo, 
                            direccion = :direccion,";

                if ($hasRepresentanteLegalColumn) {
                    $sql .= "
                            representante_legal = :representante_legal,";
                }

                $sql .= "
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
                if (!$hasRepresentanteLegalColumn) {
                    unset($params['representante_legal']);
                }

                $this->db()->prepare($sql)->execute($params);
            } else {
                $insertColumns = [
                    'tipo_persona', 'tipo_documento', 'numero_documento', 'nombre_completo',
                    'direccion', 'telefono', 'email', 'departamento', 'provincia', 'distrito',
                    'rubro_sector', 'observaciones', 'es_cliente', 'es_proveedor', 'es_empleado',
                    'estado', 'created_by', 'updated_by'
                ];
                if ($hasRepresentanteLegalColumn) {
                    array_splice($insertColumns, 5, 0, 'representante_legal');
                }

                $sql = sprintf(
                    'INSERT INTO terceros (%s) VALUES (:%s)',
                    implode(', ', $insertColumns),
                    implode(', :', $insertColumns)
                );
                
                $params = $this->filtrarParamsTercero($payload);
                $params['created_by'] = $userId;
                $params['updated_by'] = $userId;
                if (!$hasRepresentanteLegalColumn) {
                    unset($params['representante_legal']);
                }

                $this->db()->prepare($sql)->execute($params);
                $idTercero = (int) $this->db()->lastInsertId();
            }

            // Guardar datos específicos según roles
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
        $hasRepresentanteLegalColumn = $this->hasRepresentanteLegalColumn();
        
        try {
            $this->db()->beginTransaction();

            // Se agregó 'telefono' al UPDATE
            $sql = "UPDATE terceros SET 
                        tipo_persona = :tipo_persona, tipo_documento = :tipo_documento, numero_documento = :numero_documento,
                        nombre_completo = :nombre_completo, 
                        direccion = :direccion,";

            if ($hasRepresentanteLegalColumn) {
                $sql .= "
                        representante_legal = :representante_legal,";
            }

            $sql .= "
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
            if (!$hasRepresentanteLegalColumn) {
                unset($params['representante_legal']);
            }

            $this->db()->prepare($sql)->execute($params);

            // Actualizar hijas según roles
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
        $stmt = $this->db()->prepare("SELECT id FROM cargos WHERE nombre = ? LIMIT 1");
        $stmt->execute([trim($nombre)]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) return (int) $row['id'];

        $stmt = $this->db()->prepare("INSERT INTO cargos (nombre, estado) VALUES (?, 1)");
        $stmt->execute([trim($nombre)]);
        return (int) $this->db()->lastInsertId();
    }

    public function actualizarCargo(int $id, string $nombre): bool
    {
        return $this->db()->prepare("UPDATE cargos SET nombre = ? WHERE id = ?")->execute([trim($nombre), $id]);
    }

    public function eliminarCargo(int $id): bool
    {
        return $this->db()->prepare("UPDATE cargos SET estado = 0 WHERE id = ?")->execute([$id]);
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

    public function actualizarArea(int $id, string $nombre): bool
    {
        return $this->db()->prepare("UPDATE areas SET nombre = ? WHERE id = ?")->execute([trim($nombre), $id]);
    }

    public function eliminarArea(int $id): bool
    {
        return $this->db()->prepare("UPDATE areas SET estado = 0 WHERE id = ?")->execute([$id]);
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
    // SECCIÓN 5: UBIGEO (NUEVO)
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
            'telefono'         => $payload['telefono_principal'], // Extraído en mapPayload
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
        
        // Lógica para extraer el primer teléfono y guardarlo en la tabla principal
        $telefonoPrincipal = null;
        $listaTelefonos = $data['telefonos'] ?? $data['telefonos_list'] ?? [];
        if (!empty($listaTelefonos) && is_array($listaTelefonos)) {
            foreach ($listaTelefonos as $tel) {
                if (!empty($tel['telefono'])) {
                    $telefonoPrincipal = trim($tel['telefono']);
                    break; // Nos quedamos con el primero que encontremos
                }
            }
        }

        $esDistribuidor = !empty($data['es_distribuidor']) ? 1 : 0;
        $esCliente = !empty($data['es_cliente']) ? 1 : 0;
        if ($esDistribuidor) {
            $esCliente = 1;
        }

        return [
            'tipo_persona'     => trim((string)($data['tipo_persona'] ?? 'NATURAL')),
            'tipo_documento'   => trim((string)($data['tipo_documento'] ?? '')),
            'numero_documento' => $numeroDocumento,
            'nombre_completo'  => trim((string)($data['nombre_completo'] ?? '')),
            'direccion'        => trim((string)($data['direccion'] ?? '')),
            'representante_legal' => trim((string)($data['representante_legal'] ?? '')),
            'telefono_principal' => $telefonoPrincipal, // Nuevo campo derivado
            'email'            => trim((string)($data['email'] ?? '')),
            'departamento'     => !empty($data['departamento']) ? (string)$data['departamento'] : null,
            'provincia'        => !empty($data['provincia'])    ? (string)$data['provincia']    : null,
            'distrito'         => !empty($data['distrito'])     ? (string)$data['distrito']     : null,
            'rubro_sector'     => trim((string)($data['rubro_sector'] ?? '')),
            'observaciones'    => trim((string)($data['observaciones'] ?? '')),
            'es_cliente'       => $esCliente,
            'es_proveedor'     => !empty($data['es_proveedor']) ? 1 : 0,
            'es_empleado'      => !empty($data['es_empleado'])  ? 1 : 0,
            'es_distribuidor'  => $esDistribuidor,
            'estado'           => isset($data['estado']) ? (int)$data['estado'] : 1,
            
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
            'asignacion_familiar' => !empty($data['asignacion_familiar']) ? 1 : 0,
            'tipo_pago'       => $data['tipo_pago'] ?? null,
            'pago_diario'     => (float)($data['pago_diario'] ?? 0),
            'regimen_pensionario' => $data['regimen_pensionario'] ?? null,
            'tipo_comision_afp'   => $data['tipo_comision_afp'] ?? null,
            'cuspp'           => $data['cuspp'] ?? null,
            'essalud'         => !empty($data['essalud']) ? 1 : 0,

            // Distribuidores
            'zonas_exclusivas' => is_array($data['zonas_exclusivas'] ?? null)
                ? array_values(array_filter(array_map('trim', $data['zonas_exclusivas'])))
                : [],
            
            'telefonos'         => $listaTelefonos,
            'cuentas_bancarias' => $data['cuentas_bancarias'] ?? $data['cuentas_bancarias_list'] ?? []
        ];
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
            // Validar que al menos tenga un identificador
            if (empty($cta['entidad']) && empty($cta['cci']) && empty($cta['alias']) && empty($cta['numero_cuenta'])) continue;
            
            $stmt->execute([
                $terceroId, $cta['tipo'] ?? null, $cta['entidad'] ?? '', $cta['tipo_cta'] ?? null,
                trim($cta['numero_cuenta'] ?? ''), trim($cta['cci'] ?? ''), trim($cta['alias'] ?? ''),
                $cta['moneda'] ?? 'PEN', !empty($cta['principal']) ? 1 : 0, !empty($cta['billetera_digital']) ? 1 : 0,
                $cta['observaciones'] ?? null, $userId, $userId
            ]);
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
        return $this->db()->prepare($sql)->execute(['id' => $id, 'estado' => $estado, 'updated_by' => $userId]);
    }

    private function buscarPorDocumento(string $tipo, string $numero): array
    {
        $stmt = $this->db()->prepare("SELECT id FROM terceros WHERE tipo_documento = ? AND numero_documento = ? LIMIT 1");
        $stmt->execute([$tipo, $numero]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function hasRepresentanteLegalColumn(): bool
    {
        if ($this->hasRepresentanteLegalColumn !== null) {
            return $this->hasRepresentanteLegalColumn;
        }

        $stmt = $this->db()->prepare('SHOW COLUMNS FROM terceros LIKE :column');
        $stmt->execute(['column' => 'representante_legal']);
        $this->hasRepresentanteLegalColumn = (bool) $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->hasRepresentanteLegalColumn;
    }
}
