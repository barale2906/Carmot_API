# Uso Correcto de Traits HasActiveStatus y HasActiveStatusValidation

## Descripción

Este documento explica el uso correcto e integrado de los traits `HasActiveStatus` y `HasActiveStatusValidation` para manejar estados de manera consistente y escalable en toda la aplicación.

## Componentes del Sistema

### 1. HasActiveStatus (Trait Base)
Define los estados disponibles y proporciona funcionalidades básicas:
- Opciones de estado
- Texto de estado
- Scopes de consulta
- Accessors para instancias

### 2. HasActiveStatusValidation (Trait de Validación)
Genera validaciones dinámicas basadas en los estados definidos:
- Reglas de validación automáticas
- Mensajes de error dinámicos
- Se actualiza automáticamente cuando se agregan nuevos estados

## Uso Correcto

### En Requests
```php
use App\Traits\HasActiveStatus;
use App\Traits\HasActiveStatusValidation;

class StoreAreaRequest extends FormRequest
{
    use HasActiveStatus, HasActiveStatusValidation;
    
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'status' => self::getStatusValidationRule(), // ✅ Dinámico
            'sedes' => ['sometimes', 'array'],
        ];
    }
    
    public function messages(): array
    {
        return array_merge([
            'nombre.required' => 'El nombre es obligatorio.',
        ], self::getStatusValidationMessages()); // ✅ Dinámico
    }
}
```

### En Modelos
```php
use App\Traits\HasActiveStatus;

class Area extends Model
{
    use HasActiveStatus;
    
    // Automáticamente disponible:
    // - scopeActive()
    // - scopeInactive()
    // - getActiveStatusTextAttribute()
    // - getActiveStatusOptions()
    // - getActiveStatusText()
}
```

### En Resources
```php
use App\Traits\HasActiveStatus;

class AreaResource extends JsonResource
{
    use HasActiveStatus;
    
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'status' => $this->status,
            'status_text' => self::getActiveStatusText($this->status), // ✅ Dinámico
        ];
    }
}
```

## Funcionamiento Conjunto

### 1. Definición de Estados
```php
// HasActiveStatus define los estados
public static function getActiveStatusOptions(): array
{
    return [
        0 => 'Inactivo',
        1 => 'Activo',
    ];
}
```

### 2. Generación de Validaciones
```php
// HasActiveStatusValidation lee los estados y genera reglas
public static function getStatusValidationRule(): string
{
    $statusOptions = static::getActiveStatusOptions(); // ✅ Usa static::
    $statusKeys = array_keys($statusOptions);
    return 'sometimes|integer|in:' . implode(',', $statusKeys);
    // Resultado: 'sometimes|integer|in:0,1'
}
```

### 3. Generación de Mensajes
```php
// HasActiveStatusValidation genera mensajes dinámicos
public static function getStatusValidationMessages(): array
{
    $statusOptions = static::getActiveStatusOptions();
    $statusList = [];
    
    foreach ($statusOptions as $key => $value) {
        $statusList[] = "$key ($value)";
    }
    
    return [
        'status.integer' => 'El estado debe ser un número entero.',
        'status.in' => 'El estado debe ser uno de los valores válidos: ' . implode(', ', $statusList) . '.',
    ];
    // Resultado: 'El estado debe ser uno de los valores válidos: 0 (Inactivo), 1 (Activo).'
}
```

## Ejemplos de Uso

### Consultas con Scopes
```php
// Solo áreas activas
$areasActivas = Area::active()->get();

// Solo áreas inactivas
$areasInactivas = Area::inactive()->get();

// Combinar con otros scopes
$areas = Area::active()
    ->withRelations(['sedes'])
    ->orderByName()
    ->get();
```

### Accessors en Instancias
```php
$area = Area::find(1);
$statusText = $area->active_status_text; // 'Activo' o 'Inactivo'
```

### Validación Automática
```php
// En StoreAreaRequest
$request->validate([
    'status' => self::getStatusValidationRule(),
    // Automáticamente valida: sometimes|integer|in:0,1
]);
```

### Respuestas de API
```json
{
    "id": 1,
    "nombre": "Ventas",
    "status": 1,
    "status_text": "Activo"
}
```

## Agregar Nuevos Estados

### Paso 1: Modificar HasActiveStatus
```php
// En app/Traits/HasActiveStatus.php
public static function getActiveStatusOptions(): array
{
    return [
        0 => 'Inactivo',
        1 => 'Activo',
        2 => 'Pendiente',        // ✅ Nuevo estado
        3 => 'En Revisión',       // ✅ Otro nuevo estado
    ];
}
```

### Paso 2: Todo se Actualiza Automáticamente
- ✅ **Validaciones** incluyen los nuevos estados
- ✅ **Mensajes de error** muestran los nuevos estados
- ✅ **Resources** formatean los nuevos estados
- ✅ **Scopes** pueden usar los nuevos estados
- ✅ **Accessors** manejan los nuevos estados

### Paso 3: Agregar Scopes Personalizados (Opcional)
```php
// En el modelo Area
public function scopePendiente($query)
{
    return $query->where('status', 2);
}

public function scopeEnRevision($query)
{
    return $query->where('status', 3);
}
```

## Ventajas del Sistema

### 1. **Consistencia**
- Mismos estados en toda la aplicación
- Validaciones uniformes
- Mensajes coherentes

### 2. **Automatización**
- Validaciones se generan automáticamente
- Mensajes se actualizan automáticamente
- No hay que tocar múltiples archivos

### 3. **Escalabilidad**
- Fácil agregar nuevos estados
- Un solo lugar para modificar
- Se propaga automáticamente

### 4. **Mantenibilidad**
- Código centralizado
- Menos duplicación
- Fácil de entender

### 5. **Reutilización**
- Funciona en cualquier modelo
- Misma lógica en toda la app
- Traits independientes

## Archivos que se Actualizan Automáticamente

Cuando agregas un nuevo estado, estos archivos se actualizan automáticamente:

### Requests
- `StoreAreaRequest.php`
- `UpdateAreaRequest.php`
- `StoreSedeRequest.php`
- `UpdateSedeRequest.php`
- Cualquier request que use los traits

### Resources
- `AreaResource.php`
- `SedeResource.php`
- Cualquier resource que use los traits

### Modelos
- `Area.php`
- `Sede.php`
- Cualquier modelo que use HasActiveStatus

## Migración de Estados Existentes

Si necesitas cambiar valores de estados existentes:

```php
// Ejemplo de migración
Schema::table('areas', function (Blueprint $table) {
    // Cambiar estados existentes si es necesario
    DB::table('areas')
        ->where('status', 1)
        ->where('some_condition', true)
        ->update(['status' => 2]); // Nuevo estado "Pendiente"
});
```

## Mejores Prácticas

### 1. **Siempre usar ambos traits juntos en requests**
```php
use HasActiveStatus, HasActiveStatusValidation; // ✅ Correcto
```

### 2. **Usar static:: en lugar de self:: en traits**
```php
$statusOptions = static::getActiveStatusOptions(); // ✅ Correcto
$statusOptions = self::getActiveStatusOptions();   // ❌ Incorrecto
```

### 3. **Documentar nuevos estados**
```php
/**
 * Estados disponibles:
 * 0 = Inactivo
 * 1 = Activo
 * 2 = Pendiente
 * 3 = En Revisión
 */
```

### 4. **Usar accessors en resources**
```php
'status_text' => self::getActiveStatusText($this->status), // ✅ Dinámico
```

### 5. **Combinar scopes**
```php
$areas = Area::active()
    ->withRelations(['sedes'])
    ->orderByName()
    ->get();
```

## Solución de Problemas

### Error: "Call to undefined method getActiveStatusOptions()"
**Causa**: El trait HasActiveStatusValidation no puede acceder a HasActiveStatus
**Solución**: Usar `static::` en lugar de `self::`

### Error: "Validation rule not found"
**Causa**: No se está usando HasActiveStatusValidation en el request
**Solución**: Agregar el trait al request

### Error: "Status text not showing"
**Causa**: No se está usando HasActiveStatus en el resource
**Solución**: Agregar el trait y usar getActiveStatusText()

### Estados no se actualizan automáticamente
**Causa**: Cache de validaciones
**Solución**: Limpiar cache o reiniciar servidor
