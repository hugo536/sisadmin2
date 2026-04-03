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

        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'estado' => isset($_GET['estado']) && $_GET['estado'] !== '' ? (string) $_GET['estado'] : null,
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        // 1. Listar Ventas (AJAX)
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response(['ok' => true, 'data' => $this->documentoModel->listar($filtros)]);
            return;
        }

        // 2. Ver Detalle Venta (AJAX) 
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'ver') {
            $id = (int) ($_GET['id'] ?? 0);
            $venta = $this->documentoModel->obtener($id);

            // Anexar almacenes con stock a cada línea del detalle
            if (!empty($venta['detalle']) && is_array($venta['detalle'])) {
                foreach ($venta['detalle'] as &$linea) {
                    $idItem = (int) ($linea['id_item'] ?? 0);
                    $linea['almacenes_disponibles'] = $this->inventarioModel->obtenerAlmacenesConStockPorItem($idItem);
                }
            }

            json_response(['ok' => true, 'data' => $venta]);
            return;
        }

        // 3. Buscador de Clientes (AJAX)
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_clientes') {
            $q = trim((string) ($_GET['q'] ?? ''));
            json_response(['ok' => true, 'data' => $this->documentoModel->buscarClientes($q)]);
            return;
        }

        // 4. Buscador de Items con Stock (AJAX)
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

        // Guardar devolución de venta (AJAX)
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
                    $userId
                );

                json_response([
                    'ok' => true,
                    'mensaje' => 'Devolución de venta registrada correctamente. Se actualizó inventario, kardex y cuentas por cobrar.'
                ]);
            } catch (Throwable $e) {
                json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
            }
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'precio_item') {
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $idItem = (int) ($_GET['id_item'] ?? 0);
            $cantidad = (float) ($_GET['cantidad'] ?? 1);

            json_response([
                'ok' => true,
                'data' => $this->documentoModel->obtenerPrecioUnitario($idCliente, $idItem, $cantidad),
            ]);
            return;
        }

        // 5. Renderizado de Vista Principal

        // --- Generar PDF (Profesional) ---
        if ((string) ($_GET['accion'] ?? '') === 'imprimir') {
            $id = (int) ($_GET['id'] ?? 0);
            $paginas = (int) ($_GET['paginas'] ?? 1);
            if ($paginas < 1) {
                $paginas = 1;
            } elseif ($paginas > 20) {
                $paginas = 20;
            }

            if ($id <= 0) {
                die('ID de pedido inválido.');
            }

            // Obtenemos los datos completos del pedido
            $venta = $this->documentoModel->obtener($id);
            if (empty($venta)) {
                die('El pedido no existe.');
            }

            // Obtenemos los datos de la empresa
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php'; 
            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();

            // Requerimos el autoloader de Composer
            require_once BASE_PATH . '/vendor/autoload.php';

            // Usamos Dompdf
            $dompdf = new \Dompdf\Dompdf();
            
            // Opciones para cargar imágenes y logos
            $options = $dompdf->getOptions();
            $options->set(array('isRemoteEnabled' => true));
            $dompdf->setOptions($options);

            // 1. Capturamos el HTML
            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_pedido.php';
            $htmlBase = (string) ob_get_clean();
            $html = '';
            for ($i = 1; $i <= $paginas; $i++) {
                if ($i > 1) {
                    $html .= '<div style="page-break-before: always;"></div>';
                }
                $html .= $htmlBase;
            }

            // 2. Cargamos el HTML en Dompdf
            $dompdf->loadHtml($html);

            // 3. Papel A4 vertical
            $dompdf->setPaper('A4', 'portrait');

            // 4. Renderizamos
            $dompdf->render();

            // 5. Mostrar en el navegador
            $dompdf->stream('Despacho_' . $venta['codigo'] . '.pdf', ['Attachment' => false]);
            return;
        }

        $this->render('ventas', [
            'ruta_actual' => 'ventas',
            'ventas' => $this->documentoModel->listar($filtros),
            'filtros' => $filtros,
            'almacenes' => $this->documentoModel->listarAlmacenesActivos(),
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
            
            // CAMBIO AQUÍ: Ahora el fallback del backend también es 'exonerado'
            $tipoImpuesto = trim((string) ($payload['tipo_impuesto'] ?? 'exonerado')); 
            $tipoOperacion = trim((string) ($payload['tipo_operacion'] ?? 'VENTA')); 
            
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];

            if ($idCliente <= 0 || !$this->documentoModel->clienteEsValido($idCliente)) {
                throw new RuntimeException('Seleccione un cliente válido.');
            }

            if (empty($detalle)) {
                throw new RuntimeException('Debe agregar al menos un ítem al pedido.');
            }

            $itemsUnicos = [];

            foreach ($detalle as $linea) {
                $idItem = (int) ($linea['id_item'] ?? 0);
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio = (float) ($linea['precio_unitario'] ?? 0);

                if ($idItem <= 0) {
                    throw new RuntimeException('Hay líneas sin producto válido.');
                }

                if (isset($itemsUnicos[$idItem])) {
                    throw new RuntimeException('No se permiten productos repetidos en el pedido.');
                }
                $itemsUnicos[$idItem] = true;
                
                if ($cantidad <= 0) {
                    throw new RuntimeException('La cantidad de los ítems debe ser mayor a 0.');
                }
                if ($precio < 0) {
                    throw new RuntimeException('El precio no puede ser negativo.');
                }
            }

            // Pasamos la cabecera limpia al modelo incluyendo "tipo_operacion"
            $id = $this->documentoModel->crearOActualizar([
                'id' => (int) ($payload['id'] ?? 0),
                'id_cliente' => $idCliente,
                'fecha_emision' => $fechaEmision, 
                'observaciones' => $observaciones,
                'tipo_impuesto' => $tipoImpuesto,
                'tipo_operacion' => $tipoOperacion, // <-- Se envía al modelo
            ], $detalle, $userId);

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

            if ($idDocumento <= 0) {
                throw new RuntimeException('Pedido inválido.');
            }

            // 1. Obtenemos los datos actuales de la venta ANTES de aprobarla
            $venta = $this->documentoModel->obtener($idDocumento);

            // 2. Aprobamos el pedido
            $ok = $this->documentoModel->aprobar($idDocumento, $userId);
            if (!$ok) {
                throw new RuntimeException('No se pudo aprobar. Verifique que el pedido esté en borrador.');
            }

            // 3. LÓGICA DE DONACIÓN: Solo generamos deuda si NO es una donación
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

            if ($idDocumento <= 0) {
                throw new RuntimeException('Pedido inválido.');
            }

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
            $detalle = $data['detalle'] ?? [];

            if ($idDocumento <= 0) {
                throw new RuntimeException('Documento inválido');
            }

            if (empty($detalle) || !is_array($detalle)) {
                throw new RuntimeException('No hay ítems para despachar');
            }

            foreach ($detalle as $linea) {
                if (empty($linea['id_almacen']) || $linea['id_almacen'] <= 0) {
                    throw new RuntimeException('Error: Hay filas sin almacén seleccionado.');
                }
            }

            $userId = $this->obtenerUsuarioId(); 

            $this->documentoModel->guardarDespacho($idDocumento, $detalle, $observaciones, $cerrarForzado, $userId);
            
            json_response(['ok' => true, 'mensaje' => 'Despacho registrado correctamente']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    // --- Helpers Privados ---

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
