# Investigación: percepciones semanales mostrando S/ 28.57 en lugar de jornal esperado

## Caso reportado
- En Planillas, un empleado semanal con 4 días trabajados aparece con **Percepciones = S/ 28.57**.
- En Terceros, el empleado está configurado con **Frecuencia = Semanal (Pago por jornal)** y **Sueldo Básico = 50.00**.
- Esperado funcional: si 50.00 es jornal diario y trabajó 4 días, el base debería aproximar **S/ 200.00** (antes de deducciones).

## Hallazgos técnicos

### 1) El frontend indica que en SEMANAL el campo es jornal diario
En la UI de empleados, cuando `tipo_pago = SEMANAL`, se cambia la etiqueta a **"Pago por Día (Jornal)"** y la ayuda pide explícitamente poner el valor de 1 día (ej. 50.00). Es decir, para semanal, el valor ingresado en `sueldo_basico` se está usando semánticamente como pago diario.

Archivo:
- `public/assets/js/terceros/empleados.js` (función `togglePagoFields`, líneas donde define etiqueta/ayuda para semanal).

### 2) El backend fuerza `pago_diario = 0` al guardar empleado
En el controlador de terceros, incluso para empleados semanales, se fuerza:

```php
$data['pago_diario'] = '0';
```

Esto deja siempre vacío/cero el campo específico diario en base.

Archivo:
- `app/controllers/TercerosController.php` (bloque de validación de empleado).

### 3) En planillas, si `pago_diario <= 0`, divide `sueldo_basico` entre 7 para semanal
El motor de nómina hace fallback así:
- semanal: divisor 7
- quincenal: 15
- mensual: 30

Entonces, con `sueldo_basico = 50` y `SEMANAL`:
- `pagoDiario = 50 / 7 = 7.142857...`
- con 4 días pagados: `7.142857 * 4 = 28.57`

Ese cálculo coincide exactamente con el síntoma reportado.

Archivos:
- `app/models/rrhh/PlanillasModel.php` en `generarLoteNomina`.
- `app/models/rrhh/PlanillasModel.php` en `recalcularLoteNomina` (misma lógica duplicada).

## Diagnóstico
Hay una **inconsistencia de semántica del dato `sueldo_basico` para frecuencia semanal**:
- **Frontend**: para semanal lo trata como **jornal diario**.
- **Backend planillas (fallback)**: para semanal lo trata como **monto semanal** (porque lo divide entre 7).

Además, el backend de terceros fuerza `pago_diario = 0`, por lo que siempre cae en ese fallback y produce importes subpagados.

## Propuesta de solución (antes de programar)

### Objetivo
Unificar criterio de cálculo para que **SEMANAL = pago por jornal diario** (según UI actual), evitando divisiones incorrectas.

### Opción recomendada (mínimo riesgo funcional)
1. **Normalizar fuente del pago diario en RRHH**:
   - Si `tipo_pago = SEMANAL` y `pago_diario <= 0`, usar `sueldo_basico` **directo** como pago diario (sin dividir).
   - Mantener divisor 15/30 para quincenal/mensual cuando corresponda.
2. Aplicar la misma regla en:
   - `generarLoteNomina`
   - `recalcularLoteNomina`
3. No cambiar UI por ahora (ya comunica correctamente “Pago por Día”).

### Mejora estructural recomendada (segunda fase)
4. Al guardar empleado semanal, persistir también `pago_diario = sueldo_basico` para dejar el dato explícito y eliminar ambigüedad futura.
5. Crear una función privada única en `PlanillasModel` para resolver pago diario y usarla en ambos flujos (generar/recalcular), evitando lógica duplicada.
6. Ejecutar recálculo de lotes BORRADOR impactados por el bug.

## Impacto esperado
- Caso reportado corregido: 4 días * S/ 50 = S/ 200 base (menos deducción por tardanza si aplica).
- Consistencia entre configuración de Terceros y resultado en Planillas.
- Menor riesgo de regresiones al centralizar la lógica de pago diario.

## Plan de validación posterior (cuando se programe)
1. Crear empleado semanal con sueldo/jornal 50.
2. Registrar 4 asistencias válidas en rango semanal.
3. Generar lote semanal y verificar percepción base = 200.00.
4. Recalcular lote y verificar que mantiene el mismo resultado.
5. Probar empleado mensual/quincenal para confirmar que no se rompe el comportamiento actual.
