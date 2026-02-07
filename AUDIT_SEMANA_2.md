# Auditoría Semana 2 — Gestión de Identidad y Catálogos Maestros

Este documento lista los **cambios necesarios** para que la implementación actual cumpla con la especificación de la Semana 2.

## 1. Configuración Institucional (Identidad del Sistema)

### Hallazgos
- **Vista y flujo no usan AJAX ni `configuracion.js`**: el formulario de empresa se envía con `POST` tradicional y la previsualización se hace en `empresa.js`, no hay envío AJAX ni manejo de respuesta en JS. Esto incumple el paso “Frontend” indicado (AJAX + alertas). 【F:app/views/config/empresa.php†L34-L146】【F:public/assets/js/empresa.js†L1-L60】
- **No hay tarjetas separadas ni campos completos según la UI solicitada**: la vista actual solo tiene “Información General” e “Identidad Visual” y faltan campos como **slogan** e **impuesto**, además del **color del sistema** como `input[type=color]`. 【F:app/views/config/empresa.php†L34-L146】
- **Marca en Sidebar no está ligada al nombre/logo configurado**: el sidebar usa texto fijo `SISADMIN2`, por lo que la identidad no se refleja en toda la UI principal. 【F:app/views/sidebar.php†L29-L36】
- **Logo y nombre se refrescan en Login y Layout desde sesión**, pero el uso del sidebar fijo rompe la definición de “identidad corporativa única” en la navegación principal. 【F:app/views/login.php†L1-L52】【F:app/views/layout.php†L19-L25】【F:app/views/sidebar.php†L29-L36】

### Cambios necesarios
1. **Implementar envío AJAX** del formulario de empresa y manejo de alertas en JS (migrar lógica de `empresa.js` o crear `configuracion.js` como indica la especificación). 【F:app/views/config/empresa.php†L34-L146】【F:public/assets/js/empresa.js†L1-L60】
2. **Completar la UI con las 3 tarjetas** requeridas y añadir campos faltantes: `slogan`, `impuesto`, `email`, `telefono`, `moneda`, `color` (input `type=color`) y separar la sección de parámetros operativos. 【F:app/views/config/empresa.php†L34-L146】
3. **Vincular el nombre/logo configurado al sidebar/nav principal** para que refleje la identidad corporativa. 【F:app/views/sidebar.php†L29-L36】
4. **Asegurar actualización en vivo** del branding en Navbar/Login después de guardar (actualizar sesión y refrescar componentes sin limpiar caché). Esto está parcialmente cubierto en controlador, pero requiere UI que use esos datos en todas las secciones. 【F:app/controllers/EmpresaController.php†L48-L88】【F:app/views/sidebar.php†L29-L36】

## 2. Catálogo Maestro de Ítems

### Hallazgos
- **Solo soporta tipos `PRODUCTO` y `SERVICIO`**, faltan los tipos exigidos (insumo, producto terminado, proceso, empaque, envase retornable, repuesto/MRO, activo, servicio). 【F:app/views/items.php†L30-L36】【F:app/views/items.php†L140-L165】
- **Campos críticos faltantes**: unidad base, permite decimales, requiere lote/vencimiento, moneda, impuesto, retornabilidad. La vista actual no los expone. 【F:app/views/items.php†L126-L222】
- **Categorías como maestro**: la UI usa un input `id_categoria` libre y no valida contra un catálogo maestro (no hay selector/lookup). 【F:app/views/items.php†L206-L215】
- **SKU único a nivel global**: la validación de SKU ignora registros soft-deleted (solo valida `deleted_at IS NULL`). Esto permite reutilizar SKU de registros eliminados, lo que contradice la inmutabilidad/únicidad permanente del SKU. 【F:app/models/ItemModel.php†L34-L50】
- **Soft delete sin `deleted_by`**: se registra `updated_by`, pero falta `deleted_by` para auditoría completa. 【F:app/models/ItemModel.php†L100-L112】

### Cambios necesarios
1. **Ampliar tipos de ítem** a todos los requeridos en UI y validación backend. 【F:app/views/items.php†L30-L36】【F:app/controllers/ItemsController.php†L104-L123】
2. **Agregar campos del formulario maestro** (unidad base, decimales, lote/vencimiento, moneda, impuesto, retornabilidad) y persistirlos en DB/modelo. 【F:app/views/items.php†L126-L222】【F:app/models/ItemModel.php†L148-L161】
3. **Usar catálogo de categorías**: reemplazar `id_categoria` libre por un selector de catálogo y validar en backend. 【F:app/views/items.php†L206-L215】【F:app/controllers/ItemsController.php†L104-L123】
4. **Hacer SKU único e inmutable aun con soft delete**: validar existencia en toda la tabla (sin filtrar por `deleted_at`) y/o garantizar `UNIQUE INDEX` en DB. 【F:app/models/ItemModel.php†L34-L50】
5. **Completar auditoría**: incluir `deleted_by` al eliminar y registrar quién ejecutó la baja lógica. 【F:app/models/ItemModel.php†L100-L112】

## 3. Catálogo Maestro de Terceros

### Hallazgos
- **Faltan datos comerciales y laborales** (condición de pago, límite de crédito, cargo, área, fecha de ingreso, estado laboral). 【F:app/views/terceros.php†L170-L257】【F:app/views/terceros.php†L279-L352】
- **Normalización de documentos**: no se eliminan espacios ni caracteres no válidos al guardar; solo se hace `trim`. 【F:app/models/TerceroModel.php†L151-L167】
- **Validación AJAX de documento** inexistente: no hay endpoint ni JS para validar unicidad en tiempo real. 【F:public/assets/js/terceros.js†L1-L126】
- **Soft delete sin `deleted_by`**: falta el registro explícito del usuario que eliminó. 【F:app/models/TerceroModel.php†L102-L113】
- **Bloqueo por estado/rol**: el UI permite cambiar estado localmente sin persistir en backend (switch solo cambia el DOM). 【F:public/assets/js/terceros.js†L80-L116】

### Cambios necesarios
1. **Incluir campos comerciales y laborales** en vista, modelo y DB. 【F:app/views/terceros.php†L170-L257】【F:app/views/terceros.php†L279-L352】
2. **Normalizar documento** (quitar espacios y caracteres, consistencia de formato) y aplicar la regla en backend antes de validar/guardar. 【F:app/models/TerceroModel.php†L151-L167】
3. **Validación AJAX** para documento (endpoint + JS). 【F:public/assets/js/terceros.js†L1-L126】
4. **Auditoría completa**: almacenar `deleted_by` en bajas lógicas. 【F:app/models/TerceroModel.php†L102-L113】
5. **Persistir cambio de estado** del switch en backend y bloquear operativa según estado/rol (UI y servidor). 【F:public/assets/js/terceros.js†L80-L116】【F:app/controllers/TercerosController.php†L22-L71】

## 4. Normativa técnica general

### Hallazgos
- **Falta confirmar en DB**: no hay scripts de migración/DDL en repo para garantizar tipos `DECIMAL(14,4)`, índices únicos y columnas de auditoría (created_by/updated_by/deleted_by). Esto debe verificarse/crear en la base de datos. (No hay archivos de migración en el repositorio.)
- **Permisos**: los endpoints están protegidos por `require_permiso`, pero se requiere revisar permisos específicos de configuración (crear/editar). 【F:app/controllers/EmpresaController.php†L21-L44】

### Cambios necesarios
1. **Agregar/validar DDL** con `DECIMAL(14,4)` para costos/precios, índices únicos y columnas de auditoría completas.
2. **Confirmar protección por permisos** para todos los endpoints y acciones (incluyendo ajustes de branding y cambios de estado).

---

## Resumen Ejecutivo
La implementación actual **cubre parcialmente** la identidad institucional y los catálogos, pero **no cumple** con los requisitos de UI, tipos de ítem, normalización/validación documental, auditoría completa y especificaciones técnicas (AJAX, catálogos maestros, campos financieros/laborales). Se requieren ajustes en **modelos, controladores, vistas y JS**, además de validaciones y estructura de base de datos.
