# 📊 Estructura de Base de Datos - AgroLixiSync

**Fecha de generación:** 2026-06-05  
**Proyecto:** AgroLixiSync - Sistema de Monitoreo de Lixiviación en Suelos

---

## 📋 Índice de Tablas

1. [users](#usuarios)
2. [lotes](#lotes)
3. [locations](#ubicaciones)
4. [sensor_types](#tipos-de-sensores)
5. [sensors](#sensores)
6. [readings](#lecturas)
7. [analysis](#análisis)
8. [alerts](#alertas)
9. [observaciones](#observaciones)
10. [settings](#configuraciones)
11. [thesis_metrics](#métricas-de-tesis)
12. [system_tests](#pruebas-del-sistema)
13. [sensor_groups](#grupos-de-sensores)
14. [data_exports](#exportaciones-de-datos)
15. [pf_records](#registros-pf)
16. [detection_time_records](#registros-de-tiempo-de-detección)
17. [sessions](#sesiones)
18. [password_reset_tokens](#tokens-de-recuperación)

---

## 🔐 Tabla: users

**Descripción:** Almacena información de usuarios del sistema.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| name | VARCHAR | ✗ | Nombre del usuario |
| email | VARCHAR | ✗ | Email único del usuario |
| email_verified_at | TIMESTAMP | ✓ | Fecha de verificación de email |
| password | VARCHAR | ✗ | Contraseña encriptada |
| role | VARCHAR | ✓ | Rol del usuario (agregado después) |
| remember_token | VARCHAR | ✓ | Token para "recuérdame" |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Muchas sesiones (`sessions.user_id`)

**Índices:**
- `email` (UNIQUE)

---

## 🌾 Tabla: lotes

**Descripción:** Parcelas/lotes de cultivo bajo monitoreo.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| name | VARCHAR | ✗ | Nombre del lote |
| description | TEXT | ✓ | Descripción del lote |
| user_id | BIGINT | ✓ | ID del usuario propietario (FK) |
| crop_type | VARCHAR | ✓ | Tipo de cultivo (agregado después) |
| reference_ce | DECIMAL(8,3) | ✓ | CE de referencia (agregado después) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Muchas ubicaciones (`locations.lote_id`)
- Muchos análisis (`analysis.lote_id`)
- Muchas alertas (`alerts.lote_id`)
- Muchos registros de tiempo (`detection_time_records.lote_id`)

**Índices:**
- `user_id`

---

## 📍 Tabla: locations

**Descripción:** Ubicaciones específicas dentro de cada lote.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| lote_id | BIGINT | ✗ | ID del lote (FK) |
| name | VARCHAR | ✗ | Nombre de la ubicación (Zona A, Sector Central) |
| description | TEXT | ✓ | Descripción de la ubicación |
| latitude | DECIMAL(10,8) | ✓ | Latitud GPS |
| longitude | DECIMAL(11,8) | ✓ | Longitud GPS |
| is_active | BOOLEAN | ✗ | ¿Está activa? (default: true) |
| experimental_group | VARCHAR | ✓ | Grupo experimental asignado |
| alert_settings | JSON | ✓ | Configuración de alertas (agregada después) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a lote (`lotes.id`)
- Muchos sensores (`sensors.location_id`)
- Muchos análisis (`analysis.location_id`)
- Muchas alertas (`alerts.location_id`)
- Muchas métricas de tesis (`thesis_metrics.location_id`)
- Muchas pruebas del sistema (`system_tests.location_id`)
- Muchos grupos de sensores (`sensor_groups` - indirectamente)
- Muchas observaciones (`observaciones.location_id`)
- Muchos registros PF (`pf_records.location_id`)
- Muchos registros de tiempo (`detection_time_records.location_id`)

**Índices:**
- `lote_id`

---

## 🔬 Tabla: sensor_types

**Descripción:** Tipos de sensores disponibles en el sistema.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| name | VARCHAR | ✗ | Nombre del tipo (Sensor de Humedad, Conductividad) |
| description | TEXT | ✓ | Descripción detallada |
| unit | VARCHAR | ✗ | Unidad de medida (%, µS/cm, °C) |
| model | VARCHAR | ✓ | Modelo del sensor (DHT22, EC-4P) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Muchos sensores (`sensors.sensor_type_id`)

**Índices:**
- `name` (UNIQUE)

---

## 📡 Tabla: sensors

**Descripción:** Sensores individuales instalados en ubicaciones.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| code | VARCHAR | ✗ | Código único (ESP32_001, SENSOR_PROF_A) |
| name | VARCHAR | ✓ | Nombre descriptivo |
| sensor_type_id | BIGINT | ✗ | ID del tipo de sensor (FK) |
| location_id | BIGINT | ✗ | ID de la ubicación (FK) |
| depth | DECIMAL(5,2) | ✗ | Profundidad en cm (0=superficial, >0=profundo) |
| is_active | BOOLEAN | ✗ | ¿Está activo? (default: true) |
| status | VARCHAR | ✗ | Estado (active, inactive, error) |
| last_reading_at | TIMESTAMP | ✓ | Última lectura registrada |
| notes | TEXT | ✓ | Notas adicionales |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a tipo de sensor (`sensor_types.id`)
- Pertenece a ubicación (`locations.id`)
- Muchas lecturas (`readings.sensor_id`)
- Muchos análisis (como superficial o profundo)
- Muchos grupos de sensores (`sensor_groups.sensor_id`)

**Índices:**
- `code` (UNIQUE)
- `location_id`
- `sensor_type_id`
- `is_active`
- `location_id, depth` (UNIQUE)

---

## 📊 Tabla: readings

**Descripción:** Lecturas individuales de sensores.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| sensor_id | BIGINT | ✗ | ID del sensor (FK) |
| conductivity | DECIMAL(8,2) | ✓ | Conductividad (µS/cm) |
| humidity | DECIMAL(5,2) | ✓ | Humedad relativa (%) |
| temperature | DECIMAL(5,2) | ✓ | Temperatura (°C) |
| soil_moisture | DECIMAL(5,2) | ✓ | Humedad del suelo (%) |
| device_timestamp | TIMESTAMP | ✓ | Timestamp del dispositivo (agregada después) |
| recorded_at | TIMESTAMP | ✗ | Cuándo se tomó la medición |
| created_at | TIMESTAMP | ✗ | Fecha de creación en DB |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a sensor (`sensors.id`)
- Muchos análisis (como lectura superficial o profunda)

**Índices:**
- `sensor_id`
- `recorded_at`
- `sensor_id, recorded_at`

---

## 🔍 Tabla: analysis

**Descripción:** Análisis comparativos entre sensores superficiales y profundos.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| lote_id | BIGINT | ✗ | ID del lote (FK) |
| location_id | BIGINT | ✗ | ID de la ubicación (FK) |
| sensor_superficial_id | BIGINT | ✗ | ID del sensor superficial (FK) |
| sensor_profundo_id | BIGINT | ✗ | ID del sensor profundo (FK) |
| reading_superficial_id | BIGINT | ✓ | ID de la lectura superficial (FK) |
| reading_profundo_id | BIGINT | ✓ | ID de la lectura profunda (FK) |
| conductivity_superficial | DECIMAL(8,2) | ✓ | Conductividad superficial medida |
| conductivity_profundo | DECIMAL(8,2) | ✓ | Conductividad profunda medida |
| delta_conductivity | DECIMAL(8,2) | ✗ | Diferencia (profundo - superficial) |
| threshold_used | DECIMAL(8,2) | ✗ | Umbral aplicado en el análisis |
| lixiviation_detected | BOOLEAN | ✗ | ¿Se detectó lixiviación? |
| risk_level | VARCHAR | ✗ | Nivel de riesgo (bajo, medio, alto) |
| risk_percentage | DECIMAL(5,2) | ✗ | Porcentaje de riesgo |
| ilx | DECIMAL(8,3) | ✓ | Índice de Lixiviación (agregada después) |
| ilx_components | JSON | ✓ | Componentes del ILX (agregada después) |
| notes | TEXT | ✓ | Observaciones del análisis |
| analyzed_at | TIMESTAMP | ✗ | Cuándo se realizó el análisis |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a lote (`lotes.id`)
- Pertenece a ubicación (`locations.id`)
- Pertenece a sensor superficial (`sensors.id`)
- Pertenece a sensor profundo (`sensors.id`)
- Pertenece a lectura superficial (`readings.id`)
- Pertenece a lectura profunda (`readings.id`)
- Muchas alertas (`alerts.analysis_id`)
- Muchas pruebas del sistema (`system_tests.analysis_id`)

**Índices:**
- `lote_id`
- `location_id`
- `analyzed_at`
- `lixiviation_detected`
- `lote_id, analyzed_at`

---

## ⚠️ Tabla: alerts

**Descripción:** Alertas generadas por el sistema.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| analysis_id | BIGINT | ✓ | ID del análisis que la generó (FK) |
| lote_id | BIGINT | ✗ | ID del lote (FK) |
| location_id | BIGINT | ✗ | ID de la ubicación (FK) |
| type | VARCHAR | ✗ | Tipo (lixiviacion, temperatura_alta, etc) |
| level | VARCHAR | ✗ | Nivel (bajo, medio, alto, crítico) |
| description | TEXT | ✗ | Descripción de la alerta |
| recommendation | TEXT | ✓ | Recomendación de acción |
| is_resolved | BOOLEAN | ✗ | ¿Está resuelta? (default: false) |
| resolved_at | TIMESTAMP | ✓ | Cuándo se resolvió |
| resolution_notes | TEXT | ✓ | Notas de resolución |
| notified | BOOLEAN | ✗ | ¿Se notificó? (default: false) |
| notified_at | TIMESTAMP | ✓ | Cuándo se notificó |
| subparcela | VARCHAR | ✓ | Subparcela asociada (agregada después) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a análisis (`analysis.id`)
- Pertenece a lote (`lotes.id`)
- Pertenece a ubicación (`locations.id`)
- Muchas observaciones (`observaciones.alert_id`)

**Índices:**
- `lote_id`
- `location_id`
- `type`
- `level`
- `is_resolved`
- `created_at`

---

## 📝 Tabla: observaciones

**Descripción:** Observaciones validadas manualmente de eventos de lixiviación.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| location_id | BIGINT | ✗ | ID de la ubicación (FK) |
| alert_id | BIGINT | ✓ | ID de la alerta asociada (FK) |
| ce_real | DECIMAL(8,4) | ✗ | Conductividad eléctrica real medida |
| diagnostico | VARCHAR | ✗ | Diagnóstico (LIXIVIACION, RETENCION, NORMAL) |
| resultado | VARCHAR | ✓ | Resultado validado (VP, FP, VN, FN) |
| group | VARCHAR | ✓ | Grupo experimental (agregada después) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a ubicación (`locations.id`)
- Pertenece a alerta (`alerts.id`)

---

## ⚙️ Tabla: settings

**Descripción:** Configuraciones del sistema.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| key | VARCHAR | ✗ | Clave de configuración (lixiviation_threshold) |
| value | TEXT | ✗ | Valor guardado |
| data_type | VARCHAR | ✗ | Tipo de dato (string, integer, decimal, boolean, json) |
| description | TEXT | ✓ | Descripción de la configuración |
| is_editable | BOOLEAN | ✗ | ¿Puede editarse? (default: true) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Índices:**
- `key` (UNIQUE)

---

## 📈 Tabla: thesis_metrics

**Descripción:** Métricas calculadas para el proyecto de tesis.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| location_id | BIGINT | ✗ | ID de la ubicación (FK) |
| **TAR - Tiempo de Alerta de Riesgo** | | |
| tar_minutes | DECIMAL(8,2) | ✓ | Promedio(Hora Alerta - Hora Evento) en minutos |
| tar_sample_count | INTEGER | ✗ | Número de eventos para TAR |
| tar_calculated_at | TIMESTAMP | ✓ | Cuándo se calculó TAR |
| **PDS - Precisión del Diagnóstico** | | |
| pds_percentage | DECIMAL(5,2) | ✓ | (Coincidencias / Total Pruebas) * 100 |
| pds_total_tests | INTEGER | ✗ | Total de pruebas realizadas |
| pds_correct_detections | INTEGER | ✗ | Detecciones correctas (coincidencias) |
| pds_false_positives | INTEGER | ✗ | Falsos positivos |
| pds_false_negatives | INTEGER | ✗ | Falsos negativos |
| pds_calculated_at | TIMESTAMP | ✓ | Cuándo se calculó PDS |
| **NCES - Nivel Conductividad Eléctrica** | | |
| nces_value | DECIMAL(8,3) | ✓ | Promedio(CE_control) - Promedio(CE_experimental) |
| nces_control_avg | DECIMAL(8,3) | ✓ | Promedio CE del grupo control |
| nces_experimental_avg | DECIMAL(8,3) | ✓ | Promedio CE del grupo experimental |
| nces_calculated_at | TIMESTAMP | ✓ | Cuándo se calculó NCES |
| pf | DECIMAL(8,3) | ✓ | Fósforo (agregada después) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a ubicación (`locations.id`)

---

## 🧪 Tabla: system_tests

**Descripción:** Registro de pruebas del sistema para validar la precisión.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| location_id | BIGINT | ✗ | ID de la ubicación (FK) |
| analysis_id | BIGINT | ✓ | ID del análisis (FK) |
| test_type | ENUM | ✗ | SYSTEM_DETECTION, MANUAL_VALIDATION, COMPARISON |
| **Detección del Sistema** | | |
| system_detected_anomaly | BOOLEAN | ✗ | ¿El sistema detectó anomalía? |
| system_detection_type | VARCHAR | ✓ | Tipo de anomalía (LIXIVIATION, etc) |
| system_detection_time | TIMESTAMP | ✓ | Momento de detección del sistema |
| system_confidence | DECIMAL(5,2) | ✗ | Confianza del diagnóstico (%) |
| **Validación Manual** | | |
| actual_anomaly_existed | BOOLEAN | ✗ | ¿Existió anomalía real? (Ground Truth) |
| actual_anomaly_type | VARCHAR | ✓ | Tipo real de anomalía |
| actual_anomaly_time | TIMESTAMP | ✓ | Momento real del evento |
| validation_method | VARCHAR | ✓ | Método de validación (VISUAL, SENSOR, CHEMICAL) |
| validator_name | VARCHAR | ✓ | Quién validó |
| **Comparación** | | |
| match_result | BOOLEAN | ✗ | ¿Coinciden sistema y realidad? |
| match_type | VARCHAR | ✓ | VP (Verdadero Positivo), FP, VN, FN |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a ubicación (`locations.id`)
- Pertenece a análisis (`analysis.id`)

---

## 👥 Tabla: sensor_groups

**Descripción:** Clasificación de sensores como CONTROL o EXPERIMENTAL.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| sensor_id | BIGINT | ✗ | ID del sensor (FK) |
| group_type | ENUM | ✗ | CONTROL, EXPERIMENTAL, REFERENCE |
| group_name | VARCHAR | ✗ | Nombre identificador del grupo |
| description | TEXT | ✓ | Descripción del grupo |
| start_date | DATE | ✗ | Fecha de inicio del experimento |
| end_date | DATE | ✓ | Fecha de fin (NULL si en curso) |
| treatment_applied | TEXT | ✓ | Descripción del tratamiento (experimental) |
| treatment_type | ENUM | ✓ | RIEGO, NUTRIENTES, pH_CORRECTION, NONE |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a sensor (`sensors.id`)

---

## 💾 Tabla: data_exports

**Descripción:** Rastreo de exportaciones automáticas de datos.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| location_id | BIGINT | ✓ | ID de ubicación (FK) |
| export_type | ENUM | ✗ | FULL_EXPORT, ANALYSIS_EXPORT, THESIS_METRICS, etc |
| period_start | DATE | ✗ | Inicio del período exportado |
| period_end | DATE | ✗ | Fin del período exportado |
| filename | VARCHAR | ✗ | Nombre del archivo CSV |
| filepath | VARCHAR | ✗ | Ruta relativa en storage/ |
| file_size_bytes | BIGINT | ✗ | Tamaño del archivo en bytes |
| record_count | INTEGER | ✗ | Número de registros exportados |
| exported_by | VARCHAR | ✓ | Usuario que ejecutó la exportación |
| export_status | VARCHAR | ✗ | pending, completed, failed |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a ubicación (`locations.id`)

---

## 🎯 Tabla: pf_records

**Descripción:** Registros de Fósforo (P) medido en pruebas.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| location_id | BIGINT | ✓ | ID de la ubicación (FK) |
| recorded_at | TIMESTAMP | ✓ | Cuándo se registró la medición |
| ce_reference | DECIMAL(8,3) | ✓ | CE de referencia |
| ce_measured | DECIMAL(8,3) | ✓ | CE medida |
| subparcela | VARCHAR | ✓ | Identificador de subparcela |
| pf_percentage | DECIMAL(6,3) | ✓ | Porcentaje de Fósforo |
| ilx_components | JSON | ✓ | Componentes del ILX (agregada después) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a ubicación (`locations.id`)

**Índices:**
- `location_id`

---

## ⏱️ Tabla: detection_time_records

**Descripción:** Registros de tiempo promedio de detección de eventos.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | BIGINT | ✗ | Identificador único (PK) |
| fecha | DATE | ✗ | Fecha del registro |
| location_id | BIGINT | ✗ | ID de la ubicación (FK) |
| lote_id | BIGINT | ✗ | ID del lote (FK) |
| tiempo_promedio_segundos | INTEGER | ✗ | Tiempo promedio de detección (segundos) |
| cantidad_eventos | INTEGER | ✗ | Cantidad de eventos en el período |
| suma_tiempos_segundos | INTEGER | ✗ | Suma total de tiempos (segundos) |
| tipo_entrada | ENUM | ✗ | manual, automatico |
| subparcela | VARCHAR | ✓ | Subparcela asociada (agregada después) |
| created_at | TIMESTAMP | ✗ | Fecha de creación |
| updated_at | TIMESTAMP | ✗ | Fecha de última actualización |

**Relaciones:**
- Pertenece a ubicación (`locations.id`)
- Pertenece a lote (`lotes.id`)

**Índices:**
- `fecha`
- `lote_id`
- `tipo_entrada`
- `fecha, location_id` (UNIQUE)

---

## 🔐 Tabla: sessions

**Descripción:** Sesiones de usuario activas.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| id | VARCHAR | ✗ | ID único de sesión (PK) |
| user_id | BIGINT | ✓ | ID del usuario (FK) |
| ip_address | VARCHAR(45) | ✓ | Dirección IP del cliente |
| user_agent | TEXT | ✓ | User agent del navegador |
| payload | LONGTEXT | ✗ | Datos de la sesión |
| last_activity | INTEGER | ✗ | Timestamp de última actividad |

**Relaciones:**
- Pertenece a usuario (`users.id`)

**Índices:**
- `user_id`
- `last_activity`

---

## 🔑 Tabla: password_reset_tokens

**Descripción:** Tokens para recuperación de contraseña.

| Campo | Tipo | Nullable | Descripción |
|-------|------|----------|-------------|
| email | VARCHAR | ✗ | Email del usuario (PK) |
| token | VARCHAR | ✗ | Token de recuperación |
| created_at | TIMESTAMP | ✓ | Fecha de creación del token |

---

## 📊 Diagram de Relaciones

```
users
  ├── sessions (1:N)
  └── lotes (1:N)
       ├── locations (1:N)
       │    ├── sensors (1:N)
       │    │    ├── readings (1:N)
       │    │    │    ├── analysis (como sensor_superficial/sensor_profundo)
       │    │    │    └── analysis (como reading_superficial/reading_profundo)
       │    │    └── sensor_groups (1:N)
       │    ├── analysis (1:N)
       │    │    ├── alerts (1:N)
       │    │    └── system_tests (1:N)
       │    ├── alerts (1:N)
       │    ├── thesis_metrics (1:N)
       │    ├── system_tests (1:N)
       │    ├── data_exports (1:N)
       │    ├── observaciones (1:N)
       │    ├── pf_records (1:N)
       │    └── detection_time_records (1:N)
       ├── analysis (1:N)
       ├── alerts (1:N)
       └── detection_time_records (1:N)

sensor_types
  └── sensors (1:N)

alerts
  └── observaciones (1:N)
```

---

## 🔧 Configuraciones del Sistema (Ejemplos en settings)

- `lixiviation_threshold` - Umbral de detección de lixiviación
- `alert_notification_enabled` - ¿Habilitar notificaciones?
- `export_format` - Formato de exportación (CSV, JSON)
- `system_mode` - Modo de operación

---

## 📌 Notas Importantes

1. **Migraciones Aplicadas:** 47 migraciones ordenadas cronológicamente
2. **Relaciones Cascade:** La mayoría de relaciones utilizan `onDelete('cascade')` para mantener integridad
3. **Índices de Performance:** Aplicadas en campos frecuentemente consultados
4. **Campos JSON:** Utilizados en `alert_settings` y `ilx_components` para flexibilidad
5. **Auditoría:** Todas las tablas principales tienen `created_at` y `updated_at`
6. **Validación Temporal:** Las tablas incluyen timestamps para rastrear cuándo ocurrieron eventos

---

**Última actualización:** 2026-06-05  
**Estado:** Estructura completa documentada

