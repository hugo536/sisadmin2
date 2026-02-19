<?php
class PresentacionModel extends Modelo {

    private const MARGEN_MIXTO_DEFAULT = 0.20;

    public function __construct() {
        parent::__construct();
        $this->table = 'precios_presentaciones';
    }

    public function listarTodo() {
        // MODIFICACIÓN CLAVE: 
        // 1. Contamos cuántos ítems tiene el detalle (cantidad_items).
        // 2. Si es 1, la vista sabrá que NO debe mostrar el icono de "red".
        
        $sql = "SELECT p.*,
                       p.codigo_presentacion,
                       
                       -- Traemos el conteo para la lógica visual del icono
                       COALESCE(det.cantidad_items, 0) as cantidad_items_distintos,

                       COALESCE(
                            p.nombre_manual, 
                            CONCAT(
                                i.nombre,
                                CASE 
                                    WHEN s.nombre IS NOT NULL AND s.nombre != 'Ninguno' THEN CONCAT(' ', s.nombre) 
                                    ELSE '' 
                                END,
                                CASE 
                                    WHEN ip.nombre IS NOT NULL THEN CONCAT(' ', ip.nombre) 
                                    ELSE '' 
                                END,
                                ' x ', CAST(p.factor AS UNSIGNED)
                            )
                       ) as item_nombre_full,
                       
                       COALESCE(det.composicion, '') AS composicion_mixta

                FROM {$this->table} p
                LEFT JOIN items i ON p.id_item = i.id
                LEFT JOIN item_sabores s ON i.id_sabor = s.id
                LEFT JOIN item_presentaciones ip ON i.id_presentacion = ip.id
                LEFT JOIN (
                    SELECT d.id_presentacion,
                           -- Aquí contamos cuántos productos componen el pack
                           COUNT(d.id_item) AS cantidad_items, 
                           GROUP_CONCAT(CONCAT(TRIM(TRAILING '.0000' FROM TRIM(TRAILING '0' FROM FORMAT(d.cantidad, 4))), ' ', i2.nombre) ORDER BY d.id SEPARATOR ' + ') AS composicion
                    FROM precios_presentaciones_detalle d
                    INNER JOIN items i2 ON i2.id = d.id_item
                    GROUP BY d.id_presentacion
                ) det ON det.id_presentacion = p.id
                WHERE p.deleted_at IS NULL AND p.estado = 1
                ORDER BY p.id DESC"; // Ordenamos por ID descendente para ver lo último creado arriba

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [];
        }

        $row['detalle_mixto'] = $this->obtenerDetalleMixto((int) $id);
        return $row;
    }

    public function listarProductosParaSelect() {
        $sql = "SELECT i.id,
                       CONCAT(
                           i.nombre,
                           CASE 
                               WHEN s.nombre IS NOT NULL AND s.nombre != 'Ninguno' THEN CONCAT(' ', s.nombre) 
                               ELSE '' 
                           END,
                           CASE 
                               WHEN ip.nombre IS NOT NULL THEN CONCAT(' ', ip.nombre) 
                               ELSE '' 
                           END
                       ) as nombre_completo,
                       i.nombre,
                       i.sku,
                       i.unidad_base,
                       i.precio_venta
                FROM items i
                LEFT JOIN item_sabores s ON i.id_sabor = s.id
                LEFT JOIN item_presentaciones ip ON i.id_presentacion = ip.id
                WHERE i.estado = 1 
                  AND i.deleted_at IS NULL 
                  AND i.tipo_item = 'producto'
                ORDER BY i.nombre ASC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($datos) {
        try {
            $this->db->beginTransaction();

            $esMixto = (int) ($datos['es_mixto'] ?? 0) === 1;
            
            $precioMenor = !empty($datos['precio_x_menor']) ? (float) $datos['precio_x_menor'] : 0;
            $precioMayor = !empty($datos['precio_x_mayor']) ? (float) $datos['precio_x_mayor'] : null;
            $cantMinima  = !empty($datos['cantidad_minima_mayor']) ? (int) $datos['cantidad_minima_mayor'] : null;
            $pesoBruto   = isset($datos['peso_bruto']) && $datos['peso_bruto'] !== '' ? (float) $datos['peso_bruto'] : 0;
            $stockMinimo = isset($datos['stock_minimo']) && $datos['stock_minimo'] !== '' ? (float) $datos['stock_minimo'] : 0;
            $usuario     = $_SESSION['id_usuario'] ?? 1;

            $idItem = null;
            $factor = 0;
            $codigoFinal = null;
            $nombreManual = null;
            
            // Capturar Nota/Observación
            $notaPack = !empty($datos['nota_pack']) ? trim($datos['nota_pack']) : null;

            // NUEVO: Capturar Configuración Avanzada (Lote y Vencimiento)
            $exigirLote = !empty($datos['exigir_lote']) ? 1 : 0;
            $requiereVencimiento = !empty($datos['requiere_vencimiento']) ? 1 : 0;
            $diasVencimiento = !empty($datos['dias_vencimiento_alerta']) ? (int) $datos['dias_vencimiento_alerta'] : 0;

            if (!$esMixto) {
                $idItem = (int) ($datos['id_item'] ?? 0);
                $factor = (float) ($datos['factor'] ?? 0);
                
                $stmtItem = $this->db->prepare("SELECT sku FROM items WHERE id = :id");
                $stmtItem->execute([':id' => $idItem]);
                $skuBase = $stmtItem->fetchColumn();
                
                if (!empty($datos['codigo_presentacion'])) {
                     $codigoFinal = $datos['codigo_presentacion'];
                } else {
                     $codigoFinal = ($skuBase ? $skuBase : 'GEN') . '-X' . (int) $factor;
                }

            } else {
                $idItem = null; 
                $nombreManual = trim($datos['nombre_manual'] ?? 'Pack Mixto'); 
                
                $calculos = $this->calcularTotalesDetalle($datos['detalle_mixto'] ?? []);
                $factor = $calculos['factor_total'];
                
                if ($pesoBruto == 0) {
                    $pesoBruto = $calculos['peso'];
                }

                if (!empty($datos['codigo_presentacion'])) {
                    $codigoFinal = strtoupper(trim($datos['codigo_presentacion']));
                } else {
                    $codigoFinal = 'MIX-' . date('ymdHis');
                }
            }

            if (empty($datos['id'])) {
                // INSERTAR (Incluye campos de lote y vencimiento)
                $sql = "INSERT INTO {$this->table}
                        (id_item, nombre_manual, nota_pack, codigo_presentacion, factor, es_mixto, precio_x_menor, precio_x_mayor, cantidad_minima_mayor, peso_bruto, stock_minimo, exigir_lote, requiere_vencimiento, dias_vencimiento_alerta, created_by)
                        VALUES (:id_item, :nombre_manual, :nota_pack, :codigo, :factor, :es_mixto, :precio_x_menor, :precio_x_mayor, :cantidad_minima_mayor, :peso_bruto, :stock_minimo, :exigir_lote, :requiere_vencimiento, :dias_vencimiento_alerta, :created_by)";
                $params = [
                    ':id_item'               => $idItem,
                    ':nombre_manual'         => $nombreManual,
                    ':nota_pack'             => $notaPack,
                    ':codigo'                => $codigoFinal,
                    ':factor'                => $factor,
                    ':es_mixto'              => $esMixto ? 1 : 0,
                    ':precio_x_menor'        => $precioMenor,
                    ':precio_x_mayor'        => $precioMayor,
                    ':cantidad_minima_mayor' => $cantMinima,
                    ':peso_bruto'            => $pesoBruto,
                    ':stock_minimo'          => $stockMinimo,
                    ':exigir_lote'           => $exigirLote,
                    ':requiere_vencimiento'  => $requiereVencimiento,
                    ':dias_vencimiento_alerta'=> $diasVencimiento,
                    ':created_by'            => $usuario
                ];
                
                $stmt = $this->db->prepare($sql);
                $ok = $stmt->execute($params);
                
                if (!$ok) {
                    $this->db->rollBack();
                    return false;
                }
                $presentacionId = (int) $this->db->lastInsertId();

            } else {
                // ACTUALIZAR (Incluye campos de lote y vencimiento)
                $presentacionId = (int) $datos['id'];
                $actual = $this->obtener($presentacionId);
                if (!$actual) {
                    $this->db->rollBack(); return false;
                }

                $sql = "UPDATE {$this->table} SET
                        id_item = :id_item,
                        nombre_manual = :nombre_manual,
                        nota_pack = :nota_pack,
                        codigo_presentacion = :codigo,
                        factor = :factor,
                        es_mixto = :es_mixto,
                        precio_x_menor = :precio_x_menor,
                        precio_x_mayor = :precio_x_mayor,
                        cantidad_minima_mayor = :cantidad_minima_mayor,
                        peso_bruto = :peso_bruto,
                        stock_minimo = :stock_minimo,
                        exigir_lote = :exigir_lote,
                        requiere_vencimiento = :requiere_vencimiento,
                        dias_vencimiento_alerta = :dias_vencimiento_alerta,
                        updated_by = :updated_by,
                        updated_at = NOW()
                        WHERE id = :id";
                
                $params = [
                    ':id_item'               => $idItem,
                    ':nombre_manual'         => $nombreManual,
                    ':nota_pack'             => $notaPack,
                    ':codigo'                => $codigoFinal,
                    ':factor'                => $factor,
                    ':es_mixto'              => $esMixto ? 1 : 0,
                    ':precio_x_menor'        => $precioMenor,
                    ':precio_x_mayor'        => $precioMayor,
                    ':cantidad_minima_mayor' => $cantMinima,
                    ':peso_bruto'            => $pesoBruto,
                    ':stock_minimo'          => $stockMinimo,
                    ':exigir_lote'           => $exigirLote,
                    ':requiere_vencimiento'  => $requiereVencimiento,
                    ':dias_vencimiento_alerta'=> $diasVencimiento,
                    ':updated_by'            => $usuario,
                    ':id'                    => $presentacionId
                ];
                
                $stmt = $this->db->prepare($sql);
                $ok = $stmt->execute($params);
                
                if (!$ok) {
                    $this->db->rollBack(); return false;
                }

                $this->eliminarDetalleMixto($presentacionId);
            }

            if ($esMixto) {
                $this->guardarDetalleMixto($presentacionId, $datos['detalle_mixto'] ?? []);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
    }

    public function eliminar($id) {
        $sql = "UPDATE {$this->table} SET estado = 0, deleted_at = NOW(), deleted_by = :user WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':user', $_SESSION['id_usuario'] ?? 1);
        return $stmt->execute();
    }

    public function actualizarEstado(int $id, int $estado): bool {
        $sql = "UPDATE {$this->table}
                SET estado = :estado,
                    updated_by = :user,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':estado' => $estado,
            ':user' => $_SESSION['id_usuario'] ?? 1,
            ':id' => $id,
        ]);
    }

    private function calcularTotalesDetalle(array $detalle): array {
        $peso = 0.0;
        $factorTotal = 0.0;
        
        foreach ($detalle as $linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($cantidad > 0) {
                $factorTotal += $cantidad;
            }
        }

        return [
            'peso' => $peso,
            'factor_total' => $factorTotal,
        ];
    }

    private function obtenerDetalleMixto(int $idPresentacion): array {
        $sql = 'SELECT d.id, d.id_item, d.cantidad, i.unidad_base, i.precio_venta,
                       CONCAT(
                           i.nombre,
                           CASE WHEN s.nombre IS NOT NULL AND s.nombre != "Ninguno" THEN CONCAT(" ", s.nombre) ELSE "" END,
                           CASE WHEN ip.nombre IS NOT NULL THEN CONCAT(" ", ip.nombre) ELSE "" END
                       ) AS item_nombre,
                       i.nombre as nombre_base
                FROM precios_presentaciones_detalle d
                INNER JOIN items i ON i.id = d.id_item
                LEFT JOIN item_sabores s ON i.id_sabor = s.id
                LEFT JOIN item_presentaciones ip ON i.id_presentacion = ip.id
                WHERE d.id_presentacion = :id
                ORDER BY d.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $idPresentacion]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function eliminarDetalleMixto(int $idPresentacion): void {
        $stmt = $this->db->prepare('DELETE FROM precios_presentaciones_detalle WHERE id_presentacion = :id');
        $stmt->execute([':id' => $idPresentacion]);
    }

    private function guardarDetalleMixto(int $idPresentacion, array $detalle): void {
        $sql = 'INSERT INTO precios_presentaciones_detalle (id_presentacion, id_item, cantidad) VALUES (:id_presentacion, :id_item, :cantidad)';
        $stmt = $this->db->prepare($sql);

        foreach ($detalle as $linea) {
            $idItem = (int) ($linea['id_item'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($idItem <= 0 || $cantidad <= 0) {
                continue;
            }

            $stmt->execute([
                ':id_presentacion' => $idPresentacion,
                ':id_item' => $idItem,
                ':cantidad' => $cantidad,
            ]);
        }
    }
}