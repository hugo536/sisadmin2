# Análisis funcional: interacción de 3 opciones de Envases con Kardex

## Estado actual del sistema (hallazgos)

### 1) Módulo de envases separado de Kardex
- `inventario/envases` usa una tabla propia (`cta_cte_envases`) y no registra automáticamente en `inventario_movimientos`.
- Kardex (`inventario/kardex`) solo consulta `inventario_movimientos`.

**Impacto:** hoy puedes ver cuenta corriente de envases por cliente, pero no necesariamente su traza física en el Kardex general.

### 2) Tipos de operación detectados
- En `ControlEnvasesModel` ya existen reglas para:
  - `RECEPCION_VACIO` (suma)
  - `ENTREGA_LLENO` (resta)
  - `AJUSTE_CLIENTE` (resta)
- En la vista actual del modal de envases se muestran solo 2 opciones (`RECEPCION_VACIO` y `AJUSTE_CLIENTE`).
- En JS del historial sí se contempla `ENTREGA_LLENO`.

**Impacto:** hay una desalineación entre UI y lógica del modelo (el modelo y el historial esperan 3 tipos, pero el formulario actual no los expone completos).

### 3) Kardex actual no distingue movimientos de envases
- Kardex clasifica por `tipo_movimiento` de inventario (`INI`, `AJ+`, `AJ-`, `TRF`, `CON`, `COM`, `VEN`, `PROD`, etc.).
- No hay tipo específico para “operación de envases en cliente” ni relación explícita con `id_tercero`.

**Impacto:** cuesta auditar extremo a extremo: cliente ↔ envase ↔ stock físico ↔ costo/contabilidad.

---

## Propuesta de interacción para las 3 opciones

> Objetivo: que cada operación afecte **dos planos** de forma consistente:
> 1) **Cuenta corriente de envases** (cliente)
> 2) **Inventario físico/Kardex** (almacén)

## Regla base de arquitectura

Cada registro de envase debe manejarse como **operación compuesta atómica**:
1. Insert en `cta_cte_envases` (saldo cliente)
2. Insert en `inventario_movimientos` (kardex físico)
3. (Opcional) Asiento contable/referencia

Si falla uno, se revierte todo (transacción DB).

## Opción A — Recepción de envases vacíos (del cliente)

### Semántica negocio
- El cliente devuelve envases.
- Disminuye la deuda de envases del cliente.
- Aumenta stock físico de envases en planta.

### Efectos recomendados
- `cta_cte_envases.tipo_operacion = RECEPCION_VACIO`, `cantidad = +N`.
- Kardex: movimiento de entrada (`AJ+` o nuevo `ENV_REC`) al almacén de envases.
- Referencia sugerida:
  - `ENVASE | RECEPCION_VACIO | Cliente:{id/nombre} | OP:{uuid}`

## Opción B — Préstamo / Entrega manual al cliente

### Semántica negocio
- Entregas envases al cliente (quedan en su poder).
- Aumenta saldo pendiente de devolución por cliente.
- Disminuye stock físico de envases en planta.

### Efectos recomendados
- `cta_cte_envases.tipo_operacion = ENTREGA_LLENO` (o renombrar a `PRESTAMO_CLIENTE` para mayor claridad).
- Kardex: movimiento de salida (`VEN` o nuevo `ENV_ENT`) desde almacén de envases.
- Registrar `id_tercero` en referencia de movimiento para rastreo.

## Opción C — Ajuste (roto/perdido por cliente)

### Semántica negocio
- El cliente no devolverá envases prestados por pérdida/rotura.
- Debe cerrarse su obligación o convertirse en cargo económico según política.

### Efectos recomendados (dos escenarios)
1. **Sin reposición física inmediata** (más común):
   - Solo `cta_cte_envases`: `AJUSTE_CLIENTE`.
   - Kardex físico: **sin nuevo movimiento** (porque el envase ya estaba fuera en préstamo).
   - (Opcional) generar cargo comercial/contable al cliente.
2. **Con baja física interna adicional** (si realmente estaba en planta y se rompió internamente):
   - No usar `AJUSTE_CLIENTE`; usar flujo de merma interna (`SALIDA_MERMA_PLANTA`) con centro de costo.

**Regla clave:** no mezclar pérdida del cliente con merma interna de planta.

---

## Recomendaciones concretas antes de programar

1. **Definir catálogo oficial de tipos de envase (3 exactos)**
   - `RECEPCION_VACIO`
   - `ENTREGA_LLENO` (o `PRESTAMO_CLIENTE`)
   - `AJUSTE_CLIENTE`

2. **Crear mapa obligatorio envase → kardex**
   - `RECEPCION_VACIO` → entrada inventario
   - `ENTREGA_LLENO/PRESTAMO_CLIENTE` → salida inventario
   - `AJUSTE_CLIENTE` → sin movimiento físico (por defecto)

3. **Introducir `operacion_uuid`** para unir ambas tablas.
   - Campo en `cta_cte_envases` y referencia en `inventario_movimientos`.
   - Permite auditoría 1 click desde historial.

4. **Agregar almacén de envases en formulario**
   - Necesario para impactar kardex físico correctamente.

5. **Política contable explícita para ajuste cliente**
   - Definir si genera:
     - Cuenta por cobrar al cliente,
     - Ingreso por reposición,
     - o castigo por pérdida.

6. **Evitar ambigüedad de nombres**
   - “Entrega lleno” no siempre refleja “préstamo envase”; conviene texto UI: “Préstamo / Entrega al cliente”.

7. **Mejorar historial de envases**
   - Mostrar fecha/hora, usuario, almacén, referencia y `operacion_uuid`.

8. **Kardex enriquecido**
   - Filtro por “Origen = Envases” y por cliente.
   - Badge visual específico para eventos de envases.

---

## Riesgos actuales detectados

- Posible descuadre entre saldo de cliente y stock físico por falta de transacción cruzada.
- Dependencia en textos de referencia para trazabilidad (frágil).
- UI y lógica no totalmente alineadas en los tipos disponibles.

---

## Plan sugerido de implementación (orden)

1. **Alinear catálogo de tipos en UI + backend (sin tocar lógica de stock todavía).**
2. **Agregar operación compuesta transaccional (envases + kardex) con `operacion_uuid`.**
3. **Actualizar historial y reportes para trazabilidad cruzada.**
4. **Opcional: contabilidad automática para `AJUSTE_CLIENTE`.**

---

## Criterios de éxito funcional

- Cada movimiento de envases queda auditable en un solo flujo (cliente + físico).
- No existen diferencias entre saldo cliente y stock teórico por proceso.
- El usuario puede explicar cualquier saldo en < 1 minuto desde la UI.
