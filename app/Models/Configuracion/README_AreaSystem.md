# Sistema de Relaciones y Scopes para Area

## Descripción

Este sistema maneja la relación muchos a muchos entre los modelos `Area` y `Sede`, proporcionando scopes reutilizables para filtros, ordenamiento y manejo de relaciones de manera escalable y consistente.

## Componentes

### 1. HasAreaFilterScopes
Trait que proporciona scopes de filtrado específicos para el modelo Area:
- `search()` - Búsqueda general por nombre
- `byNombre()` - Filtro por nombre específico
- `bySede()` - Filtrar áreas por sede específica
- `byPoblacion()` - Filtrar por población (a través de sedes)
- `withFilters()` - Aplicar múltiples filtros dinámicamente

### 2. Relación Many-to-Many
- `Area::sedes()` - Relación con modelo Sede
- `Sede::areas()` - Relación con modelo Area
- Tabla pivot: `area_sede`

### 3. Traits Reutilizados
- `HasRelationScopes` - Manejo seguro de relaciones
- `HasSortingScopes` - Ordenamiento dinámico
- `HasActiveStatus` - Manejo de estados activo/inactivo

## Uso Básico

### En Modelos
```php
use App\Traits\HasAreaFilterScopes;
use App\Traits\HasRelationScopes;
use App\Traits\HasSortingScopes;
use App\Traits\HasActiveStatus;

class Area extends Model
{
    use HasAreaFilterScopes, HasRelationScopes, HasSortingScopes, HasActiveStatus;
    
    // Relación con sedes
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'area_sede');
    }
}
```

### En Controladores
```php
// Obtener áreas con filtros
$areas = Area::withFilters([
    'search' => 'ventas',
    'sede_id' => 1,
    'poblacion_id' => 2
])->get();

// Áreas activas con sus sedes
$areas = Area::active()
    ->withRelations(['sedes'])
    ->orderByName()
    ->get();
```

### En Requests
```php
use App\Traits\HasActiveStatus;

class StoreAreaRequest extends FormRequest
{
    use HasActiveStatus;
    
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'status' => self::getStatusValidationRule(),
            'sedes' => 'array',
            'sedes.*' => 'exists:sedes,id'
        ];
    }
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
            'status_text' => self::getActiveStatusText($this->status),
            'sedes' => SedeResource::collection($this->whenLoaded('sedes')),
            'sedes_count' => $this->when(isset($this->sedes_count), $this->sedes_count),
        ];
    }
}
```

## Scopes Disponibles

### Filtros
```php
// Búsqueda general
Area::search('administración')->get();

// Filtro por nombre
Area::byNombre('ventas')->get();

// Filtrar por sede específica
Area::bySede(1)->get();

// Filtrar por población
Area::byPoblacion(1)->get();

// Múltiples filtros
Area::withFilters([
    'search' => 'ventas',
    'sede_id' => 1,
    'poblacion_id' => 2
])->get();
```

### Estados
```php
// Solo áreas activas
Area::active()->get();

// Solo áreas inactivas
Area::inactive()->get();
```

### Ordenamiento
```php
// Ordenar por nombre
Area::orderByName()->get();

// Ordenar por fecha de creación
Area::orderByCreated()->get();

// Ordenamiento dinámico
Area::withSorting('nombre', 'asc')->get();
```

### Relaciones
```php
// Cargar relaciones específicas
Area::withRelations(['sedes', 'sedes.poblacion'])->get();

// Incluir contadores
Area::withCounts(true)->get();

// Combinar relaciones y contadores
Area::withRelationsAndCounts(['sedes'], true)->get();
```

## Manipulación de Relaciones

### Asignar Sedes
```php
$area = Area::find(1);

// Asignar múltiples sedes
$area->sedes()->attach([1, 2, 3]);

// Sincronizar sedes (remueve las que no están en el array)
$area->sedes()->sync([1, 2]);

// Agregar una sede específica
$area->sedes()->attach(4);
```

### Remover Sedes
```php
// Remover una sede específica
$area->sedes()->detach(4);

// Remover todas las sedes
$area->sedes()->detach();
```

## Consultas Avanzadas

### Áreas con Condiciones Específicas
```php
// Áreas que tienen sedes en una población específica
$areas = Area::whereHas('sedes.poblacion', function($query) {
    $query->where('poblacions.id', 1);
})->get();

// Áreas con más de 2 sedes
$areas = Area::withCount('sedes')
    ->having('sedes_count', '>', 2)
    ->get();

// Áreas que no tienen sedes
$areas = Area::doesntHave('sedes')->get();
```

### Combinaciones Complejas
```php
// Áreas activas con sus sedes, ordenadas por nombre
$areas = Area::active()
    ->withRelations(['sedes'])
    ->orderByName()
    ->get();

// Áreas con filtros, relaciones y ordenamiento
$areas = Area::withFilters(['search' => 'ventas'])
    ->withRelations(['sedes.poblacion'])
    ->withSorting('nombre', 'asc')
    ->get();
```

## Extensión del Sistema

### Agregar Nuevos Filtros
Para agregar nuevos filtros, modifica el trait `HasAreaFilterScopes`:

```php
// En HasAreaFilterScopes.php
public function scopeByNuevoFiltro($query, $valor)
{
    return $query->where('campo', $valor);
}

// En withFilters()
->when(isset($filters['nuevo_filtro']) && $filters['nuevo_filtro'], function ($q) use ($filters) {
    return $q->byNuevoFiltro($filters['nuevo_filtro']);
})
```

### Agregar Nuevos Campos de Ordenamiento
En el modelo Area, modifica `getAllowedSortFields()`:

```php
protected function getAllowedSortFields(): array
{
    return [
        'nombre',
        'status',
        'nuevo_campo',  // Nuevo campo
        'created_at',
        'updated_at'
    ];
}
```

## Archivos del Sistema

### Archivos Creados
- `database/migrations/2025_10_11_203000_create_area_sede_table.php` - Migración tabla pivot
- `app/Traits/HasAreaFilterScopes.php` - Scopes de filtrado
- `database/factories/Configuracion/AreaSedeFactory.php` - Factory para pivot

### Archivos Modificados
- `app/Models/Configuracion/Area.php` - Modelo con relaciones y scopes
- `app/Models/Configuracion/Sede.php` - Modelo con relación many-to-many
- `database/seeders/AreaSeeder.php` - Seeder con relaciones

## Beneficios del Sistema

1. **Reutilizable**: Los traits pueden ser usados en otros modelos
2. **Consistente**: Mismo comportamiento en toda la aplicación
3. **Escalable**: Fácil agregar nuevos filtros y scopes
4. **Mantenible**: Código organizado y documentado
5. **Flexible**: Múltiples formas de filtrar y ordenar
6. **Seguro**: Validación de relaciones y campos permitidos

## Migración de Datos

Si necesitas migrar datos existentes:

```php
// Ejemplo de migración para asignar sedes a áreas existentes
DB::table('areas')->get()->each(function ($area) {
    $sedes = DB::table('sedes')->inRandomOrder()->limit(rand(1, 3))->pluck('id');
    $area->sedes()->attach($sedes);
});
```
