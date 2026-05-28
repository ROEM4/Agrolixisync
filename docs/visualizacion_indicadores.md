# Análisis técnico de selección de visualizaciones para indicadores del dashboard

Objetivo: justificar técnicamente la elección de tipos de gráficos para los indicadores del dashboard de la tesis, asegurando que las decisiones se basen en buenas prácticas de visualización y criterios de legibilidad, comparación e interpretación.

**Contexto**
- Vistas actuales: [resources/views/dashboard/analisis.blade.php](resources/views/dashboard/analisis.blade.php), [resources/views/dashboard/lixiviacion.blade.php](resources/views/dashboard/lixiviacion.blade.php), [resources/views/dashboard/detection_time.blade.php](resources/views/dashboard/detection_time.blade.php).
- Alcance: evaluar solo opciones de visualización (excluyendo tablas o fichas de registro).

---

## Criterios generales de selección

- Legibilidad: la visualización debe permitir entender el mensaje sin ambigüedad.
- Comparación: facilitar comparación entre categorías o periodos cuando sea necesario.
- Tendencia: resaltar patrones temporales (creciente/descendente, estacionalidad).
- Precisión: respetar la fidelidad numérica cuando la exactitud es relevante.
- Contexto y audiencia: un dashboard académico requiere trazabilidad metodológica y facilidad para defender la elección ante evaluadores.

Metodología: para cada indicador se describe el tipo de dato, se proponen al menos 3 tipos de gráfico, se justifica técnica y finalmente se recomienda una visualización principal con alternativas complementarias.

---

## 1) Porcentaje de precisión de detección

Interpretación de los datos:
- Tipo: dato numérico continuo acotado en [0,100] expresado en porcentaje.
- Dimensiones habituales: valor agregado por sensor, por lote, por periodo (día/semana/mes) o por método de detección.

Gráficos adecuados (al menos 3):

- Barras (columnas verticales o barras horizontales)
  - Justificación: facilita comparación entre categorías (sensores, lotes o métodos). Las barras muestran diferencias absolutas con buena precisión visual. Recomendado cuando hay un número moderado de categorías (<= 20).
  - Limitación: menos útil para series temporales con muchas fechas.

- Líneas (serie temporal)
  - Justificación: si el porcentaje se mide a lo largo del tiempo, las líneas resaltan tendencias, cambios y estabilidad. Las pendientes comunican la dirección del cambio.
  - Limitación: para comparar muchas series simultáneas puede volverse confuso sin técnicas de interacción o separación.

- Bullet/Bar longitudinal o Dot plot (gráfico de puntos con barras de referencia)
  - Justificación: para mostrar precisión objetivo vs actual, o comparar contra un umbral. El dot plot ofrece capacidad de lectura exacta y evita distorsiones de área.
  - Limitación: menos estandarizado en dashboards tradicionales; requiere leyenda clara.

- Gauge / Indicador tipo velocímetro (opcional)
  - Justificación: útil como KPI único y para audiencias no técnicas; comunica rápidamente si el porcentaje está dentro de rangos aceptables (verde/amarillo/rojo).
  - Limitación: mala herramienta para comparar múltiples entidades o mostrar tendencias; puede inducir a lecturas poco precisas de valores numéricos.

Recomendación principal:
- Gráfico principal: combinación de **línea** (serie temporal) cuando el objetivo es mostrar evolución, o **barras** cuando la comparación entre categorías es prioritaria.
  - Criterio: si la evaluación académica exige demostrar estabilidad y evolución de la precisión, la línea es preferente (tendencias, rupturas, variabilidad). Si la tesis analiza rendimiento por sensor/lote, las barras permiten comparaciones claras.

Visualizaciones complementarias:
- Un `dot plot` o `bar with target` que muestre el porcentaje actual frente a un umbral aceptable (ej. 90%) para evaluación rápida.
- Un `heatmap` cuando se necesite visualizar precisión por combinación (sensor x periodo) para detectar patrones espaciales/temporales.

Mejoras sobre gráficas actuales:
- Si actualmente se usa solo un gauge, proponer agregar una serie temporal (línea) para soportar la discusión sobre estabilidad y robustez del detector.
- Si se usan barras sin orden, ordenar por valor descendente y agregar un umbral visual para reforzar interpretación.

---

## 2) Nivel de riesgo de lixiviación

Interpretación de los datos:
- Tipo: puede ser categórico ordinal (bajo, medio, alto) o numérico continuo (probabilidad estimada o score). Frecuentemente se sintetiza en niveles discretos para la toma de decisiones.
- Contexto: niveles por ubicación, lote, sensor o periodo.

Gráficos adecuados:

- Mapa de colores / Choropleth o Heatmap espacial
  - Justificación: si existe componente geográfico (localizaciones de lotes), un mapa coloreado comunica distribución espacial del riesgo, que es crucial para intervenciones dirigidas.
  - Limitación: requiere datos georreferenciados; escala de color debe ser perceptualmente uniforme y adecuada para daltonismo.

- Barras apiladas (stacked bars) o stacked area (cuando hay componentes que suman)
  - Justificación: si el riesgo se compone de factores (pH, conductividad, pluviometría), las barras apiladas comunican la contribución relativa.
  - Limitación: las barras apiladas dificultan la comparación precisa de una sola categoría entre grupos.

- Gráfico de columnas con codificación de color por nivel (bajo/medio/alto)
  - Justificación: buena para comparar niveles por lote o sensor; la codificación de color ordinal (semáforo) es intuitiva para audiencias técnicas y no técnicas.
  - Limitación: depender solo del color reduce la exactitud numérica; combinar con etiquetas mejora precisión.

- Gauge / Indicator + Tabla resumen de métricas derivadas
  - Justificación: un indicador resumen (promedio ponderado o porcentaje de lotes en riesgo alto) sirve como KPI en la cabecera del panel.
  - Limitación: pierde detalle espacial o causal.

Recomendación principal:
- Gráfico principal: **mapa de calor espacial** (si hay coordenadas) o **gráfico de columnas codificado por color** (si el análisis es por categorías no espaciales).
  - Criterio: el riesgo es una señal con fuerte componente espacial/ordinal; la mejor visualización prioriza ubicación y severidad para facilitar decisiones operativas.

Visualizaciones complementarias:
- Small multiples de mapas o pequeñas barras por periodo para mostrar evolución espacial del riesgo.
- Diagrama de Sankey o radar para descomponer contribución de factores (si el riesgo es compuesto).

Mejoras sobre gráficas actuales:
- Si actualmente solo se muestra un nivel agregado, añadir un mapa o small multiples amplía la evidencia y permite defender mejor las recomendaciones prácticas.
- Asegurar paleta colorimétrica accesible (e.g., Viridis o una secuencia de colores que soporte daltonismo) y leyenda explícita.

---

## 3) Tiempo promedio de detección

Interpretación de los datos:
- Tipo: numérico continuo (segundos, minutos, horas) con posible sesgo o colas largas (outliers si algunas detecciones tardan mucho).
- Dimensiones: promedio por sensor, por lote, por método o por periodo.

Gráficos adecuados:

- Boxplot (diagrama de caja)
  - Justificación: muestra mediana, cuartiles y outliers; ideal para evaluar la distribución del tiempo de detección y si el promedio está siendo afectado por valores extremos.
  - Limitación: menos intuitivo para audiencias no técnicas, pero esencial en contexto académico para discutir variabilidad.

- Violin plot
  - Justificación: combinación de densidad con estadística resumida; revela multimodalidad y estructura en la distribución que un simple promedio oculta.
  - Limitación: requiere explicación en la tesis, pero aporta valor analítico.

- Líneas de tendencia (serie temporal de medias) con bandas de incertidumbre (intervalos de confianza o desviación estándar)
  - Justificación: útil para mostrar cómo cambia el tiempo promedio en el tiempo y la incertidumbre asociada; las bandas ayudan a evaluar significancia práctica.
  - Limitación: depende de agregaciones; las bandas pueden ocultar detalles si no se calculan correctamente.

- Histogramas con overlay de densidad
  - Justificación: muestran la forma de la distribución y permiten detectar sesgos y colas.
  - Limitación: no compara fácilmente entre grupos sin small multiples.

Recomendación principal:
- Gráfico principal: **boxplot** (acompañado de un histograma o violin para profundizar).
  - Criterio: el tiempo de detección es una variable cuya variabilidad y outliers son relevantes; boxplots hacen explícita esa variabilidad y son defendibles académicamente.

Visualizaciones complementarias:
- Para tendencias en el tiempo: **línea con bandas de incertidumbre** (media ± std o IC95%) y small multiples por sensor para comparar desempeño.
- Para comparar grupos: `strip plot` o `beeswarm` sobrepuestos al boxplot para mostrar puntos reales.

Mejoras sobre gráficas actuales:
- Si en la vista actual solo se muestra el promedio (un solo número o gauge), añadir boxplots o violines aumentará la solidez del análisis y permitirá argumentar sobre robustez y distribuciones.
- Tratar outliers explícitamente en la tesis: mostrar con y sin outliers para discutir sensibilidad.

---

## Recomendaciones transversales y consideraciones prácticas

- Siempre acompañar los gráficos con cifras clave (n muestras, periodo, definiciones de métricas) y una breve nota metodológica que explique cómo se calculan los indicadores (p. ej. ventana temporal, filtro de sensores, tratamiento de datos faltantes).
- Interactividad: en un dashboard interactivo, permitir filtrar por periodo, sensor, lote y mostrar tooltips con valores exactos; esto eleva la capacidad comparativa sin saturar la vista principal.
- Accesibilidad: usar paletas perceptualmente uniformes y compatibles con daltonismo; añadir texturas o patrones si el color es la única codificación para usuarios con dificultades visuales.
- Integridad académica: incluir en la tesis un anexo metodológico que justifique el cálculo de métricas, el muestreo, y la selección de visualizaciones con referencias (Cleveland, Tufte, Few, Heer et al.).

## Conclusión y recomendación para la tesis

La selección de visualizaciones debe estar alineada con la pregunta analítica para cada indicador: mostrar comparación, explicar variabilidad o demostrar tendencias. Para cada indicador propuesto, se recomienda:

- Porcentaje de precisión: línea (evolución) o barras (comparación por categoría) + dot plot con umbral.
- Nivel de riesgo de lixiviación: mapa de calor espacial (si procede) o columnas codificadas por severidad.
- Tiempo promedio de detección: boxplot (variabilidad y outliers) complementado con histograma/violin y líneas de tendencia cuando corresponda.

Estas elecciones priorizan legibilidad, capacidad comparativa y evidencia para argumentación académica; además, se sugiere documentar procedimientos y agregar visualizaciones complementarias interactivas para mejorar la defensa en la evaluación.

---

Si desea, puedo:
- Generar ejemplos concretos (mock data) y prototipos en JS (Chart.js / D3 / ECharts) para cada recomendación.
- Revisar las vistas actuales y proponer cambios de código concretos en `resources/views/dashboard/*.blade.php`.

Fin del documento.
