import cv2
from ultralytics import YOLO
import os

# ==========================================
# CONFIGURACIÓN
# ==========================================
# Pon la foto que quieres probar dentro de la carpeta "prueba_foto" y ponle este nombre:
FOTO_PATH = 'foto.jpg' 

# Usar los nuevos modelos exportados a ONNX (muchísimo más rápidos en CPU)
VEHICLE_MODEL_PATH = '../yolov8n.onnx'
PLATE_MODEL_PATH = '../placas_bolivia.onnx'
CHAR_MODEL_PATH = '../caracteres_yolov8.onnx'

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
# INICIALIZACIÓN
# ==========================================
if not os.path.exists(FOTO_PATH):
    print(f"[ERROR] No se encontró la foto '{FOTO_PATH}'.")
    print(f"Por favor, copia una foto de un vehículo en la carpeta 'prueba_foto' y nómbrala '{FOTO_PATH}'")
    exit()

print("[INFO] Cargando modelos ONNX súper rápidos...")
vehicle_model = YOLO(VEHICLE_MODEL_PATH, task='detect')
plate_model = YOLO(PLATE_MODEL_PATH, task='detect')
char_model = YOLO(CHAR_MODEL_PATH, task='detect')

print("[OK] Modelos listos.")

# ==========================================
# PROCESAMIENTO DE LA FOTO
# ==========================================
print("[INFO] Analizando la foto...")
frame = cv2.imread(FOTO_PATH)
display_frame = frame.copy()

# 1. Detectar vehículos (bicicleta, auto, moto, bus, camión)
vehicle_results = vehicle_model(frame, classes=[1, 2, 3, 5, 7], verbose=False)

placas_procesadas = [] # Para evitar procesar la misma placa dos veces si hay vehículos superpuestos

for result in vehicle_results:
    boxes = result.boxes
    for box in boxes:
        x1, y1, x2, y2 = map(int, box.xyxy[0])
        conf = float(box.conf[0])
        class_id = int(box.cls[0])
        nombre_vehiculo = NOMBRES_VEHICULOS.get(class_id, "Vehiculo")
        
        cv2.rectangle(display_frame, (x1, y1), (x2, y2), (255, 0, 0), 2)
        cv2.putText(display_frame, f"{nombre_vehiculo} {conf:.2f}", (x1, y1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (255, 0, 0), 2)
        
        # 2. Recortar vehículo
        vehicle_crop = frame[y1:y2, x1:x2]
        if vehicle_crop.size == 0: continue
            
        # 3. Buscar Placa en formato ONNX
        plate_results = plate_model(vehicle_crop, verbose=False)
        for p_result in plate_results:
            p_boxes = p_result.boxes
            for p_box in p_boxes:
                px1, py1, px2, py2 = map(int, p_box.xyxy[0])
                p_conf = float(p_box.conf[0])
                
                abs_px1, abs_py1 = x1 + px1, y1 + py1
                abs_px2, abs_py2 = x1 + px2, y1 + py2
                
                # Deduplicar: Si ya procesamos una placa muy cerca de aquí, la ignoramos
                cx, cy = (abs_px1 + abs_px2) // 2, (abs_py1 + abs_py2) // 2
                es_duplicada = False
                for (pcx, pcy) in placas_procesadas:
                    if abs(cx - pcx) < 30 and abs(cy - pcy) < 30:
                        es_duplicada = True
                        break
                if es_duplicada:
                    continue
                placas_procesadas.append((cx, cy))
                
                cv2.rectangle(display_frame, (abs_px1, abs_py1), (abs_px2, abs_py2), (0, 255, 0), 2)
                
                # 4. Buscar caracteres (Letras y Números) dentro de la placa
                # Añadir un pequeño margen (padding) para no cortar las letras de los bordes
                pad_x, pad_y = 4, 4
                px1_pad = max(0, px1 - pad_x)
                py1_pad = max(0, py1 - pad_y)
                px2_pad = min(vehicle_crop.shape[1], px2 + pad_x)
                py2_pad = min(vehicle_crop.shape[0], py2 + pad_y)
                
                plate_crop = vehicle_crop[py1_pad:py2_pad, px1_pad:px2_pad]
                
                if plate_crop.size > 0:
                    # --- MAGIA: Filtro de Contraste y Escala de Grises ---
                    gray = cv2.cvtColor(plate_crop, cv2.COLOR_BGR2GRAY)
                    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
                    eq = clahe.apply(gray)
                    plate_crop_eq = cv2.cvtColor(eq, cv2.COLOR_GRAY2BGR)
                    
                    # Le bajamos el conf a 0.05 y usamos IOU=0.9
                    char_results = char_model(plate_crop_eq, conf=0.15, iou=0.9, verbose=False)
                    
                    caracteres_detectados = []
                    caracteres_todos = []
                    for c_result in char_results:
                        c_boxes = c_result.boxes
                        for c_box in c_boxes:
                            cx1, cy1, cx2, cy2 = map(int, c_box.xyxy[0])
                            c_conf = float(c_box.conf[0])
                            c_cls = int(c_box.cls[0])
                            caracter = NOMBRES_CARACTERES.get(c_cls, "?")
                            
                            caracteres_todos.append((caracter, c_conf))
                            
                            # Filtro de confianza equilibrado
                            if c_conf > 0.15:
                                caracteres_detectados.append((cx1, caracter, cx1, cy1, cx2, cy2, c_conf))
                    
                    print(f"[DEBUG] TODAS las detecciones (sin filtro): {caracteres_todos}")
                    
                    # ALGORITMO GEOMÉTRICO: Ordenar de izquierda a derecha por coordenada X1
                    caracteres_detectados.sort(key=lambda x: x[0])
                    print(f"[DEBUG] RAW Letras detectadas por la IA: {[c[1] for c in caracteres_detectados]}")
                    
                    # Filtro de Solapamiento (NMS Horizontal): Evitar letras dobles en la misma posición
                    caracteres_limpios = []
                    for c in caracteres_detectados:
                        if caracteres_limpios:
                            prev = caracteres_limpios[-1]
                            ancho_prev = prev[4] - prev[2]
                            # Si están muy cerca horizontalmente (se solapan más del 70% del ancho)
                            if (c[0] - prev[0]) < (ancho_prev * 0.3):
                                # Quedarse con el de mayor confianza
                                if c[6] > prev[6]:
                                    caracteres_limpios[-1] = c
                                continue
                        caracteres_limpios.append(c)
                    
                    # Unir los caracteres para formar el string final
                    plate_text = "".join([c[1] for c in caracteres_limpios])
                    
                    if plate_text:
                        print(f">>> PLACA DETECTADA: {plate_text} (Confianza Placa: {p_conf:.2f})")
                        # Texto Blanco con borde Negro para que sea super legible en cualquier fondo
                        cv2.putText(display_frame, plate_text, (abs_px1, abs_py1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (0, 0, 0), 4)
                        cv2.putText(display_frame, plate_text, (abs_px1, abs_py1 - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (255, 255, 255), 2)
                        
                        # Dibujar bounding boxes amarillas sobre cada letra
                        for _, char, cx1, cy1, cx2, cy2, c_conf in caracteres_limpios:
                            # Ajustar coordenadas por el padding que añadimos
                            c_abs_x1, c_abs_y1 = abs_px1 - pad_x + cx1, abs_py1 - pad_y + cy1
                            c_abs_x2, c_abs_y2 = abs_px1 - pad_x + cx2, abs_py1 - pad_y + cy2
                            cv2.rectangle(display_frame, (c_abs_x1, c_abs_y1), (c_abs_x2, c_abs_y2), (0, 255, 255), 1)
                    else:
                        print("[DEBUG] El modelo no detectó caracteres claros en esta placa.")

# Mostrar la imagen analizada
print("[INFO] Mostrando resultado. Presiona cualquier tecla en la ventana de la imagen para cerrar.")
# Guardar la imagen final analizada
print("[INFO] Guardando resultado en 'output_debug.jpg'.")
cv2.imwrite('output_debug.jpg', display_frame)
