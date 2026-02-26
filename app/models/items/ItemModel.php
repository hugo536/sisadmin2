<?php
declare(strict_types=1);

class ItemModel extends Modelo
{
    private const TABLA_MARCAS = 'item_marcas';
    private const TABLA_SABORES = 'item_sabores';
    private const TABLA_PRESENTACIONES = 'item_presentaciones';

    /**
     * Helper privado para construir la cláusula WHERE y los parámetros basados en los filtros
     */
    private function construirFiltrosBusqueda(array $filtros): array
    {
        $where = ['i.deleted_at IS NULL'];
        $params = [];

        // Búsqueda general (por SKU, Nombre o Descripción)
        $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
        if ($busqueda !== '') {
            $where[] = '(i.sku LIKE :q OR i.nombre LIKE :q OR i.descripcion LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        // Filtro por Categoría
        $categoria = trim((string) ($filtros['categoria'] ?? ''));
        if ($categoria !== '') {
            $where[] = 'i.id_categoria = :categoria';
            $params['categoria'] = $categoria;
        }

        // Filtro por Tipo de Ítem
        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $where[] = 'i.tipo_item = :tipo';
            $params['tipo'] = $tipo;
        }

        // Filtro por Estado
        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $where[] = 'i.estado = :estado';
            $params['estado'] = $estado;
        }

        return [
            'where' => implode(' AND ', $where),
            'params' => $params
        ];
    }

    /**
     * Cuenta el total de registros aplicando los filtros (Server-Side Pagination)
     */
    public function contar(array $filtros = []): int
    {
        $filtrosData = $this->construirFiltrosBusqueda($filtros);
        
        $sql = "SELECT COUNT(*) FROM items i WHERE " . $filtrosData['where'];
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($filtrosData['params']);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista los registros paginados y filtrados (Server-Side Pagination)
     */
    public function listar(int $offset = 0, int $limite = 20, array $filtros = []): array
{
    $filtrosData = $this->construirFiltrosBusqueda($filtros);
    $whereSql = $filtrosData['where'];
    $params = $filtrosData['params'];

    // Se asume que las tablas de recetas existen según el diseño del sistema
    $bomPendienteSql = "CASE
                           WHEN i.requiere_formula_bom = 1 AND NOT EXISTS (
                               SELECT 1
                               FROM produccion_recetas r
                               INNER JOIN produccion_recetas_detalle d ON d.id_receta = r.id AND d.deleted_at IS NULL
                               WHERE r.id_producto = i.id
                                 AND r.deleted_at IS NULL
                           ) THEN 1
                           ELSE 0
                       END AS bom_pendiente";

    // NUEVO: Subconsulta para traer la primera imagen asociada al ítem
    $imagenSql = "(
        SELECT ruta_archivo 
        FROM item_documentos 
        WHERE id_item = i.id 
          AND extension IN ('jpg', 'jpeg', 'png', 'webp', 'gif') 
          AND estado = 1 
        ORDER BY created_at ASC 
        LIMIT 1
    ) AS imagen_principal";

    $sql = "SELECT i.id, i.sku, i.nombre, i.descripcion, i.tipo_item, i.id_rubro, i.id_categoria,
                   i.id_marca, i.id_sabor, i.id_presentacion, i.marca,
                   i.unidad_base, i.permite_decimales, i.requiere_lote, i.requiere_vencimiento,
                   i.dias_alerta_vencimiento, i.controla_stock, i.requiere_formula_bom,
                   i.requiere_factor_conversion, i.es_envase_retornable, i.stock_minimo, i.precio_venta,
                   i.costo_referencial, i.moneda, i.impuesto_porcentaje AS impuesto, i.estado,
                   {$bomPendienteSql},
                   {$imagenSql} /* <--- NUEVA LÍNEA */
            FROM items i
            WHERE {$whereSql}
            ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC
            LIMIT " . (int)$limite . " OFFSET " . (int)$offset;

    $stmt = $this->db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agregamos la lógica de bloqueo de eliminación a cada fila obtenida
    foreach ($rows as &$row) {
        $id = (int) ($row['id'] ?? 0);
        $bloqueo = $this->obtenerBloqueoEliminacion($id);
        $row['puede_eliminar'] = $bloqueo['puede_eliminar'] ? 1 : 0;
        $row['motivo_no_eliminar'] = $bloqueo['motivo'];
    }
    unset($row);

    return $rows;
}

    /**
     * Genera la respuesta para el endpoint AJAX del datatable
     */
    public function datatable(int $pagina = 1, int $limite = 20, array $filtros = []): array
    {
        // Calcular OFFSET
        $offset = ($pagina - 1) * $limite;
        if ($offset < 0) $offset = 0;

        // Obtener datos paginados y totales
        $items = $this->listar($offset, $limite, $filtros);
        $totalFiltrado = $this->contar($filtros);
        
        // Opcional: Total absoluto sin filtros (a veces requerido por librerías como DataTables)
        // $totalAbsoluto = $this->contar([]); 

        $data = array_map(static function (array $item): array {
            return [
                'id' => (int) $item['id'],
                'sku' => (string) $item['sku'],
                'nombre' => (string) $item['nombre'],
                'descripcion' => (string) ($item['descripcion'] ?? ''),
                'tipo_item' => (string) $item['tipo_item'],
                'id_rubro' => $item['id_rubro'] !== null ? (int) $item['id_rubro'] : null,
                'id_categoria' => $item['id_categoria'] !== null ? (int) $item['id_categoria'] : null,
                'id_marca' => $item['id_marca'] !== null ? (int) $item['id_marca'] : null,
                'id_sabor' => $item['id_sabor'] !== null ? (int) $item['id_sabor'] : null,
                'id_presentacion' => $item['id_presentacion'] !== null ? (int) $item['id_presentacion'] : null,
                'marca' => (string) ($item['marca'] ?? ''),
                'unidad_base' => (string) ($item['unidad_base'] ?? ''),
                'permite_decimales' => (int) ($item['permite_decimales'] ?? 0),
                'requiere_lote' => (int) ($item['requiere_lote'] ?? 0),
                'requiere_vencimiento' => (int) ($item['requiere_vencimiento'] ?? 0),
                'dias_alerta_vencimiento' => $item['dias_alerta_vencimiento'] !== null ? (int) $item['dias_alerta_vencimiento'] : null,
                'controla_stock' => (int) ($item['controla_stock'] ?? 0),
                'requiere_formula_bom' => (int) ($item['requiere_formula_bom'] ?? 0),
                'requiere_factor_conversion' => (int) ($item['requiere_factor_conversion'] ?? 0),
                'es_envase_retornable' => (int) ($item['es_envase_retornable'] ?? 0),
                'stock_minimo' => (float) ($item['stock_minimo'] ?? 0),
                'precio_venta' => (float) ($item['precio_venta'] ?? 0),
                'costo_referencial' => (float) ($item['costo_referencial'] ?? 0),
                'moneda' => (string) ($item['moneda'] ?? ''),
                'impuesto' => (float) ($item['impuesto'] ?? 0),
                'estado' => (int) ($item['estado'] ?? 1),
                'bom_pendiente' => (int) ($item['bom_pendiente'] ?? 0), // Necesario para la UI
                'puede_eliminar' => (int) ($item['puede_eliminar'] ?? 1), // Necesario para la UI
                'motivo_no_eliminar' => (string) ($item['motivo_no_eliminar'] ?? ''), // Necesario para la UI
            ];
        }, $items);

        return [
            'data' => $data,
            'recordsFiltered' => $totalFiltrado,
            'recordsTotal' => $totalFiltrado, // Por ahora enviamos el mismo, ajusta si usas DataTables puro
            'paginaActual' => $pagina,
            'limite' => $limite
        ];
    }

    public function obtener(int $id): array
    {
        $sql = 'SELECT id, sku, nombre, descripcion, tipo_item, id_rubro, id_categoria, id_marca, id_sabor, id_presentacion, marca,
                       unidad_base, permite_decimales, requiere_lote, requiere_vencimiento,
                       dias_alerta_vencimiento, controla_stock, requiere_formula_bom, requiere_factor_conversion,
                       es_envase_retornable, stock_minimo, precio_venta, costo_referencial,
                       moneda, impuesto_porcentaje AS impuesto, estado
                FROM items
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function obtenerPerfil(int $id): array
    {
        $sql = 'SELECT i.id, i.sku, i.nombre, i.descripcion, i.tipo_item, i.id_rubro, i.id_categoria,
                       r.nombre AS rubro_nombre,
                       c.nombre AS categoria_nombre,
                       i.id_marca, i.id_sabor, i.id_presentacion,
                       i.marca, i.unidad_base, i.permite_decimales, i.requiere_lote, i.requiere_vencimiento,
                       i.dias_alerta_vencimiento, i.controla_stock, i.requiere_formula_bom,
                       i.requiere_factor_conversion, i.es_envase_retornable, i.stock_minimo, i.precio_venta,
                       i.costo_referencial, i.moneda, i.impuesto_porcentaje AS impuesto, i.estado,
                       i.created_at, i.updated_at
                FROM items i
                LEFT JOIN item_rubros r ON r.id = i.id_rubro
                LEFT JOIN categorias c ON c.id = i.id_categoria
                WHERE i.id = :id
                  AND i.deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function skuExiste(string $sku, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM items WHERE sku = :sku AND deleted_at IS NULL';
        $params = ['sku' => $sku];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function listarCategoriasActivas(): array
    {
        $sql = 'SELECT id, nombre
                FROM categorias
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarRubrosActivos(): array
    {
        $sql = 'SELECT id, nombre
                FROM item_rubros
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarRubros(): array
    {
        $sql = 'SELECT id, nombre, descripcion, estado
                FROM item_rubros
                WHERE deleted_at IS NULL
                ORDER BY nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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

        $sql = 'SELECT id, id_item, nombre, codigo_unidad, factor_conversion, peso_kg, estado
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
        $sql = 'INSERT INTO items_unidades (id_item, nombre, codigo_unidad, factor_conversion, peso_kg, estado, created_by, updated_by, created_at, updated_at)
                VALUES (:id_item, :nombre, :codigo_unidad, :factor_conversion, :peso_kg, :estado, :created_by, :updated_by, NOW(), NOW())';
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_item' => (int) $data['id_item'],
            'nombre' => trim((string) $data['nombre']),
            'codigo_unidad' => trim((string) ($data['codigo_unidad'] ?? '')) !== '' ? trim((string) $data['codigo_unidad']) : null,
            'factor_conversion' => (float) ($data['factor_conversion'] ?? 1),
            'peso_kg' => (float) ($data['peso_kg'] ?? 0),
            'estado' => (int) ($data['estado'] ?? 1),
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
        $sql = 'UPDATE items_unidades
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    estado = 0
                WHERE id = :id
                  AND id_item = :id_item
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'deleted_by' => $userId,
            'updated_by' => $userId,
            'id' => $id,
            'id_item' => $idItem
        ]);
    }

    public function rubroExisteActivo(int $idRubro): bool
    {
        $sql = 'SELECT 1
                FROM item_rubros
                WHERE id = :id
                  AND estado = 1
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idRubro]);

        return (bool) $stmt->fetchColumn();
    }

    public function crearRubro(array $data, int $userId): int
    {
        $sql = 'INSERT INTO item_rubros (nombre, descripcion, estado, created_by, updated_by, created_at, updated_at)
                VALUES (:nombre, :descripcion, :estado, :created_by, :updated_by, NOW(), NOW())';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')) !== '' ? trim((string) $data['descripcion']) : null,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizarRubro(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE item_rubros
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')) !== '' ? trim((string) $data['descripcion']) : null,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'updated_by' => $userId,
        ]);
    }

    public function eliminarRubro(int $id, int $userId): bool
    {
        $sql = 'UPDATE item_rubros
                SET estado = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'deleted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function listarCategorias(): array
    {
        $sql = 'SELECT id, nombre, descripcion, estado
                FROM categorias
                WHERE deleted_at IS NULL
                ORDER BY nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categoriaExisteActiva(int $idCategoria): bool
    {
        $sql = 'SELECT 1
                FROM categorias
                WHERE id = :id
                  AND estado = 1
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idCategoria]);

        return (bool) $stmt->fetchColumn();
    }

    public function crearCategoria(array $data, int $userId): int
    {
        $sql = 'INSERT INTO categorias (nombre, descripcion, estado, created_by, updated_by, fecha_creacion)
                VALUES (:nombre, :descripcion, :estado, :created_by, :updated_by, NOW())';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')) !== '' ? trim((string) $data['descripcion']) : null,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizarCategoria(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE categorias
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')) !== '' ? trim((string) $data['descripcion']) : null,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'updated_by' => $userId,
        ]);
    }

    public function eliminarCategoria(int $id, int $userId): bool
    {
        $sql = 'UPDATE categorias
                SET estado = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'deleted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function crear(array $data, int $userId): int
    {
        $payload = $this->mapPayload($data);

        if ($payload['sku'] === '') {
            $payload['sku'] = $this->generarSku();
        }

        $payload['created_by'] = $userId;
        $payload['updated_by'] = $userId;

        $sql = 'INSERT INTO items (sku, nombre, descripcion, tipo_item, id_rubro, id_categoria, id_marca, id_sabor, id_presentacion, marca,
                                   unidad_base, permite_decimales, requiere_lote, requiere_vencimiento,
                                   dias_alerta_vencimiento, controla_stock, requiere_formula_bom, requiere_factor_conversion,
                                   es_envase_retornable, stock_minimo, precio_venta, costo_referencial,
                                   moneda, impuesto_porcentaje, estado, created_by, updated_by, created_at, updated_at)
                VALUES (:sku, :nombre, :descripcion, :tipo_item, :id_rubro, :id_categoria, :id_marca, :id_sabor, :id_presentacion, :marca,
                        :unidad_base, :permite_decimales, :requiere_lote, :requiere_vencimiento,
                        :dias_alerta_vencimiento, :controla_stock, :requiere_formula_bom, :requiere_factor_conversion,
                        :es_envase_retornable, :stock_minimo, :precio_venta, :costo_referencial,
                        :moneda, :impuesto_porcentaje, :estado, :created_by, :updated_by, NOW(), NOW())';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($payload);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizar(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE items
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    tipo_item = :tipo_item,
                    id_rubro = :id_rubro,
                    id_categoria = :id_categoria,
                    id_marca = :id_marca,
                    id_sabor = :id_sabor,
                    id_presentacion = :id_presentacion,
                    marca = :marca,
                    /* unidad_base eliminada por seguridad */
                    permite_decimales = :permite_decimales,
                    requiere_lote = :requiere_lote,
                    requiere_vencimiento = :requiere_vencimiento,
                    dias_alerta_vencimiento = :dias_alerta_vencimiento,
                    controla_stock = :controla_stock,
                    requiere_formula_bom = :requiere_formula_bom,
                    requiere_factor_conversion = :requiere_factor_conversion,
                    es_envase_retornable = :es_envase_retornable,
                    stock_minimo = :stock_minimo,
                    precio_venta = :precio_venta,
                    costo_referencial = :costo_referencial,
                    moneda = :moneda,
                    impuesto_porcentaje = :impuesto_porcentaje,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        
        $payload = $this->mapPayload($data);
        unset($payload['sku'], $payload['unidad_base'], $payload['created_by']);
        
        $payload['id'] = $id;
        $payload['updated_by'] = $userId;

        return $this->db()->prepare($sql)->execute($payload);
    }

    public function eliminar(int $id, int $userId): bool
    {
        $bloqueo = $this->obtenerBloqueoEliminacion($id);
        if (!$bloqueo['puede_eliminar']) {
            throw new RuntimeException($bloqueo['motivo']);
        }

        $sql = 'UPDATE items
                SET estado = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'deleted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function obtenerBloqueoEliminacion(int $id): array
    {
        $referencias = [];

        $ventas = $this->contarReferenciasActivas('ventas_documentos_detalle', 'id_item', $id);
        if ($ventas > 0) {
            $referencias[] = $ventas . ' detalle(s) de venta';
        }

        $compras = $this->contarReferenciasActivas('compras_ordenes_detalle', 'id_item', $id);
        if ($compras > 0) {
            $referencias[] = $compras . ' detalle(s) de compra';
        }

        $movimientos = $this->contarReferenciasActivas('inventario_movimientos', 'id_item', $id);
        if ($movimientos > 0) {
            $referencias[] = $movimientos . ' movimiento(s) de inventario';
        }

        if ($referencias === []) {
            return ['puede_eliminar' => true, 'motivo' => ''];
        }

        return [
            'puede_eliminar' => false,
            'motivo' => 'No se puede eliminar: el ítem tiene movimientos activos (' . implode(', ', $referencias) . '). Puedes desactivarlo.',
        ];
    }

    public function actualizarEstado(int $id, int $estado, int $userId): bool
    {
        $sql = 'UPDATE items
                SET estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'estado' => $estado === 1 ? 1 : 0,
            'updated_by' => $userId,
            'id' => $id,
        ]);
    }

    public function listarMarcas(bool $soloActivos = false): array
    {
        return $this->listarAtributos(self::TABLA_MARCAS, $soloActivos);
    }

    public function listarSabores(bool $soloActivos = false): array
    {
        return $this->listarAtributos(self::TABLA_SABORES, $soloActivos);
    }

    public function listarPresentaciones(bool $soloActivos = false): array
    {
        return $this->listarAtributos(self::TABLA_PRESENTACIONES, $soloActivos);
    }

    public function listarDocumentos(int $itemId): array
    {
        $sql = 'SELECT * FROM item_documentos WHERE id_item = :id_item AND estado = 1 ORDER BY created_at DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $itemId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarDocumento(array $docData): bool
    {
        $sql = 'INSERT INTO item_documentos (id_item, tipo_documento, nombre_archivo, ruta_archivo, extension, created_by, created_at, updated_at)
                VALUES (:id_item, :tipo_documento, :nombre_archivo, :ruta_archivo, :extension, :created_by, NOW(), NOW())';

        return $this->db()->prepare($sql)->execute($docData);
    }

    public function actualizarDocumento(int $id, string $tipo): bool
    {
        $sql = 'UPDATE item_documentos SET tipo_documento = :tipo, updated_at = NOW() WHERE id = :id';

        return $this->db()->prepare($sql)->execute([
            'tipo' => $tipo,
            'id' => $id,
        ]);
    }

    public function eliminarDocumento(int $docId): bool
    {
        $stmt = $this->db()->prepare('SELECT ruta_archivo FROM item_documentos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($row) && !empty($row['ruta_archivo'])) {
            $rutaPublica = ltrim((string) $row['ruta_archivo'], '/');
            $rutaFisica = BASE_PATH . '/public/' . $rutaPublica;
            if (is_file($rutaFisica)) {
                @unlink($rutaFisica);
            }
        }

        return $this->db()->prepare('DELETE FROM item_documentos WHERE id = :id')->execute(['id' => $docId]);
    }

    public function crearMarca(array $data, int $userId): int
    {
        return $this->crearAtributo(self::TABLA_MARCAS, $data, $userId);
    }

    public function crearSabor(array $data, int $userId): int
    {
        return $this->crearAtributo(self::TABLA_SABORES, $data, $userId);
    }

    public function crearPresentacion(array $data, int $userId): int
    {
        return $this->crearAtributo(self::TABLA_PRESENTACIONES, $data, $userId);
    }

    public function actualizarMarca(int $id, array $data, int $userId): bool
    {
        return $this->actualizarAtributo(self::TABLA_MARCAS, $id, $data, $userId);
    }

    public function actualizarSabor(int $id, array $data, int $userId): bool
    {
        return $this->actualizarAtributo(self::TABLA_SABORES, $id, $data, $userId);
    }

    public function actualizarPresentacion(int $id, array $data, int $userId): bool
    {
        return $this->actualizarAtributo(self::TABLA_PRESENTACIONES, $id, $data, $userId);
    }

    public function eliminarMarca(int $id, int $userId): bool
    {
        return $this->eliminarAtributo(self::TABLA_MARCAS, $id, $userId);
    }

    public function eliminarSabor(int $id, int $userId): bool
    {
        return $this->eliminarAtributo(self::TABLA_SABORES, $id, $userId);
    }

    public function eliminarPresentacion(int $id, int $userId): bool
    {
        return $this->eliminarAtributo(self::TABLA_PRESENTACIONES, $id, $userId);
    }

    private function listarAtributos(string $tabla, bool $soloActivos = false): array
    {
        $sql = "SELECT id, nombre, estado
                FROM {$tabla}
                WHERE deleted_at IS NULL";

        if ($soloActivos) {
            $sql .= ' AND estado = 1';
        }

        $sql .= ' ORDER BY nombre ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function crearAtributo(string $tabla, array $data, int $userId): int
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($this->atributoDuplicado($tabla, $nombre)) {
            throw new RuntimeException('Ya existe un registro con ese nombre.');
        }

        $sql = "INSERT INTO {$tabla} (nombre, estado, updated_by, created_at, updated_at)
                VALUES (:nombre, :estado, :updated_by, NOW(), NOW())";
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'updated_by' => $userId
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function actualizarAtributo(string $tabla, int $id, array $data, int $userId): bool
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($this->atributoDuplicado($tabla, $nombre, $id)) {
            throw new RuntimeException('Ya existe un registro con ese nombre.');
        }

        $sql = "UPDATE {$tabla}
                SET nombre = :nombre,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL";

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'nombre' => $nombre,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'updated_by' => $userId,
        ]);
    }

    private function eliminarAtributo(string $tabla, int $id, int $userId): bool
    {
        $sql = "UPDATE {$tabla}
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    estado = 0
                WHERE id = :id
                  AND deleted_at IS NULL";

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'deleted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function atributoDuplicado(string $tabla, string $nombre, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$tabla} WHERE LOWER(nombre) = LOWER(:nombre) AND deleted_at IS NULL";
        $params = ['nombre' => $nombre];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function contarReferenciasActivas(string $tabla, string $foreignKey, int $id): int
    {
        $tieneDeletedAt = in_array($tabla, ['compras_ordenes_detalle', 'inventario_movimientos', 'ventas_documentos_detalle']);
        
        $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$foreignKey} = :id";
        if ($tieneDeletedAt) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function sincronizarDependenciasConfiguracion(int $idItem, array $data, int $userId): void
    {
        if ($idItem <= 0) {
            return;
        }

        $requiereFormula = (isset($data['requiere_formula_bom']) && in_array($data['requiere_formula_bom'], [1, '1', 'on', true], true));
        $requiereFactor = (isset($data['requiere_factor_conversion']) && in_array($data['requiere_factor_conversion'], [1, '1', 'on', true], true));

        if ($requiereFormula) {
            $this->asegurarRecetaBase($idItem, $userId);
        }

        if ($requiereFactor) {
            $this->asegurarPresentacionBase($idItem, $userId);
        }
    }

    private function asegurarRecetaBase(int $idItem, int $userId): void
    {
        $sqlExiste = 'SELECT id FROM produccion_recetas WHERE id_producto = :id_item AND deleted_at IS NULL ORDER BY id DESC LIMIT 1';
        $stmtExiste = $this->db()->prepare($sqlExiste);
        $stmtExiste->execute(['id_item' => $idItem]);
        
        if ($stmtExiste->fetchColumn()) {
            return;
        }

        $item = $this->obtener($idItem);
        $codigoBase = 'REC-ITEM-' . str_pad((string) $idItem, 6, '0', STR_PAD_LEFT);

        $sqlInsert = 'INSERT INTO produccion_recetas 
                        (id_producto, codigo, version, descripcion, estado, rendimiento_base, unidad_rendimiento, costo_teorico_unitario, created_by, updated_by, created_at, updated_at) 
                      VALUES 
                        (:id_producto, :codigo, :version, :descripcion, :estado, :rendimiento_base, :unidad_rendimiento, :costo_teorico_unitario, :created_by, :updated_by, NOW(), NOW())';
        
        $this->db()->prepare($sqlInsert)->execute([
            'id_producto' => $idItem,
            'codigo' => $codigoBase,
            'version' => 1,
            'descripcion' => 'Fórmula pendiente de configuración.',
            'estado' => 1,
            'rendimiento_base' => 1,
            'unidad_rendimiento' => trim((string) ($item['unidad_base'] ?? 'UND')) ?: 'UND',
            'costo_teorico_unitario' => 0,
            'created_by' => $userId > 0 ? $userId : 1,
            'updated_by' => $userId > 0 ? $userId : 1
        ]);
    }

    private function asegurarPresentacionBase(int $idItem, int $userId): void
    {
        try {
            $sqlExiste = 'SELECT id FROM precios_presentaciones WHERE id_item = :id_item AND deleted_at IS NULL ORDER BY id DESC LIMIT 1';
            $stmtExiste = $this->db()->prepare($sqlExiste);
            $stmtExiste->execute(['id_item' => $idItem]);
            
            if ($stmtExiste->fetchColumn()) {
                return;
            }

            $item = $this->obtener($idItem);
            $sku = trim((string) ($item['sku'] ?? 'ITM-' . $idItem));
            $nombre = trim((string) ($item['nombre'] ?? ('Item ' . $idItem)));

            $sqlInsert = 'INSERT INTO precios_presentaciones 
                            (id_item, nombre_manual, nota_pack, codigo_presentacion, factor, es_mixto, precio_x_menor, stock_minimo, estado, created_by, updated_by) 
                          VALUES 
                            (:id_item, :nombre_manual, :nota_pack, :codigo_presentacion, :factor, :es_mixto, :precio_x_menor, :stock_minimo, :estado, :created_by, :updated_by)';
            
            $this->db()->prepare($sqlInsert)->execute([
                'id_item' => $idItem,
                'nombre_manual' => $nombre,
                'nota_pack' => 'Falta configurar factor de conversión.',
                'codigo_presentacion' => $sku . '-FC',
                'factor' => 1,
                'es_mixto' => 0,
                'precio_x_menor' => 0,
                'stock_minimo' => 0,
                'estado' => 1,
                'created_by' => $userId > 0 ? $userId : 1,
                'updated_by' => $userId > 0 ? $userId : 1
            ]);
        } catch (Throwable $e) {
        }
    }

    private function mapPayload(array $data): array
    {
        $parseBool = fn($val) => (isset($val) && in_array($val, [1, '1', 'on', true], true)) ? 1 : 0;

        return [
            'sku' => trim((string) ($data['sku'] ?? '')),
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'tipo_item' => trim((string) ($data['tipo_item'] ?? '')),
            'id_rubro' => (isset($data['id_rubro']) && $data['id_rubro'] !== '') ? (int) $data['id_rubro'] : null,
            'id_categoria' => (isset($data['id_categoria']) && $data['id_categoria'] !== '') ? (int) $data['id_categoria'] : null,
            'id_marca' => (isset($data['id_marca']) && $data['id_marca'] !== '') ? (int) $data['id_marca'] : null,
            'id_sabor' => (isset($data['id_sabor']) && $data['id_sabor'] !== '') ? (int) $data['id_sabor'] : null,
            'id_presentacion' => (isset($data['id_presentacion']) && $data['id_presentacion'] !== '') ? (int) $data['id_presentacion'] : null,
            'marca' => trim((string) ($data['marca'] ?? '')),
            'unidad_base' => trim((string) ($data['unidad_base'] ?? 'UND')),
            'permite_decimales' => $parseBool($data['permite_decimales'] ?? 0),
            'requiere_lote' => $parseBool($data['requiere_lote'] ?? 0),
            'requiere_vencimiento' => $parseBool($data['requiere_vencimiento'] ?? 0),
            'dias_alerta_vencimiento' => $parseBool($data['requiere_vencimiento'] ?? 0)
                ? ((isset($data['dias_alerta_vencimiento']) && $data['dias_alerta_vencimiento'] !== '') ? (int) $data['dias_alerta_vencimiento'] : 0)
                : null,
            'controla_stock' => $parseBool($data['controla_stock'] ?? 0),
            'requiere_formula_bom' => $parseBool($data['requiere_formula_bom'] ?? 0),
            'requiere_factor_conversion' => $parseBool($data['requiere_factor_conversion'] ?? 0),
            'es_envase_retornable' => $parseBool($data['es_envase_retornable'] ?? 0),
            'stock_minimo' => (float) ($data['stock_minimo'] ?? 0),
            'precio_venta' => (float) ($data['precio_venta'] ?? 0),
            'costo_referencial' => (float) ($data['costo_referencial'] ?? 0),
            'moneda' => trim((string) ($data['moneda'] ?? 'PEN')),
            'impuesto_porcentaje' => (float) ($data['impuesto'] ?? 0),
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];
    }

    private function generarSku(): string
    {
        do {
            $sku = 'SKU-' . strtoupper(bin2hex(random_bytes(4)));
        } while ($this->skuExiste($sku));

        return $sku;
    }
}