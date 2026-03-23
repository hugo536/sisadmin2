# Transferencias internas entre cuentas de tesorería

## Estado actual (diagnóstico)

Hoy el módulo de tesorería **no soporta transferencias internas cuenta-a-cuenta** como flujo nativo.

Evidencias en código:

- La tabla `tesoreria_movimientos` solo permite `tipo` = `COBRO|PAGO` y `origen` = `CXC|CXP`; no existe tipo/origen de transferencia interna.
- El modelo `TesoreriaMovimientoModel` valida e inserta movimientos únicamente contra documentos `CXC` o `CXP` (`id_origen` obligatorio) y rechaza otros orígenes.
- La UI de Cuentas está orientada a “Cobros/Pagos” y no muestra acción de “Transferir entre cuentas”.

## Propuesta de implementación (MVP seguro)

### 1) Base de datos

Crear soporte explícito para transferencia interna:

- Opción A (recomendada): nueva tabla `tesoreria_transferencias` (cabecera) y reutilizar `tesoreria_movimientos` con dos asientos enlazados (`PAGO` en origen + `COBRO` en destino).
- Opción B: extender `tesoreria_movimientos` para permitir nuevo `origen = INTERNA` y columnas `id_cuenta_contraparte` + `id_transferencia`.

Recomendación para trazabilidad/auditoría: mantener cabecera (`tesoreria_transferencias`) con:

- `id`, `fecha`, `id_cuenta_origen`, `id_cuenta_destino`, `moneda`, `monto`, `tipo_cambio`, `comision`, `referencia`, `observaciones`, `estado`.
- Restricción: `id_cuenta_origen <> id_cuenta_destino`.
- Restricción: saldo suficiente en cuenta origen.

### 2) Dominio / backend

Agregar servicio transaccional `registrarTransferenciaInterna(...)` que en **una sola transacción SQL**:

1. Bloquee cuenta origen y destino (`FOR UPDATE`).
2. Valide cuentas activas, moneda, monto y saldo.
3. Inserte cabecera de transferencia.
4. Inserte dos movimientos enlazados:
   - egreso (`PAGO`) en cuenta origen,
   - ingreso (`COBRO`) en cuenta destino.
5. Registre asiento contable automático de transferencia (debe/haber entre cuentas de tesorería).
6. Confirme transacción.

Para anulación, crear `anularTransferenciaInterna(id)` que revierta ambos movimientos de forma atómica.

### 3) UI/UX

En `Tesorería > Cuentas` añadir botón **Transferir**:

- Cuenta origen
- Cuenta destino
- Moneda
- Monto
- Fecha
- Referencia / observaciones
- (Opcional) comisión y cuenta de gasto

Luego mostrar en `Tesorería > Movimientos` con etiqueta visual `TRANSFERENCIA` y vínculo a la transferencia padre.

### 4) Permisos y auditoría

Agregar permisos:

- `tesoreria.transferencias.ver`
- `tesoreria.transferencias.registrar`
- `tesoreria.transferencias.anular`

Registrar bitácora con usuario, IP y timestamp por alta/anulación.

### 5) Casos de prueba mínimos

- Transferencia válida entre cuentas mismas moneda.
- Rechazo por saldo insuficiente.
- Rechazo por cuenta origen = destino.
- Rechazo por cuenta inactiva.
- Anulación revierte ambos lados y asiento contable.
- Concurrencia: dos transferencias simultáneas no deben sobregirar saldo.

## Ruta rápida de entrega

1. Migración SQL (tabla/columnas + índices + permisos).
2. Método en `TesoreriaMovimientoModel` o nuevo `TesoreriaTransferenciaModel`.
3. Endpoints en `TesoreriaController`.
4. Modal + JS en vista `tesoreria_cuentas`.
5. Ajuste de listado en `tesoreria_movimientos`.
6. Pruebas manuales y validaciones de seguridad.

