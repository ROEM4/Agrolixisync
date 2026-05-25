#include <WiFi.h>
#include <HTTPClient.h>
#include <ModbusMaster.h>
#include <Wire.h>
#include <U8g2lib.h>
#include <SPI.h>
#include <SD.h>
#include <RTClib.h>
#include <math.h>

// ================= OLED =================
U8G2_SH1106_128X64_NONAME_F_HW_I2C display(U8G2_R0, U8X8_PIN_NONE);

// ================= WIFI =================
//const char* ssid = "ROEM";
//const char* password = "12345678";
//const char* serverUrl = "http://10.186.192.232:8000/api/sensor/data";


const char* ssid = "COMPUTRONICA";
const char* password = "computronic@";
const char* serverUrl = "http://192.168.1.51:8000/api/sensor/data";

// ================= RTC =================
RTC_DS3231 rtc;

// ================= SD =================
#define SD_CS 27

// ================= RS485 =================
HardwareSerial RS485_SUP(2);
HardwareSerial RS485_PROF(1);
ModbusMaster node_sup;
ModbusMaster node_prof;

// ================= PARCELA =================
String parcela = "G1";

// ================= DATA =================
struct SensorData {
  float humidity;
  float temperature;
  float ce;
  bool ok;
};

SensorData sup, prof;

// ================= VARIABLES =================
float CE_s = 0, CE_p = 0;
float H_s = 0, T_s = 0;
float H_p = 0, T_p = 0;

// NOTA: ILx y dCE se calculan en el firmware solo para OLED/SD.
// La DECISIÓN de estado oficial se hace en el backend (Laravel).
float dCE = 0;   // secundario (CE_s - CE_p)
float ILx = 0;   // PRINCIPAL (CE_p / CE_s)

int nivelRiesgo = 0;
String estado = "INIT";

bool alertaActiva = false;
unsigned long t_riesgo = 0;
unsigned long t_alerta = 0;

// ================= TIMESTAMP =================
String getTimestamp() {
  DateTime now = rtc.now();
  char buf[25];
  sprintf(buf, "%04d-%02d-%02d %02d:%02d:%02d",
          now.year(), now.month(), now.day(),
          now.hour(), now.minute(), now.second());
  return String(buf);
}

// ================= SENSOR READ =================
bool readSensor(ModbusMaster &node, SensorData &s) {
  if (node.readHoldingRegisters(0x0000, 3) != node.ku8MBSuccess) {
    s.ok = false;
    return false;
  }

  s.humidity = node.getResponseBuffer(0) / 10.0;
  s.temperature = node.getResponseBuffer(1) / 10.0;
  s.ce = node.getResponseBuffer(2) / 1000.0;
  s.ok = true;
  return true;
}

// ================= RIESGO LOCAL (ILx PRINCIPAL) =================
// NOTA: Esta lógica es SOLO para display OLED y registro SD.
// La clasificación autoritativa se realiza en el backend Laravel.
void evaluarRiesgoLocal() {

  // Calcular ILx (evitar division por cero)
  if (CE_s > 0) {
    ILx = CE_p / CE_s;
  } else {
    ILx = 0;
  }

  // Delta solo como complemento
  dCE = CE_s - CE_p;

  // ===== CLASIFICACIÓN BASADA EN ILx =====
  if (ILx > 1.2) {
    estado = "LIX ALTA";
    nivelRiesgo = 2;
  }
  else if (ILx > 1.05) {
    estado = "LIX";
    nivelRiesgo = 1;
  }
  else if (ILx >= 0.9) {
    estado = "EQUIL";
    nivelRiesgo = 0;
  }
  else if (ILx >= 0.7) {
    estado = "RET";
    nivelRiesgo = 1;
  }
  else {
    estado = "ACUM";
    nivelRiesgo = 2;
  }

  // ===== ALERTAS LOCALES =====
  if (nivelRiesgo == 2) {
    if (t_riesgo == 0) t_riesgo = millis();
    alertaActiva = true;
    if (t_alerta == 0) t_alerta = millis();
  } else {
    alertaActiva = false;
    t_riesgo = 0;
    t_alerta = 0;
  }
}

// ================= TIEMPO ALERTA =================
float tiempoAlerta() {
  if (t_riesgo == 0 || t_alerta == 0) return 0;
  return (t_alerta - t_riesgo) / 60000.0;
}

// ================= SD =================
void saveSD() {
  File file = SD.open("/datos.csv", FILE_APPEND);
  if (!file) return;

  // Cabecera: timestamp,parcela,ce_s,ce_p,ilx,dce,hum_s,temp_s,hum_p,temp_p,estado,nivel_riesgo
  file.println(
    getTimestamp() + "," +
    parcela + "," +
    String(CE_s, 3) + "," +
    String(CE_p, 3) + "," +
    String(ILx, 3) + "," +      // ILx — indicador principal
    String(dCE, 3) + "," +      // dCE — indicador secundario
    String(H_s, 1) + "," +
    String(T_s, 1) + "," +
    String(H_p, 1) + "," +
    String(T_p, 1) + "," +
    estado + "," +
    String(nivelRiesgo)
  );

  file.close();
}

// ================= SENSOR TASK =================
void taskSensor(void *pv) {

  for (;;) {

    readSensor(node_sup, sup);
    vTaskDelay(500 / portTICK_PERIOD_MS);

    readSensor(node_prof, prof);

    if (sup.ok && prof.ok) {
      CE_s = sup.ce;
      CE_p = prof.ce;

      H_s = sup.humidity;
      T_s = sup.temperature;

      H_p = prof.humidity;
      T_p = prof.temperature;
    }

    evaluarRiesgoLocal();
    saveSD();

    vTaskDelay(5000 / portTICK_PERIOD_MS);
  }
}

// ================= OLED =================
void taskOLED(void *pv) {

  for (;;) {

    display.clearBuffer();
    display.setFont(u8g2_font_6x10_tr);

    display.setCursor(0, 10);
    display.print("PARCELA: "); display.print(parcela);

    display.setCursor(0, 22);
    display.print("ILx: "); display.print(ILx, 3);  // 3 decimales para precision

    display.setCursor(0, 34);
    display.print("CEs: "); display.print(CE_s, 3);

    display.setCursor(0, 46);
    display.print("CEp: "); display.print(CE_p, 3);

    display.setCursor(0, 58);
    display.print(estado);

    display.sendBuffer();

    vTaskDelay(1000 / portTICK_PERIOD_MS);
  }
}

// ================= NETWORK =================
// El firmware envía datos CRUDOS. El backend (Laravel) determina el estado oficial.
void taskNetwork(void *pv) {

  for (;;) {

    if (WiFi.status() != WL_CONNECTED) {
      WiFi.begin(ssid, password);
      vTaskDelay(3000 / portTICK_PERIOD_MS);
      continue;
    }

    // JSON v3: incluye ilx y dce como datos calculados para referencia local.
    // El backend recalculará ILx para la clasificacion autoritativa.
    String json =
      "{\"parcela\":\"" + parcela + "\"," +
      "\"ce_s\":" + String(CE_s, 3) + "," +
      "\"ce_p\":" + String(CE_p, 3) + "," +
      "\"ilx\":" + String(ILx, 3) + "," +
      "\"dce\":" + String(dCE, 3) + "," +
      "\"temp_s\":" + String(T_s, 1) + "," +
      "\"temp_p\":" + String(T_p, 1) + "," +
      "\"hum_s\":" + String(H_s, 1) + "," +
      "\"hum_p\":" + String(H_p, 1) + "," +
      "\"estado\":\"" + estado + "\"," +
      "\"riesgo\":" + String(nivelRiesgo) + "," +
      "\"tiempo_alerta_min\":" + String(tiempoAlerta(), 2) + "}";

    Serial.println(json);

    HTTPClient http;
    http.begin(serverUrl);
    http.addHeader("Content-Type", "application/json");

    int httpCode = http.POST(json);
    Serial.print("HTTP: ");
    Serial.println(httpCode);

    http.end();

    vTaskDelay(10000 / portTICK_PERIOD_MS);
  }
}

// ================= SETUP =================
void setup() {

  Serial.begin(115200);

  Wire.begin(21, 22);
  display.begin();

  rtc.begin();
  if (rtc.lostPower()) {
    rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
  }

  SD.begin(SD_CS);

  RS485_SUP.begin(9600, SERIAL_8N1, 13, 14);
  node_sup.begin(1, RS485_SUP);

  RS485_PROF.begin(9600, SERIAL_8N1, 16, 17);
  node_prof.begin(1, RS485_PROF);

  WiFi.mode(WIFI_STA);

  xTaskCreatePinnedToCore(taskSensor,  "sensor", 4096, NULL, 3, NULL, 1);
  xTaskCreatePinnedToCore(taskOLED,   "oled",   4096, NULL, 2, NULL, 1);
  xTaskCreatePinnedToCore(taskNetwork,"net",    6144, NULL, 1, NULL, 0);
}

// ================= LOOP =================
void loop() {
  vTaskDelay(portMAX_DELAY);
}
