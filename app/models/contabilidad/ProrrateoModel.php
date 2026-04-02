<?php

declare(strict_types=1);

// Ya no necesitamos requerir el CentroCostoModel aquí, el Controlador se encarga de eso

class ProrrateoModel extends Modelo
{
    public function listar(): array
    {
        $sql = "SELECT r.id, r.nombre, r.centro_origen_id, r.estado, r.updated_at,
                       co.codigo AS origen_codigo, co.nombre AS origen_nombre
                FROM conta_prorrateo_reglas r
                INNER JOIN conta_centros_costo co ON co.id = r.centro_origen_id
                WHERE r.deleted_at IS NULL
                ORDER BY r.estado DESC, r.nombre ASC";

        $reglas = $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$reglas) {
            return [];
        }

        $ids = array_map(static fn(array $r): int => (int)$r['id'], $reglas);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmtDetalles = $this->db()->prepare(
            "SELECT d.regla_id, d.centro_destino_id, d.porcentaje,
                    cd.codigo AS destino_codigo, cd.nombre AS destino_nombre
             FROM conta_prorrateo_detalles d
             INNER JOIN conta_centros_costo cd ON cd.id = d.centro_destino_id
             WHERE d.regla_id IN ($placeholders)
             ORDER BY d.regla_id ASC, d.id ASC"
        );
        $stmtDetalles->execute($ids);
        $detallesRows = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $detallesMap = [];
        foreach ($detallesRows as $d) {
            $reglaId = (int)$d['regla_id'];
            $detallesMap[$reglaId][] = [
                'centro_destino_id' => (int)$d['centro_destino_id'],
                'destino_codigo' => (string)$d['destino_codigo'],
                'destino_nombre' => (string)$d['destino_nombre'],
                'porcentaje' => (float)$d['porcentaje'],
            ];
        }

        foreach ($reglas as &$r) {
            $id = (int)$r['id'];
            $r['detalles'] = $detallesMap[$id] ?? [];
        }

        unset($r);

        return $reglas;
    }

    // ❌ Función listarCentrosActivos() ELIMINADA (Ya lo maneja el Controlador)

    public function guardar(array $data, int $userId): int
    {
        $id = (int)($data['id'] ?? 0);
        $nombre = trim((string)($data['nombre'] ?? ''));
        $centroOrigenId = (int)($data['centro_origen_id'] ?? 0);
        $estado = (int)($data['estado'] ?? 1) === 1 ? 1 : 0;
        $detalles = is_array($data['detalles'] ?? null) ? $data['detalles'] : [];

        // Validaciones iniciales
        if ($nombre === '') {
            throw new RuntimeException('El nombre de la regla es obligatorio.');
        }
        if ($centroOrigenId <= 0) {
            throw new RuntimeException('Debe seleccionar un centro de costo de origen válido.');
        }
        if (count($detalles) === 0) {
            throw new RuntimeException('Debe definir al menos un centro de costo destino.');
        }

        // 👇 NUEVA MEJORA: Validar Duplicidad de Reglas
        // Evitamos que exista más de una regla activa para el mismo Centro de Origen
        $stmtCheck = $this->db()->prepare('SELECT 1 FROM conta_prorrateo_reglas WHERE centro_origen_id = :origen_id AND id != :id AND deleted_at IS NULL LIMIT 1');
        $stmtCheck->execute([
            'origen_id' => $centroOrigenId, 
            'id' => $id
        ]);
        if ($stmtCheck->fetchColumn()) {
            throw new RuntimeException('Ya existe una regla de prorrateo configurada para este Centro de Costo origen.');
        }
        // 👆 FIN DE LA MEJORA 👆

        $db = $this->db();
        $db->beginTransaction();

        try {
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE conta_prorrateo_reglas
                    SET nombre = :nombre,
                        centro_origen_id = :centro_origen_id,
                        estado = :estado,
                        updated_by = :user,
                        updated_at = NOW()
                    WHERE id = :id AND deleted_at IS NULL');

                $stmt->execute([
                    'nombre' => $nombre,
                    'centro_origen_id' => $centroOrigenId,
                    'estado' => $estado,
                    'user' => $userId,
                    'id' => $id,
                ]);
            } else {
                $stmt = $db->prepare('INSERT INTO conta_prorrateo_reglas
                    (centro_origen_id, nombre, estado, created_by, updated_by, created_at, updated_at)
                    VALUES (:centro_origen_id, :nombre, :estado, :user_created, :user_updated, NOW(), NOW())');

                $stmt->execute([
                    'centro_origen_id' => $centroOrigenId,
                    'nombre' => $nombre,
                    'estado' => $estado,
                    'user_created' => $userId,
                    'user_updated' => $userId,
                ]);

                $id = (int)$db->lastInsertId();
            }

            // Limpiamos los detalles viejos (si los hay)
            $stmtDel = $db->prepare('DELETE FROM conta_prorrateo_detalles WHERE regla_id = :regla_id');
            $stmtDel->execute(['regla_id' => $id]);

            // Insertamos los detalles nuevos
            $stmtIns = $db->prepare('INSERT INTO conta_prorrateo_detalles
                (regla_id, centro_destino_id, porcentaje)
                VALUES (:regla_id, :centro_destino_id, :porcentaje)');

            foreach ($detalles as $detalle) {
                $centroDestinoId = (int)($detalle['centro_destino_id'] ?? 0);
                $porcentaje = round((float)($detalle['porcentaje'] ?? 0), 2);

                if ($centroDestinoId <= 0) {
                    throw new RuntimeException('Todos los destinos deben tener un centro de costo válido.');
                }
                if ($porcentaje <= 0) {
                    throw new RuntimeException('Todos los porcentajes deben ser mayores a 0.');
                }

                $stmtIns->execute([
                    'regla_id' => $id,
                    'centro_destino_id' => $centroDestinoId,
                    'porcentaje' => $porcentaje,
                ]);
            }

            $db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}