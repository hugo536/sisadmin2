# Auditoría de nombres de tablas y columnas (módulo de terceros)

## Alcance
Esta auditoría contrasta el DDL compartido para las tablas de terceros/distribuidores con el uso real de nombres en el código PHP del proyecto (controladores, modelos y vistas).

## Resumen ejecutivo
- **Resultado general: Parcialmente consistente.**
- Se observa una base razonable (snake_case, plural en tablas, prefijo por dominio), pero hay **inconsistencias de nomenclatura** que pueden inducir errores y confusión en mantenimiento.
- Además, hay **desalineaciones entre DDL y código** en columnas de auditoría de distribuidores que podrían romper consultas/updates si el esquema real coincide con el DDL compartido.

## Hallazgos

### 1) FK inconsistente: `id_tercero` vs `tercero_id`
Se usan ambos estilos para la misma referencia a `terceros.id`:
- En tablas: `terceros_clientes.id_tercero`, `terceros_proveedores.id_tercero`, `terceros_empleados.id_tercero`, `terceros_documentos.id_tercero`.
- En otras tablas: `terceros_telefonos.tercero_id`, `terceros_cuentas_bancarias.tercero_id`, `terceros_roles.tercero_id`.

En código también coexisten ambos estilos, por ejemplo:
- Uso de `id_tercero` en joins de terceros/clientes/proveedores/empleados.
- Uso de `tercero_id` para teléfonos y cuentas bancarias.

**Riesgo:** aumenta la probabilidad de errores al escribir consultas, migraciones o DTOs.

**Recomendación:** estandarizar un único patrón para todas las FK. Sugerencia: `tercero_id` (más legible y común).

---

### 2) Nombre engañoso: `distribuidores_zonas.distribuidor_id`
En el código, `distribuidor_id` se carga con el **id de tercero** (`$idTercero`). Esto funciona funcionalmente, pero semánticamente confunde, porque parece referir a una PK propia de `distribuidores` cuando en realidad apunta a `terceros.id`/`distribuidores.id_tercero`.

**Riesgo:** interpretación errónea del modelo relacional.

**Recomendación:** renombrar a `tercero_id` o `id_tercero` (según estándar elegido) y ajustar índices/FKs/documentación.

---

### 3) Desalineación DDL ↔ código en `distribuidores`
En el código se ejecuta:
- `UPDATE distribuidores SET deleted_at = NOW(), deleted_by = ?, updated_by = ? ...`

Pero en el DDL compartido de `distribuidores` no figura la columna `deleted_by`.

**Riesgo:** error SQL en runtime si el esquema real no tiene esa columna.

**Recomendación:**
- O bien agregar `deleted_by` en la tabla,
- O remover el uso desde el modelo si no se desea ese campo.

---

### 4) Desalineación DDL ↔ código en `distribuidores_zonas`
En el código se inserta:
- `created_by`, `updated_by` en `distribuidores_zonas`.

Pero en el DDL compartido de `distribuidores_zonas` esas columnas no existen.

**Riesgo:** fallo en inserts.

**Recomendación:** alinear DDL y código (agregar columnas o ajustar INSERT).

---

### 5) Duplicidad semántica en cuentas bancarias: `tipo_cta` y `tipo_cuenta`
En `terceros_cuentas_bancarias` existen **dos columnas potencialmente redundantes**:
- `tipo_cta`
- `tipo_cuenta`

En código se utiliza `tipo_cta`; la presencia de ambas puede generar dudas sobre cuál es la oficial.

**Riesgo:** datos duplicados/inconsistentes por uso alternado.

**Recomendación:** consolidar en una sola columna canónica y deprecar la otra.

---

### 6) Ubigeo: nombres libres en `terceros` vs IDs normalizados en catálogo
`terceros` usa `departamento`, `provincia`, `distrito` (texto), mientras que el módulo geográfico maneja catálogos normalizados con `departamento_id`, `provincia_id`, `distrito_id`.

**Riesgo:** inconsistencias ortográficas y dificultad para integridad referencial/reportes.

**Recomendación:** migrar `terceros` a columnas `_id` y dejar nombres como derivados por join.

## Priorización sugerida
1. **Crítico (rompe ejecución):** alinear columnas de auditoría en `distribuidores` y `distribuidores_zonas`.
2. **Alto (deuda de nomenclatura):** unificar `id_tercero` vs `tercero_id`.
3. **Medio:** resolver `tipo_cta` vs `tipo_cuenta`.
4. **Medio/Bajo:** normalizar ubigeo en `terceros`.

## Convención propuesta (objetivo)
- Tablas: plural en `snake_case`.
- PK: `id`.
- FK: `<entidad_singular>_id` (ej. `tercero_id`, `departamento_id`).
- Campos de auditoría: `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`.
- Evitar abreviaturas ambiguas (`cta`) salvo estándar formal en todo el sistema.

## Evidencia revisada (código)
- `app/models/TercerosModel.php`
- `app/models/terceros/DistribuidoresModel.php`
- `app/models/terceros/TercerosClientesModel.php`
- `app/models/terceros/TercerosProveedoresModel.php`
- `app/models/terceros/TercerosEmpleadosModel.php`
- `app/controllers/TercerosController.php`
- `app/views/terceros_perfil.php`
