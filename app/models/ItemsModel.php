<?php
declare(strict_types=1);

class ItemsModel extends Modelo
{
    private const TABLA_MARCAS = 'item_marcas';
    private const TABLA_SABORES = 'item_sabores';
    private const TABLA_PRESENTACIONES = 'item_presentaciones';
    private array $cacheColumnasPorTabla = [];

    public function listar(): array
    {
        $sql = "SELECT i.id, i.sku, i.nombre, i.descripcion, i.tipo_item, i.id_rubro, i.id_categoria,
                       i.id_marca, i.id_sabor, i.id_presentacion, i.marca,
                       i.unidad_base, i.permite_decimales, i.requiere_lote, i.requiere_vencimiento,
                       i.dias_alerta_vencimiento, i.controla_stock, i.stock_minimo, i.precio_venta,
                       i.costo_referencial, i.moneda, i.impuesto_porcentaje AS impuesto, i.estado
                FROM items i
                WHERE i.deleted_at IS NULL
                ORDER BY COALESCE(i.updated_at, i.created_at) DESC, i.id DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $bloqueo = $this->obtenerBloqueoEliminacion($id);
            $row['puede_eliminar'] = $bloqueo['puede_eliminar'] ? 1 : 0;
            $row['motivo_no_eliminar'] = $bloqueo['motivo'];
        }
        unset($row);

        return $rows;
    }

    public function obtener(int $id): array
    {
        // CORREGIDO: Usamos un alias aquí también
        $sql = 'SELECT id, sku, nombre, descripcion, tipo_item, id_rubro, id_categoria, id_marca, id_sabor, id_presentacion, marca,
                       unidad_base, permite_decimales, requiere_lote, requiere_vencimiento,
                       dias_alerta_vencimiento, controla_stock, stock_minimo, precio_venta, costo_referencial,
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
                       i.dias_alerta_vencimiento, i.controla_stock, i.stock_minimo, i.precio_venta,
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
        $stmt->execute($this->filtrarParametrosSql($sql, $params));

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
        $sql = 'INSERT INTO item_rubros (nombre, descripcion, estado, created_by, updated_by)
                VALUES (:nombre, :descripcion, :estado, :created_by, :updated_by)';
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
        $sql = 'INSERT INTO categorias (nombre, descripcion, estado, created_by, updated_by)
                VALUES (:nombre, :descripcion, :estado, :created_by, :updated_by)';
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

        // CORREGIDO: Nombre real de la columna en BD (impuesto_porcentaje)
        $sql = 'INSERT INTO items (sku, nombre, descripcion, tipo_item, id_rubro, id_categoria, id_marca, id_sabor, id_presentacion, marca,
                                   unidad_base, permite_decimales, requiere_lote, requiere_vencimiento,
                                   dias_alerta_vencimiento, controla_stock, stock_minimo, precio_venta, costo_referencial,
                                   moneda, impuesto_porcentaje, estado, created_by, updated_by)
                VALUES (:sku, :nombre, :descripcion, :tipo_item, :id_rubro, :id_categoria, :id_marca, :id_sabor, :id_presentacion, :marca,
                        :unidad_base, :permite_decimales, :requiere_lote, :requiere_vencimiento,
                        :dias_alerta_vencimiento, :controla_stock, :stock_minimo, :precio_venta, :costo_referencial,
                        :moneda, :impuesto_porcentaje, :estado, :created_by, :updated_by)';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($this->filtrarParametrosSql($sql, $payload));

        return (int) $this->db()->lastInsertId();
    }

    public function actualizar(int $id, array $data, int $userId): bool
    {
        // CORREGIDO: Nombre real de la columna en BD (impuesto_porcentaje)
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
                    unidad_base = :unidad_base,
                    permite_decimales = :permite_decimales,
                    requiere_lote = :requiere_lote,
                    requiere_vencimiento = :requiere_vencimiento,
                    dias_alerta_vencimiento = :dias_alerta_vencimiento,
                    controla_stock = :controla_stock,
                    stock_minimo = :stock_minimo,
                    precio_venta = :precio_venta,
                    costo_referencial = :costo_referencial,
                    moneda = :moneda,
                    impuesto_porcentaje = :impuesto_porcentaje,
                    estado = :estado,
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        
        $payload = $this->mapPayload($data);
        $payload['id'] = $id;
        $payload['updated_by'] = $userId;

        return $this->db()->prepare($sql)->execute($this->filtrarParametrosSql($sql, $payload));
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
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
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

    public function datatable(): array
    {
        $items = $this->listar();
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
                'stock_minimo' => (float) ($item['stock_minimo'] ?? 0),
                'precio_venta' => (float) ($item['precio_venta'] ?? 0),
                'costo_referencial' => (float) ($item['costo_referencial'] ?? 0),
                'moneda' => (string) ($item['moneda'] ?? ''),
                // Aquí usamos 'impuesto' porque en el SELECT usamos "AS impuesto"
                'impuesto' => (float) ($item['impuesto'] ?? 0),
                'estado' => (int) ($item['estado'] ?? 1),
            ];
        }, $items);

        return [
            'data' => $data,
            'recordsTotal' => count($data),
            'recordsFiltered' => count($data),
        ];
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
        $sql = 'INSERT INTO item_documentos (id_item, tipo_documento, nombre_archivo, ruta_archivo, extension, created_by)
                VALUES (:id_item, :tipo_documento, :nombre_archivo, :ruta_archivo, :extension, :created_by)';

        return $this->db()->prepare($sql)->execute($docData);
    }

    public function actualizarDocumento(int $id, string $tipo): bool
    {
        $sql = 'UPDATE item_documentos SET tipo_documento = :tipo WHERE id = :id';

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
                WHERE 1 = 1";

        if ($this->tablaTieneColumna($tabla, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }

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

        $columnas = ['nombre', 'estado'];
        $valores = [':nombre', ':estado'];
        $params = [
            'nombre' => $nombre,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];

        if ($this->tablaTieneColumna($tabla, 'created_by')) {
            $columnas[] = 'created_by';
            $valores[] = ':created_by';
            $params['created_by'] = $userId;
        }

        if ($this->tablaTieneColumna($tabla, 'updated_by')) {
            $columnas[] = 'updated_by';
            $valores[] = ':updated_by';
            $params['updated_by'] = $userId;
        }

        if ($this->tablaTieneColumna($tabla, 'created_at')) {
            $columnas[] = 'created_at';
            $valores[] = 'NOW()';
        }

        if ($this->tablaTieneColumna($tabla, 'updated_at')) {
            $columnas[] = 'updated_at';
            $valores[] = 'NOW()';
        }

        $sql = "INSERT INTO {$tabla} (" . implode(', ', $columnas) . ")
                VALUES (" . implode(', ', $valores) . ")";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($this->filtrarParametrosSql($sql, $params));

        return (int) $this->db()->lastInsertId();
    }

    private function actualizarAtributo(string $tabla, int $id, array $data, int $userId): bool
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($this->atributoDuplicado($tabla, $nombre, $id)) {
            throw new RuntimeException('Ya existe un registro con ese nombre.');
        }

        $set = [
            'nombre = :nombre',
            'estado = :estado',
        ];

        if ($this->tablaTieneColumna($tabla, 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }

        if ($this->tablaTieneColumna($tabla, 'updated_by')) {
            $set[] = 'updated_by = :updated_by';
        }

        $sql = "UPDATE {$tabla}
                SET " . implode(",\n                    ", $set) . "
                WHERE id = :id";

        if ($this->tablaTieneColumna($tabla, 'deleted_at')) {
            $sql .= "
                  AND deleted_at IS NULL";
        }

        $params = [
            'id' => $id,
            'nombre' => $nombre,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'updated_by' => $userId,
        ];

        if (!$this->tablaTieneColumna($tabla, 'updated_by')) {
            unset($params['updated_by']);
        }

        return $this->db()->prepare($sql)->execute($this->filtrarParametrosSql($sql, $params));
    }

    private function eliminarAtributo(string $tabla, int $id, int $userId): bool
    {
        if (!$this->tablaTieneColumna($tabla, 'deleted_at')) {
            $sql = "DELETE FROM {$tabla} WHERE id = :id";

            return $this->db()->prepare($sql)->execute(['id' => $id]);
        }

        $set = ['deleted_at = NOW()'];
        $params = ['id' => $id];

        if ($this->tablaTieneColumna($tabla, 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }
        if ($this->tablaTieneColumna($tabla, 'deleted_by')) {
            $set[] = 'deleted_by = :deleted_by';
            $params['deleted_by'] = $userId;
        }
        if ($this->tablaTieneColumna($tabla, 'updated_by')) {
            $set[] = 'updated_by = :updated_by';
            $params['updated_by'] = $userId;
        }

        $sql = "UPDATE {$tabla}
                SET " . implode(",\n                    ", $set) . "
                WHERE id = :id
                  AND deleted_at IS NULL";

        return $this->db()->prepare($sql)->execute($this->filtrarParametrosSql($sql, $params));
    }

    private function atributoDuplicado(string $tabla, string $nombre, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM {$tabla} WHERE LOWER(nombre) = LOWER(:nombre)";

        if ($this->tablaTieneColumna($tabla, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $params = ['nombre' => $nombre];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($this->filtrarParametrosSql($sql, $params));

        return (bool) $stmt->fetchColumn();
    }

    private function tablaTieneColumna(string $tabla, string $columna): bool
    {
        if (!isset($this->cacheColumnasPorTabla[$tabla])) {
            $stmt = $this->db()->prepare('SHOW COLUMNS FROM ' . $tabla);
            $stmt->execute();
            $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->cacheColumnasPorTabla[$tabla] = array_map('strtolower', $columnas ?: []);
        }

        return in_array(strtolower($columna), $this->cacheColumnasPorTabla[$tabla], true);
    }

    private function contarReferenciasActivas(string $tabla, string $foreignKey, int $id): int
    {
        if (!$this->tablaTieneColumna($tabla, $foreignKey)) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM {$tabla} WHERE {$foreignKey} = :id";
        if ($this->tablaTieneColumna($tabla, 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    private function mapPayload(array $data): array
    {
        return [
            'sku' => trim((string) ($data['sku'] ?? '')),
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'tipo_item' => trim((string) ($data['tipo_item'] ?? '')),
            'id_rubro' => (isset($data['id_rubro']) && $data['id_rubro'] !== '') ? (int) $data['id_rubro'] : null,
            'id_categoria' => (isset($data['id_categoria']) && $data['id_categoria'] !== '') ? $data['id_categoria'] : null,
            'id_marca' => (isset($data['id_marca']) && $data['id_marca'] !== '') ? (int) $data['id_marca'] : null,
            'id_sabor' => (isset($data['id_sabor']) && $data['id_sabor'] !== '') ? (int) $data['id_sabor'] : null,
            'id_presentacion' => (isset($data['id_presentacion']) && $data['id_presentacion'] !== '') ? (int) $data['id_presentacion'] : null,
            'marca' => trim((string) ($data['marca'] ?? '')),
            'unidad_base' => trim((string) ($data['unidad_base'] ?? 'UND')),
            'permite_decimales' => !empty($data['permite_decimales']) ? 1 : 0,
            'requiere_lote' => !empty($data['requiere_lote']) ? 1 : 0,
            'requiere_vencimiento' => !empty($data['requiere_vencimiento']) ? 1 : 0,
            'dias_alerta_vencimiento' => !empty($data['requiere_vencimiento'])
                ? ((isset($data['dias_alerta_vencimiento']) && $data['dias_alerta_vencimiento'] !== '') ? (int) $data['dias_alerta_vencimiento'] : 0)
                : null,
            'controla_stock' => !empty($data['controla_stock']) ? 1 : 0,
            'stock_minimo' => (float) ($data['stock_minimo'] ?? 0),
            'precio_venta' => (float) ($data['precio_venta'] ?? 0),
            'costo_referencial' => (float) ($data['costo_referencial'] ?? 0),
            'moneda' => trim((string) ($data['moneda'] ?? 'PEN')),
            // CORREGIDO: Mapeamos el input 'impuesto' a la clave de BD 'impuesto_porcentaje'
            'impuesto_porcentaje' => (float) ($data['impuesto'] ?? 0),
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];
    }


    private function filtrarParametrosSql(string $sql, array $params): array
    {
        if ($params === []) {
            return [];
        }

        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $coincidencias);
        $placeholders = array_flip($coincidencias[1] ?? []);

        if ($placeholders === []) {
            return [];
        }

        return array_intersect_key($params, $placeholders);
    }

    private function generarSku(): string
    {
        do {
            $sku = 'SKU-' . strtoupper(bin2hex(random_bytes(4)));
        } while ($this->skuExiste($sku));

        return $sku;
    }
}
