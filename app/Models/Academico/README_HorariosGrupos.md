# Gestión de Horarios de Grupos

Este documento describe la funcionalidad implementada para la gestión de horarios específicos de grupos académicos.

## Descripción General

Los grupos académicos ahora pueden tener horarios específicos que definen:
- Día de la semana en que se dictan las clases
- Hora de inicio de cada clase
- Área donde se dicta la clase
- Duración y frecuencia de las clases

## Estructura de Datos

### Relación Grupo-Horario

- **Un grupo** puede tener **múltiples horarios**
- **Cada horario** pertenece a **un grupo específico**
- Los horarios de grupo se identifican con `tipo = false` en la tabla `horarios`
- Los campos `grupo_id` y `grupo_nombre` se llenan automáticamente

### Campos del Horario de Grupo

```php
[
    'sede_id' => 'ID de la sede (heredado del grupo)',
    'area_id' => 'ID del área donde se dicta la clase',
    'grupo_id' => 'ID del grupo (automático)',
    'grupo_nombre' => 'Nombre del grupo (automático)',
    'tipo' => false, // Siempre false para horarios de grupo
    'periodo' => true, // Siempre true (hora de inicio)
    'dia' => 'lunes|martes|miércoles|jueves|viernes|sábado|domingo',
    'hora' => 'HH:MM', // Hora de inicio
    'duracion_horas' => '1-8', // Duración de la clase en horas (nuevo campo)
    'status' => '1|0' // Activo|Inactivo
]
```

## Endpoints Disponibles

### 1. Crear Grupo con Horarios (Nuevo)
```
POST /api/academico/grupos
```

**Cuerpo de la petición:**
```json
{
    "sede_id": 1,
    "modulo_id": 2,
    "profesor_id": 3,
    "nombre": "Grupo A",
    "inscritos": 25,
    "jornada": 0,
    "status": 1,
    "horarios": [
        {
            "area_id": 1,
            "dia": "lunes",
            "hora": "08:00",
            "duracion_horas": 3,
            "status": 1
        },
        {
            "area_id": 2,
            "dia": "miércoles",
            "hora": "10:00",
            "duracion_horas": 2,
            "status": 1
        }
    ]
}
```

**Respuesta:**
```json
{
    "message": "Grupo creado exitosamente. Horarios asignados correctamente.",
    "data": {
        "id": 1,
        "nombre": "Grupo A",
        "horarios": [
            {
                "id": 1,
                "dia": "lunes",
                "hora": "08:00:00",
                "duracion_horas": 3,
                "hora_fin": "11:00:00",
                "area": {
                    "id": 1,
                    "nombre": "Aula 101"
                }
            }
        ],
        "total_horas_semana": 5,
        "dias_clase": ["lunes", "miércoles"],
        "tiene_horarios": true
    }
}
```

### 2. Obtener Horarios de un Grupo
```
GET /api/academico/grupos/{grupo}/horarios
```

**Parámetros de consulta:**
- `status`: Filtrar por estado (1=activo, 0=inactivo)
- `dia`: Filtrar por día de la semana

**Respuesta:**
```json
{
    "data": [
        {
            "id": 1,
            "dia": "lunes",
            "hora": "08:00:00",
            "duracion_horas": 3,
            "hora_fin": "11:00:00",
            "area": {
                "id": 1,
                "nombre": "Aula 101"
            },
            "status": 1,
            "status_text": "Activo",
            "created_at": "2024-01-01 10:00:00",
            "updated_at": "2024-01-01 10:00:00"
        }
    ]
}
```

### 2. Asignar Horarios a un Grupo
```
POST /api/academico/grupos/{grupo}/horarios
```

**Cuerpo de la petición:**
```json
{
    "horarios": [
        {
            "area_id": 1,
            "dia": "lunes",
            "hora": "08:00",
            "duracion_horas": 3,
            "status": 1
        },
        {
            "area_id": 2,
            "dia": "miércoles",
            "hora": "10:00",
            "duracion_horas": 2,
            "status": 1
        }
    ]
}
```

**Respuesta:**
```json
{
    "message": "Horarios asignados exitosamente al grupo.",
    "data": {
        "id": 1,
        "nombre": "Grupo A",
        "horarios": [...],
        "total_horas_semana": 5,
        "dias_clase": ["lunes", "miércoles"],
        "tiene_horarios": true
    }
}
```

### 3. Actualizar Horarios de un Grupo
```
PUT /api/academico/grupos/{grupo}/horarios
```

**Cuerpo de la petición:** (igual que POST)

### 4. Eliminar Horarios de un Grupo
```
DELETE /api/academico/grupos/{grupo}/horarios
```

**Respuesta:**
```json
{
    "message": "Horarios del grupo eliminados exitosamente."
}
```

### 5. Obtener Estadísticas de Horarios
```
GET /api/academico/grupos/{grupo}/horarios/estadisticas
```

**Respuesta:**
```json
{
    "data": {
        "total_horarios": 2,
        "total_horas_semana": 2,
        "dias_clase": ["lunes", "miércoles"],
        "horarios_por_dia": {
            "lunes": {
                "cantidad": 1,
                "horas": 1,
                "horarios": ["08:00:00"]
            },
            "miércoles": {
                "cantidad": 1,
                "horas": 1,
                "horarios": ["10:00:00"]
            }
        },
        "areas_utilizadas": ["Aula 101", "Aula 102"]
    }
}
```

## Validaciones Implementadas

### 1. Validación de Solapamiento
- Se valida que no haya solapamiento de horarios en el mismo día
- Considera la duración real de cada clase (`duracion_horas`)
- Calcula automáticamente la hora de fin para detectar conflictos
- Ejemplo: Clase de 8:00-11:00 (3h) no puede solaparse con clase de 10:00-12:00 (2h)

### 2. Validación de Datos
- `area_id`: Debe existir en la tabla `areas`
- `dia`: Debe ser un día válido de la semana
- `hora`: Debe tener formato HH:MM
- `duracion_horas`: Debe ser entero entre 1 y 8 horas
- `status`: Debe ser 1 (activo) o 0 (inactivo)

## Métodos del Modelo Grupo

### 1. Relación con Horarios
```php
$grupo->horarios() // Obtiene todos los horarios del grupo
```

### 2. Métodos de Utilidad
```php
$grupo->tieneHorarios() // Verifica si tiene horarios configurados
$grupo->total_horas_semana // Obtiene total de horas por semana (suma duraciones reales)
$grupo->dias_clase // Obtiene array de días de clase
$grupo->getHorasPorDia() // Obtiene total de horas por día (suma duraciones)
```

## Permisos Requeridos

- `aca_grupos`: Para consultar horarios y estadísticas
- `aca_grupoCrear`: Para asignar horarios
- `aca_grupoEditar`: Para actualizar horarios
- `aca_grupoInactivar`: Para eliminar horarios

## Ejemplos de Uso

### Crear Grupo con Horarios
```php
// Crear grupo con horarios en una sola operación
$grupo = Grupo::create([
    'sede_id' => 1,
    'modulo_id' => 2,
    'profesor_id' => 3,
    'nombre' => 'Grupo Programación',
    'inscritos' => 25,
    'jornada' => 0,
    'status' => 1
]);

$horarios = [
    [
        'area_id' => 1,
        'dia' => 'lunes',
        'hora' => '08:00',
        'duracion_horas' => 3, // Clase de 3 horas
        'status' => 1
    ],
    [
        'area_id' => 2,
        'dia' => 'miércoles',
        'hora' => '10:00',
        'duracion_horas' => 2, // Clase de 2 horas
        'status' => 1
    ]
];

$controller->asignarHorariosAGrupo($grupo, $horarios);
```

### Asignar Horarios a un Grupo Existente
```php
// En el controlador
$horarios = [
    [
        'area_id' => 1,
        'dia' => 'lunes',
        'hora' => '08:00',
        'duracion_horas' => 3,
        'status' => 1
    ],
    [
        'area_id' => 1,
        'dia' => 'miércoles',
        'hora' => '08:00',
        'duracion_horas' => 2,
        'status' => 1
    ]
];

$grupo = Grupo::find(1);
$controller->asignarHorariosAGrupo($grupo, $horarios);
```

### Obtener Estadísticas
```php
$estadisticas = $controller->obtenerEstadisticasHorariosGrupo($grupo);
echo "Total de horas por semana: " . $estadisticas['total_horas_semana'];
```

## Factory y Seeder

### Factory de Grupos con Horarios
```php
// Crear grupo con horarios aleatorios
Grupo::factory()->conHorarios()->create();

// Crear grupo de mañana con horarios específicos
Grupo::factory()->manana()->conHorariosManana()->create();

// Crear grupo de tarde con horarios específicos
Grupo::factory()->tarde()->conHorariosTarde()->create();

// Crear grupo de noche con horarios específicos
Grupo::factory()->noche()->conHorariosNoche()->create();

// Crear grupo de fin de semana
Grupo::factory()->finDeSemana()->conHorariosFinSemana()->create();

// Crear grupo intensivo (clases largas)
Grupo::factory()->conHorariosIntensivos()->create();
```

### Seeder Actualizado
El `GrupoSeeder` ahora crea grupos con distribución realista:
- **20%** grupos sin horarios
- **30%** grupos con horarios aleatorios
- **15%** grupos de mañana con horarios específicos
- **15%** grupos de tarde con horarios específicos
- **10%** grupos de noche con horarios específicos
- **5%** grupos de fin de semana
- **5%** grupos intensivos

## Notas Importantes

1. **Eliminación en Cascada**: Al eliminar un grupo, sus horarios se eliminan automáticamente
2. **Validación de Solapamiento**: Se valida automáticamente al crear o actualizar horarios considerando la duración real
3. **Campos Automáticos**: `grupo_id`, `grupo_nombre`, `sede_id`, `tipo` y `periodo` se llenan automáticamente
4. **Duración de Clases**: Campo `duracion_horas` permite clases de 1-8 horas (por defecto 1 hora)
5. **Soft Delete**: Los horarios respetan el soft delete del grupo
6. **Creación en Una Sola Petición**: Se puede crear un grupo con horarios usando el endpoint `POST /api/academico/grupos`
7. **Cálculo Automático**: El campo `hora_fin` se calcula automáticamente como `hora + duracion_horas`
8. **Estadísticas Precisas**: `total_horas_semana` suma las duraciones reales de todos los horarios
