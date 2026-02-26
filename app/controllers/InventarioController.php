<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/InventarioModel.php';
require_once BASE_PATH . '/app/models/configuracion/AlmacenModel.php';
require_once BASE_PATH . '/app/controllers/PermisosController.php';

class InventarioController extends Controlador
{
    private InventarioModel $inventarioModel;
    private AlmacenModel $almacenModel;

    public function __construct()
    {
        $this->inventarioModel = new InventarioModel();
        $this->almacenModel = new AlmacenModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        if (!PermisosController::tienePermiso('inventario.ver')) {
            require_permiso('inventario.ver');
        }

        $idAlmacenFiltro = (int) ($_GET['id_almacen'] ?? 0);
        $stockActualRaw = $this->inventarioModel->obtenerStock($idAlmacenFiltro);
        
        // ACCIÓN 1 y 2: Procesar la data cruda para inyectarle la lógica de estado y formato
        $stockProcesado = $this->procesarEstadosStock($stockActualRaw);

        $datos = [
            'ruta_actual' => 'inventario',
            'stockActual' => $stockProcesado,
            'almacenes' => $this->almacenModel->listarActivos(),
            'proveedores' => $this->inventarioModel->listarProveedoresActivos(),
            'items' => $this->inventarioModel->listarItems(),
            'flash' => ['tipo' => '', 'texto' => ''],
            'id_almacen_filtro' => $idAlmacenFiltro,
        ];

        $this->vista('inventario', $datos);
    }

    // --- LÓGICA DE ESTADOS Y FORMATOS ---
    private function procesarEstadosStock(array $filas): array
    {
        $resultado = [];
        
        foreach ($filas as $fila) {
            $stock = (float) ($fila['stock_actual'] ?? 0);
            $stockMin = (float) ($fila['stock_minimo'] ?? 0);
            // Fallback: en algunos registros heredados el stock consolidado puede estar en 0
            // pero el desglose por movimientos sí refleja saldo positivo.
            if ($stock <= 0.0 && !empty($fila['desglose']) && is_array($fila['desglose'])) {
                $stockDesdeDesglose = 0.0;
                foreach ($fila['desglose'] as $desgloseFila) {
                    $stockDesdeDesglose += (float) ($desgloseFila['cantidad'] ?? 0);
                }
                if ($stockDesdeDesglose > 0.0) {
                    $stock = $stockDesdeDesglose;
                    $fila['stock_actual'] = $stock;
                }
            }
            $controlaStock = (int) ($fila['controla_stock'] ?? 1);
            $permiteDecimales = (int) ($fila['permite_decimales'] ?? 0);
            $lote = trim((string) ($fila['lote_actual'] ?? ''));
            $ultimoMovimiento = trim((string) ($fila['ultimo_movimiento'] ?? ''));
            $requiereVencimiento = (int) ($fila['requiere_vencimiento'] ?? 0) === 1;
            $diasAlerta = max(0, (int) ($fila['dias_alerta_vencimiento'] ?? 0));
            $proximoVencimiento = trim((string) ($fila['proximo_vencimiento'] ?? ''));

            if ((int)$fila['id_almacen'] === 0 && $stock === 0.0 && $lote === '') {
                $fila['almacen_nombre'] = 'Sin Ubicación Física';
            }
            
            // 1. Lógica de Decimales
            $fila['stock_formateado'] = number_format($stock, $permiteDecimales === 1 ? 3 : 0, '.', ',');
            $fila['stock_minimo_formateado'] = number_format($stockMin, $permiteDecimales === 1 ? 3 : 0, '.', ',');

            // 2. Procesar Desglose para Texto Plano (Usado en Exportación Excel/CSV)
            $desgloseText = '';
            if (!empty($fila['desglose']) && is_array($fila['desglose'])) {
                $partes = [];
                foreach ($fila['desglose'] as $d) {
                    // Extraemos solo el texto del badge
                    $partes[] = $d['texto'];
                }
                $desgloseText = implode(' + ', $partes);
            }
            $fila['desglose_texto'] = $desgloseText;

            // 3. Lógica de Estados y Colores
            $fila['estado_vencimiento'] = '';
            $fila['detalle_alerta'] = '';

            if ($requiereVencimiento && $proximoVencimiento !== '') {
                $hoy = new DateTimeImmutable('today');
                $fechaVencimiento = DateTimeImmutable::createFromFormat('Y-m-d', $proximoVencimiento);
                if ($fechaVencimiento instanceof DateTimeImmutable) {
                    $diasRestantes = (int) $hoy->diff($fechaVencimiento)->format('%r%a');
                    if ($diasRestantes < 0) {
                        $fila['estado_vencimiento'] = 'vencido';
                        $fila['detalle_alerta'] = 'Venció el ' . $proximoVencimiento;
                    } elseif ($diasRestantes <= $diasAlerta) {
                        $fila['estado_vencimiento'] = 'proximo_a_vencer';
                        $fila['detalle_alerta'] = 'Vence el ' . $proximoVencimiento;
                    }
                }
            }

            if ($controlaStock === 0) {
                $fila['badge_estado'] = 'Disponible';
                $fila['badge_color'] = 'bg-success bg-opacity-10 text-success';
            } elseif ($ultimoMovimiento === '') {
                $fila['badge_estado'] = 'Sin Movimientos';
                $fila['badge_color'] = 'bg-light text-muted border border-secondary';
            } elseif ($fila['estado_vencimiento'] === 'vencido') {
                $fila['badge_estado'] = 'Vencido';
                $fila['badge_color'] = 'bg-danger bg-opacity-10 text-danger border border-danger';
            } elseif ($stock <= 0.0) {
                $fila['badge_estado'] = 'Agotado';
                $fila['badge_color'] = 'bg-danger bg-opacity-10 text-danger border border-danger';
            } elseif ($stock <= $stockMin) {
                $fila['badge_estado'] = 'Bajo Mínimo';
                $fila['badge_color'] = 'bg-warning bg-opacity-10 text-warning border border-warning';
            } elseif ($fila['estado_vencimiento'] === 'proximo_a_vencer') {
                $fila['badge_estado'] = 'Próximo a Vencer';
                $fila['badge_color'] = 'bg-warning bg-opacity-10 text-warning border border-warning';
            } else {
                $fila['badge_estado'] = 'Disponible';
                $fila['badge_color'] = 'bg-success bg-opacity-10 text-success';
            }

            $resultado[] = $fila;
        }
        
        return $resultado;
    }

    public function guardarMovimiento(): void
    {
        AuthMiddleware::handle();
        if (!PermisosController::tienePermiso('inventario.movimiento.crear')) {
            require_permiso('inventario.movimiento.crear');
        }

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $tipo = trim((string) ($_POST['tipo_movimiento'] ?? ''));
            $idAlmacen = (int) ($_POST['id_almacen'] ?? 0);
            $tipoRegistro = trim((string) ($_POST['tipo_registro'] ?? 'item'));
            if (!in_array($tipoRegistro, ['item', 'pack'], true)) {
                $tipoRegistro = 'item';
            }

            $idItem = (int) ($_POST['id_item'] ?? 0);
            $idPack = (int) ($_POST['id_pack'] ?? 0);
            $cantidad = (float) ($_POST['cantidad'] ?? 0);
            $referencia = trim((string) ($_POST['referencia'] ?? ''));
            $motivo = trim((string) ($_POST['motivo'] ?? ''));
            
            $lote = trim((string) ($_POST['lote'] ?? ''));
            $fechaPost = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
            $fechaVencimiento = $fechaPost !== '' ? $fechaPost : null;
            
            $costoUnitario = (float) ($_POST['costo_unitario'] ?? 0);
            $idItemUnidad = (int) ($_POST['id_item_unidad'] ?? 0);

            if (in_array($tipo, ['AJ+', 'AJ-', 'CON'], true) && $motivo === '') {
                throw new InvalidArgumentException('Debe seleccionar un motivo para este tipo de movimiento.');
            }

            if (in_array($tipo, ['AJ+', 'AJ-', 'INI'], true) && $referencia === '') {
                throw new InvalidArgumentException('La referencia es obligatoria para este tipo de movimiento.');
            }

            if ($motivo !== '') {
                $referencia = $referencia !== '' ? ('Motivo: ' . $motivo . ' | ' . $referencia) : ('Motivo: ' . $motivo);
            }

            $datos = [
                'tipo_movimiento' => $tipo,
                'tipo_registro' => $tipoRegistro,
                'id_item' => $idItem,
                'id_pack' => $idPack,
                'id_item_unidad' => $idItemUnidad > 0 ? $idItemUnidad : null,
                'cantidad' => $cantidad,
                'referencia' => $referencia,
                'lote' => $lote,
                'fecha_vencimiento' => $fechaVencimiento,
                'costo_unitario' => $costoUnitario,
                'created_by' => (int) ($_SESSION['id'] ?? 0),
            ];

            if ($tipo === 'TRF') {
                $datos['id_almacen_origen'] = $idAlmacen;
                $datos['id_almacen_destino'] = (int) ($_POST['id_almacen_destino'] ?? 0);
            } elseif (in_array($tipo, ['AJ-', 'CON', 'VEN'], true)) {
                // Las SALIDAS solo tienen origen
                $datos['id_almacen_origen'] = $idAlmacen;
                $datos['id_almacen_destino'] = 0;
            } else {
                // Las ENTRADAS (INI, AJ+, COM, PROD) solo tienen destino
                $datos['id_almacen_origen'] = 0;
                $datos['id_almacen_destino'] = $idAlmacen;
            }

            $idMovimiento = $this->inventarioModel->registrarMovimiento($datos);

            json_response([
                'ok' => true,
                'mensaje' => 'Movimiento registrado correctamente.',
                'id' => $idMovimiento,
            ]);
        } catch (Throwable $e) {
            json_response([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 400);
        }
    }

    public function guardarMovimientoLote(): void
    {
        AuthMiddleware::handle();
        if (!PermisosController::tienePermiso('inventario.movimiento.crear')) {
            require_permiso('inventario.movimiento.crear');
        }

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $raw = (string) file_get_contents('php://input');
            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                throw new InvalidArgumentException('Formato de payload inválido.');
            }

            $cabecera = is_array($payload['cabecera'] ?? null) ? $payload['cabecera'] : [];
            $lineas = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $modo = strtolower(trim((string) ($payload['modo'] ?? 'atomico')));
            $atomico = $modo !== 'parcial';

            if (empty($lineas)) {
                throw new InvalidArgumentException('Debe agregar al menos una línea para registrar movimientos.');
            }

            $resultado = $this->inventarioModel->registrarMovimientoLote([
                'tipo_movimiento' => trim((string) ($cabecera['tipo_movimiento'] ?? '')),
                'id_almacen' => (int) ($cabecera['id_almacen'] ?? 0),
                'id_almacen_destino' => (int) ($cabecera['id_almacen_destino'] ?? 0),
                'referencia' => trim((string) ($cabecera['referencia'] ?? '')),
                'motivo' => trim((string) ($cabecera['motivo'] ?? '')),
                'created_by' => (int) ($_SESSION['id'] ?? 0),
            ], $lineas, $atomico);

            json_response([
                'ok' => true,
                'mensaje' => 'Movimientos registrados correctamente.',
                'operacion_uuid' => (string) ($resultado['operacion_uuid'] ?? ''),
                'ids' => $resultado['ids'] ?? [],
                'errores' => $resultado['errores'] ?? [],
            ]);
        } catch (Throwable $e) {
            json_response([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 400);
        }
    }

    public function buscarItems(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.movimiento.crear');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '') {
            json_response(['ok' => true, 'items' => []]);
            return;
        }

        $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);
        $soloConStock = (string) ($_GET['solo_con_stock'] ?? '0') === '1';

        $items = $this->inventarioModel->buscarItems($q, 25, $idAlmacen, $soloConStock);
        json_response(['ok' => true, 'items' => $items]);
    }

    public function buscarLotes(): void 
    {
        AuthMiddleware::handle();
        
        if (!es_ajax()) {
            json_response(['ok' => false], 400);
            return;
        }

        $idItem = (int) ($_GET['id_item'] ?? 0);
        $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);

        if ($idItem <= 0 || $idAlmacen <= 0) {
            json_response(['ok' => true, 'lotes' => []]);
            return;
        }

        $lotes = $this->inventarioModel->listarLotesDisponibles($idItem, $idAlmacen);
        
        json_response(['ok' => true, 'lotes' => $lotes]);
    }

    public function stockItem(): void
    {
        AuthMiddleware::handle();
        
        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        $idItem = (int) ($_GET['id_item'] ?? 0);
        $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);
        $tipoRegistro = (string) ($_GET['tipo_registro'] ?? 'item');
        if (!in_array($tipoRegistro, ['item', 'pack'], true)) {
            $tipoRegistro = 'item';
        }

        if ($idItem <= 0 || $idAlmacen <= 0) {
            json_response(['ok' => true, 'stock' => 0]);
            return;
        }

        $stock = $this->inventarioModel->obtenerStockPorItemAlmacen($idItem, $idAlmacen, $tipoRegistro);
        json_response(['ok' => true, 'stock' => $stock]);
    }

    public function resumenItem(): void
    {
        AuthMiddleware::handle();

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        $idItem = (int) ($_GET['id_item'] ?? 0);
        $tipoRegistro = (string) ($_GET['tipo_registro'] ?? 'item');
        if (!in_array($tipoRegistro, ['item', 'pack'], true)) {
            $tipoRegistro = 'item';
        }

        if ($idItem <= 0) {
            json_response(['ok' => true, 'resumen' => ['stock_actual' => 0, 'costo_promedio_actual' => 0]]);
            return;
        }

        $resumen = $this->inventarioModel->obtenerResumenRegistro($idItem, $tipoRegistro);
        json_response(['ok' => true, 'resumen' => $resumen]);
    }


    public function desglosePresentaciones(): void
    {
        AuthMiddleware::handle();

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        $idItem = (int) ($_GET['id_item'] ?? 0);
        $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);

        if ($idItem <= 0) {
            json_response(['ok' => true, 'items' => []]);
            return;
        }

        $desglose = $this->inventarioModel->obtenerDesglosePresentaciones($idItem, $idAlmacen);
        json_response(['ok' => true, 'items' => $desglose]);
    }

    public function kardex(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        $idItemFiltro = (int) ($_GET['id_item'] ?? ($_GET['item_id'] ?? 0));
        $filtros = [
            'id_item' => $idItemFiltro,
            'lote' => trim((string) ($_GET['lote'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        $movimientos = $this->inventarioModel->obtenerKardex($filtros);
        $items = $this->inventarioModel->listarItems();

        $this->vista('inventario_kardex', [
            'ruta_actual' => 'inventario',
            'movimientos' => $movimientos,
            'items' => $items,
            'filtros' => $filtros,
        ]);
    }

    public function exportar(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        $formato = strtolower(trim((string) ($_GET['formato'] ?? 'csv')));
        $idAlmacenFiltro = (int) ($_GET['id_almacen'] ?? 0);
        $filasRaw = $this->inventarioModel->obtenerStock($idAlmacenFiltro);
        
        $filas = $this->procesarEstadosStock($filasRaw);

        if ($formato === 'pdf') {
            header('Content-Type: text/plain; charset=utf-8');
            echo "Exportación PDF pendiente de implementación.";
            return;
        }

        $filename = $formato === 'excel' ? 'inventario_stock.xls' : 'inventario_stock.csv';
        $separator = $formato === 'excel' ? "\t" : ',';
        $contentType = $formato === 'excel' ? 'application/vnd.ms-excel; charset=utf-8' : 'text/csv; charset=utf-8';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'wb');
        
        // COLUMNA AGREGADA PARA EXCEL: 'Desglose Físico'
        $headers = ['SKU', 'Producto', 'Almacén', 'Lote', 'Stock Base (UND)', 'Desglose Físico', 'Stock Mínimo', 'Situación / Alertas'];
        fputcsv($out, $headers, $separator);

        foreach ($filas as $fila) {
            fputcsv($out, [
                (string) ($fila['sku'] ?? ''),
                (string) ($fila['item_nombre'] ?? ''),
                (string) ($fila['almacen_nombre'] ?? ''),
                (string) ($fila['lote_actual'] !== '' ? $fila['lote_actual'] : '-'),
                (string) ($fila['stock_formateado'] ?? '0'),
                (string) ($fila['desglose_texto'] ?? '-'), // INSERCIÓN DEL DESGLOSE EN EXCEL
                (string) ($fila['stock_minimo_formateado'] ?? '0'),
                trim((string) ($fila['badge_estado'] ?? '') . ' ' . (string) ($fila['detalle_alerta'] ?? '')),
            ], $separator);
        }

        fclose($out);
    }

    protected function vista(string $rutaVista, array $datos = []): void
    {
        $this->render($rutaVista, $datos);
    }
}
