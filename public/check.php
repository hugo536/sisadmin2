<?php
/**
 * SCRIPT DE DEPURACIÓN: Cálculo de Depreciación Activos Fijos
 */
declare(strict_types=1);

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h2>🔍 SCRIPT DE DEPURACIÓN: Matemática de Depreciación</h2>";

// --- 1. SIMULAMOS LO QUE EL USUARIO ESCRIBE EN EL FORMULARIO ---
// Puedes cambiar estos valores para probar diferentes escenarios
$datos_simulados = [
    'fecha_adquisicion' => '2021-01-15', // Hace un par de años
    'costo_adquisicion' => 12000,
    'valor_residual'    => 2000,
    'vida_util_meses'   => 60 // 5 años
];

echo "<table border='1' cellpadding='8' style='border-collapse: collapse; background: #f9f9f9;'>";
echo "<tr style='background: #ddd;'><th>Campo del Formulario</th><th>Valor Simulado</th></tr>";
foreach ($datos_simulados as $k => $v) {
    echo "<tr><td>$k</td><td><b>$v</b></td></tr>";
}
echo "</table><br>";

try {
    // --- 2. EL CÁLCULO EXACTO DE TU MODELO ---
    $fechaAdq = new DateTime($datos_simulados['fecha_adquisicion']);
    $hoy = new DateTime();
    $mesesPasados = 0;
    $vida_util = max(1, (int)$datos_simulados['vida_util_meses']);
    
    echo "<p><b>▶ Paso 1:</b> Fechas reconocidas. Hoy es <b>" . $hoy->format('Y-m-d') . "</b></p>";

    if ($fechaAdq < $hoy) {
        $diff = $fechaAdq->diff($hoy);
        // Multiplicamos los años por 12 y sumamos los meses
        $mesesPasados = ($diff->y * 12) + $diff->m;
        echo "<p><b>▶ Paso 2:</b> Diferencia real calculada: {$diff->y} años y {$diff->m} meses (Total: <b>$mesesPasados meses pasados</b>).</p>";
    } else {
        echo "<p><b>▶ Paso 2:</b> El activo se compró hoy o en el futuro. Meses = 0.</p>";
    }

    if ($mesesPasados > $vida_util) {
        echo "<p style='color:orange;'><b>⚠️ Aviso:</b> El activo ya superó su vida útil ($mesesPasados > $vida_util). Se limitará el cálculo a $vida_util meses.</p>";
        $mesesPasados = $vida_util;
    }

    $costo = round((float)$datos_simulados['costo_adquisicion'], 4);
    $residual = round((float)$datos_simulados['valor_residual'], 4);
    $base = max(0, $costo - $residual);
    
    echo "<p><b>▶ Paso 3:</b> Base depreciable (Costo $costo - Residual $residual) = <b>$base</b></p>";

    // Depreciación por mes * meses pasados
    $dep_mensual = $base / $vida_util;
    $dep_acumulada = round($dep_mensual * $mesesPasados, 4);
    
    echo "<p><b>▶ Paso 4:</b> Fórmula final -> (Base $base / Vida $vida_util) * $mesesPasados meses = <b>$dep_acumulada</b></p>";

    $valorLibros = max((float)$residual, $costo - $dep_acumulada);

    echo "<hr>";
    echo "<h2 style='color:green;'>✅ RESULTADO FINAL (LO QUE SE GUARDARÍA EN LA BD)</h2>";
    echo "<h3>💰 Dep. Acumulada: $ " . number_format($dep_acumulada, 4) . "</h3>";
    echo "<h3>📘 Valor en Libros: $ " . number_format($valorLibros, 4) . "</h3>";

} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ ERROR FATAL DE PHP: " . $e->getMessage() . "</h2>";
}
echo "</div>";