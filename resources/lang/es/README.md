# Traducciones al Español - API Carmot

## Configuración Completada

✅ **Paquetes instalados:**
- `spatie/laravel-translatable` - Para contenido dinámico multilingüe
- Archivos de traducción del framework Laravel

✅ **Configuración realizada:**
- Idioma por defecto: Español (`es`)
- Idioma de respaldo: Inglés (`en`)
- Faker configurado para español (`es_ES`)

## Archivos de Traducción

### 1. **validation.php** - Mensajes de validación
Contiene todas las traducciones de las reglas de validación de Laravel.

### 2. **auth.php** - Mensajes de autenticación
Mensajes para login, logout y errores de autenticación.

### 3. **passwords.php** - Restablecimiento de contraseñas
Mensajes para el proceso de restablecimiento de contraseñas.

### 4. **pagination.php** - Paginación
Enlaces de navegación para paginación.

### 5. **api.php** - Mensajes personalizados de la API
Mensajes específicos para tu aplicación Carmot.

## Uso en el Código

### Traducciones Estáticas
```php
// Obtener un mensaje traducido
$message = __('api.success.created');
$message = __('validation.required', ['attribute' => 'nombre']);

// Con parámetros
$message = __('api.user.profile_updated');
```

### Traducciones Dinámicas (Spatie Translatable)
```php
// En tu modelo User
$user = User::find(1);

// Establecer traducciones
$user->setTranslation('name', 'es', 'Juan Pérez');
$user->setTranslation('name', 'en', 'John Doe');

// Obtener traducción
$nameInSpanish = $user->getTranslation('name', 'es');
$nameInEnglish = $user->getTranslation('name', 'en');

// Obtener en el idioma actual
$name = $user->name; // Automáticamente en el idioma configurado
```

### Cambiar Idioma Dinámicamente
```php
// Cambiar idioma de la aplicación
App::setLocale('en');

// Obtener idioma actual
$currentLocale = App::getLocale();
```

## Endpoints de la API

### 1. **GET /api/translations/messages**
Obtiene todos los mensajes de la API en el idioma actual.

### 2. **POST /api/translations/locale**
Cambia el idioma de la aplicación.
```json
{
    "locale": "es"
}
```

### 3. **POST /api/translations/validate-example**
Ejemplo de validación con mensajes en español.
```json
{
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

## Configuración del Modelo User

El modelo `User` ya está configurado para usar traducciones:

```php
use Spatie\Translatable\HasTranslations;

class User extends Authenticatable
{
    use HasTranslations;
    
    public $translatable = [
        'name', // Campo traducible
    ];
}
```

## Agregar Nuevos Campos Traducibles

1. **Agregar al modelo:**
```php
public $translatable = [
    'name',
    'description', // Nuevo campo
    'bio',         // Nuevo campo
];
```

2. **Crear migración:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->json('description')->nullable();
    $table->json('bio')->nullable();
});
```

3. **Usar en el código:**
```php
$user->setTranslation('description', 'es', 'Descripción en español');
$user->setTranslation('description', 'en', 'Description in English');
```

## Mejores Prácticas

1. **Siempre usar `__()` para mensajes estáticos**
2. **Usar `App::setLocale()` para cambiar idioma dinámicamente**
3. **Configurar campos traducibles en `$translatable`**
4. **Usar `getTranslation()` y `setTranslation()` para contenido dinámico**
5. **Mantener consistencia en los nombres de las claves de traducción**

## Próximos Pasos

1. **Agregar más campos traducibles según necesidades**
2. **Crear traducciones para otros modelos**
3. **Implementar middleware para detectar idioma del cliente**
4. **Agregar más idiomas si es necesario**
