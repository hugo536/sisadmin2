<?php
declare(strict_types=1);

class UnidadConversionModel extends Modelo
{
    private ?bool $soportaUnidadPredeterminada = null;

    public function listarUnidadesConversion(): array
    {
        $sql = 'SELECT i.id, i.sku, i.nombre, i.unidad_base, i.requiere_factor_conversion,
                       COUNT(u.id) AS total_unidades
                FROM items i
                LEFT JOIN items_unidades u ON u.id_item = i.id AND u.deleted_at IS NULL AND u.estado = 1
                WHERE i.deleted_at IS NULL
                  AND i.requiere_factor_conversion = 1
                GROUP BY i.id, i.sku, i.nombre, i.unidad_base, i.requiere_factor_conversion
                ORDER BY (COUNT(u.id) = 0) DESC, i.nombre ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarDetalleUnidadesConversion(int $idItem): array
    {
        if ($idItem <= 0) {
            return [];
        }

        // La columna se agregó después de que este módulo ya estuviera en uso.
        // Mantener este fallback evita que una base pendiente de migración rompa la
        // carga completa de las conversiones con un error 500.
        $campoPredeterminado = $this->soportaPredeterminada()
            ? 'es_predeterminada, 1 AS puede_fijar_predeterminada'
            : '0 AS es_predeterminada, 0 AS puede_fijar_predeterminada';

        $sql = 'SELECT id, id_item, nombre, codigo_unidad, factor_conversion, peso_kg, estado, ' . $campoPredeterminado . '
                FROM items_unidades
                WHERE id_item = :id_item
                  AND deleted_at IS NULL
                ORDER BY nombre ASC, id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearUnidadConversion(array $data, int $userId): int
    {
        // 1. Verificamos si ya existen unidades previas para este ítem
        $sqlCheck = 'SELECT COUNT(id) FROM items_unidades WHERE id_item = :id_item AND deleted_at IS NULL';
        $stmtCheck = $this->db()->prepare($sqlCheck);
        $stmtCheck->execute(['id_item' => (int) $data['id_item']]);
        $totalUnidades = (int) $stmtCheck->fetchColumn();

        // 2. Si el conteo es 0, es la primera unidad y será la predeterminada (1)
        $esPredeterminada = ($totalUnidades === 0) ? 1 : 0;

        // 3. Insertamos agregando el campo es_predeterminada
        $sql = 'INSERT INTO items_unidades (id_item, nombre, codigo_unidad, factor_conversion, peso_kg, estado, es_predeterminada, created_by, updated_by, created_at, updated_at)
                VALUES (:id_item, :nombre, :codigo_unidad, :factor_conversion, :peso_kg, :estado, :es_predeterminada, :created_by, :updated_by, NOW(), NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_item' => (int) $data['id_item'],
            'nombre' => trim((string) $data['nombre']),
            'codigo_unidad' => trim((string) ($data['codigo_unidad'] ?? '')) !== '' ? trim((string) $data['codigo_unidad']) : null,
            'factor_conversion' => (float) ($data['factor_conversion'] ?? 1),
            'peso_kg' => (float) ($data['peso_kg'] ?? 0),
            'estado' => (int) ($data['estado'] ?? 1),
            'es_predeterminada' => $esPredeterminada,
            'created_by' => $userId,
            'updated_by' => $userId
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizarUnidadConversion(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE items_unidades
                SET nombre = :nombre,
                    codigo_unidad = :codigo_unidad,
                    factor_conversion = :factor_conversion,
                    peso_kg = :peso_kg,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND id_item = :id_item
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'nombre' => trim((string) $data['nombre']),
            'codigo_unidad' => trim((string) ($data['codigo_unidad'] ?? '')) !== '' ? trim((string) $data['codigo_unidad']) : null,
            'factor_conversion' => (float) ($data['factor_conversion'] ?? 1),
            'peso_kg' => (float) ($data['peso_kg'] ?? 0),
            'estado' => (int) ($data['estado'] ?? 1),
            'updated_by' => $userId,
            'id' => $id,
            'id_item' => (int) $data['id_item']
        ]);
    }

    public function eliminarUnidadConversion(int $id, int $idItem, int $userId): bool
    {
        $bloqueos = $this->obtenerBloqueosEliminacionUnidad($id);
        if ($bloqueos !== []) {
            throw new RuntimeException('No se puede eliminar esta unidad porque ya tiene uso en: ' . implode(', ', $bloqueos) . '.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Verificar si la unidad a eliminar es la predeterminada actualmente
            $stmtCheck = $db->prepare('SELECT es_predeterminada FROM items_unidades WHERE id = :id');
            $stmtCheck->execute(['id' => $id]);
            $esPredeterminada = (int) $stmtCheck->fetchColumn();

            // 2. Realizar el borrado lógico (quitándole también la estrellita por seguridad)
            $sqlDelete = 'UPDATE items_unidades
                          SET deleted_at = NOW(),
                              deleted_by = :deleted_by,
                              updated_at = NOW(),
                              updated_by = :updated_by,
                              estado = 0,
                              es_predeterminada = 0
                          WHERE id = :id
                            AND id_item = :id_item
                            AND deleted_at IS NULL';

            $db->prepare($sqlDelete)->execute([
                'deleted_by' => $userId,
                'updated_by' => $userId,
                'id' => $id,
                'id_item' => $idItem
            ]);

            // 3. Si era la predeterminada, transferir la estrella a la siguiente unidad disponible
            if ($esPredeterminada === 1) {
                $sqlReasignar = 'UPDATE items_unidades 
                                 SET es_predeterminada = 1, 
                                     updated_at = NOW(), 
                                     updated_by = :updated_by
                                 WHERE id_item = :id_item 
                                   AND deleted_at IS NULL 
                                 ORDER BY id ASC LIMIT 1';
                                 
                $db->prepare($sqlReasignar)->execute([
                    'updated_by' => $userId,
                    'id_item' => $idItem
                ]);
            }

            $db->commit();
            return true;

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Indica si la unidad ya está referenciada por algún documento o movimiento.
     *
     * El controlador usa esta comprobación al cargar el detalle para deshabilitar
     * la eliminación de unidades con historial. Debe vivir en este modelo (y no
     * en ItemModel), pues es el modelo que atiende el módulo de conversiones.
     */
    public function tieneHistorial(int $idUnidad): bool
    {
        if ($idUnidad <= 0) {
            return false;
        }

        return $this->obtenerBloqueosEliminacionUnidad($idUnidad) !== [];
    }

    private function obtenerBloqueosEliminacionUnidad(int $idUnidad): array
    {
        $db = $this->db();
        $usos = [];

        $checks = [
            'compras_ordenes_detalle' => 'SELECT COUNT(*) FROM compras_ordenes_detalle WHERE id_item_unidad = :id',
            'compras_recepciones_detalle' => 'SELECT COUNT(*) FROM compras_recepciones_detalle WHERE id_item_unidad = :id',
            'movimientos_inventario_detalle' => 'SELECT COUNT(*) FROM movimientos_inventario_detalle WHERE id_item_unidad = :id',
            'comercial_acuerdos_proveedor_precios' => 'SELECT COUNT(*) FROM comercial_acuerdos_proveedor_precios WHERE id_unidad_conversion = :id',
        ];

        foreach ($checks as $tabla => $sql) {
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute(['id' => $idUnidad]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $usos[] = $tabla;
                }
            } catch (Throwable $e) {
                // Algunas instalaciones aún no tienen todas las migraciones de
                // referencias. No impedir la carga del detalle por ese desfase.
                error_log('Error al verificar historial de unidad de conversión en ' . $tabla . ': ' . $e->getMessage());
            }
        }

        return $usos;
    }

    private function tablaExiste(string $tabla): bool
    {
        try {
            $stmt = $this->db()->prepare('SHOW TABLES LIKE :tabla');
            $stmt->execute(['tabla' => $tabla]);
            return (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * =========================================================================
     * NUEVA FUNCIÓN: Marca una unidad como predeterminada y apaga las demás
     * =========================================================================
     */
    public function marcarComoPredeterminada(int $idUnidad, int $idItem, int $userId): bool
    {
        if (!$this->soportaPredeterminada()) {
            throw new RuntimeException(
                'La base de datos requiere la migración 20261003_items_unidades_predeterminada.sql para usar unidades predeterminadas.'
            );
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Apagamos todas las unidades de este ítem (es_predeterminada = 0)
            $stmt1 = $db->prepare('UPDATE items_unidades 
                                   SET es_predeterminada = 0, updated_by = :user, updated_at = NOW() 
                                   WHERE id_item = :id_item');
            $stmt1->execute(['user' => $userId, 'id_item' => $idItem]);

            // 2. Encendemos SOLO la unidad que el usuario seleccionó (es_predeterminada = 1)
            $stmt2 = $db->prepare('UPDATE items_unidades 
                                   SET es_predeterminada = 1, updated_by = :user, updated_at = NOW() 
                                   WHERE id = :id AND id_item = :id_item');
            $stmt2->execute(['user' => $userId, 'id' => $idUnidad, 'id_item' => $idItem]);

            $db->commit();
            return true;

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function soportaPredeterminada(): bool
    {
        if ($this->soportaUnidadPredeterminada !== null) {
            return $this->soportaUnidadPredeterminada;
        }

        try {
            $stmt = $this->db()->prepare('SHOW COLUMNS FROM items_unidades LIKE :columna');
            $stmt->execute(['columna' => 'es_predeterminada']);
            $this->soportaUnidadPredeterminada = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $this->soportaUnidadPredeterminada = false;
        }

        return $this->soportaUnidadPredeterminada;
    }
}
