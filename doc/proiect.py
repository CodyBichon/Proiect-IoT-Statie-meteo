# proiect.py
from machine import Pin, ADC, I2C
import ssd1306
import bmp280
import ahtx0
import time
import network
import urequests
import json


# Configurarea I2C
i2c = I2C(0, scl=Pin(5), sda=Pin(4))

# Configurarea display-ului OLED
oled = ssd1306.SSD1306_I2C(128, 64, i2c)

# Configurarea senzorului BMP280 (adresa corectă: 0x77)
bmp = bmp280.BMP280(i2c, addr=0x77) 

# Configurarea senzorului AHT20
aht = ahtx0.AHT20(i2c)

# Configurarea senzorului GUVA-S12SD
uv_sensor = ADC(27) # GPIO26 (Pin 32)
# Parametrii senzorului GUVA-S12SD
responsivitate = 0.14  # A/W
# curent_luminos_referinta = 112.5e-9 = 112.5 nA pentru 1 mW/cm² (media dintre 101 si 125)
curent_luminos_referinta = 101e-9  # limita inferioară
curent_intunecat = 1e-9  # 1 nA
bias_uv = 256 # valoarea bias

# Configurarea senzorului de vant (senzor de rotatie impulsuri)
wind_sensor_pin = Pin(2, Pin.IN, Pin.PULL_UP) # Pin 4 (GPIO2) ca intrare cu pull-up intern
impulsuri = 0
timp_anterior_vant = 0
viteza_vant = 0

def gestioneaza_impuls(pin):
    global impulsuri
    impulsuri += 1

wind_sensor_pin.irq(trigger=Pin.IRQ_FALLING, handler=gestioneaza_impuls)

wifi_config = [
    {"ssid": "GalaxyA13", "password": ""}, # WiFi hotspot telefon mobi, fara parola, necriptat
    {"ssid": "Acasa", "password": "pisicu"}, # WiFi de acasa
]

wlan = network.WLAN(network.STA_IF)
wlan.active(True)

def connect_wifi(ssid, password):
    print(f"Încerc conectarea la {ssid}...")
    wlan.connect(ssid, password)
    for i in range(10):
        if wlan.isconnected():
            print(f"Conectat cu succes la {ssid}!")
            return True
        time.sleep(1)
    print(f"Nu s-a putut conecta la {ssid}.")
    return False

conectat = False
for config in wifi_config:
    if connect_wifi(config["ssid"], config["password"]):
        conectat = True
        break

if not conectat:
    print("Nu s-a putut conecta la nicio rețea Wi-Fi!")
else:
    print("Conectat la Wi-Fi")

# URL-urile serverelor
#url = "http://192.168.0.108/meteo/pico.php"  # adresa de acasa
url_server_local = "http://192.168.75.131/meteo/pico.php"  # adresa conexiunii la telefon
url_server_cloud = "https://meteo.aswic.ro/upload_meteo.php"  # adresa cloud

headers = {'Content-Type': 'application/json; charset=utf-8'} # Specificăm codificarea UTF-8

while True:
    try:
        # Citirea datelor de la BMP280
        temp_bmp = bmp.temperature
        pressure = bmp.pressure / 100  # Convertim Pa în hPa

        # Citirea datelor de la AHT20
        hum = aht.relative_humidity

        # Citirea valorii analogice de la senzorul UV
        uv_valoare_analogica = uv_sensor.read_u16()
        uv_valoare_analogica_compensata = max(0, uv_valoare_analogica - bias_uv)
        tensiune_uv = uv_valoare_analogica_compensata * 3.3 / 65535
        curent_uv = tensiune_uv / 10000 # 10k ohmi, probabil
        curent_uv_compensat = max(0, curent_uv - curent_intunecat)
        intensitate_uv = curent_uv_compensat / curent_luminos_referinta

        # Citirea și calcularea vitezei vântului
        timp_curent_vant = time.ticks_ms()
        delta_timp_vant = time.ticks_diff(timp_curent_vant, timp_anterior_vant)

        if delta_timp_vant >= 1000:
            frecventa_vant = impulsuri / (delta_timp_vant / 1000) # impulsuri pe secundă
            factor_calibrare_vant = 0.032 # experimental
            viteza_vant = frecventa_vant * factor_calibrare_vant
            viteza_vant = viteza_vant * 3.6 # Km/h
            impulsuri = 0
            timp_anterior_vant = timp_curent_vant

        # Formatare date cu două zecimale
        temp_bmp_formatat = "{:.2f}".format(temp_bmp)
        hum_formatat = "{:.2f}".format(hum)
        pressure_formatat = "{:.2f}".format(pressure)
        intensitate_uv_formatat = "{:.2f}".format(intensitate_uv)
        wind_speed_formatat = "{:.2f}".format(viteza_vant)

        # Formatați datele în JSON
        date_meteo = {
            "temperatura": temp_bmp_formatat,
            "umiditate": hum_formatat,
            "presiune": pressure_formatat,
            "intensitate_uv": intensitate_uv_formatat,
            "viteza_vant": wind_speed_formatat  
        }
        date_json = json.dumps(date_meteo)


        # --- Trimiterea datelor către PRIMUL SERVER (Local XAMPP) ---
        try:
            print("Trimitere date către serverul local...")
            response_local = urequests.post(url_server_local, headers=headers, json=date_meteo)
            print("Răspuns server local:", response_local.text)
            response_local.close()
        except OSError as e:
            print(f"Eroare la trimiterea datelor către serverul local: {e}")

        # --- Trimiterea datelor către AL DOILEA SERVER (Cloud / meteo.aswic.ro) ---
        try:
            print("Trimitere date către serverul cloud...")
            response_cloud = urequests.post(url_server_cloud, headers=headers, json=date_meteo) 
            print("Răspuns server cloud:", response_cloud.text)
            response_cloud.close()
        except OSError as e:
            print(f"Eroare la trimiterea datelor către serverul cloud: {e}")

        # Afișarea datelor pe display-ul OLED
        oled.fill(0)
        oled.text("Temp: {:.1f} C".format(temp_bmp), 0, 0) # Temp bmp.
        #oled.text("T2: {:.1f} C".format(temp_aht), 0, 10) # Temp aht.
        oled.text("Hum: {:.1f} %".format(hum), 0, 10)
        oled.text("Pres: {:.2f} hPa".format(pressure), 0, 20)
        oled.text("UV: {:.2f} mW/cm2".format(intensitate_uv), 0, 30)
        oled.text("Vant: {} Km/h".format(wind_speed_formatat), 0, 40)
        oled.text("O zi minunata", 0, 50)
        oled.show()

        # Afișarea datelor în terminal
        print("Temperatura: {:.1f} C".format(temp_bmp))
        #print("Temperatura AHT20: {:.1f} C".format(temp_aht))
        print("Umiditate: {:.1f} %".format(hum))
        print("Presiune: {:.2f} hPa".format(pressure))
        print("Intensitate UV: {:.2f} mW/cm2".format(intensitate_uv))
        print("UV: {}".format(uv_valoare_analogica))
        print("Viteza vant: {} Km/h".format(wind_speed_formatat))
        print("-" * 20)  # Linie de separator pentru claritate

    except OSError as e:
        print("Eroare la citirea senzorului")

    time.sleep(2)

