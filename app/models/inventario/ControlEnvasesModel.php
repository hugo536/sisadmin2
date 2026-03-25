<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/core/Modelo.php';
require_once BASE_PATH . '/app/models/inventario/InventarioModel.php';

class ControlEnvasesModel extends Modelo {
    
    public function __construct() {
        parent::__construct(); 
    }

    public function registrarMovimiento($id_tercero, $id_item_envase, $tipo_operacion, $cantidad, $id_venta = null, $observaciones = '') {
        $sql = "INSERT INTO cta_cte_envases 
                (id_tercero, id_item_envase, tipo_operacion, cantidad, id_venta, observaciones) 
                VALUES (:id_tercero, :id_item_envase, :tipo_operacion, :cantidad, :id_venta, :observaciones)";
        
        $stmt = $this->db->prepare($sql);
        
        $stmt->bindParam(':id_tercero', $id_tercero, PDO::PARAM_INT);
        $stmt->bindParam(':id_item_envase', $id_item_envase, PDO::PARAM_INT);
        $stmt->bindParam(':tipo_operacion', $tipo_operacion, PDO::PARAM_STR);
        $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->bindParam(':id_venta', $id_venta, PDO::PARAM_INT);
        $stmt->bindParam(':observaciones', $observaciones, PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function registrarMovimientoConKardex(
        int $idTercero,
        int $idItemEnvase,
        string $tipoOperacion,
        int $cantidad,
        ?int $idVenta,
        string $observaciones,
        int $idUsuario,
        int $idAlmacen
    ): bool {
        $tipoOperacion = trim($tipoOperacion);
        $observaciones = trim($observaciones);
        $operacionUuid = bin2hex(random_bytes(8));

        if ($idTercero <= 0 || $idItemEnvase <= 0 || $cantidad <= 0 || $idUsuario <= 0) {
            throw new InvalidArgumentException('Datos inválidos para registrar movimiento de envases.');
        }

        $this->db->beginTransaction();

        try {
            $ok = $this->registrarMovimiento(
                $idTercero,
                $idItemEnvase,
                $tipoOperacion,
                $cantidad,
                $idVenta,
                ($observaciones !== '' ? $observaciones . ' | ' : '') . 'OP:' . $operacionUuid
            );

            if (!$ok) {
                throw new RuntimeException('No se pudo registrar el movimiento de envases en cuenta corriente.');
            }

            if (in_array($tipoOperacion, ['RECEPCION_VACIO', 'ENTREGA_LLENO'], true)) {
                if ($idAlmacen <= 0) {
                    throw new InvalidArgumentException('Debe seleccionar un almacén para impactar Kardex.');
                }

                $inventarioModel = new InventarioModel();
                $referencia = 'ENVASE | ' . $tipoOperacion . ' | Tercero:' . $idTercero . ' | OP:' . $operacionUuid;

                $datosKardex = [
                    'tipo_movimiento' => $tipoOperacion === 'RECEPCION_VACIO' ? 'AJ+' : 'VEN',
                    'tipo_registro' => 'item',
                    'id_item' => $idItemEnvase,
                    'cantidad' => $cantidad,
                    'referencia' => $referencia,
                    'created_by' => $idUsuario,
                    'operacion_uuid' => $operacionUuid,
                    'id_almacen_origen' => $tipoOperacion === 'ENTREGA_LLENO' ? $idAlmacen : 0,
                    'id_almacen_destino' => $tipoOperacion === 'RECEPCION_VACIO' ? $idAlmacen : 0,
                ];

                $inventarioModel->registrarMovimiento($datosKardex);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerSaldoCliente($id_tercero, $id_item_envase) {
        // ACTUALIZADO: Agregamos el AJUSTE_CLIENTE restando del saldo
        $sql = "SELECT 
                    SUM(
                        CASE 
                            WHEN tipo_operacion = 'RECEPCION_VACIO' THEN cantidad 
                            WHEN tipo_operacion = 'ENTREGA_LLENO' THEN -cantidad 
                            WHEN tipo_operacion = 'AJUSTE_CLIENTE' THEN -cantidad
                            ELSE 0 
                        END
                    ) as saldo_en_planta
                FROM cta_cte_envases
                WHERE id_tercero = :id_tercero AND id_item_envase = :id_item_envase";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id_tercero', $id_tercero, PDO::PARAM_INT);
        $stmt->bindParam(':id_item_envase', $id_item_envase, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['saldo_en_planta'] ? (int) $resultado['saldo_en_planta'] : 0;
    }

    public function obtenerSaldosGlobales(): array {
        // ACTUALIZADO: Agregamos el AJUSTE_CLIENTE restando del saldo
        $sql = "SELECT 
                    t.nombre_completo as cliente_nombre, 
                    i.nombre as envase_nombre,
                    m.id_tercero,
                    m.id_item_envase,
                    SUM(CASE 
                        WHEN m.tipo_operacion = 'RECEPCION_VACIO' THEN m.cantidad 
                        WHEN m.tipo_operacion = 'ENTREGA_LLENO' THEN -cantidad 
                        WHEN m.tipo_operacion = 'AJUSTE_CLIENTE' THEN -cantidad
                        ELSE 0 
                    END) as saldo_en_planta
                FROM cta_cte_envases m
                INNER JOIN terceros t ON m.id_tercero = t.id
                INNER JOIN items i ON m.id_item_envase = i.id
                GROUP BY m.id_tercero, m.id_item_envase
                HAVING saldo_en_planta <> 0"; 

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerClientes(): array {
        $sql = "SELECT id, nombre_completo 
                FROM terceros 
                WHERE estado = 1 
                  AND deleted_at IS NULL 
                  AND es_cliente = 1
                ORDER BY nombre_completo ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerEnvasesDisponibles(): array {
        $sql = "SELECT id, nombre 
                FROM items 
                WHERE estado = 1 
                  AND deleted_at IS NULL 
                  AND es_envase_retornable = 1 
                ORDER BY nombre ASC";
                
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAlmacenesActivos(): array {
        $sql = "SELECT id, nombre
                FROM almacenes
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==========================================
    // NUEVA FUNCIÓN: Obtener historial para el Modal
    // ==========================================
    public function obtenerHistorial(int $id_tercero, int $id_item_envase): array {
        $columnas = $this->obtenerColumnasTablaEnvases();

        $idCol = null;
        foreach (['id', 'id_cta_cte_envase', 'id_movimiento'] as $candidata) {
            if (isset($columnas[$candidata])) {
                $idCol = $candidata;
                break;
            }
        }

        $orderCol = null;
        foreach (['created_at', 'fecha_movimiento', 'fecha', 'id', 'id_cta_cte_envase', 'id_movimiento'] as $candidata) {
            if (isset($columnas[$candidata])) {
                $orderCol = $candidata;
                break;
            }
        }

        $selectId = $idCol ? "{$idCol} AS id" : "0 AS id";
        $selectObs = isset($columnas['observaciones']) ? "observaciones" : "'' AS observaciones";
        $orderBy = $orderCol ? "{$orderCol} DESC" : "tipo_operacion ASC";

        $sql = "SELECT {$selectId}, tipo_operacion, cantidad, {$selectObs}
                FROM cta_cte_envases
                WHERE id_tercero = :id_tercero
                  AND id_item_envase = :id_item_envase
                ORDER BY {$orderBy}
                LIMIT 50";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id_tercero', $id_tercero, PDO::PARAM_INT);
        $stmt->bindParam(':id_item_envase', $id_item_envase, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerColumnasTablaEnvases(): array {
        $sql = "SHOW COLUMNS FROM cta_cte_envases";
        $stmt = $this->db->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $columnas = [];
        foreach ($filas as $fila) {
            $nombre = (string)($fila['Field'] ?? '');
            if ($nombre !== '') {
                $columnas[$nombre] = true;
            }
        }

        return $columnas;
    }
}
?>
