# Resumen de Fórmulas del Sistema AgroLixiSync

## 1. Índice de Lixiviación (ILx)
- **Fórmula:** `ILx = CE_prof / CE_sup`
- **Descripción:** Relación entre la conductividad medida a 60 cm (profunda) y a 20 cm (superficial). Si el backend envía el valor `analysis.ilx`, se usa ese directamente; de lo contrario se calcula con la fórmula anterior.

## 2. Δ Conductividad (ΔCE)
- **Fórmula:** `ΔCE = CE_sup - CE_prof`
- **Descripción:** Diferencia entre la conductividad superficial y la profunda.

## 3. ΔCE Temporal (vs. lectura anterior)
- **Fórmula:** `ΔCE_temporal = CE_sup_actual - CE_sup_anterior`
- **Descripción:** Cambio de la conductividad superficial respecto a la lectura previa.

## 4. Tiempo de Alerta de Riesgo (TAR)
- **Fórmula:** `TAR = (now - alertStartTime) / 60000`  (expresado en minutos)
- **Descripción:** Tiempo transcurrido desde que el sistema detectó una condición de alerta (ILx crítico o de advertencia) hasta que se resuelve.

## 5. Precisión del Diagnóstico del Sistema (PDS)
- **Fórmula principal:** `PDS = (VP / (VP + FP)) * 100 %`
- **Variables:**
  - `VP` – Verdaderos Positivos.
  - `FP` – Falsos Positivos.
- **Cálculos auxiliares mostrados en la vista analisis:**
  - `VP % = (VP / total_eventos) * 100`
  - `FP % = (FP / total_eventos) * 100`

## 6. Umbrales de Clasificación de ILx
| Rango de ILx | Estado | Color | Icono |
|--------------|--------|-------|-------|
| `ILx > 1.20` | Lixiviación Crítica | rojo | 🔴 |
| `ILx > 1.05` | Lixiviación | naranja | 🟠 |
| `0.90 ≤ ILx ≤ 1.05` | Equilibrio | verde | ✅ |
| `0.70 ≤ ILx < 0.90` | Retención | azul | 🔵 |
| `ILx < 0.70` | Acumulación | amarillo | 🟡 |

## 7. Umbrales de Conductividad (CE)
- **Superficial (20 cm):** `CE_sup > 0.600 dS/m` → muestra badge **ALERTA** en la tarjeta correspondiente.
- **Profundo (60 cm):** `CE_prof > 0.750 dS/m` → muestra badge **ALERTA** en la tarjeta correspondiente.

## 8. Indicadores de Tendencia (setTrend)
- Diferencia `diff = valor_actual - valor_anterior`
  - `diff > 0.005` → flecha ↑ (rojo)
  - `diff < -0.005` → flecha ↓ (verde)
  - `|diff| ≤ 0.005` → flecha → (gris)

## 9. Formato de Delta en tabla de alertas
- Si `ΔCE > 0.5` se colorea en rojo (`var(--accent-red)`), de lo contrario se muestra en color neutro `#1f2937`.

---
**Nota:** Todas las fórmulas están implementadas en los archivos `resources/views/dashboard/alertas.blade.php`, `resources/views/dashboard/realtime.blade.php` y `resources/views/dashboard/analisis.blade.php` mediante JavaScript y Laravel Blade.
