<?php
class ListaPrecioModel extends Modelo {

    /** @var array<string,bool> */
    private array $columnCache = [];

    public function soportaPresentacionesComerciales(): bool {
        // Como ahora usaremos la tabla 'items' directamente, forzamos que siempre sea true
        return true;
    }

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
        if (!$this->soportaPresentacionesComerciales()) {
            return [];
        }

        // Hacemos el JOIN directamente con la tabla 'items' en lugar de la tabla vieja
        $sql = "SELECT
                    cap.id,
                    cap.id_acuerdo,
                    cap.id_presentacion AS id_item,
                    cap.precio_pactado,
                    cap.estado,
                    i.sku AS codigo_presentacion,
                    1 AS factor,
                    i.nombre AS item_nombre,
                    s.nombre AS sabor_nombre,
                    ip.nombre AS presentacion_nombre
                FROM comercial_acuerdos_precios cap
                INNER JOIN items i ON i.id = cap.id_presentacion
                LEFT JOIN item_sabores s ON s.id = i.id_sabor
                LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                WHERE cap.id_acuerdo = :id_acuerdo
                ORDER BY i.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_acuerdo' => $idAcuerdo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['producto_nombre'] = $this->construirNombrePresentacion($row);
        }

        return $rows;
    }

    public function obtenerMatrizPreciosVolumen(): array {
        if (!$this->tablaExiste('item_precios_volumen')) {
            return [];
        }

        $sql = "SELECT
                    ipv.id,
                    ipv.id_item,
                    ipv.cantidad_minima,
                    ipv.precio_unitario,
                    ipv.precio_unitario AS precio_pactado, -- <--- ALIAS PARA EL FRONTEND
                    i.sku AS codigo_presentacion,
                    1 AS factor,                           -- <--- PARA LA FUNCIÓN DE NOMBRES
                    i.nombre AS item_nombre,
                    s.nombre AS sabor_nombre,
                    ip.nombre AS presentacion_nombre
                FROM item_precios_volumen ipv
                INNER JOIN items i ON i.id = ipv.id_item
                LEFT JOIN item_sabores s ON s.id = i.id_sabor
                LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                ORDER BY i.nombre ASC, ipv.cantidad_minima ASC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['producto_nombre'] = $this->construirNombrePresentacion($row);
        }

        return $rows;
    }

    public function listarItemsParaVolumen(): array {
        $sql = "SELECT
                    i.id,
                    i.sku AS codigo_presentacion,
                    1 AS factor,
                    i.nombre AS item_nombre,
                    s.nombre AS sabor_nombre,
                    ip.nombre AS presentacion_nombre
                FROM items i
                LEFT JOIN item_sabores s ON s.id = i.id_sabor
                LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                WHERE i.estado = 1
                  AND i.deleted_at IS NULL
                  AND i.tipo_item IN ('producto_terminado', 'servicio') -- <--- NUEVA LÍNEA AGREGADA
                ORDER BY i.nombre ASC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['producto_nombre'] = $this->construirNombrePresentacion($row);
        }

        return $rows;
    }

    public function agregarPrecioVolumen(int $idItem, float $cantidadMinima, float $precioUnitario, int $idUsuario): bool {
        $sql = "INSERT INTO item_precios_volumen (id_item, cantidad_minima, precio_unitario, created_by)
                VALUES (:id_item, :cantidad_minima, :precio_unitario, :created_by)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id_item' => $idItem,
            ':cantidad_minima' => $cantidadMinima,
            ':precio_unitario' => $precioUnitario,
            ':created_by' => $idUsuario,
        ]);
    }

    public function actualizarPrecioVolumen(int $idDetalle, float $cantidadMinima, float $precioUnitario, int $idUsuario): bool {
        $sql = "UPDATE item_precios_volumen
                SET cantidad_minima = :cantidad_minima,
                    precio_unitario = :precio_unitario,
                    updated_by = :updated_by
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':cantidad_minima' => $cantidadMinima,
            ':precio_unitario' => $precioUnitario,
            ':updated_by' => $idUsuario,
            ':id' => $idDetalle,
        ]);
    }

    public function eliminarPrecioVolumen(int $idDetalle): bool {
        $sql = "DELETE FROM item_precios_volumen WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $idDetalle]);
    }

    public function listarPresentacionesDisponibles(int $idAcuerdo): array {
        if (!$this->soportaPresentacionesComerciales()) {
            return [];
        }

        $sql = "SELECT
                    i.id,
                    i.sku AS codigo_presentacion,
                    1 AS factor,
                    i.nombre AS item_nombre,
                    s.nombre AS sabor_nombre,
                    ip.nombre AS presentacion_nombre
                FROM items i
                LEFT JOIN item_sabores s ON s.id = i.id_sabor
                LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                WHERE i.estado = 1
                  AND i.deleted_at IS NULL
                  AND i.tipo_item IN ('producto_terminado', 'servicio') -- <--- NUEVA LÍNEA AGREGADA
                  AND NOT EXISTS (
                      SELECT 1
                      FROM comercial_acuerdos_precios cap
                      WHERE cap.id_acuerdo = :id_acuerdo
                        AND cap.id_presentacion = i.id
                  )
                ORDER BY i.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_acuerdo' => $idAcuerdo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['producto_nombre'] = $this->construirNombrePresentacion($row);
        }

        return $rows;
    }

    public function agregarProductoAcuerdo(int $idAcuerdo, int $idPresentacion, float $precio): bool {
        if (!$this->soportaPresentacionesComerciales()) {
            return false;
        }

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

    public function listarAcuerdosProveedor(): array {
        if (!$this->tablaExiste('comercial_acuerdos_proveedor') || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return [];
        }

        $nombreComercialExpr = $this->terceroExpr('nombre_comercial');
        $nombreExpr = $this->terceroExpr('nombre', 'nombre_completo');
        $apellidoExpr = $this->terceroExpr('apellido');

        $sql = "SELECT
                    capv.id,
                    capv.id_tercero,
                    capv.estado,
                    capv.observaciones,
                    MAX({$nombreComercialExpr}) AS nombre_comercial,
                    MAX({$nombreExpr}) AS nombre,
                    MAX({$apellidoExpr}) AS apellido,
                    COUNT(capp.id) AS total_productos
                FROM comercial_acuerdos_proveedor capv
                INNER JOIN terceros t ON t.id = capv.id_tercero
                LEFT JOIN comercial_acuerdos_proveedor_precios capp ON capp.id_acuerdo_proveedor = capv.id
                GROUP BY capv.id, capv.id_tercero, capv.estado, capv.observaciones
                ORDER BY capv.created_at DESC, capv.id DESC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['proveedor_nombre'] = $this->construirNombreCliente($row);
        }
        return $rows;
    }

    public function obtenerAcuerdoProveedor(int $idAcuerdo): ?array {
        if ($idAcuerdo <= 0 || !$this->tablaExiste('comercial_acuerdos_proveedor') || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return null;
        }

        $nombreComercialExpr = $this->terceroExpr('nombre_comercial');
        $nombreExpr = $this->terceroExpr('nombre', 'nombre_completo');
        $apellidoExpr = $this->terceroExpr('apellido');

        $sql = "SELECT
                    capv.*,
                    MAX({$nombreComercialExpr}) AS nombre_comercial,
                    MAX({$nombreExpr}) AS nombre,
                    MAX({$apellidoExpr}) AS apellido,
                    COUNT(capp.id) AS total_productos
                FROM comercial_acuerdos_proveedor capv
                INNER JOIN terceros t ON t.id = capv.id_tercero
                LEFT JOIN comercial_acuerdos_proveedor_precios capp ON capp.id_acuerdo_proveedor = capv.id
                WHERE capv.id = :id
                GROUP BY capv.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $idAcuerdo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['proveedor_nombre'] = $this->construirNombreCliente($row);
        return $row;
    }

    public function listarProveedoresDisponibles(): array {
        if (!$this->tablaExiste('comercial_acuerdos_proveedor')) {
            return [];
        }

        $nombreComercialExpr = $this->terceroExpr('nombre_comercial');
        $nombreExpr = $this->terceroExpr('nombre', 'nombre_completo');
        $apellidoExpr = $this->terceroExpr('apellido');

        $sql = "SELECT
                    t.id,
                    {$nombreComercialExpr} AS nombre_comercial,
                    {$nombreExpr} AS nombre,
                    {$apellidoExpr} AS apellido
                FROM terceros t
                WHERE t.es_proveedor = 1
                  AND t.estado = 1
                  AND t.deleted_at IS NULL
                  AND NOT EXISTS (
                      SELECT 1
                      FROM comercial_acuerdos_proveedor capv
                      WHERE capv.id_tercero = t.id
                  )
                ORDER BY TRIM(CONCAT(COALESCE({$nombreComercialExpr}, ''), ' ', COALESCE({$nombreExpr}, ''), ' ', COALESCE({$apellidoExpr}, ''))) ASC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['proveedor_nombre'] = $this->construirNombreCliente($row);
        }
        return $rows;
    }

    public function crearAcuerdoProveedor(int $idTercero, ?string $observaciones = null): int {
        if ($idTercero <= 0 || !$this->tablaExiste('comercial_acuerdos_proveedor')) {
            return 0;
        }

        $sql = "INSERT INTO comercial_acuerdos_proveedor (id_tercero, observaciones, estado)
                VALUES (:id_tercero, :observaciones, 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_tercero' => $idTercero,
            ':observaciones' => $observaciones !== null && trim($observaciones) !== '' ? trim($observaciones) : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function obtenerMatrizPreciosProveedor(int $idAcuerdo): array {
        if ($idAcuerdo <= 0 || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return [];
        }

        $sql = "SELECT
                    capp.id,
                    capp.id_item,
                    capp.id_unidad_conversion, -- NUEVO CAMPO
                    capp.precio_recomendado,
                    capp.estado,
                    i.sku AS codigo_presentacion,
                    1 AS factor,
                    i.nombre AS item_nombre,
                    s.nombre AS sabor_nombre,
                    ip.nombre AS presentacion_nombre,
                    uc.nombre AS unidad_nombre -- NUEVO CAMPO (Nombre de la unidad)
                FROM comercial_acuerdos_proveedor_precios capp
                INNER JOIN items i ON i.id = capp.id_item
                LEFT JOIN item_sabores s ON s.id = i.id_sabor
                LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                LEFT JOIN items_unidades uc ON uc.id = capp.id_unidad_conversion
                WHERE capp.id_acuerdo_proveedor = :id_acuerdo
                ORDER BY i.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_acuerdo' => $idAcuerdo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['producto_nombre'] = $this->construirNombrePresentacion($row);
        }
        return $rows;
    }

    public function listarItemsDisponiblesAcuerdoProveedor(int $idAcuerdo): array {
        if ($idAcuerdo <= 0 || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return [];
        }

        $sql = "SELECT
                    i.id,
                    i.sku AS codigo_presentacion,
                    1 AS factor,
                    i.nombre AS item_nombre,
                    s.nombre AS sabor_nombre,
                    ip.nombre AS presentacion_nombre
                FROM items i
                LEFT JOIN item_sabores s ON s.id = i.id_sabor
                LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                WHERE i.estado = 1
                  AND i.deleted_at IS NULL
                  AND i.tipo_item IN ('materia_prima', 'insumo', 'material_empaque', 'servicio')
                  AND NOT EXISTS (
                      SELECT 1
                      FROM comercial_acuerdos_proveedor_precios capp
                      WHERE capp.id_acuerdo_proveedor = :id_acuerdo
                        AND capp.id_item = i.id
                  )
                ORDER BY i.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_acuerdo' => $idAcuerdo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['producto_nombre'] = $this->construirNombrePresentacion($row);
        }
        return $rows;
    }

    // Agregamos ?int $idUnidad como parámetro
    public function agregarPrecioProveedor(int $idAcuerdo, int $idItem, ?int $idUnidad, float $precio): bool {
        if ($idAcuerdo <= 0 || $idItem <= 0 || $precio <= 0 || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return false;
        }

        // Modificamos el INSERT para incluir id_unidad_conversion
        $sql = "INSERT INTO comercial_acuerdos_proveedor_precios (id_acuerdo_proveedor, id_item, id_unidad_conversion, precio_recomendado, estado)
                VALUES (:id_acuerdo, :id_item, :id_unidad, :precio, 1)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id_acuerdo' => $idAcuerdo,
            ':id_item' => $idItem,
            ':id_unidad' => $idUnidad, // Puede ser null si es unidad base
            ':precio' => $precio,
        ]);
    }

    public function actualizarPrecioProveedor(int $idDetalle, float $precio): bool {
        if ($idDetalle <= 0 || $precio < 0 || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return false;
        }

        $sql = "UPDATE comercial_acuerdos_proveedor_precios
                SET precio_recomendado = :precio
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':precio' => $precio, ':id' => $idDetalle]);
    }

    public function actualizarUnidadProveedor(int $idDetalle, ?int $idUnidad): bool {
        if ($idDetalle <= 0 || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return false;
        }

        $sql = "UPDATE comercial_acuerdos_proveedor_precios
                SET id_unidad_conversion = :id_unidad
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id_unidad' => $idUnidad,
            ':id' => $idDetalle,
        ]);
    }

    public function eliminarPrecioProveedor(int $idDetalle): bool {
        if ($idDetalle <= 0 || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return false;
        }

        $sql = "DELETE FROM comercial_acuerdos_proveedor_precios WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $idDetalle]);
    }

    public function obtenerPrecioRecomendadoProveedor(int $idProveedor, int $idItem, ?int $idUnidad = null): ?float {
        if ($idProveedor <= 0 || $idItem <= 0 || !$this->tablaExiste('comercial_acuerdos_proveedor') || !$this->tablaExiste('comercial_acuerdos_proveedor_precios')) {
            return null;
        }

        // Buscamos coincidencia EXACTA de la unidad solicitada
        $sql = "SELECT capp.precio_recomendado
                FROM comercial_acuerdos_proveedor_precios capp
                INNER JOIN comercial_acuerdos_proveedor capv ON capv.id = capp.id_acuerdo_proveedor
                WHERE capv.id_tercero = :id_proveedor
                  AND capv.estado = 1
                  AND capp.estado = 1
                  AND capp.id_item = :id_item
                  AND (
                        (:id_unidad_1 IS NOT NULL AND capp.id_unidad_conversion = :id_unidad_2)
                        OR
                        (:id_unidad_3 IS NULL AND capp.id_unidad_conversion IS NULL)
                  )
                ORDER BY capp.id DESC
                LIMIT 1";
                
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            ':id_proveedor' => $idProveedor,
            ':id_item'      => $idItem,
            ':id_unidad_1'  => $idUnidad,
            ':id_unidad_2'  => $idUnidad,
            ':id_unidad_3'  => $idUnidad,
        ]);
        
        $valor = $stmt->fetchColumn();
        
        if ($valor === false) {
            return null;
        }
        
        return (float)$valor;
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

    /**
     * Verifica si una tabla existe en la base de datos actual.
     */
    private function tablaExiste(string $tabla): bool {
        static $cache = [];

        $tabla = trim($tabla);
        if ($tabla === '') {
            return false;
        }

        if (array_key_exists($tabla, $cache)) {
            return $cache[$tabla];
        }

        // Consultamos al esquema de la base de datos si la tabla existe
        $stmt = $this->db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tabla LIMIT 1');
        $stmt->execute([':tabla' => $tabla]);
        
        $cache[$tabla] = (bool)$stmt->fetchColumn();
        return $cache[$tabla];
    }

    public function obtenerUnidadesPorItem(int $idItem): array {
        if (!$this->tablaExiste('items_unidades')) {
            return [];
        }

        $sql = "SELECT id, nombre, factor_conversion 
                FROM items_unidades 
                WHERE id_item = :id_item AND estado = 1
                ORDER BY factor_conversion ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_item' => $idItem]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
