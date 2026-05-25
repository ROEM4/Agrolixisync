# Resumen del Proyecto: AgroLixiSync

## 📌 ¿Qué hace el proyecto?
**AgroLixiSync** es un sistema inteligente de monitoreo agrícola diseñado para la detección en tiempo real de la **lixiviación de fertilizantes** (movimiento de sales y nutrientes hacia capas profundas del suelo donde las raíces no pueden aprovecharlos).

El sistema actúa como una herramienta de soporte a la decisión para agricultores, permitiéndoles:
1.  **Monitorear en tiempo real** la Conductividad Eléctrica (CE), Humedad y Temperatura del suelo.
2.  **Detectar riesgos** de pérdida de nutrientes mediante algoritmos deterministas.
3.  **Recibir alertas automáticas** vía Telegram cuando se detectan anomalías.
4.  **Analizar indicadores académicos (KPIs)** para evaluar la eficiencia del sistema y el impacto en el cultivo.

---

## 📊 Recopilación y Procesamiento de Datos

El sistema recopila datos a través de una arquitectura IoT (Internet de las Cosas) y los procesa siguiendo esta jerarquía:

### 1. Origen de los Datos (Sensores IoT)
Los datos son generados por dispositivos **ESP32** equipados con sensores industriales insertados en el suelo a dos profundidades críticas:
*   **Nivel Superficial (20 cm):** Mide la zona de mayor actividad radicular y aplicación de fertilizantes.
*   **Nivel Profundo (60 cm):** Mide la zona de lixiviación (pérdida).

**Datos capturados por sensor:**
*   **Conductividad Eléctrica (CE):** Medida en dS/m (decisiemens por metro). Es el indicador principal de la concentración de sales/fertilizantes.
*   **Humedad:** Porcentaje de agua en el suelo.
*   **Temperatura:** En grados Celsius (°C).

### 2. Transmisión y Persistencia
Los dispositivos envían los datos mediante protocolos HTTP (POST) a la API del sistema (`/api/sensor/data`). 
*   **Validación:** El sistema verifica que los datos sean coherentes y provengan de dispositivos registrados.
*   **Almacenamiento:** Se guardan en la tabla `readings` (lecturas individuales) y se agregan diariamente en el `Historian` para análisis a largo plazo.

### 3. Cálculo de Indicadores de Lixiviación
Una vez recibidas las lecturas de ambos niveles (superficial y profundo), el motor de análisis calcula:
*   **ΔCE (Delta CE):** `CE_superficial - CE_profundo`. Una caída brusca en la superficie con aumento en la profundidad indica movimiento de sales.
*   **Ratio CE:** `CE_profundo / CE_superficial`. Si el ratio supera 1.2, se considera un riesgo alto de lixiviación.

### 4. Indicadores de Tesis (KPIs Analíticos)
Para evaluar el rendimiento del sistema, se calculan métricas avanzadas:
*   **TAR (Tiempo de Alerta de Riesgo):** Tiempo transcurrido desde que el sensor detecta el inicio de un evento hasta que el sistema emite la alerta.
*   **PDS (Precisión del Diagnóstico del Sistema):** Porcentaje de aciertos (Verdaderos Positivos + Verdaderos Negativos) validados contra observaciones de campo.
*   **NCES (Nivel de Conductividad Eléctrica en Suelo):** Comparativa de promedios entre lotes controlados y lotes experimentales.

---

## 🔔 Sistema de Alertas
El flujo de alerta es el siguiente:
1.  **Detección:** El `LixiviationService` clasifica el riesgo como **BAJO, MEDIO o ALTO**.
2.  **Notificación:** Si el riesgo es MEDIO o ALTO, el `TelegramService` envía un mensaje inmediato al grupo de monitoreo con:
    *   Nombre del lote y ubicación.
    *   Valores de CE superficial y profunda.
    *   Nivel de riesgo y tendencia.
3.  **Gestión:** Las alertas pueden ser resueltas manualmente desde el Dashboard o automáticamente si el sistema detecta que los niveles han vuelto a la normalidad.

---

## 🛠️ Tecnologías Principales
*   **Backend:** Laravel (PHP) con arquitectura modular.
*   **Frontend:** Blade Templates, JavaScript (Vite) y Chart.js para visualización dinámica.
*   **Base de Datos:** MySQL (XAMPP).
*   **IoT:** Microcontroladores ESP32 y sensores RS485/Modbus.
*   **Integraciones:** Telegram Bot API para notificaciones push.
