<?php
declare(strict_types=1);

class DistribuidoresModel extends Modelo
{
    public function guardar(int $idTercero, array $data, int $userId): void
    {
        $sql = "INSERT INTO distribuidores (id_tercero, zona_exclusiva, meta_volumen, created_by, updated_by)
                VALUES (:id_tercero, :zona_exclusiva, :meta_volumen, :created_by, :updated_by)
                ON DUPLICATE KEY UPDATE
                    zona_exclusiva = VALUES(zona_exclusiva),
                    meta_volumen = VALUES(meta_volumen),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW(),
                    deleted_at = NULL";

        $this->db()->prepare($sql)->execute([
            'id_tercero'     => $idTercero,
            'zona_exclusiva' => $data['distribuidor_zona_exclusiva'] ?? null,
            'meta_volumen'   => (float)($data['distribuidor_meta_volumen'] ?? 0),
            'created_by'     => $userId,
            'updated_by'     => $userId
        ]);
    }

    public function listar(): array
    {
        $sql = "SELECT t.id, t.tipo_documento, t.numero_documento, t.nombre_completo,
                       t.telefono, t.email, t.departamento, t.provincia, t.distrito,
                       d.zona_exclusiva, d.meta_volumen
                FROM distribuidores d
                INNER JOIN terceros t ON t.id = d.id_tercero
                WHERE t.deleted_at IS NULL AND d.deleted_at IS NULL
                ORDER BY t.id DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
