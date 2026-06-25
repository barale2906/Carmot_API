# Carmot API

Laravel 10 / PHP 8.1 API. Auth: Sanctum. Autorización: spatie/laravel-permission. spatie/laravel-translatable. Docs de API generadas por dedoc/scramble. Tests: PHPUnit.

## Reglas que se aplican a TODO cambio de backend

Estas reglas son siempre vigentes, sin importar lo que pida el prompt puntual. Para plantillas de código completas (Controller, FormRequest, Resource, rutas, test) y el detalle de cada regla, usar la skill `backend-standards` (`.claude/skills/backend-standards/`) — se invoca sola en tareas de código, pero estos puntos resumen lo no negociable:

1. **Respetar la arquitectura existente por módulo** (Academico, Financiero, Crm, Configuracion, Dashboard): controladores delgados → FormRequest valida → modelo aplica scopes/traits → Resource da forma a la respuesta. Antes de escribir código nuevo, mirar el archivo hermano más cercano en el mismo módulo y replicar su patrón.
2. **Código profesional, limpio y reutilizable**: sin lógica de negocio en el controlador, sin duplicar lo que ya existe en traits/scopes (`HasActiveStatus`, `HasFilterScopes`, `HasRelationScopes`, `HasSortingScopes`, etc.), sin abstracciones especulativas ni código muerto.
3. **Actuar como backend senior experto en este stack**: seguir convenciones ya establecidas (envoltorio JSON `data`/`message`/`meta`, permisos por middleware, soft delete + restore + forceDelete + trashed, sistema de status vía traits) en vez de improvisar nuevas.
4. **Generar todo lo que la estructura requiere para el cambio**, no solo el archivo mencionado: migración/modelo, Store/Update FormRequest, controlador, Resource, rutas (con PHPDoc en español en cada método público), registrado en el archivo de rutas del módulo correspondiente.
5. **Tests obligatorios, no opcionales**: actualizar los tests Feature afectados y agregar los que falten (happy path, validación, permiso denegado, casos borde). Después de cualquier cambio, correr toda la suite (`php artisan test`) y confirmar que pasa completa antes de reportar la tarea como terminada. Nunca reportar éxito sin haber corrido y visto pasar los tests.
6. **Documentar lo que se genera, no opcional**: Al finalizar cada ajuste o creación de codigo actualizar o generar la documentación respectiva PHPDoc para mantener así la documentación del sistema en todo momento.
