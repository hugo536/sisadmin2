<?php

class PresentacionModel extends Modelo {

    private const MARGEN_MIXTO_DEFAULT = 0.20;

    public function __construct() {
        parent::__construct();
        $this->table = 'precios_presentaciones';
    }

    public function listarTodo() {
    $sql = "SELECT p.*,
                   i.sku AS codigo_presentacion,
                   
                   COALESCE(p.nombre_manual, i.nombre) AS item_nombre_full,

                   COALESCE(det.cantidad_items, 0) as cantidad_items_distintos,
                   COALESCE(det.composicion, '') AS composicion_mixta
            FROM {$this->table} p
            INNER JOIN items i ON p.id_item = i.id
            LEFT JOIN (
                SELECT d.id_presentacion,
                       COUNT(d.id_item) AS cantidad_items, 
                       GROUP_CONCAT(CONCAT(TRIM(TRAILING '.0000' FROM TRIM(TRAILING '0' FROM FORMAT(d.cantidad, 4))), ' ', i2.nombre) ORDER BY d.id SEPARATOR ' + ') AS composicion
                FROM precios_presentaciones_detalle d
                INNER JOIN items i2 ON i2.id = d.id_item
                GROUP BY d.id_presentacion
            ) det ON det.id_presentacion = p.id
            WHERE p.deleted_at IS NULL AND p.estado = 1
            ORDER BY p.id DESC";

    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

    public function obtener($id) {
        $sql = "SELECT p.*, i.sku as codigo_presentacion, i.nombre as nombre_manual 
                FROM {$this->table} p
                INNER JOIN items i ON p.id_item = i.id
                WHERE p.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [];
        }

        // Si es pack simple, la lógica Frontend tal vez necesite saber cuál era el semielaborado base.
        // Lo sacamos del detalle ya que ahora TODO tiene detalle.
        $row['detalle_mixto'] = $this->obtenerDetalleMixto((int) $id);
        
        if ($row['es_mixto'] == 0 && !empty($row['detalle_mixto'])) {
             $row['id_item_base'] = $row['detalle_mixto'][0]['id_item'];
             $row['factor'] = $row['detalle_mixto'][0]['cantidad'];
        }

        return $row;
    }

    public function listarProductosParaSelect() {
        // Se mantiene igual, sirve para poblar los selects con los líquidos base.
        $sql = "SELECT i.id,
                       i.nombre AS nombre_completo,
                       i.nombre,
                       i.sku,
                       i.unidad_base,
                       i.precio_venta
                FROM items i
                WHERE i.estado = 1 
                  AND i.deleted_at IS NULL 
                  AND i.tipo_item = 'semielaborado'
                ORDER BY i.nombre ASC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($datos) {
        try {
            $this->db->beginTransaction();

            $idPresentacionForm = !empty($datos['id']) ? (int)$datos['id'] : 0;
            $esMixto = (int) ($datos['es_mixto'] ?? 0) === 1;
            
            $precioMenor = !empty($datos['precio_x_menor']) ? (float) $datos['precio_x_menor'] : 0;
            $precioMayor = !empty($datos['precio_x_mayor']) ? (float) $datos['precio_x_mayor'] : null;
            $cantMinima  = !empty($datos['cantidad_minima_mayor']) ? (int) $datos['cantidad_minima_mayor'] : null;
            $pesoBruto   = isset($datos['peso_bruto']) && $datos['peso_bruto'] !== '' ? (float) $datos['peso_bruto'] : 0;
            $stockMinimo = isset($datos['stock_minimo']) && $datos['stock_minimo'] !== '' ? (float) $datos['stock_minimo'] : 0;
            
            $notaPack = !empty($datos['nota_pack']) ? trim($datos['nota_pack']) : null;
            $exigirLote = !empty($datos['exigir_lote']) ? 1 : 0;
            $requiereVencimiento = !empty($datos['requiere_vencimiento']) ? 1 : 0;
            $diasVencimiento = !empty($datos['dias_vencimiento_alerta']) ? (int) $datos['dias_vencimiento_alerta'] : 0;
            
            $usuario = $_SESSION['id_usuario'] ?? 1;

            $nombreManual = '';
            $codigoFinal = '';
            $factor = 0;
            $detalleComponentes = [];

            // 1. Preparar Datos Estructurales (Unificando Flujos)
            if (!$esMixto) {
                $idSemielaborado = (int) ($datos['id_item'] ?? 0);
                $factor = (float) ($datos['factor'] ?? 0);
                
                // Obtener datos del semielaborado para heredar nombre si no se envía uno manual
                $stmtBase = $this->db->prepare("
                    SELECT i.sku, i.nombre
                    FROM items i
                    WHERE i.id = :id
                ");
                $stmtBase->execute([':id' => $idSemielaborado]);
                $base = $stmtBase->fetch(PDO::FETCH_ASSOC);

                $skuBase = $base ? $base['sku'] : 'GEN';
                $codigoFinal = !empty($datos['codigo_presentacion']) ? $datos['codigo_presentacion'] : ($skuBase . '-X' . (int)$factor);
                
                $nombreManual = trim($datos['nombre_manual'] ?? '');
                if (empty($nombreManual) && $base) {
                    $nombreManual = trim((string) ($base['nombre'] ?? '')) . ' x ' . (int) $factor;
                }

                // El detalle de un pack simple es su único semielaborado
                $detalleComponentes = [
                    ['id_item' => $idSemielaborado, 'cantidad' => $factor]
                ];

            } else {
                $nombreManual = trim($datos['nombre_manual'] ?? 'Pack Mixto'); 
                $codigoFinal = !empty($datos['codigo_presentacion']) ? strtoupper(trim($datos['codigo_presentacion'])) : ('MIX-' . date('ymdHis'));
                
                $detalleComponentes = $datos['detalle_mixto'] ?? [];
                $calculos = $this->calcularTotalesDetalle($detalleComponentes);
                
                $factor = $calculos['factor_total'];
                if ($pesoBruto == 0) {
                    $pesoBruto = $calculos['peso'];
                }
            }

            // 2. Ejecutar Guardado (Tabla Items -> Tabla Presentaciones -> Tabla Detalles)
            if ($idPresentacionForm === 0) {
                // A) Crear Ítem Comercial
                $sqlItem = "INSERT INTO items (sku, nombre, tipo_item, id_categoria, unidad_base, controla_stock, moneda, estado, created_by) 
                            VALUES (:sku, :nombre, 'producto_terminado', 2, 'UND', 1, 'PEN', 1, :user)";
                $stmtItem = $this->db->prepare($sqlItem);
                $stmtItem->execute([
                    ':sku' => $codigoFinal,
                    ':nombre' => $nombreManual,
                    ':user' => $usuario
                ]);
                $idItemComercial = (int) $this->db->lastInsertId();

                // B) Crear Configuración de Venta
                $sqlPP = "INSERT INTO {$this->table} 
                          (id_item, nombre_manual, nota_pack, codigo_presentacion, factor, es_mixto, precio_x_menor, precio_x_mayor, cantidad_minima_mayor, peso_bruto, stock_minimo, exigir_lote, requiere_vencimiento, dias_vencimiento_alerta, created_by)
                          VALUES (:id_item, :nombre_manual, :nota_pack, :codigo, :factor, :es_mixto, :precio_x_menor, :precio_x_mayor, :cantidad_minima_mayor, :peso_bruto, :stock_minimo, :exigir_lote, :requiere_vencimiento, :dias_vencimiento_alerta, :created_by)";
                
                $stmtPP = $this->db->prepare($sqlPP);
                $stmtPP->execute([
                    ':id_item'               => $idItemComercial,
                    ':nombre_manual'         => $nombreManual,
                    ':nota_pack'             => $notaPack,
                    ':codigo'                => $codigoFinal, // Mantenemos redundancia temporal para vistas viejas
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
                ]);
                
                $idPresentacionForm = (int) $this->db->lastInsertId();

            } else {
                // A) Obtener ID del Ítem vinculado
                $actual = $this->obtener($idPresentacionForm);
                if (!$actual) {
                    $this->db->rollBack(); return false;
                }
                $idItemComercial = $actual['id_item'];

                // B) Actualizar tabla Items
                $sqlUpdateItem = "UPDATE items SET sku = :sku, nombre = :nombre WHERE id = :id";
                $this->db->prepare($sqlUpdateItem)->execute([
                    ':sku' => $codigoFinal,
                    ':nombre' => $nombreManual,
                    ':id' => $idItemComercial
                ]);

                // C) Actualizar Configuración de Venta
                $sqlPP = "UPDATE {$this->table} SET
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
                
                $stmtPP = $this->db->prepare($sqlPP);
                $stmtPP->execute([
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
                    ':id'                    => $idPresentacionForm
                ]);

                $this->eliminarDetalleMixto($idPresentacionForm);
            }

            // 3. Guardar Componentes (Para simple y mixto)
            $this->guardarDetalleMixto($idPresentacionForm, $detalleComponentes);

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
        $stmtGet = $this->db->prepare("SELECT id_item FROM {$this->table} WHERE id = :id");
        $stmtGet->execute([':id' => $id]);
        $idItem = $stmtGet->fetchColumn();

        $user = $_SESSION['id_usuario'] ?? 1;

        // Desactivar presentación
        $sql = "UPDATE {$this->table} SET estado = 0, deleted_at = NOW(), deleted_by = :user WHERE id = :id";
        $this->db->prepare($sql)->execute([':id' => $id, ':user' => $user]);

        // Desactivar ítem asociado
        if ($idItem) {
            $sqlItem = "UPDATE items SET estado = 0, deleted_at = NOW(), deleted_by = :user WHERE id = :id";
            $this->db->prepare($sqlItem)->execute([':id' => $idItem, ':user' => $user]);
        }

        return true;
    }

    public function actualizarEstado(int $id, int $estado): bool {
        $stmtGet = $this->db->prepare("SELECT id_item FROM {$this->table} WHERE id = :id");
        $stmtGet->execute([':id' => $id]);
        $idItem = $stmtGet->fetchColumn();

        $user = $_SESSION['id_usuario'] ?? 1;

        $sql = "UPDATE {$this->table}
                SET estado = :estado, updated_by = :user, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL";
        $this->db->prepare($sql)->execute([':estado' => $estado, ':user' => $user, ':id' => $id]);

        if ($idItem) {
            $sqlItem = "UPDATE items SET estado = :estado, updated_by = :user, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL";
            $this->db->prepare($sqlItem)->execute([':estado' => $estado, ':user' => $user, ':id' => $idItem]);
        }

        return true;
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
                       i.nombre AS item_nombre,
                       i.nombre as nombre_base
                FROM precios_presentaciones_detalle d
                INNER JOIN items i ON i.id = d.id_item
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
