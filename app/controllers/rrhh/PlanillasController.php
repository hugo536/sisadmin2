<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/rrhh/PlanillasModel.php';
require_once BASE_PATH . '/app/models/TercerosModel.php'; // Para listar los empleados en el filtro

class PlanillasController extends Controlador
{
    private PlanillasModel $planillasModel;
    private TercerosModel $tercerosModel;

    public function __construct()
    {
        parent::__construct();
        $this->planillasModel = new PlanillasModel();
        $this->tercerosModel = new TercerosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver'); // Idealmente crearías un permiso 'planillas.ver'

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

        // 2. Obtener datos crudos del Modelo
        $resumenCrudo = $this->planillasModel->obtenerResumenPlanilla($desde, $hasta, $idTercero);

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
            'empleados' => $this->tercerosModel->listar(), // Filtramos solo empleados en la vista
            'planillas' => $planillasCalculadas,
            'totales' => [
                'planilla' => $totalPlanilla,
                'descuentos' => $totalDescuentos,
                'extras' => $totalExtras
            ]
        ]);
    }

    /**
     * Motor de cálculo de nómina/planillas.
     */
    private function procesarCalculosFinancieros(array $resumenCrudo): array
    {
        $resultado = [];

        foreach ($resumenCrudo as $row) {
            // --- VARIABLES BASE ---
            $sueldoBasico = (float) ($row['sueldo_basico'] ?? 0);
            $tipoPago = strtolower(trim((string) ($row['tipo_pago'] ?? 'mensual')));
            $pagoDiarioConfig = (float) ($row['pago_diario'] ?? 0);
            
            $diasAsistidos = (int) ($row['dias_asistidos'] ?? 0);
            $diasJustificados = (int) ($row['dias_justificados'] ?? 0);
            $diasFalta = (int) ($row['dias_falta'] ?? 0);
            
            $horasExtras = (float) ($row['total_horas_extras'] ?? 0);
            $minutosTardanza = (int) ($row['total_minutos_tardanza'] ?? 0);

            // Días a pagar (Asistidos + Justificados con goce)
            $diasAPagar = $diasAsistidos + $diasJustificados;

            // --- CÁLCULO DE TARIFAS ---
            $tarifaDiaria = 0;
            
            if ($tipoPago === 'mensual') {
                // Estándar laboral: Sueldo mensual / 30 días
                $tarifaDiaria = $sueldoBasico > 0 ? ($sueldoBasico / 30) : 0;
            } elseif ($tipoPago === 'diario') {
                $tarifaDiaria = $pagoDiarioConfig;
            } else {
                // Por hora (Asumimos 8 horas = 1 día para simplificar el fallback)
                $tarifaDiaria = $pagoDiarioConfig * 8; 
            }

            // Calculamos el valor de 1 hora y 1 minuto (Asumiendo jornada de 8 horas)
            $tarifaHora = $tarifaDiaria / 8;
            $tarifaMinuto = $tarifaHora / 60;

            // --- CÁLCULO DE INGRESOS ---
            $sueldoCalculado = $tarifaDiaria * $diasAPagar;
            
            // Horas extras (Por defecto: 25% adicional sobre el valor hora normal)
            $tarifaHoraExtra = $tarifaHora * 1.25; 
            $montoHorasExtras = $horasExtras * $tarifaHoraExtra;

            $bonosManuales = 0; // Aquí en el futuro podrías sumar bonos extra de otra tabla
            $asignacionFamiliar = (int)($row['asignacion_familiar'] ?? 0) === 1 ? (102.50 / 30 * $diasAPagar) : 0; // Ejemplo Perú (10% RMV)

            $totalIngresos = $sueldoCalculado + $montoHorasExtras + $asignacionFamiliar + $bonosManuales;

            // --- CÁLCULO DE DESCUENTOS ---
            $montoDescuentoTardanza = $minutosTardanza * $tarifaMinuto;
            // Las faltas ya están descontadas porque solo multiplicamos $tarifaDiaria * $diasAPagar
            
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
                
                // Estadísticas de tiempo
                'dias_asistidos' => $diasAsistidos,
                'dias_falta' => $diasFalta,
                'horas_extras' => round($horasExtras, 2),
                'minutos_tardanza' => $minutosTardanza,
                
                // Tarifas (Para mostrar en la vista)
                'tarifa_diaria' => round($tarifaDiaria, 2),
                'tarifa_hora' => round($tarifaHora, 2),

                // Dinero
                'sueldo_base_calculado' => round($sueldoCalculado, 2),
                'monto_horas_extras' => round($montoHorasExtras, 2),
                'asignacion_familiar' => round($asignacionFamiliar, 2),
                'monto_descuento_tardanza' => round($montoDescuentoTardanza, 2),
                
                'total_ingresos' => round($totalIngresos, 2),
                'total_descuentos' => round($totalDescuentos, 2),
                'neto_a_pagar' => round($netoAPagar, 2)
            ];
        }

        return $resultado;
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

        // Reutilizamos el motor matemático para este único empleado
        $resumenCrudo = $this->planillasModel->obtenerResumenPlanilla($desde, $hasta, $idTercero);
        if (empty($resumenCrudo)) {
            die("No hay datos de asistencia en este rango para el empleado seleccionado.");
        }

        $calculo = $this->procesarCalculosFinancieros($resumenCrudo)[0];
        $detalleAsistencia = $this->planillasModel->obtenerDetalleAsistenciaEmpleado($idTercero, $desde, $hasta);

        // Renderizamos una vista especial sin menú lateral, lista para la impresora térmica (Ticketera 80mm)
        $this->render('planillas_impresion_ticket', [
            'calculo' => $calculo,
            'detalle' => $detalleAsistencia,
            'desde' => $desde,
            'hasta' => $hasta
        ], true); // 'true' asumiendo que tu método render soporta un layout vacío/limpio
    }
}