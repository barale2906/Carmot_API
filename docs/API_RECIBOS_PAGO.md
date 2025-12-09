# Documentación API - Recibos de Pago

## Descripción General

El módulo de Recibos de Pago permite registrar todos los pagos que ingresan al instituto por diferentes conceptos de las listas de precios. Incluye funcionalidades para crear, editar, anular, cerrar recibos, generar PDFs, enviar por correo y generar reportes.

## Base URL

```
/api/financiero/recibos-pago
```

## Autenticación

Todas las rutas requieren autenticación mediante Sanctum. Incluir el token en el header:

```
Authorization: Bearer {token}
```

## Endpoints

### 1. Listar Recibos de Pago

**GET** `/api/financiero/recibos-pago`

Obtiene una lista paginada de recibos de pago con filtros opcionales.

**Permisos requeridos:** `fin_recibos_pago`

**Parámetros de consulta:**

| Parámetro        | Tipo    | Descripción                                                       |
| ---------------- | ------- | ----------------------------------------------------------------- |
| `sede_id`        | integer | Filtrar por sede                                                  |
| `estudiante_id`  | integer | Filtrar por estudiante                                            |
| `cajero_id`      | integer | Filtrar por cajero                                                |
| `matricula_id`   | integer | Filtrar por matrícula                                             |
| `origen`         | integer | Filtrar por origen (0=Inventarios, 1=Académico)                   |
| `status`         | integer | Filtrar por estado (0=En proceso, 1=Creado, 2=Cerrado, 3=Anulado) |
| `fecha_inicio`   | date    | Fecha inicio del rango                                            |
| `fecha_fin`      | date    | Fecha fin del rango                                               |
| `cierre`         | integer | Filtrar por número de cierre                                      |
| `producto_id`    | integer | Filtrar por producto vendido                                      |
| `poblacion_id`   | integer | Filtrar por población (ciudad)                                    |
| `search`         | string  | Búsqueda por número de recibo o prefijo                           |
| `vigentes`       | boolean | Solo recibos vigentes (no anulados). Default: true                |
| `with`           | string  | Relaciones a incluir (separadas por coma)                         |
| `sort_by`        | string  | Campo para ordenar                                                |
| `sort_direction` | string  | Dirección de ordenamiento (asc/desc)                              |
| `per_page`       | integer | Elementos por página. Default: 15                                 |

**Ejemplo de respuesta:**

```json
{
    "data": [
        {
            "id": 1,
            "numero_recibo": "ACAD-0001",
            "consecutivo": 1,
            "prefijo": "ACAD",
            "origen": 1,
            "origen_text": "Académico",
            "fecha_recibo": "2025-12-08",
            "fecha_transaccion": "2025-12-08 10:30:00",
            "valor_total": 500000.00,
            "descuento_total": 50000.00,
            "banco": "Bancolombia",
            "status": 1,
            "status_text": "Creado",
            "cierre": null,
            "sede_id": 1,
            "estudiante_id": 5,
            "cajero_id": 2,
            "matricula_id": 10,
            "sede": {...},
            "estudiante": {...},
            "cajero": {...},
            "conceptos_pago": [...],
            "productos": [...],
            "descuentos": [...],
            "medios_pago": [...]
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 1,
        "from": 1,
        "to": 1
    }
}
```

### 2. Crear Recibo de Pago

**POST** `/api/financiero/recibos-pago`

Crea un nuevo recibo de pago. El número de recibo se genera automáticamente.

**Permisos requeridos:** `fin_reciboPagoCrear`

**Body (JSON):**

```json
{
    "sede_id": 1,
    "estudiante_id": 5,
    "cajero_id": 2,
    "matricula_id": 10,
    "origen": 1,
    "fecha_recibo": "2025-12-08",
    "fecha_transaccion": "2025-12-08 10:30:00",
    "valor_total": 500000.0,
    "descuento_total": 50000.0,
    "banco": "Bancolombia",
    "conceptos_pago": [
        {
            "concepto_pago_id": 1,
            "valor": 300000.0,
            "tipo": 1,
            "producto": "Matrícula",
            "cantidad": 1,
            "unitario": 300000.0,
            "subtotal": 300000.0,
            "id_relacional": 10,
            "observaciones": null
        }
    ],
    "listas_precio": [1, 2],
    "productos": [
        {
            "producto_id": 1,
            "cantidad": 2,
            "precio_unitario": 100000.0,
            "subtotal": 200000.0
        }
    ],
    "descuentos": [
        {
            "descuento_id": 1,
            "valor_descuento": 50000.0,
            "valor_original": 500000.0,
            "valor_final": 450000.0
        }
    ],
    "medios_pago": [
        {
            "medio_pago": "efectivo",
            "valor": 300000.0,
            "referencia": null,
            "banco": null
        },
        {
            "medio_pago": "tarjeta",
            "valor": 200000.0,
            "referencia": "1234567890",
            "banco": "Visa"
        }
    ]
}
```

**Validaciones:**

-   `sede_id`: Requerido, debe existir
-   `cajero_id`: Requerido, debe existir
-   `origen`: Requerido, debe ser 0 o 1
-   `fecha_recibo`: Requerido, formato fecha
-   `fecha_transaccion`: Requerido, formato fecha/hora
-   `valor_total`: Requerido, numérico, mínimo 0
-   `descuento_total`: Opcional, numérico, mínimo 0, no puede ser mayor que valor_total
-   `conceptos_pago`: Requerido, array con al menos un elemento
-   `medios_pago`: Requerido, array con al menos un elemento
-   La suma de `medios_pago[].valor` debe ser igual a `valor_total`
-   Los subtotales deben ser iguales a cantidad × unitario/precio_unitario

**Ejemplo de respuesta:**

```json
{
    "message": "Recibo de pago creado exitosamente.",
    "data": {
        "id": 1,
        "numero_recibo": "ACAD-0001",
        ...
    }
}
```

### 3. Mostrar Recibo de Pago

**GET** `/api/financiero/recibos-pago/{id}`

Obtiene los detalles de un recibo de pago específico.

**Permisos requeridos:** `fin_recibos_pago`

**Parámetros de consulta:**

| Parámetro | Tipo   | Descripción                               |
| --------- | ------ | ----------------------------------------- |
| `with`    | string | Relaciones a incluir (separadas por coma) |

**Ejemplo de respuesta:**

```json
{
    "data": {
        "id": 1,
        "numero_recibo": "ACAD-0001",
        ...
    }
}
```

### 4. Actualizar Recibo de Pago

**PUT** `/api/financiero/recibos-pago/{id}`

Actualiza un recibo de pago. Solo se pueden editar recibos en proceso (status = 0).

**Permisos requeridos:** `fin_reciboPagoEditar`

**Body:** Similar al de creación, pero todos los campos son opcionales (excepto los requeridos para relaciones).

**Validaciones:**

-   El recibo debe estar en proceso (status = 0)
-   Las mismas validaciones que en creación

**Ejemplo de respuesta:**

```json
{
    "message": "Recibo de pago actualizado exitosamente.",
    "data": {...}
}
```

### 5. Eliminar Recibo de Pago

**DELETE** `/api/financiero/recibos-pago/{id}`

Elimina (soft delete) un recibo de pago. Solo se pueden eliminar recibos en proceso.

**Permisos requeridos:** `fin_reciboPagoEditar`

**Ejemplo de respuesta:**

```json
{
    "message": "Recibo de pago eliminado exitosamente."
}
```

### 6. Anular Recibo de Pago

**POST** `/api/financiero/recibos-pago/{id}/anular`

Anula un recibo de pago cambiando su estado a ANULADO.

**Permisos requeridos:** `fin_reciboPagoAnular`

**Validaciones:**

-   El recibo no debe estar cerrado
-   El recibo no debe estar ya anulado

**Ejemplo de respuesta:**

```json
{
    "message": "Recibo de pago anulado exitosamente.",
    "data": {...}
}
```

### 7. Cerrar Recibo de Pago

**POST** `/api/financiero/recibos-pago/{id}/cerrar`

Cierra un recibo de pago cambiando su estado a CERRADO.

**Permisos requeridos:** `fin_reciboPagoCerrar`

**Body (opcional):**

```json
{
    "cierre": 5
}
```

**Validaciones:**

-   El recibo no debe estar anulado
-   El recibo no debe estar ya cerrado

**Ejemplo de respuesta:**

```json
{
    "message": "Recibo de pago cerrado exitosamente.",
    "data": {...}
}
```

### 8. Generar PDF

**GET** `/api/financiero/recibos-pago/{id}/pdf`

Genera y descarga el PDF del recibo de pago.

**Permisos requeridos:** `fin_reciboPagoPDF`

**Respuesta:** Archivo PDF descargable

### 9. Enviar Recibo por Correo

**POST** `/api/financiero/recibos-pago/{id}/enviar-email`

Envía el recibo de pago por correo electrónico al estudiante asociado.

**Permisos requeridos:** `fin_reciboPagoPDF`

**Validaciones:**

-   El recibo debe tener un estudiante asociado
-   El estudiante debe tener un correo electrónico configurado

**Ejemplo de respuesta:**

```json
{
    "message": "Recibo de pago enviado por correo exitosamente.",
    "recibo_id": 1,
    "numero_recibo": "ACAD-0001",
    "estudiante_email": "estudiante@example.com"
}
```

### 10. Generar Reportes

**GET** `/api/financiero/recibos-pago/reportes`

Genera reportes de recibos de pago con diferentes agrupaciones.

**Permisos requeridos:** `fin_reciboPagoReportes`

**Parámetros de consulta:**

| Parámetro       | Tipo    | Descripción                                                                                             |
| --------------- | ------- | ------------------------------------------------------------------------------------------------------- |
| `tipo_reporte`  | string  | Tipo de reporte: `resumen`, `por_sede`, `por_producto`, `por_cajero`, `por_descuentos`, `por_poblacion` |
| `sede_id`       | integer | Filtrar por sede                                                                                        |
| `estudiante_id` | integer | Filtrar por estudiante                                                                                  |
| `cajero_id`     | integer | Filtrar por cajero                                                                                      |
| `origen`        | integer | Filtrar por origen                                                                                      |
| `status`        | integer | Filtrar por estado                                                                                      |
| `fecha_inicio`  | date    | Fecha inicio del rango                                                                                  |
| `fecha_fin`     | date    | Fecha fin del rango                                                                                     |
| `producto_id`   | integer | Filtrar por producto                                                                                    |
| `poblacion_id`  | integer | Filtrar por población                                                                                   |

**Ejemplo de respuesta (tipo_reporte=resumen):**

```json
{
    "message": "Reporte generado exitosamente.",
    "tipo_reporte": "resumen",
    "filtros_aplicados": {...},
    "data": {
        "total_recibos": 150,
        "total_ingresos": 75000000.00,
        "total_descuentos": 5000000.00,
        "ingresos_netos": 70000000.00,
        "por_origen": [
            {
                "origen": 1,
                "total": 100,
                "ingresos": 50000000.00
            }
        ],
        "por_status": [...]
    }
}
```

## Códigos de Estado HTTP

-   `200` - Éxito
-   `201` - Creado exitosamente
-   `422` - Error de validación
-   `500` - Error del servidor
-   `501` - Funcionalidad no implementada

## Códigos de Error

Los errores se retornan en el siguiente formato:

```json
{
    "message": "Descripción del error",
    "error": "Detalle técnico del error (solo en desarrollo)"
}
```

## Reglas de Negocio

1. **Numeración Consecutiva:**

    - Cada sede tiene su propio consecutivo por origen
    - El consecutivo se incrementa automáticamente
    - No se pueden reutilizar números de recibo anulados

2. **Estados del Recibo:**

    - **En Proceso (0):** Recibo en creación, puede editarse
    - **Creado (1):** Recibo finalizado, no puede editarse
    - **Cerrado (2):** Recibo cerrado en cierre de caja
    - **Anulado (3):** Recibo anulado, no puede modificarse

3. **Validaciones:**

    - El valor_total debe ser mayor o igual a descuento_total
    - La suma de medios de pago debe ser igual al valor_total
    - Un recibo en proceso puede editarse
    - Un recibo creado solo puede anularse o cerrarse
    - Un recibo cerrado no puede modificarse
    - Un recibo anulado no puede modificarse

4. **Medios de Pago:**
    - Un recibo puede tener múltiples medios de pago
    - La suma de todos los medios debe ser igual al valor_total
    - Medios disponibles: efectivo, tarjeta, transferencia, cheque, otros

## Ejemplos de Uso

### Crear un recibo académico

```bash
curl -X POST https://api.example.com/api/financiero/recibos-pago \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "sede_id": 1,
    "estudiante_id": 5,
    "cajero_id": 2,
    "origen": 1,
    "fecha_recibo": "2025-12-08",
    "fecha_transaccion": "2025-12-08 10:30:00",
    "valor_total": 500000.00,
    "conceptos_pago": [...],
    "medios_pago": [...]
  }'
```

### Generar PDF

```bash
curl -X GET https://api.example.com/api/financiero/recibos-pago/1/pdf \
  -H "Authorization: Bearer {token}" \
  --output recibo.pdf
```

### Generar reporte por sede

```bash
curl -X GET "https://api.example.com/api/financiero/recibos-pago/reportes?tipo_reporte=por_sede&fecha_inicio=2025-01-01&fecha_fin=2025-12-31" \
  -H "Authorization: Bearer {token}"
```
