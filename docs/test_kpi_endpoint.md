# Prueba del Endpoint de Agrupación de KPIs

## Endpoint probado:
```
GET dashboard/kpis/models/1/group-by/sede_id
```

## Configuración del modelo Grupo:
- **Clase**: `App\Models\Academico\Grupo`
- **Relaciones disponibles**:
  - `sede()` → `App\Models\Configuracion\Sede`
  - `modulo()` → `App\Models\Academico\Modulo`
  - `profesor()` → `App\Models\User`

## Campos de prueba:
- `sede_id` → debería resolver a `sede.nombre`
- `modulo_id` → debería resolver a `modulo.nombre`
- `profesor_id` → debería resolver a `user.name`

## Respuesta esperada:

### Para `sede_id`:
```json
{
  "field": "sede_id",
  "model": {
    "id": 1,
    "display_name": "Grupos"
  },
  "options": [
    { "value": 1, "label": "1 - Sede Centro", "count": 15 },
    { "value": 2, "label": "2 - Sede Norte", "count": 8 },
    { "value": 3, "label": "3 - Sede Sur", "count": 12 }
  ],
  "total": 3
}
```

### Para `modulo_id`:
```json
{
  "field": "modulo_id",
  "model": {
    "id": 1,
    "display_name": "Grupos"
  },
  "options": [
    { "value": 1, "label": "1 - Programación Web", "count": 10 },
    { "value": 2, "label": "2 - Base de Datos", "count": 7 },
    { "value": 3, "label": "3 - Desarrollo Móvil", "count": 5 }
  ],
  "total": 3
}
```

### Para `profesor_id`:
```json
{
  "field": "profesor_id",
  "model": {
    "id": 1,
    "display_name": "Grupos"
  },
  "options": [
    { "value": 1, "label": "1 - Juan Pérez", "count": 8 },
    { "value": 2, "label": "2 - María García", "count": 6 },
    { "value": 3, "label": "3 - Carlos López", "count": 4 }
  ],
  "total": 3
}
```

## Flujo completo de uso:

1. **Frontend** llama a `GET kpis/models/1/group-by/sede_id`
2. **Usuario** ve opciones: "1 - Sede Centro", "2 - Sede Norte", etc.
3. **Usuario** selecciona "1 - Sede Centro" (value: 1)
4. **Frontend** crea la card con `group_by: "sede_id"` y `filters: {sede_id: 1}`
5. **Frontend** llama a `GET dashboard-cards/{card}/compute` para obtener el gráfico

## Ventajas del sistema:
- ✅ **Automático**: Detecta relaciones sin configuración manual
- ✅ **Inteligente**: Busca campos como 'nombre', 'name', 'title'
- ✅ **Robusto**: Si falla la relación, muestra solo el ID
- ✅ **Flexible**: Funciona con cualquier modelo con relaciones bien definidas
