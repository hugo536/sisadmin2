<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/rrhh/PlanillasModel.php';
require_once BASE_PATH . '/app/models/TercerosModel.php'; 
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';

class PlanillasController extends Controlador
{
    private PlanillasModel $planillasModel;
    private TercerosModel $tercerosModel;
    private TesoreriaCuentaModel $cuentasModel;

    public function __construct()
    {
        parent::__construct();
        $this->planillasModel = new PlanillasModel();
        $this->tercerosModel = new TercerosModel();
        $this->cuentasModel = new TesoreriaCuentaModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver'); 

        // 1. Recoger filtros de fecha (Por defecto: La semana actual)
        $fechaActual = new DateTimeImmutable();
        $desdeDefault = $fechaActual->modify('monday this week')->format('Y-m-d');
        $hastaDefault = $fechaActual->modify('sunday this week')->format('Y-m-d');

        $desde = (string) ($_GET['desde'] ?? $desdeDefault);
        $hasta = (string) ($_GET['hasta'] ?? $hastaDefault);
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = $desdeDefault;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = $hastaDefault;
        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

        $idTercero = (int) ($_GET['id_tercero'] ?? 0);
        if ($idTercero <= 0) $idTercero = null;

        // NUEVO: Capturar el filtro de frecuencia de pago
        $frecuencia = strtoupper(trim((string) ($_GET['frecuencia_pago'] ?? '')));
        $frecuenciasPermitidas = ['SEMANAL', 'QUINCENAL', 'MENSUAL'];
        if ($frecuencia === '' || !in_array($frecuencia, $frecuenciasPermitidas, true)) {
            $frecuencia = null;
        }

        // 2. Obtener datos crudos del Modelo (Agregamos la frecuencia)
        $resumenCrudo = $this->planillasModel->obtenerResumenPlanilla($desde, $hasta, $idTercero, $frecuencia);


        $semana = '';
        $inicioSemana = DateTimeImmutable::createFromFormat('Y-m-d', $desde);
        if ($inicioSemana instanceof DateTimeImmutable) {
            $lunes = $inicioSemana->modify('monday this week');
            $domingo = $inicioSemana->modify('sunday this week');
            if ($lunes->format('Y-m-d') === $desde && $domingo->format('Y-m-d') === $hasta) {
                $semana = $inicioSemana->format('o-\WW');
            }
        }

        // 3. Procesar Matemáticas Financieras
        $planillasCalculadas = $this->procesarCalculosFinancieros($resumenCrudo);

        // 4. Totales Generales para la cabecera del Dashboard
        $totalPlanilla = array_sum(array_column($planillasCalculadas, 'neto_a_pagar'));
        $totalDescuentos = array_sum(array_column($planillasCalculadas, 'monto_descuento_tardanza'));
        $totalExtras = array_sum(array_column($planillasCalculadas, 'monto_horas_extras'));

        // 5. Renderizar la Vista
        $this->render('rrhh/planillas', [
            'ruta_actual' => 'planillas',
            'desde' => $desde,
            'hasta' => $hasta,
            'id_tercero' => $idTercero,
            'frecuencia' => $frecuencia, // Pasamos la frecuencia para mantenerla en el formulario HTML
            'semana' => $semana,
            'empleados' => $this->tercerosModel->listar(), 
            'planillas' => $planillasCalculadas,
            'cuentas' => $this->cuentasModel->listarActivas(),
            'metodos' => $this->listarMetodosPago(),
            'totales' => [
                'planilla' => $totalPlanilla,
                'descuentos' => $totalDescuentos,
                'extras' => $totalExtras
            ]
        ]);
    }

    private function listarMetodosPago(): array
    {
        $sql = 'SELECT id, nombre
                FROM tesoreria_metodos_pago
                WHERE estado = 1 AND deleted_at IS NULL
                ORDER BY nombre ASC';

        return Conexion::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Motor de cálculo de nómina/planillas.
     */
    private function procesarCalculosFinancieros(array $resumenCrudo): array
    {
        $resultado = [];

        foreach ($resumenCrudo as $row) {
            $estadoPago = (string) ($row['estado_pago'] ?? 'PENDIENTE');
            if ($estadoPago === 'SIN_REGISTROS') {
                continue; 
            }

            // --- VARIABLES BASE (NUEVA LÓGICA) ---
            // Tomamos el sueldo_basico que ahora sirve tanto para sueldo mensual como para jornal diario
            $remuneracionBase = (float) ($row['sueldo_basico'] ?? 0);
            
            // Forzamos mayúsculas para evitar errores ('Mensual', 'MENSUAL', 'mensual')
            $tipoPago = strtoupper(trim((string) ($row['tipo_pago'] ?? 'MENSUAL')));
            
            $diasAsistidos = (int) ($row['dias_asistidos'] ?? 0);
            $diasJustificados = (int) ($row['dias_justificados'] ?? 0);
            $diasFalta = (int) ($row['dias_falta'] ?? 0);
            
            $horasExtras = (float) ($row['total_horas_extras'] ?? 0);
            $minutosTardanza = (int) ($row['total_minutos_tardanza'] ?? 0);

            // Días a pagar (Asistidos + Justificados con goce)
            $diasAPagar = $diasAsistidos + $diasJustificados;

            // --- CÁLCULO DE TARIFAS ---
            $tarifaDiaria = 0;
            
            if ($tipoPago === 'MENSUAL' || $tipoPago === 'QUINCENAL') {
                // Si le pago fijo al mes, divido su sueldo base entre 30 días
                $tarifaDiaria = $remuneracionBase > 0 ? ($remuneracionBase / 30) : 0;
            } elseif ($tipoPago === 'SEMANAL') {
                // Si es semanal (Jornal), la remuneración base que pusimos en el formulario ES la tarifa de 1 día
                $tarifaDiaria = $remuneracionBase;
            } else {
                // Fallback de seguridad
                $tarifaDiaria = 0; 
            }

            // Calculamos el valor de 1 hora y 1 minuto (Asumiendo jornada de 8 horas)
            $tarifaHora = $tarifaDiaria / 8;
            $tarifaMinuto = $tarifaHora / 60;

            // --- CÁLCULO DE INGRESOS ---
            $sueldoCalculado = $tarifaDiaria * $diasAPagar;
            
            // Horas extras (Por defecto: 25% adicional sobre el valor hora normal)
            $tarifaHoraExtra = $tarifaHora * 1.25; 
            $montoHorasExtras = $horasExtras * $tarifaHoraExtra;

            $bonosManuales = 0; 
            $asignacionFamiliar = (int)($row['asignacion_familiar'] ?? 0) === 1 ? (102.50 / 30 * $diasAPagar) : 0;

            $totalIngresos = $sueldoCalculado + $montoHorasExtras + $asignacionFamiliar + $bonosManuales;

            // --- CÁLCULO DE DESCUENTOS ---
            $montoDescuentoTardanza = $minutosTardanza * $tarifaMinuto;
            
            $totalDescuentos = $montoDescuentoTardanza;

            // --- TOTAL NETO ---
            $netoAPagar = $totalIngresos - $totalDescuentos;

            // --- ENSAMBLAR FILA RESULTANTE ---
            $resultado[] = [
                'id_tercero' => $row['id_tercero'],
                'numero_documento' => $row['numero_documento'],
                'nombre_completo' => $row['nombre_completo'],
                'cargo' => $row['cargo'],
                'moneda' => $row['moneda'] ?? 'PEN',
                'tipo_pago' => $tipoPago, // Lo pasamos a la vista para las insignias visuales
                
                // Estadísticas de tiempo
                'dias_asistidos' => $diasAsistidos,
                'dias_falta' => $diasFalta,
                'horas_extras' => round($horasExtras, 2),
                'minutos_tardanza' => $minutosTardanza,
                
                // Tarifas 
                'tarifa_diaria' => round($tarifaDiaria, 2),
                'tarifa_hora' => round($tarifaHora, 2),

                // Dinero
                'sueldo_base_calculado' => round($sueldoCalculado, 2),
                'monto_horas_extras' => round($montoHorasExtras, 2),
                'asignacion_familiar' => round($asignacionFamiliar, 2),
                'monto_descuento_tardanza' => round($montoDescuentoTardanza, 2),
                
                'total_ingresos' => round($totalIngresos, 2),
                'total_descuentos' => round($totalDescuentos, 2),
                'neto_a_pagar' => round($netoAPagar, 2),
                
                'estado_pago' => $estadoPago
            ];
        }

        return $resultado;
    }

    // ENDPOINT: Recibe el POST del modal para pagar y descontar de tesorería
    public function registrar_pago(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver'); 

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('planillas');
        }

        $idEmpleado = (int) ($_POST['id_empleado'] ?? 0);
        $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);
        $idMetodoPago = (int) ($_POST['id_metodo_pago'] ?? 0);
        
        $fechaInicio = (string) ($_POST['fecha_inicio'] ?? '');
        $fechaFin = (string) ($_POST['fecha_fin'] ?? '');
        $fechaPago = (string) ($_POST['fecha_pago'] ?? date('Y-m-d'));
        $referencia = (string) ($_POST['referencia'] ?? '');

        if ($idEmpleado <= 0 || $idCuenta <= 0 || $idMetodoPago <= 0 || empty($fechaInicio) || empty($fechaFin)) {
            $msgError = urlencode('Datos incompletos para el pago.');
            redirect("planillas?error={$msgError}");
            return;
        }

        // Seguridad: Recalcular el monto a pagar desde el backend usando la misma lógica
        $resumenCrudo = $this->planillasModel->obtenerResumenPlanilla($fechaInicio, $fechaFin, $idEmpleado);
        
        if (empty($resumenCrudo)) {
            $msgError = urlencode('No se encontraron datos de asistencia para este periodo.');
            redirect("planillas?error={$msgError}");
            return;
        }

        $calculoReal = $this->procesarCalculosFinancieros($resumenCrudo);
        if (empty($calculoReal) || $calculoReal[0]['estado_pago'] === 'PAGADA') {
            $msgError = urlencode('La planilla ya fue pagada o no hay monto calculable.');
            redirect("planillas?error={$msgError}");
            return;
        }

        $montoRealBackend = (float) $calculoReal[0]['neto_a_pagar'];

        if ($montoRealBackend <= 0) {
            $msgError = urlencode('El monto a pagar no puede ser menor o igual a cero.');
            redirect("planillas?error={$msgError}");
            return;
        }

        $datosPago = [
            'id_empleado' => $idEmpleado,
            'id_cuenta' => $idCuenta,
            'id_metodo_pago' => $idMetodoPago,
            'monto_pagar' => $montoRealBackend, 
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'fecha_pago' => $fechaPago,
            'referencia' => $referencia
        ];

        // Ejecutar el pago transaccional
        $userId = AuthMiddleware::getUserId();
        $exito = $this->planillasModel->registrarPagoPlanilla($datosPago, $userId);

        if ($exito) {
            redirect("planillas?desde={$fechaInicio}&hasta={$fechaFin}&ok=1");
        } else {
            $msgError = urlencode('Error al registrar el pago. Verifica que la cuenta de tesorería tenga saldo suficiente.');
            redirect("planillas?desde={$fechaInicio}&hasta={$fechaFin}&error={$msgError}");
        }
    }

    // --- ENDPOINTS PARA IMPRESIÓN ---
    public function imprimirTicket(): void
    {
        AuthMiddleware::handle();
        $idTercero = (int) ($_GET['id'] ?? 0);
        $desde = (string) ($_GET['desde'] ?? '');
        $hasta = (string) ($_GET['hasta'] ?? '');

        if ($idTercero <= 0 || empty($desde) || empty($hasta)) {
            die("Datos incompletos para generar el ticket.");
        }

        $resumenCrudo = $this->planillasModel->obtenerResumenPlanilla($desde, $hasta, $idTercero);
        if (empty($resumenCrudo)) {
            die("No hay datos de asistencia en este rango para el empleado seleccionado.");
        }

        $calculo = $this->procesarCalculosFinancieros($resumenCrudo)[0];
        $detalleAsistencia = $this->planillasModel->obtenerDetalleAsistenciaEmpleado($idTercero, $desde, $hasta);

        $this->render('planillas_impresion_ticket', [
            'calculo' => $calculo,
            'detalle' => $detalleAsistencia,
            'desde' => $desde,
            'hasta' => $hasta
        ], true); 
    }
}
