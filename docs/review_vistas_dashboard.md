# Revisión y propuestas para las vistas del dashboard

Objetivo: revisar las vistas actuales y proponer cambios de visualización (sin modificar las vistas originales). Incluye recomendaciones concretas y mockups HTML con datos simulados en `docs/mockups/`.

Vistas analizadas:
- `resources/views/dashboard/analisis.blade.php`
- `resources/views/dashboard/lixiviacion.blade.php`
- `resources/views/dashboard/detection_time.blade.php`

---

## Resumen ejecutivo

Las tres vistas presentan una interfaz cuidada y KPIs claros, pero en términos de visualización estadística y defensa académica se pueden fortalecer los siguientes puntos:

- Claridad de mensaje: algunos KPIs (ej. `Precisión del Diagnóstico`) son mostrados como un solo valor; esto es adecuado para un KPI, pero insuficiente como evidencia analítica. Complementar con series temporales o distribuciones mejora la defensa metodológica.
- Variabilidad y outliers: `Tiempo de Detección` muestra promedios pero no la dispersión; se recomienda boxplot/violin para demostrar robustez y discutir sesgos.
- Componentes espaciales: `Índice de Lixiviación` tiene fuerte componente espacial; si hay coordenadas, integrar mapas (choropleth / mapas de puntos) aporta evidencia operativa.
- Accesibilidad y paleta: asegurar paleta para daltonismo y consistencia en el uso de color para estado/criticidad.
- Interactividad mínima: tooltips con valores exactos, filtros por periodo y small multiples facilitan comparación sin saturar la vista.

---

## Propuesta por vista

### `resources/views/dashboard/analisis.blade.php`

Estado actual (observado):
- Panel con 3 KPIs principales, progres bars y una tabla de eventos diarios.
- KPI `Precisión del Diagnóstico` mostrado como porcentaje y resumen de VP/FP.

Problemas / oportunidades:
- Falta representación de la evolución temporal de la precisión (solo snapshot). Esto dificulta argumentar estabilidad o mejora en la tesis.
- La tabla es adecuada, pero la correlación entre eventos y PD (%) no se visualiza directamente.

Propuestas concretas (no intrusivas):
- Gráfico principal recomendado: **Serie temporal (línea) de `PD (%)`** con puntos y banda de confianza (IC95%) para mostrar variabilidad.
- Complementos: barras por categoría (sensor/lote) para comparación; `dot plot` con referencia de umbral (ej. 90%) y un `heatmap` sensor x periodo para detectar patrones.
- Interactividad: tooltip con VP/FP/FN por punto de tiempo; filtro por `location` ya existente.

Mockup: `docs/mockups/analisis_mock.html` (incluye línea, barras y dot plot con datos ficticios).

Justificación académica:
- Las líneas muestran tendencias y rupturas; las bandas aportan evidencia de incertidumbre. Las barras permiten comparar efectividad por entidad, necesario para discusión de resultados.

### `resources/views/dashboard/lixiviacion.blade.php`

Estado actual (observado):
- Monitoreo en tiempo real, KPIs de CE superficial/profunda y tabla de historial con ILx por registro.
- Lógica cliente para clasificación ILx y polling en tiempo real.

Problemas / oportunidades:
- No se explota la componente espacial (si existe). El uso de badges y KPIs está bien para operación, pero la defensa académica necesita mostrar distribución por lote/área.
- Los thresholds están hardcodeados en JS; documentar y exponer la base metodológica (cómo se eligieron) es necesario.

Propuestas concretas:
- Gráfico principal recomendado: **Mapa (choropleth) o scatter map** mostrando ILx por ubicación; como alternativa si no hay geo, **bar chart codificado por severidad**.
- Complementos: small multiples por periodo; stacked bars para descomponer factores que influyen en riesgo (si existen variables explicativas).
- UX: destacar controles de período (ya existen) y añadir leyenda persistente de severidad.

Mockup: `docs/mockups/lixiviacion_mock.html` (barras codificadas por severidad + placeholder de mapa).

Justificación académica:
- El riesgo es una medida espacial/ordinal; priorizar visualizaciones que muestren dónde y con qué severidad ocurre facilita la recomendación de intervenciones y la validación experimental.

### `resources/views/dashboard/detection_time.blade.php`

Estado actual (observado):
- KPIs y un listado tabular por día con tiempos promedio.
- Modal para ingreso manual y edición de registros.

Problemas / oportunidades:
- Se muestra promedio general pero no la distribución ni presencia de outliers. Promedio sin dispersión puede ocultar problemas operativos.

Propuestas concretas:
- Gráfico principal recomendado: **Boxplot** por subparcela/sensor para mostrar mediana, cuartiles y outliers; acompañar con histograma o violin para detalle de densidad.
- Complementos: línea de medias en el tiempo con bandas de incertidumbre; small multiples por subparcela para comparar rendimiento.
- Interactividad: overlay de puntos (beeswarm) sobre boxplots para mostrar observaciones reales.

Mockup: `docs/mockups/detection_time_mock.html` (boxplot + histograma + serie temporal con bandas).

Justificación académica:
- Boxplots son claros para argumentar sobre variabilidad y efectos de valores extremos; a nivel tesis permiten discutir robustez y sensibilidad del estimador promedio.

---

## Recomendaciones técnicas comunes

- Paleta: usar paletas como Viridis / Cividis o esquemas desarrollados para datos ordinales; evitar rojo/verde puro como única codificación.
- Anotar: siempre mostrar `n` (tamaño de muestra), ventana temporal y definición de la métrica en cada gráfico o en tooltip.
- Interactividad mínima: filtros, tooltips, y posibilidad de descargar datos de la vista para reproducibilidad.
- Referencias a citar en la tesis: Cleveland (1994), Tufte (2001), Stephen Few (2012), Heer & Bostock (2010) para justificar decisiones.

---

## Mockups generados

He generado HTML estáticos con datos simulados en:
- `docs/mockups/analisis_mock.html`
- `docs/mockups/lixiviacion_mock.html`
- `docs/mockups/detection_time_mock.html`

Estos mockups son autosuficientes y usan Chart.js (CDN) para ilustrar cómo quedarían las visualizaciones propuestas.

---

Si quieres que priorice la exportación de gráficos en formato imagen (PNG/SVG) o que prepare el diff de los cambios a implementar en las vistas Blade (solo propuesta, sin aplicar), indícalo y lo preparo.

Fin de la revisión.
