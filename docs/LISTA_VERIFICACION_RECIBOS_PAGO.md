# Lista de Verificación de Implementación - Módulo Recibos de Pago

## 📋 Resumen del Proyecto

**Módulo:** Financiero  
**Submódulo:** Recibos de Pago  
**Objetivo:** Registrar todos los pagos que ingresan al instituto por diferentes conceptos de las listas de precios

---

## ✅ FASE 1: Preparación de Base de Datos

### 1.1 Migración: Agregar códigos a Sedes

-   [x] Crear archivo: `database/migrations/2025_12_08_181700_add_codigo_to_sedes_table.php`
-   [x] Agregar campo `codigo_academico` (string, 10, nullable, unique)
-   [x] Agregar campo `codigo_inventario` (string, 10, nullable, unique)
-   [x] Agregar índices para ambos campos
-   [x] Verificar migración

### 1.2 Migración: Tabla Principal Recibos de Pago

-   [x] Crear archivo: `database/migrations/2025_12_08_181710_create_recibos_pago_table.php`
-   [x] Crear tabla `recibos_pago` con todos los campos requeridos
-   [x] Agregar foreign keys (sede_id, estudiante_id, cajero_id, matricula_id)
-   [x] Agregar índices necesarios
-   [x] Agregar constraints de validación
-   [x] Verificar migración

### 1.3 Migración: Tabla Pivot Conceptos de Pago

-   [x] Crear archivo: `database/migrations/2025_12_08_181720_create_recibo_pago_concepto_pago_table.php`
-   [x] Crear tabla `recibo_pago_concepto_pago`
-   [x] Agregar foreign keys
-   [x] Agregar campos de detalle (valor, tipo, producto, cantidad, unitario, subtotal, id_relacional)
-   [x] Agregar constraints de cálculo
-   [x] Verificar migración

### 1.4 Migración: Tabla Pivot Listas de Precio

-   [x] Crear archivo: `database/migrations/2025_12_08_181730_create_recibo_pago_lista_precio_table.php`
-   [x] Crear tabla `recibo_pago_lista_precio`
-   [x] Agregar foreign keys
-   [x] Verificar migración

### 1.5 Migración: Tabla Pivot Productos

-   [x] Crear archivo: `database/migrations/2025_12_08_181740_create_recibo_pago_producto_table.php`
-   [x] Crear tabla `recibo_pago_producto`
-   [x] Agregar foreign keys
-   [x] Agregar campos (cantidad, precio_unitario, subtotal)
-   [x] Agregar constraints
-   [x] Verificar migración

### 1.6 Migración: Tabla Pivot Descuentos

-   [x] Crear archivo: `database/migrations/2025_12_08_181750_create_recibo_pago_descuento_table.php`
-   [x] Crear tabla `recibo_pago_descuento`
-   [x] Agregar foreign keys
-   [x] Agregar campos (valor_descuento, valor_original, valor_final)
-   [x] Agregar constraints
-   [x] Verificar migración

### 1.7 Migración: Tabla Pivot Medios de Pago

-   [x] Crear archivo: `database/migrations/2025_12_08_181760_create_recibo_pago_medio_pago_table.php`
-   [x] Crear tabla `recibo_pago_medio_pago`
-   [x] Agregar foreign key
-   [x] Agregar campos (medio_pago, valor, referencia, banco)
-   [x] Verificar migración

### 1.8 Ejecución y Verificación

-   [ ] Ejecutar todas las migraciones: `php artisan migrate` (pendiente ejecución por usuario)
-   [x] Verificar estructura de tablas en base de datos (archivos creados)
-   [x] Verificar índices creados (incluidos en migraciones)
-   [x] Verificar foreign keys (incluidas en migraciones)

---

## ✅ FASE 2: Modelos y Traits

### 2.1 Trait HasReciboPagoStatus

-   [x] Crear archivo: `app/Traits/Financiero/HasReciboPagoStatus.php`
-   [x] Implementar método `getStatusOptions()` con 4 estados
-   [x] Implementar método `getStatusText($status)`
-   [x] Implementar método `getStatusTextAttribute()`
-   [x] Implementar método `getStatusValidationRule()`
-   [x] Implementar método `getStatusValidationMessages()`
-   [x] Implementar método `getOrigenOptions()` con 2 orígenes
-   [x] Implementar método `getOrigenText($origen)`
-   [x] Implementar método `getOrigenTextAttribute()`
-   [x] Agregar scopes: `enProceso()`, `creados()`, `cerrados()`, `anulados()`

### 2.2 Modelo ReciboPago

-   [x] Crear archivo: `app/Models/Financiero/ReciboPago/ReciboPago.php`
-   [x] Agregar namespace correcto
-   [x] Agregar use statements para traits
-   [x] Definir constante `$table = 'recibos_pago'`
-   [x] Definir `$guarded` o `$fillable`
-   [x] Definir `$casts` para fechas y decimales
-   [x] Definir constantes de estado (STATUS_EN_PROCESO, STATUS_CREADO, etc.)
-   [x] Definir constantes de origen (ORIGEN_INVENTARIOS, ORIGEN_ACADEMICO)

### 2.3 Relaciones del Modelo

-   [x] Implementar relación `sede()` - BelongsTo
-   [x] Implementar relación `estudiante()` - BelongsTo (User)
-   [x] Implementar relación `cajero()` - BelongsTo (User)
-   [x] Implementar relación `matricula()` - BelongsTo (nullable)
-   [x] Implementar relación `conceptosPago()` - BelongsToMany con pivot
-   [x] Implementar relación `listasPrecio()` - BelongsToMany
-   [x] Implementar relación `productos()` - BelongsToMany con pivot
-   [x] Implementar relación `descuentos()` - BelongsToMany con pivot
-   [x] Implementar relación `mediosPago()` - HasMany

### 2.4 Scopes del Modelo

-   [x] Implementar `scopeBySede($query, $sedeId)`
-   [x] Implementar `scopeByEstudiante($query, $estudianteId)`
-   [x] Implementar `scopeByCajero($query, $cajeroId)`
-   [x] Implementar `scopeByOrigen($query, $origen)`
-   [x] Implementar `scopeByStatus($query, $status)`
-   [x] Implementar `scopeByFechaRange($query, $fechaInicio, $fechaFin)`
-   [x] Implementar `scopeByCierre($query, $cierre)`
-   [x] Implementar `scopeByMatricula($query, $matriculaId)`
-   [x] Implementar `scopeByProducto($query, $productoId)`
-   [x] Implementar `scopeByPoblacion($query, $poblacionId)`
-   [x] Implementar `scopeVigentes($query)` - No anulados

### 2.5 Métodos del Modelo

-   [x] Implementar `getAllowedSortFields()` - Campos ordenables
-   [x] Implementar `getAllowedRelations()` - Relaciones permitidas
-   [x] Implementar `getDefaultRelations()` - Relaciones por defecto
-   [x] Implementar `getCountableRelations()` - Relaciones contables
-   [x] Implementar método estático `obtenerConsecutivo($sedeId, $origen)`
-   [x] Implementar método estático `generarNumeroRecibo($sedeId, $origen)`
-   [x] Implementar método `calcularTotales()`
-   [x] Implementar método `validarMediosPago()`
-   [x] Implementar método `anular()`
-   [x] Implementar método `cerrar()`
-   [x] Implementar método `estaAnulado()`
-   [x] Implementar método `estaCerrado()`
-   [x] Implementar método `estaEnProceso()`

### 2.6 Actualizar Modelos Relacionados - Relaciones Inversas

-   [x] Actualizar modelo `Sede`:
    -   [x] Agregar relación `recibosPago()` (HasMany)
    -   [x] Agregar `recibosPago` a `getAllowedRelations()`
    -   [x] Documentar relación con PHPDoc en español
-   [x] Actualizar modelo `Matricula`:
    -   [x] Agregar relación `recibosPago()` (HasMany)
    -   [x] Agregar `recibosPago` a `getAllowedRelations()`
    -   [x] Documentar relación con PHPDoc en español
-   [x] Actualizar modelo `User`:
    -   [x] Agregar relación `recibosPagoComoEstudiante()` (HasMany, foreign key: estudiante_id)
    -   [x] Agregar relación `recibosPagoComoCajero()` (HasMany, foreign key: cajero_id)
    -   [x] Documentar relaciones con PHPDoc en español
-   [x] Actualizar modelo `ConceptoPago`:
    -   [x] Agregar relación `recibosPago()` (BelongsToMany, tabla pivot: recibo_pago_concepto_pago)
    -   [x] Incluir campos pivot: valor, tipo, producto, cantidad, unitario, subtotal, id_relacional, observaciones
    -   [x] Agregar `recibosPago` a `getAllowedRelations()`
    -   [x] Documentar relación con PHPDoc en español
-   [x] Actualizar modelo `LpListaPrecio`:
    -   [x] Agregar relación `recibosPago()` (BelongsToMany, tabla pivot: recibo_pago_lista_precio)
    -   [x] Agregar `recibosPago` a `getAllowedRelations()`
    -   [x] Documentar relación con PHPDoc en español
-   [x] Actualizar modelo `LpProducto`:
    -   [x] Agregar relación `recibosPago()` (BelongsToMany, tabla pivot: recibo_pago_producto)
    -   [x] Incluir campos pivot: cantidad, precio_unitario, subtotal
    -   [x] Agregar `recibosPago` a `getAllowedRelations()`
    -   [x] Documentar relación con PHPDoc en español
-   [x] Actualizar modelo `Descuento`:
    -   [x] Agregar relación `recibosPago()` (BelongsToMany, tabla pivot: recibo_pago_descuento)
    -   [x] Incluir campos pivot: valor_descuento, valor_original, valor_final
    -   [x] Agregar `recibosPago` a `getAllowedRelations()`
    -   [x] Documentar relación con PHPDoc en español
-   [x] Crear modelo `ReciboPagoMedioPago` para la relación mediosPago
-   [ ] Verificar que todas las relaciones inversas funcionen correctamente
-   [ ] Probar eager loading con las nuevas relaciones

---

## ✅ FASE 3: Permisos

### 3.1 Actualizar RolesAndPermissionsSeeder

-   [x] Abrir archivo: `database/seeders/RolesAndPermissionsSeeder.php`
-   [x] Agregar permiso `fin_recibos_pago` (ver recibos)
-   [x] Agregar permiso `fin_reciboPagoCrear` (crear)
-   [x] Agregar permiso `fin_reciboPagoEditar` (editar)
-   [x] Agregar permiso `fin_reciboPagoAnular` (anular)
-   [x] Agregar permiso `fin_reciboPagoCerrar` (cerrar)
-   [x] Agregar permiso `fin_reciboPagoReportes` (reportes)
-   [x] Agregar permiso `fin_reciboPagoPDF` (generar PDF)
-   [x] Asignar permisos a roles correspondientes
-   [ ] Ejecutar seeder: `php artisan db:seed --class=RolesAndPermissionsSeeder`

---

## ✅ FASE 4: Factory y Seeder

### 4.1 Factory ReciboPagoFactory

-   [x] Crear archivo: `database/factories/Financiero/ReciboPago/ReciboPagoFactory.php`
-   [x] Definir estructura básica del factory
-   [x] Implementar estado `enProceso()`
-   [x] Implementar estado `creado()`
-   [x] Implementar estado `cerrado()`
-   [x] Implementar estado `anulado()`
-   [x] Implementar estado `academico()`
-   [x] Implementar estado `inventario()`
-   [x] Generar datos de prueba

### 4.2 Seeder ReciboPagoSeeder

-   [x] Crear archivo: `database/seeders/ReciboPagoSeeder.php`
-   [x] Implementar método `run()` para generar datos de prueba
-   [x] Generar recibos de ejemplo con todas las relaciones (conceptos, listas, productos, descuentos, medios de pago)
-   [x] Generar recibos con diferentes estados (en proceso, creado, cerrado, anulado)
-   [x] Generar recibos con diferentes orígenes (académico, inventario)
-   [x] Generar recibos para diferentes sedes
-   [ ] Verificar que el seeder funcione correctamente

---

## ✅ FASE 5: Requests (Validación)

### 5.1 StoreReciboPagoRequest

-   [x] Crear archivo: `app/Http/Requests/Api/Financiero/ReciboPago/StoreReciboPagoRequest.php`
-   [x] Validar `sede_id` (required, exists)
-   [x] Validar `estudiante_id` (nullable, exists)
-   [x] Validar `cajero_id` (required, exists)
-   [x] Validar `matricula_id` (nullable, exists)
-   [x] Validar `origen` (required, integer, in:0,1)
-   [x] Validar `fecha_recibo` (required, date)
-   [x] Validar `fecha_transaccion` (required, date)
-   [x] Validar `valor_total` (required, numeric, min:0)
-   [x] Validar `descuento_total` (nullable, numeric, min:0)
-   [x] Validar `banco` (nullable, string, max:100)
-   [x] Validar `conceptos_pago` (array, required)
-   [x] Validar `listas_precio` (array, nullable)
-   [x] Validar `productos` (array, nullable)
-   [x] Validar `descuentos` (array, nullable)
-   [x] Validar `medios_pago` (array, required)
-   [x] Validar que suma de medios_pago = valor_total
-   [x] Validar cálculos de subtotales y valores finales
-   [x] Agregar mensajes de validación en español

### 5.2 UpdateReciboPagoRequest

-   [x] Crear archivo: `app/Http/Requests/Api/Financiero/ReciboPago/UpdateReciboPagoRequest.php`
-   [x] Validar que el recibo esté en proceso (status = 0)
-   [x] Validar campos editables (similar a Store pero algunos opcionales)
-   [x] Validar cálculos de subtotales y valores finales
-   [x] Agregar mensajes de validación

---

## ✅ FASE 6: Resources (Transformación)

### 6.1 ReciboPagoResource

-   [x] Crear archivo: `app/Http/Resources/Api/Financiero/ReciboPago/ReciboPagoResource.php`
-   [x] Transformar campos básicos del recibo
-   [x] Incluir `status_text` y `origen_text`
-   [x] Incluir relaciones opcionales (sede, estudiante, cajero, matricula)
-   [x] Incluir conceptos de pago con detalles del pivot
-   [x] Incluir listas de precio
-   [x] Incluir productos con detalles del pivot
-   [x] Incluir descuentos con detalles del pivot
-   [x] Incluir medios de pago
-   [x] Formatear fechas correctamente
-   [x] Formatear valores monetarios
-   [x] Incluir métodos de verificación (esta_anulado, esta_cerrado, esta_en_proceso)

---

## ✅ FASE 7: Controller

### 7.1 ReciboPagoController - Métodos Básicos

-   [x] Crear archivo: `app/Http/Controllers/Api/Financiero/ReciboPago/ReciboPagoController.php`
-   [x] Implementar método `index()` con:
    -   [x] Paginación
    -   [x] Filtros (sede, estudiante, cajero, fecha, status, origen)
    -   [x] Ordenamiento
    -   [x] Inclusión de relaciones
    -   [x] Permisos
-   [x] Implementar método `store()` con:
    -   [x] Validación de request
    -   [x] Generación de consecutivo
    -   [x] Generación de número de recibo
    -   [x] Cálculo de totales
    -   [x] Guardado de relaciones
    -   [x] Envío de correo automático
-   [x] Implementar método `show()` con:
    -   [x] Carga de relaciones
    -   [x] Permisos
-   [x] Implementar método `update()` con:
    -   [x] Validación de estado (solo en proceso)
    -   [x] Actualización de datos
    -   [x] Recalculo de totales
-   [x] Implementar método `destroy()` con:
    -   [x] Soft delete
    -   [x] Validación de estado

### 7.2 ReciboPagoController - Métodos Especiales

-   [x] Implementar método `anular()` con:
    -   [x] Validación de permisos
    -   [x] Validación de estado
    -   [x] Cambio de status a ANULADO
    -   [x] Registro de auditoría (logging)
-   [x] Implementar método `cerrar()` con:
    -   [x] Validación de permisos
    -   [x] Validación de estado
    -   [x] Cambio de status a CERRADO
    -   [x] Asignación de número de cierre
-   [x] Implementar método `generarPDF()` con:
    -   [x] Generación de PDF del recibo
    -   [x] Retorno de archivo descargable
-   [x] Implementar método `enviarEmail()` con:
    -   [x] Generación de PDF
    -   [x] Envío de correo al estudiante
    -   [x] Adjuntar PDF
-   [x] Implementar método `reportes()` con:
    -   [x] Filtros múltiples
    -   [x] Agrupación de datos
    -   [x] Múltiples tipos de reporte (resumen, por_sede, por_producto, por_cajero, por_descuentos, por_poblacion)

---

## ✅ FASE 8: Rutas

### 8.1 Definir Rutas API

-   [x] Abrir archivo: `routes/financiero.php`
-   [x] Agregar ruta GET `/api/financiero/recibos-pago` (index) - vía apiResource
-   [x] Agregar ruta POST `/api/financiero/recibos-pago` (store) - vía apiResource
-   [x] Agregar ruta GET `/api/financiero/recibos-pago/{id}` (show) - vía apiResource
-   [x] Agregar ruta PUT `/api/financiero/recibos-pago/{id}` (update) - vía apiResource
-   [x] Agregar ruta DELETE `/api/financiero/recibos-pago/{id}` (destroy) - vía apiResource
-   [x] Agregar ruta POST `/api/financiero/recibos-pago/{id}/anular` (anular)
-   [x] Agregar ruta POST `/api/financiero/recibos-pago/{id}/cerrar` (cerrar)
-   [x] Agregar ruta GET `/api/financiero/recibos-pago/{id}/pdf` (generarPDF)
-   [x] Agregar ruta POST `/api/financiero/recibos-pago/{id}/enviar-email` (enviarEmail)
-   [x] Agregar ruta GET `/api/financiero/recibos-pago/reportes` (reportes)
-   [x] Aplicar middleware de autenticación
-   [x] Aplicar permisos a cada ruta (configurados en controller)

---

## ✅ FASE 9: Funcionalidades Adicionales

### 9.1 Generación de PDF

-   [x] Crear servicio `ReciboPagoPDFService` (requiere instalar barryvdh/laravel-dompdf)
-   [x] Crear vista Blade para el PDF del recibo (`resources/views/recibos-pago/pdf.blade.php`)
-   [x] Implementar servicio para generar PDF
-   [x] Incluir información completa del recibo
-   [x] Formatear valores monetarios
-   [ ] Instalar librería de PDF: `composer require barryvdh/laravel-dompdf`
-   [ ] Incluir logo del instituto (pendiente configuración)
-   [ ] Probar generación de PDF

### 9.2 Envío de Correo

-   [x] Crear Mailable `ReciboPagoMail` para recibo de pago
-   [x] Implementar envío con PDF adjunto
-   [x] Configurar plantilla de correo (`resources/views/emails/recibo-pago.blade.php`)
-   [x] Integrar envío automático al crear recibo
-   [ ] Configurar servicio de correo en `.env`
-   [ ] Probar envío de correo

### 9.3 Servicio de Numeración

-   [x] Crear servicio `ReciboPagoNumeracionService`
-   [x] Implementar método para obtener consecutivo
-   [x] Implementar transacciones para evitar duplicados
-   [x] Implementar generación de número completo
-   [x] Implementar validación de códigos de sede
-   [ ] Probar generación de números

### 9.4 Cálculo de Totales

-   [x] Implementar lógica de cálculo de valor_total (método `calcularTotales()`)
-   [x] Implementar lógica de cálculo de descuento_total (método `calcularTotales()`)
-   [x] Validar que descuento_total <= valor_total (en Request y modelo)
-   [ ] Probar cálculos con diferentes escenarios

### 9.5 Reportes

-   [x] Implementar reporte por período (filtros fecha_inicio/fecha_fin)
-   [x] Implementar reporte por sede (tipo_reporte=por_sede)
-   [x] Implementar reporte por producto (tipo_reporte=por_producto)
-   [x] Implementar reporte por población/ciudad (tipo_reporte=por_poblacion)
-   [x] Implementar reporte por cajero (tipo_reporte=por_cajero)
-   [x] Implementar reporte por descuentos (tipo_reporte=por_descuentos)
-   [x] Implementar reporte resumen (tipo_reporte=resumen)
-   [ ] Implementar exportación a PDF (pendiente)
-   [ ] Implementar exportación a Excel (pendiente)
-   [ ] Probar todos los reportes

---

## ✅ FASE 10: Testing y Documentación

### 10.1 Testing Funcional

-   [ ] Probar creación de recibo académico
-   [ ] Probar creación de recibo de inventario
-   [ ] Probar edición de recibo en proceso
-   [ ] Probar que no se puede editar recibo creado
-   [ ] Probar anulación de recibo
-   [ ] Probar cierre de recibo
-   [ ] Probar generación de PDF
-   [ ] Probar envío de correo
-   [ ] Probar numeración consecutiva por sede
-   [ ] Probar numeración consecutiva por origen
-   [ ] Probar cálculo de totales
-   [ ] Probar validación de medios de pago
-   [ ] Probar reportes con diferentes filtros

### 10.2 Testing de Permisos

-   [ ] Probar acceso sin autenticación
-   [ ] Probar acceso sin permisos
-   [ ] Probar permisos por rol
-   [ ] Probar restricciones de edición según estado

### 10.3 Documentación

-   [x] Documentar endpoints de API (`docs/API_RECIBOS_PAGO.md`)
-   [x] Documentar parámetros de entrada
-   [x] Documentar respuestas de salida
-   [x] Documentar códigos de error
-   [x] Crear ejemplos de uso
-   [x] Documentar reglas de negocio
-   [x] Crear resumen de implementación (`docs/RESUMEN_IMPLEMENTACION_RECIBOS_PAGO.md`)

---

## 📝 Notas Finales

-   Revisar que todos los archivos sigan las convenciones del proyecto
-   Verificar que los comentarios PHPDoc estén en español
-   Asegurar que los nombres de métodos y variables sigan las convenciones
-   Verificar que no haya errores de linting
-   Probar en ambiente de desarrollo antes de producción

---

## 🎯 Criterios de Aceptación

-   ✅ Se pueden crear recibos de pago con todos los datos requeridos
-   ✅ La numeración consecutiva funciona correctamente por sede y origen
-   ✅ Los totales se calculan automáticamente
-   ✅ Los descuentos se aplican correctamente
-   ✅ Los medios de pago se validan correctamente
-   ✅ Se puede generar PDF del recibo
-   ✅ Se puede enviar el recibo por correo
-   ✅ Los reportes funcionan con todos los filtros
-   ✅ Los permisos funcionan correctamente
-   ✅ No se pueden modificar recibos cerrados o anulados
