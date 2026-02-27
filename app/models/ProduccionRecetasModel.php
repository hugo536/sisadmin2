<?php
declare(strict_types=1);

class ProduccionRecetasModel extends Modelo
{
    public function listarRecetas(): array
    {
        $sql = 'SELECT r.id, r.codigo, r.version, r.descripcion, r.estado, r.created_at,
                       r.rendimiento_base, r.unidad_rendimiento,
                       r.costo_teorico_unitario AS costo_teorico,
                       i.id AS id_producto, i.sku AS producto_sku, i.nombre AS producto_nombre,
                       (
                           SELECT COUNT(*)
                           FROM produccion_recetas_detalle d
                           WHERE d.id_receta = r.id
                             AND d.deleted_at IS NULL
                       ) AS total_insumos
                FROM items i
                INNER JOIN produccion_recetas r ON r.id = (
                    SELECT pr.id 
                    FROM produccion_recetas pr
                    WHERE pr.id_producto = i.id 
                      AND pr.deleted_at IS NULL
                    ORDER BY pr.estado DESC, pr.version DESC 
                    LIMIT 1
                )
                WHERE i.deleted_at IS NULL
                ORDER BY i.nombre ASC';

        $stmt = $this->db()->query($sql);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Generamos la variable "sin_receta" en memoria (CPU), aliviando a la Base de Datos
        foreach ($resultados as &$fila) {
            $fila['sin_receta'] = ((int)$fila['total_insumos'] === 0) ? 1 : 0;
        }

        return $resultados;
    }

    public function listarRecetasActivas(): array
    {
        $sql = 'SELECT r.id, r.codigo, r.version, i.nombre AS producto_nombre
                FROM produccion_recetas r
                INNER JOIN items i ON i.id = r.id_producto
                WHERE r.estado = 1
                  AND r.deleted_at IS NULL
                ORDER BY i.nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // NUEVA FUNCIÓN OPTIMIZADA: Para el buscador AJAX del Tom Select
    public function buscarInsumosStockeables(string $termino, int $limite = 30): array
    {
        $busqueda = '%' . $termino . '%';
        
        $sql = 'SELECT id, sku, nombre, tipo_item, requiere_lote, costo_referencial
                FROM items
                WHERE estado = 1
                  AND deleted_at IS NULL
                  AND controla_stock = 1
                  AND (nombre LIKE :termino_nombre OR sku LIKE :termino_sku)
                ORDER BY nombre ASC
                LIMIT ' . (int)$limite;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'termino_nombre' => $busqueda,
            'termino_sku' => $busqueda
        ]);
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Calculamos el costo dinámico en un loop rápido (Máximo 30 iteraciones)
        // Esto es muchísimo más eficiente que agrupar toda la tabla en un JOIN
        foreach ($items as &$item) {
            $item['costo_calculado'] = $this->obtenerCostoReferencial((int)$item['id']);
        }

        return $items;
    }

    // Se mantiene por retrocompatibilidad, pero ahora usa la lógica rápida
    public function listarItemsStockeables(): array
    {
        $sql = 'SELECT id, sku, nombre, tipo_item, requiere_lote, costo_referencial
                FROM items
                WHERE estado = 1
                  AND deleted_at IS NULL
                  AND controla_stock = 1
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as &$item) {
            $item['costo_calculado'] = $this->obtenerCostoReferencial((int)$item['id']);
        }

        return $items;
    }

    public function listarAlmacenesActivos(): array
    {
        $sql = 'SELECT id, nombre
                FROM almacenes
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarAlmacenesActivosPorTipo(string $tipo): array
    {
        $tipo = trim($tipo);
        if ($tipo === '') {
            return [];
        }

        $stmt = $this->db()->prepare('SELECT id, nombre
                                      FROM almacenes
                                      WHERE estado = 1
                                        AND deleted_at IS NULL
                                        AND tipo = :tipo
                                      ORDER BY nombre ASC');
        $stmt->execute(['tipo' => $tipo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarParametrosCatalogo(): array
    {
        $sql = 'SELECT id, nombre, unidad_medida, descripcion 
                FROM produccion_parametros_catalogo 
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearParametroCatalogo(array $data): int
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $unidadMedida = trim((string) ($data['unidad_medida'] ?? ''));
        $descripcion = trim((string) ($data['descripcion'] ?? ''));

        if ($nombre === '') {
            throw new RuntimeException('El nombre del parámetro es obligatorio.');
        }

        $stmt = $this->db()->prepare('INSERT INTO produccion_parametros_catalogo (nombre, unidad_medida, descripcion)
                                      VALUES (:nombre, :unidad_medida, :descripcion)');
        $stmt->execute([
            'nombre' => $nombre,
            'unidad_medida' => $unidadMedida !== '' ? $unidadMedida : null,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizarParametroCatalogo(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new RuntimeException('Parámetro inválido.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        $unidadMedida = trim((string) ($data['unidad_medida'] ?? ''));
        $descripcion = trim((string) ($data['descripcion'] ?? ''));

        if ($nombre === '') {
            throw new RuntimeException('El nombre del parámetro es obligatorio.');
        }

        $stmt = $this->db()->prepare('UPDATE produccion_parametros_catalogo
                                      SET nombre = :nombre,
                                          unidad_medida = :unidad_medida,
                                          descripcion = :descripcion
                                      WHERE id = :id');

        return $stmt->execute([
            'id' => $id,
            'nombre' => $nombre,
            'unidad_medida' => $unidadMedida !== '' ? $unidadMedida : null,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
        ]);
    }

    public function eliminarParametroCatalogo(int $id): bool
    {
        if ($id <= 0) {
            throw new RuntimeException('Parámetro inválido.');
        }

        $stmtUso = $this->db()->prepare('SELECT COUNT(*) FROM produccion_recetas_parametros WHERE id_parametro = :id');
        $stmtUso->execute(['id' => $id]);
        if ((int) $stmtUso->fetchColumn() > 0) {
            throw new RuntimeException('No se puede eliminar el parámetro porque está asociado a recetas.');
        }

        $stmt = $this->db()->prepare('DELETE FROM produccion_parametros_catalogo WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function obtenerParametrosReceta(int $idReceta): array
    {
        $sql = 'SELECT id_parametro, valor_objetivo, margen_tolerancia 
                FROM produccion_recetas_parametros 
                WHERE id_receta = :id_receta';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_receta' => $idReceta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarVersionesReceta(int $idRecetaBase): array
    {
        $recetaBase = $this->obtenerRecetaPorId($idRecetaBase);
        if ($recetaBase === []) {
            throw new RuntimeException('La receta base no existe.');
        }

        $codigoBase = $this->limpiarCodigoVersion((string) ($recetaBase['codigo'] ?? ''));

        $stmt = $this->db()->prepare('SELECT id, codigo, version, estado
                                      FROM produccion_recetas
                                      WHERE id_producto = :id_producto
                                        AND deleted_at IS NULL
                                      ORDER BY version DESC, id DESC');
        $stmt->execute(['id_producto' => (int) $recetaBase['id_producto']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $versiones = [];
        foreach ($rows as $row) {
            $codigo = (string) ($row['codigo'] ?? '');
            if ($this->limpiarCodigoVersion($codigo) !== $codigoBase) {
                continue;
            }

            $versiones[] = [
                'id' => (int) ($row['id'] ?? 0),
                'codigo' => $codigo,
                'version' => (int) ($row['version'] ?? 1),
                'estado' => (int) ($row['estado'] ?? 0),
            ];
        }

        return $versiones;
    }

    public function obtenerRecetaVersionParaEdicion(int $idReceta): array
    {
        // Añadido el JOIN para traer el nombre del producto destino
        $sql = 'SELECT r.*, i.nombre AS producto_nombre 
                FROM produccion_recetas r 
                INNER JOIN items i ON i.id = r.id_producto 
                WHERE r.id = :id LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idReceta]);
        $receta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receta) {
            throw new RuntimeException('La receta no existe.');
        }

        return [
            'id' => (int) $receta['id'],
            'id_producto' => (int) $receta['id_producto'],
            'producto_nombre' => (string) $receta['producto_nombre'], // <-- Nuevo
            'codigo' => (string) ($receta['codigo'] ?? ''),
            'version' => (int) ($receta['version'] ?? 1),
            'descripcion' => (string) ($receta['descripcion'] ?? ''),
            'rendimiento_base' => (float) ($receta['rendimiento_base'] ?? 0),
            'unidad_rendimiento' => (string) ($receta['unidad_rendimiento'] ?? ''),
            'detalles' => $this->obtenerDetalleRecetaVersion($idReceta),
            'parametros' => $this->obtenerParametrosReceta($idReceta),
        ];
    }

    public function crearReceta(array $payload, int $userId): int
    {
        $idProducto = (int) ($payload['id_producto'] ?? 0);
        $codigo = trim((string) ($payload['codigo'] ?? ''));
        $version = max(1, (int) ($payload['version'] ?? 1));
        $descripcion = trim((string) ($payload['descripcion'] ?? ''));
        $rendimientoBase = (float) ($payload['rendimiento_base'] ?? 0);
        $unidadRendimiento = trim((string) ($payload['unidad_rendimiento'] ?? ''));
        
        $detalles = is_array($payload['detalles'] ?? null) ? $payload['detalles'] : [];
        $parametros = is_array($payload['parametros'] ?? null) ? $payload['parametros'] : [];

        if ($idProducto <= 0 || $codigo === '' || $detalles === [] || $rendimientoBase <= 0) {
            throw new RuntimeException('Debe completar producto, código, rendimiento base y al menos un detalle de insumos.');
        }

        $insumosUtilizados = [];
        foreach ($detalles as $detalle) {
            $idInsumo = (int) ($detalle['id_insumo'] ?? 0);
            if ($idInsumo <= 0) continue;

            if ($idInsumo === $idProducto) {
                throw new RuntimeException('No se puede usar el mismo producto destino como insumo de su propia receta.');
            }

            if (isset($insumosUtilizados[$idInsumo])) {
                throw new RuntimeException('No se permiten insumos repetidos en la misma receta.');
            }

            $insumosUtilizados[$idInsumo] = true;
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $costoTotalReceta = 0.0;
            foreach ($detalles as $detalle) {
                $idInsumo = (int) ($detalle['id_insumo'] ?? 0);
                $cantidad = (float) ($detalle['cantidad_por_unidad'] ?? 0);
                $merma = (float) ($detalle['merma_porcentaje'] ?? 0);

                if ($idInsumo > 0 && $cantidad > 0) {
                    $costoCapturado = isset($detalle['costo_unitario']) ? (float) $detalle['costo_unitario'] : 0.0;
                    $costoInsumo = $costoCapturado > 0 ? $costoCapturado : $this->obtenerCostoReferencial($idInsumo);
                    $cantidadReal = $cantidad * (1 + ($merma / 100));
                    $costoTotalReceta += ($costoInsumo * $cantidadReal);
                }
            }

            $costoUnitarioTeorico = $costoTotalReceta;

            $stmtPendiente = $db->prepare('SELECT r.id
                                           FROM produccion_recetas r
                                           LEFT JOIN produccion_recetas_detalle d ON d.id_receta = r.id AND d.deleted_at IS NULL
                                           WHERE r.codigo = :codigo
                                             AND r.id_producto = :id_producto
                                             AND r.deleted_at IS NULL
                                           GROUP BY r.id
                                           HAVING COUNT(d.id) = 0
                                           LIMIT 1');
            $stmtPendiente->execute([
                'codigo' => $codigo,
                'id_producto' => $idProducto,
            ]);
            $idRecetaPendiente = (int) ($stmtPendiente->fetchColumn() ?: 0);

            if ($idRecetaPendiente > 0) {
                $stmt = $db->prepare('UPDATE produccion_recetas
                                      SET version = :version,
                                          descripcion = :descripcion,
                                          rendimiento_base = :rendimiento_base,
                                          unidad_rendimiento = :unidad_rendimiento,
                                          costo_teorico_unitario = :costo_unitario,
                                          estado = 1,
                                          updated_at = NOW(),
                                          updated_by = :updated_by
                                      WHERE id = :id_receta');
                $stmt->execute([
                    'version' => $version,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                    'rendimiento_base' => number_format($rendimientoBase, 4, '.', ''),
                    'unidad_rendimiento' => $unidadRendimiento !== '' ? $unidadRendimiento : null,
                    'costo_unitario' => number_format($costoUnitarioTeorico, 4, '.', ''),
                    'updated_by' => $userId,
                    'id_receta' => $idRecetaPendiente,
                ]);
                $idReceta = $idRecetaPendiente;
            } else {
                $stmtExiste = $db->prepare('SELECT id FROM produccion_recetas WHERE codigo = :codigo AND deleted_at IS NULL LIMIT 1');
                $stmtExiste->execute(['codigo' => $codigo]);
                if ((int) ($stmtExiste->fetchColumn() ?: 0) > 0) {
                    throw new RuntimeException('Ya existe una receta con ese código. Use "Nueva Versión" o cambie el código.');
                }

                $stmt = $db->prepare('INSERT INTO produccion_recetas
                                        (id_producto, codigo, version, descripcion,
                                         rendimiento_base, unidad_rendimiento,
                                         costo_teorico_unitario, estado, created_by, updated_by)
                                      VALUES
                                        (:id_producto, :codigo, :version, :descripcion,
                                         :rendimiento_base, :unidad_rendimiento,
                                         :costo_unitario, 1, :created_by, :updated_by)');

                $stmt->execute([
                    'id_producto' => $idProducto,
                    'codigo' => $codigo,
                    'version' => $version,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                    'rendimiento_base' => number_format($rendimientoBase, 4, '.', ''),
                    'unidad_rendimiento' => $unidadRendimiento !== '' ? $unidadRendimiento : null,
                    'costo_unitario' => number_format($costoUnitarioTeorico, 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $idReceta = (int) $db->lastInsertId();
            }

            $stmtDesactivar = $db->prepare('UPDATE produccion_recetas
                                            SET estado = 0,
                                                updated_at = NOW(),
                                                updated_by = :updated_by
                                            WHERE id_producto = :id_producto
                                              AND id <> :id_receta
                                              AND deleted_at IS NULL');
            $stmtDesactivar->execute([
                'updated_by' => $userId,
                'id_producto' => $idProducto,
                'id_receta' => $idReceta,
            ]);

            $stmtDet = $db->prepare('INSERT INTO produccion_recetas_detalle
                                        (id_receta, id_insumo, etapa, cantidad_por_unidad, merma_porcentaje, costo_unitario, created_by, updated_by)
                                     VALUES
                                        (:id_receta, :id_insumo, :etapa, :cantidad_por_unidad, :merma_porcentaje, :costo_unitario, :created_by, :updated_by)');

            foreach ($detalles as $detalle) {
                $idInsumo = (int) ($detalle['id_insumo'] ?? 0);
                $cantidad = (float) ($detalle['cantidad_por_unidad'] ?? 0);
                $merma = (float) ($detalle['merma_porcentaje'] ?? 0);
                $etapa = trim((string) ($detalle['etapa'] ?? 'General'));
                $costoUnitario = isset($detalle['costo_unitario']) ? (float) $detalle['costo_unitario'] : $this->obtenerCostoReferencial($idInsumo);

                if ($idInsumo <= 0 || $cantidad <= 0) {
                    throw new RuntimeException('Detalle de receta inválido.');
                }

                $stmtDet->execute([
                    'id_receta' => $idReceta,
                    'id_insumo' => $idInsumo,
                    'etapa' => $etapa,
                    'cantidad_por_unidad' => number_format($cantidad, 4, '.', ''),
                    'merma_porcentaje' => number_format($merma, 2, '.', ''),
                    'costo_unitario' => number_format(max(0, $costoUnitario), 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            if (!empty($parametros)) {
                $stmtParam = $db->prepare('INSERT INTO produccion_recetas_parametros 
                                            (id_receta, id_parametro, valor_objetivo) 
                                           VALUES 
                                            (:id_receta, :id_parametro, :valor_objetivo)');

                foreach ($parametros as $param) {
                    $idParametro = (int) ($param['id_parametro'] ?? 0);
                    $valorObjetivo = $param['valor_objetivo'] ?? '';

                    if ($idParametro > 0 && $valorObjetivo !== '') {
                        $stmtParam->execute([
                            'id_receta' => $idReceta,
                            'id_parametro' => $idParametro,
                            'valor_objetivo' => number_format((float) $valorObjetivo, 4, '.', '')
                        ]);
                    }
                }
            }

            $stmtUpdateItem = $db->prepare('UPDATE items 
                                            SET costo_referencial = :costo,
                                                updated_at = NOW(),
                                                updated_by = :user
                                            WHERE id = :id');
            $stmtUpdateItem->execute([
                'costo' => number_format($costoUnitarioTeorico, 4, '.', ''),
                'user' => $userId,
                'id' => $idProducto
            ]);

            $db->commit();
            return $idReceta;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }


    private function obtenerCostoReferencial(int $idItem): float
    {
        // 1. Primero buscamos el costo fijo en el ítem
        $stmt = $this->db()->prepare('SELECT costo_referencial FROM items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $idItem]);
        $costoFijo = (float)($stmt->fetchColumn() ?: 0);
        if ($costoFijo > 0) return $costoFijo;

        // 2. Si no hay, buscamos el costo de su última receta (Si es producto terminado)
        $stmtRec = $this->db()->prepare('SELECT costo_teorico_unitario 
                                         FROM produccion_recetas 
                                         WHERE id_producto = :id AND estado = 1 AND deleted_at IS NULL 
                                         ORDER BY id DESC LIMIT 1');
        $stmtRec->execute(['id' => $idItem]);
        $costoReceta = (float)($stmtRec->fetchColumn() ?: 0);
        if ($costoReceta > 0) return $costoReceta;

        // 3. Si no hay receta, buscamos el último costo al que se compró o movió
        $stmtMov = $this->db()->prepare('SELECT costo_unitario 
                                         FROM inventario_movimientos 
                                         WHERE id_item = :id AND costo_unitario IS NOT NULL AND costo_unitario > 0 
                                         ORDER BY id DESC LIMIT 1');
        $stmtMov->execute(['id' => $idItem]);
        return (float)($stmtMov->fetchColumn() ?: 0);
    }

    public function crearNuevaVersion(int $idRecetaBase, int $userId): int
    {
        $receta = $this->obtenerRecetaPorId($idRecetaBase);
        if ($receta === []) {
            throw new RuntimeException('La receta base no existe.');
        }

        $detalles = $this->obtenerDetalleReceta($idRecetaBase);
        if ($detalles === []) {
            throw new RuntimeException('La receta base no tiene detalles.');
        }

        $parametrosOld = $this->obtenerParametrosReceta($idRecetaBase);

        $siguienteVersion = $this->obtenerSiguienteVersion((int) $receta['id_producto']);

        return $this->crearReceta([
            'id_producto' => (int) $receta['id_producto'],
            'codigo' => $this->generarCodigoVersion((string) $receta['codigo'], $siguienteVersion),
            'version' => $siguienteVersion,
            'descripcion' => (string) ($receta['descripcion'] ?? ''),
            'rendimiento_base' => (float) ($receta['rendimiento_base'] ?? 0),
            'unidad_rendimiento' => (string) ($receta['unidad_rendimiento'] ?? ''),
            
            'detalles' => array_map(static fn (array $d): array => [
                'id_insumo' => (int) $d['id_insumo'],
                'etapa' => (string) ($d['etapa'] ?? ''),
                'cantidad_por_unidad' => (float) $d['cantidad_por_unidad'],
                'merma_porcentaje' => (float) $d['merma_porcentaje'],
            ], $detalles),

            'parametros' => array_map(static fn (array $p): array => [
                'id_parametro' => (int) $p['id_parametro'],
                'valor_objetivo' => (float) $p['valor_objetivo']
            ], $parametrosOld),

        ], $userId);
    }

    public function crearNuevaVersionDesdePayload(int $idRecetaBase, array $payload, int $userId): int
    {
        $recetaBase = $this->obtenerRecetaPorId($idRecetaBase);
        if ($recetaBase === []) {
            throw new RuntimeException('La receta base no existe.');
        }

        $idProductoPayload = (int) ($payload['id_producto'] ?? 0);
        if ($idProductoPayload !== (int) $recetaBase['id_producto']) {
            throw new RuntimeException('No se puede cambiar el producto destino al crear una versión.');
        }

        // --- LÓGICA DE TRAZABILIDAD ---
        // Buscamos cuál es la receta ACTIVA actualmente para compararla con lo que envían.
        // Esto permite cargar la v.1 y guardarla como v.5, porque la v.1 es distinta a la v.4 (activa).
        $stmtActiva = $this->db()->prepare('SELECT id FROM produccion_recetas WHERE id_producto = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
        $stmtActiva->execute(['id' => $idProductoPayload]);
        $idRecetaActiva = (int) ($stmtActiva->fetchColumn() ?: $idRecetaBase);

        $detallesActiva = $this->obtenerDetalleRecetaVersion($idRecetaActiva);
        $parametrosActiva = $this->obtenerParametrosReceta($idRecetaActiva);

        $recetaActivaFila = $this->obtenerRecetaPorId($idRecetaActiva);

        $normalizadoActiva = [
            'descripcion' => trim((string) ($recetaActivaFila['descripcion'] ?? '')),
            'rendimiento_base' => number_format((float) ($recetaActivaFila['rendimiento_base'] ?? 0), 4, '.', ''),
            'unidad_rendimiento' => trim((string) ($recetaActivaFila['unidad_rendimiento'] ?? '')),
            'detalles' => $this->normalizarDetallesComparacion($detallesActiva),
            'parametros' => $this->normalizarParametrosComparacion($parametrosActiva),
        ];

        $normalizadoPayload = [
            'descripcion' => trim((string) ($payload['descripcion'] ?? '')),
            'rendimiento_base' => number_format((float) ($payload['rendimiento_base'] ?? 0), 4, '.', ''),
            'unidad_rendimiento' => trim((string) ($payload['unidad_rendimiento'] ?? '')),
            'detalles' => $this->normalizarDetallesComparacion((array) ($payload['detalles'] ?? [])),
            'parametros' => $this->normalizarParametrosComparacion((array) ($payload['parametros'] ?? [])),
        ];

        if ($normalizadoActiva === $normalizadoPayload) {
            throw new RuntimeException('La fórmula ingresada es exactamente igual a la versión activa actual. No se generó una nueva versión.');
        }

        $siguienteVersion = $this->obtenerSiguienteVersion((int) $recetaBase['id_producto']);

        return $this->crearReceta([
            'id_producto' => (int) $recetaBase['id_producto'],
            'codigo' => $this->generarCodigoVersion((string) $recetaBase['codigo'], $siguienteVersion),
            'version' => $siguienteVersion,
            'descripcion' => $normalizadoPayload['descripcion'],
            'rendimiento_base' => (float) $normalizadoPayload['rendimiento_base'],
            'unidad_rendimiento' => $normalizadoPayload['unidad_rendimiento'],
            'detalles' => (array) ($payload['detalles'] ?? []),
            'parametros' => (array) ($payload['parametros'] ?? []),
        ], $userId);
    }

    private function obtenerDetalleRecetaVersion(int $idReceta): array
    {
        // Añadido el JOIN para traer el nombre del insumo y arreglar el "Insumo ID: 32"
        $sql = 'SELECT d.id_insumo,
                       d.etapa,
                       d.cantidad_por_unidad,
                       d.merma_porcentaje,
                       d.costo_unitario,
                       i.nombre AS insumo_nombre 
                FROM produccion_recetas_detalle d
                INNER JOIN items i ON i.id = d.id_insumo
                WHERE d.id_receta = :id_receta
                  AND d.deleted_at IS NULL
                ORDER BY d.id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_receta' => $idReceta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizarDetallesComparacion(array $detalles): array
    {
        $normalizados = [];
        foreach ($detalles as $detalle) {
            $idInsumo = (int) ($detalle['id_insumo'] ?? 0);
            $cantidad = (float) ($detalle['cantidad_por_unidad'] ?? 0);
            if ($idInsumo <= 0 || $cantidad <= 0) {
                continue;
            }

            $normalizados[] = [
                'id_insumo' => $idInsumo,
                'etapa' => trim((string) ($detalle['etapa'] ?? 'General')),
                'cantidad_por_unidad' => number_format($cantidad, 4, '.', ''),
                'merma_porcentaje' => number_format((float) ($detalle['merma_porcentaje'] ?? 0), 4, '.', ''),
                'costo_unitario' => number_format((float) ($detalle['costo_unitario'] ?? 0), 4, '.', ''),
            ];
        }

        usort($normalizados, static fn (array $a, array $b): int => [$a['id_insumo'], $a['etapa']] <=> [$b['id_insumo'], $b['etapa']]);
        return $normalizados;
    }

    private function normalizarParametrosComparacion(array $parametros): array
    {
        $normalizados = [];
        foreach ($parametros as $parametro) {
            $idParametro = (int) ($parametro['id_parametro'] ?? 0);
            if ($idParametro <= 0) {
                continue;
            }

            $normalizados[] = [
                'id_parametro' => $idParametro,
                'valor_objetivo' => number_format((float) ($parametro['valor_objetivo'] ?? 0), 4, '.', ''),
            ];
        }

        usort($normalizados, static fn (array $a, array $b): int => $a['id_parametro'] <=> $b['id_parametro']);
        return $normalizados;
    }

    private function limpiarCodigoVersion(string $codigo): string
    {
        return (string) preg_replace('/-V\d+$/', '', trim($codigo));
    }

    private function obtenerRecetaPorId(int $idReceta): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM produccion_recetas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $idReceta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    private function obtenerSiguienteVersion(int $idProducto): int
    {
        $stmt = $this->db()->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM produccion_recetas WHERE id_producto = :id_producto AND deleted_at IS NULL');
        $stmt->execute(['id_producto' => $idProducto]);
        return max(1, (int) $stmt->fetchColumn());
    }

    private function generarCodigoVersion(string $codigoBase, int $version): string
    {
        $codigoLimpio = $this->limpiarCodigoVersion($codigoBase);
        return sprintf('%s-V%d', $codigoLimpio ?: 'REC', $version);
    }

    private function obtenerUnidadPorDefecto(int $idItem): ?int
    {
        $stmt = $this->db()->prepare('SELECT id_item_unidad 
                                      FROM inventario_movimientos 
                                      WHERE id_item = :id_item 
                                        AND id_item_unidad IS NOT NULL 
                                      ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id_item' => $idItem]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    public function obtenerDatosParaNuevaVersion(int $idRecetaBase): array
    {
        $receta = $this->obtenerRecetaVersionParaEdicion($idRecetaBase);
        if ($receta === []) {
            throw new RuntimeException('La receta base no existe.');
        }

        $siguienteVersion = $this->obtenerSiguienteVersion((int)$receta['id_producto']);

        return [
            'id'                  => $receta['id'],
            'id_producto'         => $receta['id_producto'],
            'producto_nombre'     => $receta['producto_nombre'],
            'version'             => $siguienteVersion,
            'codigo'              => $this->generarCodigoVersion($receta['codigo'], $siguienteVersion),
            'descripcion'         => $receta['descripcion'],
            'detalles'            => $receta['detalles'],
            'parametros'          => $receta['parametros'],
            'es_nueva_version'    => true,
        ];
    }

    private function obtenerUnidadPrincipalItem(int $idItem): string
    {
        $stmt = $this->db()->prepare('SELECT COALESCE(unidad_principal, "UND") FROM items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $idItem]);
        return (string)$stmt->fetchColumn();
    }
}
