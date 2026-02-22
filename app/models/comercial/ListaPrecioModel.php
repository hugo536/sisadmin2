<?php
class ListaPrecioModel extends Modelo {

    /** @var array<string,bool> */
    private array $columnCache = [];

    public function listarAcuerdos(): array {
        $nombreComercialExpr = $this->terceroExpr('nombre_comercial');
        $nombreExpr = $this->terceroExpr('nombre', 'nombre_completo');
        $apellidoExpr = $this->terceroExpr('apellido');

        $sql = "SELECT
                    ca.id,
                    ca.id_tercero,
                    ca.estado,
                    ca.observaciones,
                    MAX({$nombreComercialExpr}) AS nombre_comercial,
                    MAX({$nombreExpr}) AS nombre,
                    MAX({$apellidoExpr}) AS apellido,
                    COUNT(cap.id) AS total_productos,
                    SUM(CASE WHEN cap.estado = 1 THEN 1 ELSE 0 END) AS total_activos
                FROM comercial_acuerdos ca
                INNER JOIN terceros t ON t.id = ca.id_tercero
                LEFT JOIN comercial_acuerdos_precios cap ON cap.id_acuerdo = ca.id
                GROUP BY ca.id, ca.id_tercero, ca.estado, ca.observaciones
                ORDER BY ca.created_at DESC, ca.id DESC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['cliente_nombre'] = $this->construirNombreCliente($row);
            $row['nombre'] = $row['cliente_nombre'];
            $row['sin_tarifas'] = ((int)($row['total_productos'] ?? 0) === 0) ? 1 : 0;
        }

        return $rows;
    }

    public function obtenerAcuerdo(int $idAcuerdo): ?array {
        $nombreComercialExpr = $this->terceroExpr('nombre_comercial');
        $nombreExpr = $this->terceroExpr('nombre', 'nombre_completo');
        $apellidoExpr = $this->terceroExpr('apellido');

        $sql = "SELECT
                    ca.*,
                    MAX({$nombreComercialExpr}) AS nombre_comercial,
                    MAX({$nombreExpr}) AS nombre,
                    MAX({$apellidoExpr}) AS apellido,
                    COUNT(cap.id) AS total_productos
                FROM comercial_acuerdos ca
                INNER JOIN terceros t ON t.id = ca.id_tercero
                LEFT JOIN comercial_acuerdos_precios cap ON cap.id_acuerdo = ca.id
                WHERE ca.id = :id
                GROUP BY ca.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $idAcuerdo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['cliente_nombre'] = $this->construirNombreCliente($row);
        return $row;
    }

    public function listarClientesDisponibles(): array {
        $nombreComercialExpr = $this->terceroExpr('nombre_comercial');
        $nombreExpr = $this->terceroExpr('nombre', 'nombre_completo');
        $apellidoExpr = $this->terceroExpr('apellido');
        $documentoExpr = $this->terceroExpr('numero_documento', 'documento_numero');

        $sql = "SELECT
                    t.id,
                    {$nombreComercialExpr} AS nombre_comercial,
                    {$nombreExpr} AS nombre,
                    {$apellidoExpr} AS apellido,
                    {$documentoExpr} AS numero_documento
                FROM terceros t
                WHERE t.es_cliente = 1
                  AND t.estado = 1
                  AND t.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1
                      FROM comercial_acuerdos ca
                      WHERE ca.id_tercero = t.id
                  )
                ORDER BY TRIM(CONCAT(COALESCE({$nombreComercialExpr}, ''), ' ', COALESCE({$nombreExpr}, ''), ' ', COALESCE({$apellidoExpr}, ''))) ASC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['cliente_nombre'] = $this->construirNombreCliente($row);
        }

        return $rows;
    }

    public function crearAcuerdo(int $idTercero, ?string $observaciones = null): int {
        $sql = "INSERT INTO comercial_acuerdos (id_tercero, observaciones, estado) VALUES (:id_tercero, :observaciones, 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_tercero' => $idTercero,
            ':observaciones' => $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function actualizarEstadoAcuerdo(int $idAcuerdo, int $estado): bool {
        $sql = "UPDATE comercial_acuerdos SET estado = :estado WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':estado' => $estado,
            ':id' => $idAcuerdo,
        ]);
    }

    public function eliminarAcuerdo(int $idAcuerdo): bool {
        $sql = "DELETE FROM comercial_acuerdos WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $idAcuerdo]);
    }

    public function contarPreciosAcuerdo(int $idAcuerdo): int {
        $sql = "SELECT COUNT(*) FROM comercial_acuerdos_precios WHERE id_acuerdo = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $idAcuerdo]);
        return (int)$stmt->fetchColumn();
    }

    public function obtenerMatrizPrecios(int $idAcuerdo): array {
        $sql = "SELECT
                    cap.id,
                    cap.id_acuerdo,
                    cap.id_presentacion,
                    cap.precio_pactado,
                    cap.estado,
                    p.codigo_presentacion,
                    p.factor,
                    i.nombre AS item_nombre,
                    s.nombre AS sabor_nombre,
                    ip.nombre AS presentacion_nombre
                FROM comercial_acuerdos_precios cap
                INNER JOIN precios_presentaciones p ON p.id = cap.id_presentacion
                INNER JOIN items i ON i.id = p.id_item
                LEFT JOIN item_sabores s ON s.id = i.id_sabor
                LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                WHERE cap.id_acuerdo = :id_acuerdo
                ORDER BY i.nombre ASC, p.factor ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_acuerdo' => $idAcuerdo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['producto_nombre'] = $this->construirNombrePresentacion($row);
        }

        return $rows;
    }

    public function listarPresentacionesDisponibles(int $idAcuerdo): array {
        $sql = "SELECT
                    p.id,
                    p.codigo_presentacion,
                    p.factor,
                    i.nombre AS item_nombre,
                    s.nombre AS sabor_nombre,
                    ip.nombre AS presentacion_nombre
                FROM precios_presentaciones p
                INNER JOIN items i ON i.id = p.id_item
                LEFT JOIN item_sabores s ON s.id = i.id_sabor
                LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                WHERE p.estado = 1
                  AND p.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                  AND i.estado = 1
                  AND NOT EXISTS (
                      SELECT 1
                      FROM comercial_acuerdos_precios cap
                      WHERE cap.id_acuerdo = :id_acuerdo
                        AND cap.id_presentacion = p.id
                  )
                ORDER BY i.nombre ASC, p.factor ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_acuerdo' => $idAcuerdo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['producto_nombre'] = $this->construirNombrePresentacion($row);
        }

        return $rows;
    }

    public function agregarProductoAcuerdo(int $idAcuerdo, int $idPresentacion, float $precio): bool {
        $sql = "INSERT INTO comercial_acuerdos_precios (id_acuerdo, id_presentacion, precio_pactado, estado)
                VALUES (:id_acuerdo, :id_presentacion, :precio_pactado, 1)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id_acuerdo' => $idAcuerdo,
            ':id_presentacion' => $idPresentacion,
            ':precio_pactado' => $precio,
        ]);
    }

    public function actualizarPrecioPactado(int $idDetalle, float $precio): bool {
        $sql = "UPDATE comercial_acuerdos_precios SET precio_pactado = :precio WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':precio' => $precio,
            ':id' => $idDetalle,
        ]);
    }

    public function actualizarEstadoPrecio(int $idDetalle, int $estado): bool {
        $sql = "UPDATE comercial_acuerdos_precios SET estado = :estado WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':estado' => $estado,
            ':id' => $idDetalle,
        ]);
    }

    public function eliminarProductoAcuerdo(int $idDetalle): bool {
        $sql = "DELETE FROM comercial_acuerdos_precios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $idDetalle]);
    }

    private function construirNombreCliente(array $row): string {
        $comercial = trim((string)($row['nombre_comercial'] ?? ''));
        $persona = trim((string)($row['nombre'] ?? '') . ' ' . (string)($row['apellido'] ?? ''));

        if ($comercial !== '') {
            return $comercial;
        }

        return $persona !== '' ? $persona : 'Cliente #' . ($row['id_tercero'] ?? '');
    }

    private function construirNombrePresentacion(array $row): string {
        $nombre = trim((string)($row['item_nombre'] ?? ''));
        $sabor = trim((string)($row['sabor_nombre'] ?? ''));
        $presentacion = trim((string)($row['presentacion_nombre'] ?? ''));
        $factor = (int)($row['factor'] ?? 0);

        if ($sabor !== '' && mb_strtolower($sabor) !== 'ninguno') {
            $nombre .= ' ' . $sabor;
        }

        if ($presentacion !== '') {
            $nombre .= ' ' . $presentacion;
        }

        if ($factor > 0) {
            $nombre .= ' x ' . $factor;
        }

        return trim($nombre);
    }

    private function terceroExpr(string ...$candidatas): string {
        foreach ($candidatas as $columna) {
            if ($this->terceroTieneColumna($columna)) {
                return 't.' . $columna;
            }
        }

        return 'NULL';
    }

    private function terceroTieneColumna(string $columna): bool {
        if (array_key_exists($columna, $this->columnCache)) {
            return $this->columnCache[$columna];
        }

        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabla AND COLUMN_NAME = :columna LIMIT 1');
        $stmt->execute([
            ':tabla' => 'terceros',
            ':columna' => $columna,
        ]);

        $this->columnCache[$columna] = (bool)$stmt->fetchColumn();
        return $this->columnCache[$columna];
    }
}
