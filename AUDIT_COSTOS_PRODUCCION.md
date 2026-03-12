# Auditoría profunda: costos unitarios y costo de producción

## 1) Estado actual del sistema (resumen ejecutivo)

El sistema **ya tiene una base funcional** para costo de producción:

- Calcula y guarda **costo teórico unitario** en recetas (`produccion_recetas.costo_teorico_unitario`), a partir de insumos, cantidades y merma.
- Al ejecutar una orden, calcula y guarda **costo real**: suma consumos reales y distribuye entre cantidad producida para obtener costo unitario de ingreso.
- Tiene reporte de producción con costo unitario promedio y costo total de consumos.

Conclusión: **sí puede funcionar hoy** para costeo básico de manufactura (materia prima directa).

## 2) Dónde conviene crear “el lugar” para cálculo y análisis

### Opción recomendada (mínimo riesgo)

Crear un nuevo submódulo de reportes:

- Ruta sugerida: `reportes/costos_produccion`
- Vista sugerida: `app/views/reportes/costos_produccion.php`
- Modelo sugerido: `app/models/reportes/ReporteCostosProduccionModel.php`

¿Por qué aquí?

1. Ya existe módulo de reportes con patrón controlador-modelo-vista.
2. La información necesaria ya existe en tablas de producción e inventario.
3. Evita tocar el flujo transaccional de ejecución de órdenes (menos riesgo operativo).

### Qué mostrar en ese nuevo lugar

- **Costo unitario teórico vs real** por orden y por producto.
- **Variación absoluta y %** (desviación de costos).
- **Costo total de orden** (consumo + adicionales futuros).
- **Trazabilidad de insumos**: cantidades, costos unitarios, merma aplicada.
- **Semáforos** por umbral de variación (verde/amarillo/rojo).

## 3) Qué ya está bien implementado

1. **Costeo teórico en recetas**
   - Al crear receta se calcula costo total por insumo considerando merma, y se guarda como costo teórico unitario.

2. **Costeo real al ejecutar orden**
   - Se registran consumos reales con costo unitario.
   - Se calcula costo total de consumos.
   - Se calcula costo unitario del ingreso (`costoTotalConsumo / cantidadTotalProducida`) y se registra.

3. **Reporte productivo base**
   - Ya existe reporte por producto con cantidad producida y costo unitario promedio.
   - Ya existe consumo de insumos con costo total.

## 4) Qué le falta para un costeo “completo/gerencial”

### A. Falta separar componentes del costo de producción

Hoy el costo se concentra en materia prima/insumos. Faltaría incorporar:

- Mano de obra directa (MOD).
- Costos indirectos de fabricación (CIF): energía, depreciación planta, mantenimiento, etc.
- Costos por centro de costo / línea / proceso.

### B. Falta “snapshot” de receta usada por orden

Si una receta cambia con el tiempo, conviene guardar en la orden:

- costo teórico al momento de planificar,
- versión exacta de receta y parámetros de cálculo,

para análisis histórico consistente (sin reescribir pasado).

### C. Falta tablero de variaciones por orden

Actualmente hay datos, pero no un tablero formal que compare:

- Teórico planeado vs real ejecutado.
- Variación por insumo, por merma, por rendimiento.

### D. Falta una política clara de origen de costo

`obtenerCostoReferencial()` usa fallback (costo referencial, receta activa o último movimiento). Está bien para operar, pero para contabilidad de costos conviene definir prioridad oficial por tipo de ítem y periodo de valuación.

## 5) ¿Puede funcionar tal cual hoy?

Sí, **funciona para costeo operativo básico**:

- Puedes obtener costo teórico de receta.
- Puedes obtener costo real al ejecutar órdenes.
- Puedes ver agregados de costo en reportes.

No alcanza todavía para **costeo industrial completo** (MOD/CIF/desviaciones avanzadas/analítica histórica sólida).

## 6) Plan recomendado (práctico)

### Fase 1 (rápida)

- Crear `reportes/costos_produccion` con:
  - costo teórico vs real por orden,
  - variación y ranking de desviaciones,
  - filtros por fecha, producto, orden.

### Fase 2 (control)

- Guardar snapshot de costo teórico y versión de receta en `produccion_ordenes`.
- Registrar métricas de rendimiento real (merma real, eficiencia).

### Fase 3 (gerencial)

- Añadir MOD/CIF por orden/centro de costo.
- Consolidar costo total de producción (MD + MOD + CIF).
- Integrar reporte de margen (precio vs costo real total).

## 7) Riesgos detectados en el estado actual

- El script SQL base de producción (`20260213_produccion_base.sql`) contiene bloques duplicados/inconsistentes; conviene depurarlo para evitar instalaciones nuevas defectuosas.
- El costeo teórico y real existe, pero si no se congela el teórico por orden, la comparación histórica puede quedar sesgada cuando cambian recetas.

## 8) Veredicto final

Tu sistema **sí está adelantado** y **ya puede operar costeo unitario básico**.

La mejor decisión ahora es **crear un reporte especializado de costos de producción** (sin romper lo actual), y luego robustecer con snapshot + MOD/CIF para pasar de costeo operativo a costeo gerencial completo.
