<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/config/Conexion.php';

class PacksModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::get();
    }

    public function obtenerTodosLosPacks(): array
    {
        $sql = "SELECT p.id,
                       p.codigo_presentacion AS sku,
                       COALESCE(NULLIF(TRIM(p.nombre_manual), ''), i.nombre) AS nombre,
                       COALESCE(NULLIF(p.precio_x_menor, 0), i.precio_venta, 0) AS precio_venta
                FROM precios_presentaciones p
                INNER JOIN items i ON i.id = p.id_item
                WHERE p.estado = 1
                  AND p.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                ORDER BY nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function buscarComponentes(string $termino): array
    {
        $sql = "SELECT i.id,
                       i.nombre,
                       i.sku,
                       i.unidad_base,
                       i.tipo_item
                FROM items i
                WHERE i.estado = 1
                  AND i.deleted_at IS NULL
                  AND LOWER(i.tipo_item) IN ('semielaborado', 'insumo')";

        if ($termino !== '') {
            $sql .= " AND (
                        i.nombre LIKE :termino
                        OR i.sku LIKE :termino
                        OR i.tipo_item LIKE :termino
                      )";
        }

        $sql .= ' ORDER BY i.nombre ASC LIMIT 50';

        $stmt = $this->db->prepare($sql);
        if ($termino !== '') {
            $busqueda = '%' . $termino . '%';
            $stmt->bindValue(':termino', $busqueda, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerComponentesPorPack(int $idPack): array
    {
        $sql = "SELECT d.id AS id_detalle,
                       d.id_item,
                       i.nombre AS nombre_item,
                       d.cantidad,
                       COALESCE(d.es_bonificacion, 0) AS es_bonificacion,
                       i.unidad_base
                FROM precios_presentaciones_detalle d
                INNER JOIN items i ON i.id = d.id_item
                WHERE d.id_presentacion = :id_pack
                ORDER BY d.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_pack', $idPack, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function agregarComponente(array $payload): int
    {
        $idPack = (int) ($payload['id_pack'] ?? 0);
        $idItem = (int) ($payload['id_item'] ?? 0);
        $cantidad = (float) ($payload['cantidad'] ?? 0);
        $esBonificacion = (int) ($payload['es_bonificacion'] ?? 0);
        $usuarioId = (int) ($payload['usuario_id'] ?? 0);

        if ($idPack <= 0 || $idItem <= 0 || $cantidad <= 0) {
            throw new RuntimeException('Datos inválidos para agregar el componente.');
        }

        $this->validarPackExiste($idPack);
        $this->validarComponentePermitido($idItem);

        $sqlExiste = 'SELECT id FROM precios_presentaciones_detalle WHERE id_presentacion = :id_pack AND id_item = :id_item LIMIT 1';
        $stmtExiste = $this->db->prepare($sqlExiste);
        $stmtExiste->execute(['id_pack' => $idPack, 'id_item' => $idItem]);
        if ($stmtExiste->fetchColumn()) {
            throw new RuntimeException('El componente ya existe en este pack.');
        }

        $sql = 'INSERT INTO precios_presentaciones_detalle
                    (id_presentacion, id_item, cantidad, es_bonificacion, created_by, updated_by)
                VALUES
                    (:id_presentacion, :id_item, :cantidad, :es_bonificacion, :created_by, :updated_by)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_presentacion' => $idPack,
            'id_item' => $idItem,
            'cantidad' => $cantidad,
            'es_bonificacion' => $esBonificacion,
            'created_by' => $usuarioId > 0 ? $usuarioId : null,
            'updated_by' => $usuarioId > 0 ? $usuarioId : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function eliminarComponente(int $idDetalle): bool
    {
        $sql = 'DELETE FROM precios_presentaciones_detalle WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $idDetalle, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    private function validarPackExiste(int $idPack): void
    {
        $sql = 'SELECT id
                FROM precios_presentaciones
                WHERE id = :id_pack AND estado = 1 AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pack' => $idPack]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('El pack seleccionado no existe o está inactivo.');
        }
    }

    private function validarComponentePermitido(int $idItem): void
    {
        $sql = "SELECT id
                FROM items
                WHERE id = :id_item
                  AND estado = 1
                  AND deleted_at IS NULL
                  AND LOWER(tipo_item) IN ('semielaborado', 'insumo')
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Solo puedes agregar ítems de tipo semielaborado o insumo.');
        }
    }
}
