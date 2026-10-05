import cv2
from ultralytics import YOLO
import time
import requests
import base64
import json
import argparse
import threading

# Configuración por consola o por defecto
parser = argparse.ArgumentParser()
parser.add_argument('--camera', type=str, default='0', help='IP de la camara o 0')
parser.add_argument('--sentido', type=str, default='ingreso', help='ingreso o salida')
parser.add_argument('--puerta', type=str, default='Cámara Principal', help='Nombre de la puerta')
args = parser.parse_args()

# 1. URL de la cámara
CAMERA_SOURCE = int(args.camera) if args.camera.isdigit() else args.camera

# 2. Configuración para el Sistema Web SIGAV
API_URL = "http://localhost/ai_emotion/BORRAR/taller1/sigav-web/api/registrar_acceso.php"
SENTIDO = args.sentido
NOMBRE_PUERTA = args.puerta

# 3. Archivos de los modelos de Roboflow exportados a ONNX
PLATE_MODEL_PATH = 'placas_bolivia.onnx'
CHAR_MODEL_PATH = 'caracteres_yolov8.onnx'

# Nombres de las clases en español
NOMBRES_VEHICULOS = {
    1: "Bicicleta",
    2: "Auto",
    3: "Moto",
    5: "Bus",
    7: "Camion"
}

# Diccionario de las 36 clases (0-9, A-Z)
NOMBRES_CARACTERES = {
    0: '0', 1: '1', 2: '2', 3: '3', 4: '4', 5: '5', 6: '6', 7: '7', 8: '8', 9: '9',
    10: 'A', 11: 'B', 12: 'C', 13: 'D', 14: 'E', 15: 'F', 16: 'G', 17: 'H', 18: 'I', 19: 'J',
    20: 'K', 21: 'L', 22: 'M', 23: 'N', 24: 'O', 25: 'P', 26: 'Q', 27: 'R', 28: 'S', 29: 'T',
    30: 'U', 31: 'V', 32: 'W', 33: 'X', 34: 'Y', 35: 'Z'
}

# ==========================================
# INICIALIZACIÓN DE MODELOS
# ==========================================
print("[INFO] Cargando modelos, por favor espera...")

# Cargar YOLO base para vehículos
try:
    vehicle_model = YOLO('yolov8n.onnx', task='detect')
    print("[OK] Modelo de vehículos cargado.")
except Exception as e:
    print(f"[ERROR] No se pudo cargar el modelo de vehículos: {e}")

# Cargar YOLO para placas
try:
    plate_model = YOLO(PLATE_MODEL_PATH, task='detect')
    print("[OK] Modelo de placas cargado.")
except Exception as e:
    print(f"[ERROR] No se encontró '{PLATE_MODEL_PATH}'. Asegúrate de descargarlo de Roboflow y ponerlo en esta carpeta.")
    print("El script continuará, pero solo detectará vehículos, no placas.")
    plate_model = None

# Cargar YOLO para caracteres
try:
    char_model = YOLO(CHAR_MODEL_PATH, task='detect')
    print("[OK] Modelo de caracteres cargado.")
except Exception as e:
    print(f"[ERROR] No se encontró '{CHAR_MODEL_PATH}'. Asegúrate de haberlo convertido a ONNX.")
    char_model = None

# ==========================================
# CICLO PRINCIPAL DE VIDEO
# ==========================================
ultimos_envios = {}  # Diccionario para no mandar la misma placa 100 veces seguidas
TIEMPO_ESPERA_DUPLICADOS = 10  # Segundos antes de volver a registrar la misma placa en la base de datos

def enviar_datos_api(placa, tipo_vehiculo, confianza, foto_pano_b64, foto_placa_b64):
    try:
        payload = {
            "placa": placa,
            "tipo_vehiculo": tipo_vehiculo,
            "confianza": confianza,
            "puerta": NOMBRE_PUERTA,
            "sentido": SENTIDO,
            "foto_panoramica": foto_pano_b64,
            "foto_placa": foto_placa_b64
        }
        res = requests.post(API_URL, json=payload, timeout=3)
        print(f"[API] Respuesta BD: {res.text}")
    except Exception as e:
        print(f"[API ERROR] No se pudo comunicar con la plataforma web: {e}")

cap = cv2.VideoCapture(CAMERA_SOURCE)

if not cap.isOpened():
    print("[ERROR] No se pudo abrir la cámara. Revisa CAMERA_SOURCE.")
    exit()

print("[INFO] Iniciando video... Presiona 'q' para salir.")

while True:
    ret, frame = cap.read()
    if not ret:
        print("[ERROR] No se recibió imagen de la cámara.")
        break
        
    # Copia del frame para dibujar sobre él
    display_frame = frame.copy()

    # 1. DETECTAR VEHÍCULOS
    # Filtramos por clases: 1 (bici), 2 (auto), 3 (moto), 5 (bus), 7 (camión)
    vehicle_results = vehicle_model(frame, classes=[1, 2, 3, 5, 7], verbose=False)
    
    placas_procesadas = []
    
    for result in vehicle_results:
        boxes = result.boxes
        for box in boxes:
            # Obtener coordenadas del vehículo
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            conf = float(box.conf[0])
            class_id = int(box.cls[0])
            nombre_vehiculo = NOMBRES_VEHICULOS.get(class_id, "Vehiculo")
            
            # Dibujar caja del vehículo (Azul)
            cv2.rectangle(display_frame, (x1, y1), (x2, y2), (255, 0, 0), 2)
            cv2.putText(display_frame, f"{nombre_vehiculo} {conf:.2f}", (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 0, 0), 2)
            
            # 2. RECORTAR VEHÍCULO Y BUSCAR PLACA (Solo si tenemos el modelo de placas)
            if plate_model is not None:
                vehicle_crop = frame[y1:y2, x1:x2]
                
                # Prevenir errores si el recorte es inválido
                if vehicle_crop.size == 0:
                    continue
                    
                # Buscar placa dentro del recorte del vehículo
                plate_results = plate_model(vehicle_crop, verbose=False)
                
                for p_result in plate_results:
                    p_boxes = p_result.boxes
                    for p_box in p_boxes:
                        # Coordenadas de la placa RELATIVAS al recorte del vehículo
                        px1, py1, px2, py2 = map(int, p_box.xyxy[0])
                        p_conf = float(p_box.conf[0])
                        
                        # Coordenadas ABSOLUTAS en el frame completo para dibujar
                        abs_px1 = x1 + px1
                        abs_py1 = y1 + py1
                        abs_px2 = x1 + px2
                        abs_py2 = y1 + py2
                        
                        # Deduplicar
                        cx, cy = (abs_px1 + abs_px2) // 2, (abs_py1 + abs_py2) // 2
                        es_duplicada = False
                        for (pcx, pcy) in placas_procesadas:
                            if abs(cx - pcx) < 30 and abs(cy - pcy) < 30:
                                es_duplicada = True
                                break
                        if es_duplicada:
                            continue
                        placas_procesadas.append((cx, cy))
                        
                        # Dibujar caja de la placa (Verde)
                        cv2.rectangle(display_frame, (abs_px1, abs_py1), (abs_px2, abs_py2), (0, 255, 0), 2)
                        
                        # 3. EXTRAER TEXTO DE LA PLACA (OCR YOLO)
                        if char_model is not None:
                            pad_x, pad_y = 4, 4
                            px1_pad = max(0, px1 - pad_x)
                            py1_pad = max(0, py1 - pad_y)
                            px2_pad = min(vehicle_crop.shape[1], px2 + pad_x)
                            py2_pad = min(vehicle_crop.shape[0], py2 + pad_y)
                            
                            plate_crop = vehicle_crop[py1_pad:py2_pad, px1_pad:px2_pad]
                            
                            if plate_crop.size > 0:
                                # Filtro de contraste
                                gray = cv2.cvtColor(plate_crop, cv2.COLOR_BGR2GRAY)
                                clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
                                eq = clahe.apply(gray)
                                plate_crop_eq = cv2.cvtColor(eq, cv2.COLOR_GRAY2BGR)
                                
                                char_results = char_model(plate_crop_eq, conf=0.15, iou=0.9, verbose=False)
                                
                                caracteres_detectados = []
                                for c_result in char_results:
                                    c_boxes = c_result.boxes
                                    for c_box in c_boxes:
                                        cx1, cy1, cx2, cy2 = map(int, c_box.xyxy[0])
                                        c_conf = float(c_box.conf[0])
                                        c_cls = int(c_box.cls[0])
                                        caracter = NOMBRES_CARACTERES.get(c_cls, "?")
                                        
                                        # Filtro de confianza equilibrado
                                        if c_conf > 0.15:
                                            caracteres_detectados.append((cx1, caracter, cx1, cy1, cx2, cy2, c_conf))
                                
                                # ALGORITMO GEOMÉTRICO: Ordenar de izquierda a derecha por coordenada X1
                                caracteres_detectados.sort(key=lambda x: x[0])
                                
                                # Filtro de Solapamiento (NMS Horizontal)
                                caracteres_limpios = []
                                for c in caracteres_detectados:
                                    if caracteres_limpios:
                                        prev = caracteres_limpios[-1]
                                        ancho_prev = prev[4] - prev[2]
                                        if (c[0] - prev[0]) < (ancho_prev * 0.6):
                                            if c[6] > prev[6]:
                                                caracteres_limpios[-1] = c
                                            continue
                                    caracteres_limpios.append(c)
                                
                                plate_text = "".join([c[1] for c in caracteres_limpios])
                                
                                if plate_text:
                                    print(f">>> PLACA DETECTADA: {plate_text} (Confianza Placa: {p_conf:.2f})")
                                    # Escribir el texto con borde negro
                                    cv2.putText(display_frame, plate_text, (abs_px1, abs_py1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (0, 0, 0), 4)
                                    cv2.putText(display_frame, plate_text, (abs_px1, abs_py1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (255, 255, 255), 2)
                                    
                                    # Dibujar bounding boxes amarillas sobre cada letra
                                    for _, char, cx1, cy1, cx2, cy2, c_conf in caracteres_limpios:
                                        c_abs_x1, c_abs_y1 = abs_px1 - pad_x + cx1, abs_py1 - pad_y + cy1
                                        c_abs_x2, c_abs_y2 = abs_px1 - pad_x + cx2, abs_py1 - pad_y + cy2
                                        cv2.rectangle(display_frame, (c_abs_x1, c_abs_y1), (c_abs_x2, c_abs_y2), (0, 255, 255), 1)

                                    # --- INTEGRACIÓN CON EL SISTEMA WEB ---
                                    # Filtro básico: Las placas en Bolivia suelen tener de 6 a 8 caracteres (ej. 1234ABC)
                                    if 5 <= len(plate_text) <= 9:
                                        tiempo_actual = time.time()
                                        # Verificar que no hayamos enviado esta placa en los últimos 10 segundos
                                        if plate_text not in ultimos_envios or (tiempo_actual - ultimos_envios[plate_text]) > TIEMPO_ESPERA_DUPLICADOS:
                                            ultimos_envios[plate_text] = tiempo_actual
                                            
                                            # Extraer imágenes en jpg y convertirlas a Base64
                                            _, buffer_pano = cv2.imencode('.jpg', frame)
                                            foto_pano_b64 = "data:image/jpeg;base64," + base64.b64encode(buffer_pano).decode('utf-8')
                                            
                                            _, buffer_placa = cv2.imencode('.jpg', plate_crop_eq)
                                            foto_placa_b64 = "data:image/jpeg;base64," + base64.b64encode(buffer_placa).decode('utf-8')
                                            
                                            # Enviar a la Base de Datos usando un hilo (para no congelar el video mientras espera respuesta)
                                            threading.Thread(
                                                target=enviar_datos_api, 
                                                args=(plate_text, nombre_vehiculo, round(p_conf * 100, 2), foto_pano_b64, foto_placa_b64)
                                            ).start()

    # Mostrar la imagen
    cv2.imshow('Deteccion de Placas UAGRM', display_frame)
    
    # Salir si se presiona 'q'
    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

# Liberar recursos
cap.release()
cv2.destroyAllWindows()
print("[INFO] Programa finalizado.")
