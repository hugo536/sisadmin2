<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/VentasDocumentoModel.php';
require_once BASE_PATH . '/app/models/VentasDespachoModel.php';
require_once BASE_PATH . '/app/models/inventario/InventarioModel.php'; 
require_once BASE_PATH . '/app/controllers/PermisosController.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxcModel.php';

class VentasController extends Controlador
{
    private VentasDocumentoModel $documentoModel;
    private VentasDespachoModel $despachoModel;
    private InventarioModel $inventarioModel; 
    private TesoreriaCxcModel $tesoreriaCxcModel;

    public function __construct()
    {
        $this->documentoModel = new VentasDocumentoModel();
        $this->despachoModel = new VentasDespachoModel();
        $this->inventarioModel = new InventarioModel(); 
        $this->tesoreriaCxcModel = new TesoreriaCxcModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.ver');

        $fechaHastaDef = date('Y-m-d');
        $fechaDesdeDef = date('Y-m-d', strtotime('-7 days'));

        $esVistaInicial = empty($_GET['q']) && !isset($_GET['estado']) && empty($_GET['fecha_desde']) && empty($_GET['fecha_hasta']);

        $filtros = [
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'estado'      => isset($_GET['estado']) && $_GET['estado'] !== '' ? (string) $_GET['estado'] : null,
            'fecha_desde' => $esVistaInicial ? $fechaDesdeDef : trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => $esVistaInicial ? $fechaHastaDef : trim((string) ($_GET['fecha_hasta'] ?? '')),
            'orden_fecha' => trim((string) ($_GET['orden_fecha'] ?? 'emision')),
        ];

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response(['ok' => true, 'data' => $this->documentoModel->listar($filtros)]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'ver') {
            $id = (int) ($_GET['id'] ?? 0);
            $venta = $this->documentoModel->obtener($id);

            if (!empty($venta['detalle']) && is_array($venta['detalle'])) {
                foreach ($venta['detalle'] as &$linea) {
                    $rawId = (string) ($linea['id_item'] ?? '');
                    
                    if (strpos($rawId, 'ITEM-') === 0) {
                        $idItemFisico = (int) str_replace('ITEM-', '', $rawId);
                        $linea['almacenes_disponibles'] = $this->inventarioModel->obtenerAlmacenesConStockPorItem($idItemFisico);
                    } elseif (strpos($rawId, 'PACK-') === 0) {
                        $idPack = (int) str_replace('PACK-', '', $rawId);
                        $linea['almacenes_disponibles'] = $this->inventarioModel->obtenerAlmacenesConStockPorPack($idPack);
                    } else {
                        $linea['almacenes_disponibles'] = [];
                    }
                }
            }

            json_response(['ok' => true, 'data' => $venta]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_clientes') {
            $q = trim((string) ($_GET['q'] ?? ''));
            json_response(['ok' => true, 'data' => $this->documentoModel->buscarClientes($q)]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_items') {
            $q = trim((string) ($_GET['q'] ?? ''));
            $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $cantidad = (float) ($_GET['cantidad'] ?? 1);
            $metaAcuerdo = $this->documentoModel->tieneAcuerdoConProductosVigentes($idCliente);

            json_response([
                'ok' => true,
                'data' => $this->documentoModel->buscarItems($q, $idAlmacen, $idCliente, $cantidad),
                'meta' => $metaAcuerdo,
            ]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'guardar_devolucion') {
            try {
                $payload = $this->leerJson();
                $userId = $this->obtenerUsuarioId();

                if (empty($payload['id_documento']) || empty($payload['motivo']) || empty($payload['detalle'])) {
                    throw new RuntimeException('Faltan datos obligatorios para la devolución.');
                }

                $this->despachoModel->registrarDevolucion(
                    (int) ($payload['id_documento'] ?? 0),
                    (string) ($payload['motivo'] ?? ''),
                    (string) ($payload['resolucion'] ?? ''),
                    is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [],
                    $userId,
                    (string) ($payload['motivo_codigo'] ?? '')
                );

                json_response([
                    'ok' => true,
                    'mensaje' => 'Devolución registrada correctamente. Se actualizó inventario y cuentas por cobrar.'
                ]);
            } catch (Throwable $e) {
                json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
            }
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'revertir') {
            try {
                $payload = $this->leerJson();
                $idDocumento = (int) ($payload['id'] ?? 0);
                $userId = $this->obtenerUsuarioId();

                if ($idDocumento <= 0) {
                    throw new RuntimeException('ID de pedido inválido para revertir.');
                }

                $this->despachoModel->revertirABorrador($idDocumento, $userId);

                json_response([
                    'ok' => true,
                    'mensaje' => 'El pedido ha regresado a Borrador exitosamente.'
                ]);
            } catch (Throwable $e) {
                json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
            }
            return; // ¡Este return es el que evita que se cargue la vista HTML completa!
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'precio_item') {
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $idItemRaw = (string) ($_GET['id_item'] ?? '');
            $cantidad = (float) ($_GET['cantidad'] ?? 1);

            json_response([
                'ok' => true,
                'data' => $this->documentoModel->obtenerPrecioUnitario($idCliente, $idItemRaw, $cantidad),
            ]);
            return;
        }

        if ((string) ($_GET['accion'] ?? '') === 'imprimir') {
            $id = (int) ($_GET['id'] ?? 0);
            $paginas = (int) ($_GET['paginas'] ?? 1); 
            
            if ($paginas < 1) $paginas = 1;
            elseif ($paginas > 20) $paginas = 20;

            if ($id <= 0) die('ID de pedido inválido.');

            $venta = $this->documentoModel->obtener($id);
            if (empty($venta)) die('El pedido no existe.');

            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php'; 
            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();
            require_once BASE_PATH . '/vendor/autoload.php';

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(array('isRemoteEnabled' => true));
            $dompdf->setOptions($options);

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_pedido.php';
            $html = (string) ob_get_clean();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream('Despacho_' . $venta['codigo'] . '.pdf', ['Attachment' => false]);
            return;
        }

        if ((string) ($_GET['accion'] ?? '') === 'imprimir_proforma') {
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) die('ID de pedido inválido.');

            $venta = $this->documentoModel->obtener($id);
            if (empty($venta)) die('El pedido no existe.');

            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php'; 
            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();
            require_once BASE_PATH . '/vendor/autoload.php';

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(array('isRemoteEnabled' => true));
            $dompdf->setOptions($options);

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_proforma.php';
            $html = (string) ob_get_clean();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream('Proforma_' . $venta['codigo'] . '.pdf', ['Attachment' => false]);
            return;
        }

        // Carga inicial de la página
        $this->render('ventas', [
            'ruta_actual' => 'ventas',
            'ventas'      => $this->documentoModel->listar($filtros),
            'filtros'     => $filtros,
            'almacenes'   => $this->documentoModel->listarAlmacenesActivos(),
            // Llamadas correctas y seguras al modelo
            'cuentas'     => $this->tesoreriaCxcModel->obtenerCuentasActivas(),
            'metodos'     => $this->tesoreriaCxcModel->obtenerMetodosActivos(),
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.crear');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $userId = $this->obtenerUsuarioId();

            $idCliente = (int) ($payload['id_cliente'] ?? 0);
            $fechaEmision = !empty($payload['fecha_emision']) ? trim((string) $payload['fecha_emision']) : null;
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $tipoImpuesto = trim((string) ($payload['tipo_impuesto'] ?? 'exonerado')); 
            $tipoOperacion = trim((string) ($payload['tipo_operacion'] ?? 'VENTA')); 
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];
            
            $esCobroInmediato = filter_var($payload['cobro_inmediato'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $metodosPago = is_array($payload['metodos_pago'] ?? null) ? $payload['metodos_pago'] : [];

            if ($idCliente <= 0 || !$this->documentoModel->clienteEsValido($idCliente)) {
                throw new RuntimeException('Seleccione un cliente válido.');
            }

            if (empty($detalle)) {
                throw new RuntimeException('Debe agregar al menos un ítem al pedido.');
            }

            if ($esCobroInmediato && $tipoOperacion !== 'DONACION') {
                if (empty($metodosPago)) {
                    throw new RuntimeException('Debe especificar al menos un método de pago para el cobro inmediato.');
                }
                foreach ($metodosPago as $pago) {
                    if (empty($pago['id_cuenta']) || empty($pago['id_metodo']) || empty($pago['monto']) || (float)$pago['monto'] <= 0) {
                        throw new RuntimeException('Todos los métodos de pago ingresados deben tener cuenta, método y un monto válido.');
                    }
                }
            }

            $itemsUnicos = [];

            foreach ($detalle as $linea) {
                $rawId = trim((string) ($linea['id_item'] ?? ''));
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio = (float) ($linea['precio_unitario'] ?? 0);

                if ($rawId === '' || $rawId === '0') {
                    throw new RuntimeException('Hay líneas sin producto válido.');
                }

                if (isset($itemsUnicos[$rawId])) {
                    throw new RuntimeException('No se permiten productos repetidos en el pedido.');
                }
                $itemsUnicos[$rawId] = true;
                
                if ($cantidad <= 0) {
                    throw new RuntimeException('La cantidad de los ítems debe ser mayor a 0.');
                }
                if ($precio < 0) {
                    throw new RuntimeException('El precio no puede ser negativo.');
                }
            }

            $id = $this->documentoModel->crearOActualizar([
                'id' => (int) ($payload['id'] ?? 0),
                'id_cliente' => $idCliente,
                'fecha_emision' => $fechaEmision, 
                'observaciones' => $observaciones,
                'tipo_impuesto' => $tipoImpuesto,
                'tipo_operacion' => $tipoOperacion, 
            ], $detalle, $userId);

            if ($esCobroInmediato && $tipoOperacion !== 'DONACION') {
                $ok = $this->documentoModel->aprobar($id, $userId);
                if (!$ok) throw new RuntimeException('Error interno al intentar aprobar el pedido para su cobro.');
                
                $this->tesoreriaCxcModel->crearDesdeVenta($id, $userId);
                
                $deuda = $this->tesoreriaCxcModel->obtenerPorVenta($id);
                if (empty($deuda)) throw new RuntimeException('No se pudo encontrar la deuda generada para aplicar el cobro.');
                
                foreach ($metodosPago as $pago) {
                    $idCuenta = (int) $pago['id_cuenta'];
                    $idMetodo = (int) $pago['id_metodo'];
                    $montoPago = (float) $pago['monto'];
                    
                    $this->tesoreriaCxcModel->registrarCobroDirecto(
                        (int) $deuda['id'],
                        $idCuenta,
                        $idMetodo,
                        $montoPago,
                        $fechaEmision ?? date('Y-m-d'),
                        'Cobro Inmediato (Múltiple) - Caja',
                        $userId
                    );
                }
                
                json_response(['ok' => true, 'mensaje' => 'Pedido guardado, aprobado y cobrado exitosamente.', 'id' => $id]);
                return;
            }

            json_response(['ok' => true, 'mensaje' => 'Pedido guardado correctamente.', 'id' => $id]);

        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function aprobar(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.aprobar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idDocumento = (int) ($payload['id'] ?? 0);
            $userId = $this->obtenerUsuarioId();

            if ($idDocumento <= 0) throw new RuntimeException('Pedido inválido.');

            $venta = $this->documentoModel->obtener($idDocumento);
            $ok = $this->documentoModel->aprobar($idDocumento, $userId);
            if (!$ok) throw new RuntimeException('No se pudo aprobar. Verifique que el pedido esté en borrador.');

            $tipoOperacion = $venta['tipo_operacion'] ?? 'VENTA';
            if ($tipoOperacion !== 'DONACION') {
                $this->tesoreriaCxcModel->crearDesdeVenta($idDocumento, $userId);
            }

            json_response(['ok' => true, 'mensaje' => 'Pedido aprobado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function anular(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.eliminar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idDocumento = (int) ($payload['id'] ?? 0);
            $userId = $this->obtenerUsuarioId();

            if ($idDocumento <= 0) throw new RuntimeException('Pedido inválido.');

            $this->documentoModel->anular($idDocumento, $userId);
            json_response(['ok' => true, 'mensaje' => 'Pedido anulado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function despachar(): void
    {
        AuthMiddleware::handle();

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Acceso denegado'], 400);
            return;
        }

        try {
            $data = $this->leerJson(); 
            
            $idDocumento = (int) ($data['id_documento'] ?? 0);
            $cerrarForzado = filter_var(($data['cerrar_forzado'] ?? false), FILTER_VALIDATE_BOOLEAN);
            $observaciones = trim($data['observaciones'] ?? '');
            
            $fechaDespacho = trim($data['fecha_despacho'] ?? '');
            if (empty($fechaDespacho)) {
                $fechaDespacho = date('Y-m-d'); 
            }

            $envasesDevueltos = is_array($data['envases_devueltos'] ?? null) ? $data['envases_devueltos'] : [];

            $detalle = $data['detalle'] ?? [];

            if ($idDocumento <= 0) throw new RuntimeException('Documento inválido');
            if (empty($detalle) || !is_array($detalle)) throw new RuntimeException('No hay ítems para despachar');

            foreach ($detalle as $linea) {
                if (empty($linea['id_almacen']) || $linea['id_almacen'] <= 0) {
                    throw new RuntimeException('Error: Hay filas sin almacén seleccionado.');
                }
            }

            $userId = $this->obtenerUsuarioId(); 
            
            $this->documentoModel->guardarDespacho($idDocumento, $detalle, $observaciones, $cerrarForzado, $userId, $fechaDespacho, $envasesDevueltos);
            
            json_response(['ok' => true, 'mensaje' => 'Despacho registrado correctamente']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    private function leerJson(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode((string) $input, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new RuntimeException('Error en los datos enviados (JSON inválido).');
        }
        return $data;
    }

    private function obtenerUsuarioId(): int
    {
        $id = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('La sesión ha expirado o es inválida.');
        }
        return $id;
    }
}