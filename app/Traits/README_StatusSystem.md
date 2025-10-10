# Sistema de Status Escalable

## Descripción

Este sistema permite manejar status de manera dinámica y escalable para modelos que necesiten estados activo/inactivo o estados personalizados.

## Componentes

### 1. HasActiveStatus
Trait principal que maneja los status básicos y proporciona métodos para:
- Obtener opciones de status
- Obtener texto de status
- Scopes de consulta (active, inactive, byStatus)

### 2. HasActiveStatusValidation
Trait que proporciona reglas de validación dinámicas:
- Genera reglas de validación basadas en los status disponibles
- Genera mensajes de error dinámicos
- Se actualiza automáticamente cuando se agregan nuevos status

## Uso Básico

### En Modelos
```php
use App\Traits\HasActiveStatus;

class MiModelo extends Model
{
    use HasActiveStatus;
    
    // El modelo automáticamente tendrá acceso a:
    // - getActiveStatusOptions()
    // - getActiveStatusText()
    // - Scopes: active(), inactive(), byStatus()
}
```

### En Requests
```php
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;

class StoreMiModeloRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;
    
    public function rules(): array
    {
        return [
            'status' => self::getStatusValidationRule(),
            // Otras reglas...
        ];
    }
    
    public function messages(): array
    {
        return array_merge([
            // Otros mensajes...
        ], self::getStatusValidationMessages());
    }
}
```

### En Resources
```php
use App\Traits\HasActiveStatus;

class MiModeloResource extends JsonResource
{
    use HasActiveStatus;
    
    public function toArray($request): array
    {
        return [
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status),
            // Otros campos...
        ];
    }
}
```

## Agregar Nuevos Status

Para agregar nuevos status, simplemente modifica el método `getActiveStatusOptions()` en el trait `HasActiveStatus`:

```php
public static function getActiveStatusOptions(): array
{
    return [
        0 => 'Inactivo',
        1 => 'Activo',
        2 => 'Pendiente',        // Nuevo status
        3 => 'En Revisión',       // Otro nuevo status
        4 => 'Archivado',         // Otro nuevo status
    ];
}
```

### Beneficios de este Enfoque

1. **Automático**: Todos los requests, resources y controladores se actualizan automáticamente
2. **Consistente**: Mismo comportamiento en toda la aplicación
3. **Escalable**: Fácil agregar nuevos status sin tocar múltiples archivos
4. **Mantenible**: Un solo lugar para modificar los status
5. **Validación Dinámica**: Las reglas de validación se generan automáticamente

## Ejemplo de Extensión

Si quieres agregar un status "Pendiente" (valor 2):

1. Modifica `HasActiveStatus::getActiveStatusOptions()`:
```php
return [
    0 => 'Inactivo',
    1 => 'Activo',
    2 => 'Pendiente',
];
```

2. Todos los archivos que usan los traits se actualizarán automáticamente:
- ✅ Validaciones incluirán el nuevo status
- ✅ Mensajes de error mostrarán el nuevo status
- ✅ Resources mostrarán el texto correcto
- ✅ Controladores tendrán las opciones actualizadas

## Archivos que se Actualizan Automáticamente

- `StoreModuloRequest.php`
- `UpdateModuloRequest.php`
- `StoreCursoRequest.php`
- `UpdateCursoRequest.php`
- `ModuloResource.php`
- `CursoResource.php`
- `ModuloController.php`
- `CursoController.php`
- Cualquier otro archivo que use los traits

## Migración de Status Existentes

Si ya tienes datos con status hardcodeados, puedes crear una migración para actualizar los valores:

```php
// Ejemplo de migración para agregar nuevo status
Schema::table('modulos', function (Blueprint $table) {
    // Si necesitas cambiar valores existentes
    DB::table('modulos')
        ->where('status', 1)
        ->where('some_condition', true)
        ->update(['status' => 2]); // Nuevo status "Pendiente"
});
```
