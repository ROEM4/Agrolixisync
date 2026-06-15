#include <WiFi.h>
#include <HTTPClient.h>
#include <ModbusMaster.h>
#include <Wire.h>
#include <U8g2lib.h>
#include <SPI.h>
#include <SD.h>
#include <RTClib.h>

// ================= OLED =================
U8G2_SH1106_128X64_NONAME_F_HW_I2C display(U8G2_R0, U8X8_PIN_NONE);

// ================= WIFI =================
const char* ssid = "COMPUTRONICA";
const char* password = "computronic@";
const char* serverUrl = "http://192.168.1.9:8000/api/sensor/data";

// ================= RTC =================
RTC_DS3231 rtc;

// ================= SD =================
#define SD_CS 27

// ================= RS485 =================
HardwareSerial RS485_SUP(2);
HardwareSerial RS485_PROF(1);
ModbusMaster node_sup;
ModbusMaster node_prof;

// ================= IDENTIFICACIÓN =================
String parcela = "G29";  // ← CAMBIAR según la planta: G1, G2, G3... hasta G30

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

// ================= TIMESTAMP =================
String getTimestamp() {
  DateTime now = rtc.now();
  char buf[25];
  sprintf(buf, "%04d-%02d-%02dT%02d:%02d:%02d",
          now.year(), now.month(), now.day(),
          now.hour(), now.minute(), now.second());
  return String(buf);
}

// ================= SENSOR READ =================
bool readSensor(ModbusMaster &node, SensorData &s) {
  uint8_t result = node.readHoldingRegisters(0x0000, 3);
  
  if (result == node.ku8MBSuccess) {
    s.humidity = node.getResponseBuffer(0) / 10.0;
    s.temperature = node.getResponseBuffer(1) / 10.0;
    s.ce = node.getResponseBuffer(2) / 1000.0;
    s.ok = true;
    return true;
  }
  
  s.ok = false;
  return false;
}

// ================= SD =================
void saveSD() {
  File file = SD.open("/datos.csv", FILE_APPEND);
  if (!file) return;

  file.println(
    getTimestamp() + "," +
    parcela + "," +
    String(CE_s,3) + "," +
    String(CE_p,3) + "," +
    String(H_s,1) + "," +
    String(T_s,1) + "," +
    String(H_p,1) + "," +
    String(T_p,1)
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
    display.print("CEs: "); display.print(CE_s, 3);

    display.setCursor(0, 34);
    display.print("CEp: "); display.print(CE_p, 3);

    display.setCursor(0, 46);
    display.print("WiFi: "); 
    display.print(WiFi.status() == WL_CONNECTED ? "OK" : "OFF");

    display.sendBuffer();
    vTaskDelay(1000 / portTICK_PERIOD_MS);
  }
}

// ================= NETWORK =================
void taskNetwork(void *pv) {
  for (;;) {
    if (WiFi.status() != WL_CONNECTED) {
      WiFi.begin(ssid, password);
      vTaskDelay(3000 / portTICK_PERIOD_MS);
      continue;
    }

    // JSON CORREGIDO - Campos exactos que espera Laravel
    String json = 
      "{"
      "\"device\":\"" + parcela + "\","
      "\"ts\":\"" + getTimestamp() + "Z\","
      "\"ce_s\":" + String(CE_s, 3) + ","
      "\"ce_p\":" + String(CE_p, 3) + ","
      "\"temp_s\":" + String(T_s, 1) + ","
      "\"temp_p\":" + String(T_p, 1) + ","
      "\"hum_s\":" + String(H_s, 1) + ","
      "\"hum_p\":" + String(H_p, 1) + 
      "}";

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

  xTaskCreatePinnedToCore(taskSensor, "sensor", 4096, NULL, 3, NULL, 1);
  xTaskCreatePinnedToCore(taskOLED, "oled", 4096, NULL, 2, NULL, 1);
  xTaskCreatePinnedToCore(taskNetwork, "net", 6144, NULL, 1, NULL, 0);
}

// ================= LOOP =================
void loop() {
  vTaskDelay(portMAX_DELAY);
}