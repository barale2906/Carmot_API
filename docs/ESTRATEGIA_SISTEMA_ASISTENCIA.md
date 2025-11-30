# Estrategia de Diseño: Sistema de Registro de Asistencia

## 📋 Índice

1. [Contexto y Requerimientos](#contexto-y-requerimientos)
2. [Análisis del Modelo de Datos Actual](#análisis-del-modelo-de-datos-actual)
3. [Diseño de Base de Datos](#diseño-de-base-de-datos)
4. [Modelos y Relaciones](#modelos-y-relaciones)
5. [Lógica de Negocio](#lógica-de-negocio)
6. [Endpoints y Funcionalidades](#endpoints-y-funcionalidades)
7. [Cálculos y Reportes](#cálculos-y-reportes)
8. [Consideraciones Técnicas](#consideraciones-técnicas)

---

## 1. Contexto y Requerimientos

### 1.1 Requerimientos Funcionales

-   ✅ **Registro de asistencia por estudiante-grupo-ciclo**
-   ✅ **Los grupos son los mismos en el tiempo, pero se registran a diferentes ciclos**
-   ✅ **Las asistencias están atadas al grupo (módulo) y al ciclo (definido en la matrícula)**
-   ✅ **Las fechas de clases se definen según el ciclo y las fechas del grupo en ese ciclo**
-   ✅ **Desplegar lista de asistencia mostrando estudiantes por grupo para ciclos activos**
-   ✅ **Definir topes mínimos de asistencia para no perder por fallas**
-   ✅ **Generar informe de asistencia por estudiante de todos los módulos con porcentaje de asistencia**
-   ✅ **Cálculo separado por curso (un estudiante puede asistir a diferentes cursos)**

### 1.2 Entidades Clave Identificadas

-   **Estudiante**: Usuario matriculado
-   **Matrícula**: Relaciona estudiante con curso y ciclo
-   **Ciclo**: Define período académico con fechas de inicio/fin
-   **Grupo**: Representa un módulo específico con horarios
-   **Ciclo-Grupo**: Relación muchos-a-muchos con fechas específicas (`fecha_inicio_grupo`, `fecha_fin_grupo`)
-   **Horario**: Define días y horas de clase del grupo

---

## 2. Análisis del Modelo de Datos Actual

### 2.1 Estructura Actual

```
Estudiante (User)
    ↓ (matricula)
Matrícula
    ├─ curso_id → Curso
    ├─ ciclo_id → Ciclo
    └─ estudiante_id → User
        └─ status: 1 (Activo)

Ciclo
    ├─ fecha_inicio
    ├─ fecha_fin
    └─ grupos (muchos-a-muchos)
        └─ ciclo_grupo (pivot)
            ├─ fecha_inicio_grupo
            ├─ fecha_fin_grupo
            └─ orden

Grupo
    ├─ modulo_id → Módulo
    ├─ profesor_id → User
    └─ horarios (uno-a-muchos)
        ├─ dia (día de la semana)
        ├─ hora
        └─ duracion_horas
```

### 2.2 Observaciones Importantes

1. **Grupos reutilizables**: Los mismos grupos se usan en diferentes ciclos
2. **Fechas específicas por ciclo**: Cada grupo tiene fechas diferentes en cada ciclo (`fecha_inicio_grupo`, `fecha_fin_grupo`)
3. **Horarios del grupo**: Los horarios definen los días y horas de clase
4. **Estudiantes por matrícula**: Se obtienen a través de matrículas activas del ciclo

---

## 3. Diseño de Base de Datos

### 3.1 Tabla: `asistencia_clases_programadas`

Almacena las sesiones de clase programadas para cada grupo en cada ciclo.

```sql
CREATE TABLE asistencia_clases_programadas (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Relaciones
    grupo_id BIGINT UNSIGNED NOT NULL,
    ciclo_id BIGINT UNSIGNED NOT NULL,

    -- Información de la clase
    fecha_clase DATE NOT NULL COMMENT 'Fecha en que se dicta la clase',
    hora_inicio TIME NOT NULL COMMENT 'Hora de inicio de la clase',
    hora_fin TIME NOT NULL COMMENT 'Hora de fin de la clase',
    duracion_horas DECIMAL(4,2) NOT NULL COMMENT 'Duración en horas de la clase',

    -- Estado y control
    estado ENUM('programada', 'dictada', 'cancelada', 'reprogramada') DEFAULT 'programada',
    observaciones TEXT NULL COMMENT 'Observaciones sobre la clase (ej: cambio de aula)',

    -- Auditoría
    creado_por_id BIGINT UNSIGNED NULL COMMENT 'Usuario que programó la clase',
    fecha_programacion DATETIME NULL COMMENT 'Fecha en que se programó',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    -- Índices
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (ciclo_id) REFERENCES ciclos(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por_id) REFERENCES users(id) ON DELETE SET NULL,

    -- Índice único para evitar clases duplicadas
    UNIQUE KEY unique_clase_grupo_ciclo_fecha_hora (grupo_id, ciclo_id, fecha_clase, hora_inicio),

    -- Índices para búsquedas rápidas
    INDEX idx_fecha_clase (fecha_clase),
    INDEX idx_ciclo_grupo (ciclo_id, grupo_id),
    INDEX idx_estado (estado)
) COMMENT='Sesiones de clase programadas para grupos en ciclos específicos';
```

**Consideraciones**:

-   Una clase se identifica por: grupo + ciclo + fecha + hora_inicio
-   El estado permite manejar clases canceladas o reprogramadas
-   La duración se calcula automáticamente o se puede definir manualmente

### 3.2 Tabla: `asistencias` (YA EXISTE)

Registra la asistencia de cada estudiante a cada clase.

**NOTA**: Esta tabla ya existe y debe ser actualizada con los campos necesarios.

```sql
-- Migración para actualizar tabla existente
ALTER TABLE asistencias (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Relaciones principales
    estudiante_id BIGINT UNSIGNED NOT NULL COMMENT 'Estudiante que asiste',
    clase_programada_id BIGINT UNSIGNED NOT NULL COMMENT 'Clase a la que asiste',
    grupo_id BIGINT UNSIGNED NOT NULL COMMENT 'Grupo (para búsquedas rápidas)',
    ciclo_id BIGINT UNSIGNED NOT NULL COMMENT 'Ciclo (para búsquedas rápidas)',
    modulo_id BIGINT UNSIGNED NOT NULL COMMENT 'Módulo (para reportes)',
    curso_id BIGINT UNSIGNED NOT NULL COMMENT 'Curso (para reportes por curso)',

    -- Información de asistencia
    estado ENUM('presente', 'ausente', 'justificado', 'tardanza') DEFAULT 'presente',
    hora_registro TIME NULL COMMENT 'Hora en que se registró la asistencia',
    observaciones TEXT NULL COMMENT 'Observaciones (ej: motivo de justificación)',

    -- Auditoría
    registrado_por_id BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que registró la asistencia',
    fecha_registro DATETIME NOT NULL COMMENT 'Fecha y hora del registro',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    -- Índices y claves foráneas
    FOREIGN KEY (estudiante_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (clase_programada_id) REFERENCES asistencia_clases_programadas(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (ciclo_id) REFERENCES ciclos(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Índice único: un estudiante solo puede tener una asistencia por clase
    UNIQUE KEY unique_asistencia_estudiante_clase (estudiante_id, clase_programada_id),

    -- Índices para búsquedas y reportes
    INDEX idx_estudiante_ciclo (estudiante_id, ciclo_id),
    INDEX idx_estudiante_grupo (estudiante_id, grupo_id),
    INDEX idx_estudiante_curso (estudiante_id, curso_id),
    INDEX idx_clase_programada (clase_programada_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha_registro (fecha_registro)
) COMMENT='Registro de asistencia de estudiantes a clases programadas';
```

**Consideraciones**:

-   Se guardan campos redundantes (grupo_id, ciclo_id, modulo_id, curso_id) para optimizar consultas de reportes
-   El estado permite manejar diferentes tipos de asistencia
-   Un estudiante solo puede tener un registro por clase (índice único)

### 3.3 Tabla: `asistencia_configuraciones`

Define los topes mínimos de asistencia por curso o módulo.

```sql
CREATE TABLE asistencia_configuraciones (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    -- Alcance de la configuración
    curso_id BIGINT UNSIGNED NULL COMMENT 'Si es NULL, aplica a todos los cursos',
    modulo_id BIGINT UNSIGNED NULL COMMENT 'Si es NULL, aplica a todos los módulos del curso',

    -- Configuración de asistencia
    porcentaje_minimo DECIMAL(5,2) NOT NULL DEFAULT 80.00 COMMENT 'Porcentaje mínimo de asistencia requerido (0-100)',
    horas_minimas INT NULL COMMENT 'Horas mínimas de asistencia requeridas (alternativa al porcentaje)',
    aplicar_justificaciones BOOLEAN DEFAULT TRUE COMMENT 'Si las ausencias justificadas cuentan para el mínimo',

    -- Configuración de pérdida
    perder_por_fallas BOOLEAN DEFAULT TRUE COMMENT 'Si se pierde por no cumplir el mínimo',
    fecha_inicio_vigencia DATE NULL COMMENT 'Fecha desde la cual aplica esta configuración',
    fecha_fin_vigencia DATE NULL COMMENT 'Fecha hasta la cual aplica esta configuración',

    -- Observaciones
    observaciones TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    -- Índices y claves foráneas
    FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,

    -- Índice para búsquedas rápidas
    INDEX idx_curso_modulo (curso_id, modulo_id),
    INDEX idx_vigencia (fecha_inicio_vigencia, fecha_fin_vigencia)
) COMMENT='Configuración de topes mínimos de asistencia por curso o módulo';
```

**Consideraciones**:

-   Permite configuración global (curso_id y modulo_id NULL)
-   Permite configuración por curso (solo curso_id)
-   Permite configuración por módulo específico (curso_id y modulo_id)
-   Las fechas de vigencia permiten cambios históricos

---

## 4. Modelos y Relaciones

### 4.1 Modelo: `AsistenciaClaseProgramada`

```php
class AsistenciaClaseProgramada extends Model
{
    // Relaciones
    public function grupo(): BelongsTo
    public function ciclo(): BelongsTo
    public function creadoPor(): BelongsTo
    public function asistencias(): HasMany

    // Scopes
    public function scopeByGrupo($query, $grupoId)
    public function scopeByCiclo($query, $cicloId)
    public function scopeByFecha($query, $fecha)
    public function scopeDictadas($query)
    public function scopeProgramadas($query)

    // Métodos auxiliares
    public function calcularDuracionHoras(): float
    public function estaEnRangoFechasGrupo(): bool
    public function puedeRegistrarAsistencia(): bool
}
```

### 4.2 Modelo: `Asistencia`

```php
class Asistencia extends Model
{
    // Relaciones
    public function estudiante(): BelongsTo
    public function claseProgramada(): BelongsTo
    public function grupo(): BelongsTo
    public function ciclo(): BelongsTo
    public function modulo(): BelongsTo
    public function curso(): BelongsTo
    public function registradoPor(): BelongsTo

    // Scopes
    public function scopeByEstudiante($query, $estudianteId)
    public function scopeByGrupo($query, $grupoId)
    public function scopeByCiclo($query, $cicloId)
    public function scopeByCurso($query, $cursoId)
    public function scopePresentes($query)
    public function scopeAusentes($query)
    public function scopeJustificadas($query)

    // Métodos auxiliares
    public function esPresente(): bool
    public function esJustificada(): bool
    public function contarParaMinimo(): bool
}
```

### 4.3 Modelo: `AsistenciaConfiguracion`

```php
class AsistenciaConfiguracion extends Model
{
    // Relaciones
    public function curso(): BelongsTo
    public function modulo(): BelongsTo

    // Scopes
    public function scopeVigente($query, $fecha = null)
    public function scopeByCurso($query, $cursoId)
    public function scopeByModulo($query, $moduloId)

    // Métodos auxiliares
    public function esVigente($fecha = null): bool
    public function aplicarA($cursoId, $moduloId = null): bool
    public static function obtenerPara($cursoId, $moduloId = null, $fecha = null)
}
```

---

## 5. Lógica de Negocio

### 5.1 Generación Automática de Clases Programadas

**Proceso**:

1. Cuando se asigna un grupo a un ciclo, se generan automáticamente las clases programadas
2. Se basan en:
    - Fechas del grupo en el ciclo (`fecha_inicio_grupo`, `fecha_fin_grupo`)
    - Horarios del grupo (días y horas de la semana)
    - Duración de cada horario

**Algoritmo**:

```php
function generarClasesProgramadas($grupo, $ciclo) {
    $fechaInicio = $ciclo->grupos()->where('grupo_id', $grupo->id)->first()->pivot->fecha_inicio_grupo;
    $fechaFin = $ciclo->grupos()->where('grupo_id', $grupo->id)->first()->pivot->fecha_fin_grupo;
    $horarios = $grupo->horarios;

    $fechaActual = Carbon::parse($fechaInicio);
    $fechaFinCarbon = Carbon::parse($fechaFin);

    while ($fechaActual <= $fechaFinCarbon) {
        foreach ($horarios as $horario) {
            if ($fechaActual->dayOfWeek == $horario->dia) {
                AsistenciaClaseProgramada::create([
                    'grupo_id' => $grupo->id,
                    'ciclo_id' => $ciclo->id,
                    'fecha_clase' => $fechaActual->format('Y-m-d'),
                    'hora_inicio' => $horario->hora,
                    'hora_fin' => $horario->hora->addHours($horario->duracion_horas),
                    'duracion_horas' => $horario->duracion_horas,
                    'estado' => 'programada',
                    'creado_por_id' => auth()->id(),
                ]);
            }
        }
        $fechaActual->addDay();
    }
}
```

### 5.2 Registro de Asistencia

**Proceso**:

1. Profesor selecciona **solo el grupo** (NO necesita seleccionar ciclo)
2. Sistema busca automáticamente todos los ciclos activos/vigentes que contienen ese grupo
3. Sistema muestra lista de estudiantes matriculados en esos ciclos activos
4. Sistema muestra clases programadas para ese grupo en los ciclos activos
5. Profesor registra asistencia por clase o masivamente

**Validaciones**:

-   El estudiante debe estar matriculado en un ciclo activo que contiene el grupo
-   La clase debe estar programada para ese grupo en un ciclo activo
-   La fecha de la clase debe estar dentro del rango del grupo en el ciclo
-   No puede haber duplicados (estudiante + clase)
-   Solo se muestran ciclos con `status = 1` y fechas vigentes (fecha_inicio <= hoy <= fecha_fin)

### 5.3 Obtención de Estudiantes para Lista de Asistencia

**Proceso** (mejorado - solo requiere grupo):

```php
function obtenerEstudiantesParaAsistencia($grupoId) {
    // 1. Obtener el grupo
    $grupo = Grupo::findOrFail($grupoId);

    // 2. Obtener todos los ciclos activos/vigentes que contienen este grupo
    $ciclosActivos = Ciclo::whereHas('grupos', function($query) use ($grupoId) {
            $query->where('grupos.id', $grupoId);
        })
        ->where('status', 1) // Ciclo activo
        ->where(function($query) {
            $query->where('fecha_inicio', '<=', now())
                  ->where(function($q) {
                      $q->whereNull('fecha_fin')
                        ->orWhere('fecha_fin', '>=', now());
                  });
        })
        ->get();

    if ($ciclosActivos->isEmpty()) {
        return collect([]);
    }

    // 3. Obtener IDs de ciclos activos
    $ciclosIds = $ciclosActivos->pluck('id');

    // 4. Obtener matrículas activas de esos ciclos
    $matriculas = Matricula::whereIn('ciclo_id', $ciclosIds)
        ->where('status', 1) // Matrícula activa
        ->with(['estudiante', 'ciclo'])
        ->get();

    // 5. Extraer estudiantes únicos con información del ciclo
    $estudiantes = $matriculas->map(function($matricula) {
        return [
            'estudiante' => $matricula->estudiante,
            'ciclo' => $matricula->ciclo,
            'matricula_id' => $matricula->id
        ];
    })->unique(function($item) {
        return $item['estudiante']->id;
    });

    return $estudiantes;
}
```

### 5.4 Cálculo de Porcentaje de Asistencia

**Fórmula básica**:

```
Porcentaje = (Horas Asistidas / Horas Totales Programadas) × 100
```

**Consideraciones**:

-   **Horas Asistidas**: Suma de `duracion_horas` de clases donde `estado = 'presente'` o `estado = 'justificado'` (si aplica)
-   **Horas Totales**: Suma de `duracion_horas` de todas las clases programadas (`AsistenciaClaseProgramada`) con `estado = 'dictada'` o `estado = 'programada'`
-   Las ausencias justificadas pueden o no contar según configuración (`AsistenciaConfiguracion`)

**Cálculo por módulo**:

```php
function calcularPorcentajeAsistenciaModulo($estudianteId, $grupoId, $cicloId) {
    // Obtener clases programadas del grupo en el ciclo
    $clasesProgramadas = AsistenciaClaseProgramada::where('grupo_id', $grupoId)
        ->where('ciclo_id', $cicloId)
        ->whereIn('estado', ['programada', 'dictada'])
        ->get();

    $horasTotales = $clasesProgramadas->sum('duracion_horas');

    // Obtener asistencias del estudiante
    $asistencias = Asistencia::where('estudiante_id', $estudianteId)
        ->where('grupo_id', $grupoId)
        ->where('ciclo_id', $cicloId)
        ->whereIn('estado', ['presente', 'justificado'])
        ->with('claseProgramada')
        ->get();

    $horasAsistidas = $asistencias->sum(function($asistencia) {
        return $asistencia->claseProgramada->duracion_horas;
    });

    if ($horasTotales == 0) {
        return 0;
    }

    return ($horasAsistidas / $horasTotales) * 100;
}
```

### 5.5 Verificación de Pérdida por Fallas

**Proceso**:

1. Obtener configuración de asistencia (`AsistenciaConfiguracion`) vigente para el curso/módulo
2. Calcular porcentaje de asistencia del estudiante
3. Comparar con porcentaje mínimo configurado
4. Si no cumple y `perder_por_fallas = true`, marcar como perdido

---

## 6. Endpoints y Funcionalidades

### 6.1 Gestión de Clases Programadas

#### `POST /api/asistencia-clases-programadas`

Crear una clase programada manualmente.

**Request**:

```json
{
    "grupo_id": 1,
    "ciclo_id": 1,
    "fecha_clase": "2025-01-15",
    "hora_inicio": "08:00:00",
    "hora_fin": "10:00:00",
    "duracion_horas": 2.0,
    "observaciones": "Cambio de aula"
}
```

#### `POST /api/asistencia-clases-programadas/generar-automaticas`

Generar clases programadas automáticamente para un grupo en un ciclo.

**Request**:

```json
{
    "grupo_id": 1,
    "ciclo_id": 1
}
```

#### `GET /api/asistencia-clases-programadas`

Listar clases programadas con filtros.

**Query Parameters**:

-   `grupo_id`: Filtrar por grupo
-   `ciclo_id`: Filtrar por ciclo
-   `fecha_inicio`: Fecha inicio rango
-   `fecha_fin`: Fecha fin rango
-   `estado`: Filtrar por estado

#### `GET /api/asistencia-clases-programadas/{id}`

Obtener detalles de una clase programada.

#### `PUT /api/asistencia-clases-programadas/{id}`

Actualizar una clase programada (ej: cambiar estado a cancelada).

#### `DELETE /api/asistencia-clases-programadas/{id}`

Eliminar una clase programada (soft delete).

### 6.2 Registro de Asistencia

#### `GET /api/asistencias/lista-asistencia`

Obtener lista de asistencia para un grupo (muestra estudiantes de ciclos activos).

**Query Parameters**:

-   `grupo_id`: ID del grupo (requerido)
-   `fecha_clase`: Fecha específica (opcional, si no se envía muestra todas las clases)
-   `ciclo_id`: ID del ciclo (opcional, si se envía filtra por ese ciclo específico)

**Response**:

```json
{
    "data": {
        "grupo": {
            "id": 1,
            "nombre": "Matemáticas 101 - Mañana"
        },
        "ciclo": {
            "id": 1,
            "nombre": "Ciclo 2025-1"
        },
        "clases_programadas": [
            {
                "id": 1,
                "fecha_clase": "2025-01-15",
                "hora_inicio": "08:00:00",
                "hora_fin": "10:00:00",
                "duracion_horas": 2.0,
                "estado": "programada"
            }
        ],
        "estudiantes": [
            {
                "id": 1,
                "name": "Juan Pérez",
                "documento": "123456789",
                "asistencias": [
                    {
                        "clase_programada_id": 1,
                        "estado": "presente",
                        "hora_registro": "08:05:00"
                    }
                ]
            }
        ]
    }
}
```

#### `POST /api/asistencias/registrar`

Registrar asistencia de un estudiante a una clase.

**Request**:

```json
{
    "estudiante_id": 1,
    "clase_programada_id": 1,
    "estado": "presente",
    "observaciones": "Llegó puntual"
}
```

#### `POST /api/asistencias/registrar-masivo`

Registrar asistencia masiva para múltiples estudiantes en una clase.

**Request**:

```json
{
    "clase_programada_id": 1,
    "asistencias": [
        {
            "estudiante_id": 1,
            "estado": "presente"
        },
        {
            "estudiante_id": 2,
            "estado": "ausente"
        },
        {
            "estudiante_id": 3,
            "estado": "justificado",
            "observaciones": "Excusa médica"
        }
    ]
}
```

#### `PUT /api/asistencias/{id}`

Actualizar un registro de asistencia.

#### `DELETE /api/asistencias/{id}`

Eliminar un registro de asistencia (soft delete).

### 6.3 Configuración de Asistencia

#### `GET /api/asistencia-configuraciones`

Listar configuraciones de asistencia.

#### `POST /api/asistencia-configuraciones`

Crear configuración de asistencia.

**Request**:

```json
{
    "curso_id": 1,
    "modulo_id": null,
    "porcentaje_minimo": 80.0,
    "aplicar_justificaciones": true,
    "perder_por_fallas": true,
    "fecha_inicio_vigencia": "2025-01-01",
    "fecha_fin_vigencia": null
}
```

#### `PUT /api/asistencia-configuraciones/{id}`

Actualizar configuración.

#### `DELETE /api/asistencia-configuraciones/{id}`

Eliminar configuración.

### 6.4 Reportes de Asistencia

#### `GET /api/asistencias/reporte/estudiante/{estudianteId}`

Reporte completo de asistencia de un estudiante.

**Query Parameters**:

-   `ciclo_id`: Filtrar por ciclo (opcional)
-   `curso_id`: Filtrar por curso (opcional)

**Response**:

```json
{
    "data": {
        "estudiante": {
            "id": 1,
            "name": "Juan Pérez",
            "documento": "123456789"
        },
        "resumen_por_curso": [
            {
                "curso": {
                    "id": 1,
                    "nombre": "Ingeniería de Software"
                },
                "modulos": [
                    {
                        "modulo": {
                            "id": 1,
                            "nombre": "Matemáticas 101"
                        },
                        "grupo": {
                            "id": 1,
                            "nombre": "Matemáticas 101 - Mañana"
                        },
                        "ciclo": {
                            "id": 1,
                            "nombre": "Ciclo 2025-1"
                        },
                        "estadisticas": {
                            "horas_totales": 40,
                            "horas_asistidas": 35,
                            "horas_ausentes": 5,
                            "horas_justificadas": 2,
                            "porcentaje_asistencia": 87.5,
                            "cumple_minimo": true,
                            "configuracion": {
                                "porcentaje_minimo": 80.0,
                                "perder_por_fallas": true
                            }
                        },
                        "detalle_clases": [
                            {
                                "fecha_clase": "2025-01-15",
                                "hora_inicio": "08:00:00",
                                "duracion_horas": 2.0,
                                "estado_asistencia": "presente",
                                "hora_registro": "08:05:00"
                            }
                        ]
                    }
                ],
                "resumen_curso": {
                    "total_horas": 120,
                    "total_asistidas": 105,
                    "porcentaje_general": 87.5
                }
            }
        ],
        "resumen_general": {
            "total_cursos": 2,
            "total_modulos": 5,
            "porcentaje_promedio": 85.2
        }
    }
}
```

#### `GET /api/asistencias/reporte/grupo/{grupoId}/ciclo/{cicloId}`

Reporte de asistencia de un grupo en un ciclo.

**Response**:

```json
{
    "data": {
        "grupo": {
            "id": 1,
            "nombre": "Matemáticas 101 - Mañana"
        },
        "ciclo": {
            "id": 1,
            "nombre": "Ciclo 2025-1"
        },
        "estudiantes": [
            {
                "estudiante": {
                    "id": 1,
                    "name": "Juan Pérez"
                },
                "estadisticas": {
                    "horas_totales": 40,
                    "horas_asistidas": 35,
                    "porcentaje_asistencia": 87.5,
                    "cumple_minimo": true
                }
            }
        ],
        "resumen_grupo": {
            "total_estudiantes": 25,
            "promedio_asistencia": 85.2,
            "estudiantes_en_riesgo": 3
        }
    }
}
```

---

## 7. Cálculos y Reportes

### 7.1 Cálculo de Porcentaje de Asistencia

**Fórmula**:

```
Porcentaje = (Horas Asistidas / Horas Totales) × 100
```

**Horas Asistidas**:

-   Suma de `duracion_horas` de clases donde:
    -   `estado = 'presente'` → Siempre cuenta
    -   `estado = 'justificado'` → Cuenta si `aplicar_justificaciones = true` en configuración

**Horas Totales**:

-   Suma de `duracion_horas` de clases programadas (`AsistenciaClaseProgramada`) donde:
    -   `estado IN ('programada', 'dictada')`
    -   La fecha de la clase está dentro del rango del grupo en el ciclo

### 7.2 Verificación de Cumplimiento de Mínimo

**Proceso**:

1. Obtener configuración vigente para el curso/módulo
2. Calcular porcentaje de asistencia del estudiante
3. Comparar:
    - Si `porcentaje >= porcentaje_minimo` → Cumple
    - Si `porcentaje < porcentaje_minimo` y `perder_por_fallas = true` → No cumple (pierde)

### 7.3 Reporte por Estudiante

**Estructura**:

-   Agrupado por curso
-   Dentro de cada curso, agrupado por módulo
-   Para cada módulo:
    -   Estadísticas de asistencia
    -   Detalle de clases
    -   Estado de cumplimiento

### 7.4 Reporte por Grupo

**Estructura**:

-   Lista de estudiantes del grupo
-   Estadísticas individuales
-   Resumen grupal:
    -   Promedio de asistencia
    -   Estudiantes en riesgo (por debajo del mínimo)
    -   Total de horas programadas vs asistidas

---

## 8. Consideraciones Técnicas

### 8.1 Optimización de Consultas

-   **Campos redundantes**: Se guardan `grupo_id`, `ciclo_id`, `modulo_id`, `curso_id` en la tabla `asistencias` para evitar joins en reportes
-   **Índices**: Índices estratégicos en campos de búsqueda frecuente
-   **Caché**: Considerar caché para configuraciones de asistencia (cambian poco)
-   **Filtrado de ciclos activos**: Scope reutilizable para filtrar ciclos activos/vigentes

### 8.2 Integridad de Datos

-   **Validaciones**:
    -   Un estudiante solo puede tener una asistencia por clase (índice único)
    -   La clase debe pertenecer al grupo y ciclo correctos
    -   El estudiante debe estar matriculado en el ciclo
    -   Las fechas deben estar dentro del rango del grupo en el ciclo

### 8.3 Eventos y Observadores

**Eventos sugeridos**:

-   `AsistenciaClaseProgramada::created` → Validar que la fecha está en rango del grupo en el ciclo
-   `Asistencia::created` → Validar estudiante matriculado en ciclo activo
-   `Asistencia::updated` → Recalcular porcentajes si cambia estado
-   `Matricula::created` → No requiere acción (las asistencias se registran después)
-   `Matricula::updated` → Si cambia ciclo, considerar migración de asistencias
-   `Ciclo::updated` → Si cambia status o fechas, recalcular clases programadas si es necesario

### 8.4 Permisos y Roles

**Permisos sugeridos**:

-   `aca_asistencias` → Ver asistencias
-   `aca_asistenciaCrear` → Registrar asistencia
-   `aca_asistenciaEditar` → Editar asistencia
-   `aca_asistenciaInactivar` → Eliminar asistencia
-   `aca_asistenciaReportes` → Ver reportes
-   `aca_claseProgramar` → Programar clases
-   `aca_configuracionAsistencia` → Configurar topes mínimos

### 8.5 Migraciones y Seeders

**Migraciones**:

1. `create_asistencia_clases_programadas_table` (NUEVA)
2. `update_asistencias_table` (ACTUALIZAR tabla existente)
3. `create_asistencia_configuraciones_table` (NUEVA)

**Seeders**:

-   `AsistenciaSeeder`: Ya existe, debe ser actualizado
-   `AsistenciaConfiguracionSeeder`: Configuración por defecto (80% mínimo)
-   `AsistenciaClaseProgramadaSeeder`: Opcional, para datos de prueba

### 8.6 Factories

-   `AsistenciaClaseProgramadaFactory`: Generar clases programadas de prueba
-   `AsistenciaFactory`: Ya existe, debe ser actualizado
-   `AsistenciaConfiguracionFactory`: Generar configuraciones de prueba

---

## 9. Flujo de Trabajo Completo

### 9.1 Configuración Inicial

1. **Crear ciclo** con grupos asignados
2. **Configurar topes mínimos** de asistencia por curso/módulo
3. **Generar clases programadas** automáticamente o manualmente

### 9.2 Registro Diario de Asistencia

1. Profesor accede a lista de asistencia (`GET /api/asistencias/lista-asistencia`)
2. Selecciona grupo y ciclo activo
3. Sistema muestra:
    - Estudiantes matriculados en el ciclo
    - Clases programadas para ese grupo-ciclo
    - Asistencias ya registradas
4. Profesor registra asistencia (individual o masiva)
5. Sistema valida y guarda

### 9.3 Consulta y Reportes

1. **Estudiante**: Consulta su reporte de asistencia
2. **Profesor**: Consulta reporte del grupo
3. **Coordinador**: Consulta reportes generales
4. Sistema calcula porcentajes y verifica cumplimiento

### 9.4 Alertas y Notificaciones

**Sugerencias futuras**:

-   Alertar cuando un estudiante está cerca del mínimo
-   Notificar al estudiante cuando no cumple mínimo
-   Reportes automáticos semanales

---

## 10. Preguntas y Decisiones Pendientes

### 10.1 Preguntas Abiertas

1. **¿Las clases canceladas cuentan para el cálculo?**

    - **Decisión propuesta**: No, solo clases con estado `programada` o `dictada`

2. **¿Qué pasa si se reprograma una clase?**

    - **Decisión propuesta**: Se crea nueva clase con estado `reprogramada`, la original se marca como `cancelada`

3. **¿Las tardanzas afectan el porcentaje?**

    - **Decisión propuesta**: Sí, pero se puede configurar si las tardanzas cuentan como presente o ausente

4. **¿Se pueden registrar asistencias retroactivas?**

    - **Decisión propuesta**: Sí, con validación de permisos especiales

5. **¿Cómo se manejan estudiantes que se retiran a mitad de ciclo?**
    - **Decisión propuesta**: Se marca la matrícula como inactiva, las asistencias anteriores se mantienen

### 10.2 Mejoras Futuras

-   **Dashboard de asistencia**: Vista gráfica de asistencia por grupo
-   **Exportación a Excel/PDF**: Reportes exportables
-   **Integración con notificaciones**: Alertas automáticas
-   **API de consulta para estudiantes**: Endpoint público para que estudiantes consulten su asistencia
-   **Sistema de justificaciones**: Flujo de aprobación de justificaciones

---

## 11. Resumen Ejecutivo

### 11.1 Componentes Principales

1. **Clases Programadas**: Sesiones de clase generadas automáticamente o manualmente
2. **Asistencias**: Registro de asistencia de estudiantes a clases
3. **Configuración**: Topes mínimos de asistencia por curso/módulo
4. **Reportes**: Cálculo de porcentajes y verificación de cumplimiento

### 11.2 Ventajas del Diseño

-   ✅ **Flexible**: Permite configuración por curso o módulo
-   ✅ **Escalable**: Índices optimizados para grandes volúmenes
-   ✅ **Auditable**: Campos de auditoría completos
-   ✅ **Integrado**: Usa la estructura existente (matrículas, ciclos, grupos)
-   ✅ **Completo**: Cubre todos los requerimientos solicitados

### 11.3 Próximos Pasos

1. Revisar y aprobar esta estrategia
2. Crear migraciones de base de datos
3. Crear modelos y relaciones
4. Implementar controladores y endpoints
5. Crear recursos y requests
6. Implementar lógica de cálculo
7. Crear reportes
8. Testing y validación

---

---

## 12. Cambios Aplicados y Decisiones de Diseño

### 12.1 Nomenclatura Unificada

**Decisión**: Todos los modelos y tablas del módulo de asistencia inician con "Asistencia" para facilitar la gestión y organización.

**Implementación**:

-   ✅ `AsistenciaClaseProgramada` → tabla: `asistencia_clases_programadas`
-   ✅ `AsistenciaConfiguracion` → tabla: `asistencia_configuraciones`
-   ✅ `Asistencia` → tabla: `asistencias` (ya existente)

### 12.2 Simplificación del Registro de Asistencia

**Decisión**: El registro de asistencia solo requiere seleccionar el grupo, no el ciclo. El sistema busca automáticamente los ciclos activos/vigentes.

**Beneficios**:

-   ✅ Interfaz más simple para el profesor
-   ✅ Reduce errores de selección incorrecta de ciclo
-   ✅ Muestra automáticamente todos los estudiantes relevantes

**Implementación**:

-   El endpoint `GET /api/asistencias/lista-asistencia` solo requiere `grupo_id`
-   El sistema busca ciclos activos que contienen el grupo:
    -   `status = 1` (activo)
    -   `fecha_inicio <= hoy`
    -   `fecha_fin >= hoy` o `fecha_fin IS NULL`
-   Muestra estudiantes de todos esos ciclos activos

### 12.3 Archivos Existentes a Actualizar

**Archivos base ya creados** (requieren desarrollo completo):

-   ✅ `app/Models/Academico/Asistencia.php` - Modelo básico
-   ✅ `database/migrations/2025_11_29_201012_create_asistencias_table.php` - Migración básica
-   ✅ `app/Http/Controllers/Api/Academico/AsistenciaController.php` - Controlador básico
-   ✅ `app/Http/Requests/Api/StoreAsistenciaRequest.php` - Request básico
-   ✅ `app/Http/Requests/Api/UpdateAsistenciaRequest.php` - Request básico
-   ✅ `app/Http/Resources/Api/Academico/AsistenciaResource.php` - Resource básico
-   ✅ `database/seeders/AsistenciaSeeder.php` - Seeder básico

**Nota**: Estos archivos tienen la estructura básica pero necesitan ser desarrollados completamente según esta estrategia.

### 12.4 Scope para Ciclos Activos

**Nuevo scope agregado al modelo Ciclo**:

```php
public function scopeActivosVigentes($query) {
    return $query->where('status', 1)
        ->where('fecha_inicio', '<=', now())
        ->where(function($q) {
            $q->whereNull('fecha_fin')
              ->orWhere('fecha_fin', '>=', now());
        });
}
```

Este scope se utilizará en múltiples lugares para filtrar ciclos activos.

---

**Documento generado el**: 2025-01-XX  
**Versión**: 1.1 (Actualizada con cambios solicitados)  
**Autor**: Sistema de Asistencia - Diseño Estratégico

**Ver también**: `LISTA_VERIFICACION_SISTEMA_ASISTENCIA.md` para la lista de verificación detallada paso a paso.
