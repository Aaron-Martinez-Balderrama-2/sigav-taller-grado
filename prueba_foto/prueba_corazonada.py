import cv2
import numpy as np
from ultralytics import YOLO
import easyocr
import random
from collections import Counter

# ==========================================
# CONFIGURACIÓN
# ==========================================
FOTO_PATH = 'foto.jpg' 
VEHICLE_MODEL_PATH = '../yolov8n.onnx'
PLATE_MODEL_PATH = '../placas_yolov8.onnx'

# ==========================================
# FUNCIONES DE SIMULACIÓN Y LIMPIEZA
# ==========================================
def simular_movimiento_camara(imagen):
    """Aplica pequeños cambios aleatorios para simular una cámara de video en vivo"""
    img_modificada = imagen.copy()
    
    # 1. Micro-zoom aleatorio (simula pulso)
    escala = random.uniform(0.95, 1.05)
    alto, ancho = img_modificada.shape[:2]
    nueva_dim = (int(ancho * escala), int(alto * escala))
    img_modificada = cv2.resize(img_modificada, nueva_dim)
    
    # Si se hizo pequeña, la rellenamos. Si se hizo grande, la recortamos.
    if escala < 1.0:
        pad_y = (alto - nueva_dim[1]) // 2
        pad_x = (ancho - nueva_dim[0]) // 2
        img_modificada = cv2.copyMakeBorder(img_modificada, pad_y, alto-nueva_dim[1]-pad_y, pad_x, ancho-nueva_dim[0]-pad_x, cv2.BORDER_REPLICATE)
    elif escala > 1.0:
        start_y = (nueva_dim[1] - alto) // 2
        start_x = (nueva_dim[0] - ancho) // 2
        img_modificada = img_modificada[start_y:start_y+alto, start_x:start_x+ancho]
        
    # 2. Micro-desenfoque aleatorio
    if random.choice([True, False]):
        img_modificada = cv2.GaussianBlur(img_modificada, (3, 3), 0)
        
    return img_modificada

def limpiar_placa_cruda(imagen):
    # Sin blanco y negro, solo escalado a color puro
    return cv2.resize(imagen, None, fx=4, fy=4, interpolation=cv2.INTER_CUBIC)

# ==========================================
# INICIALIZACIÓN
# ==========================================
print("[INFO] Cargando modelos...")
vehicle_model = YOLO(VEHICLE_MODEL_PATH, task='detect')
plate_model = YOLO(PLATE_MODEL_PATH, task='detect')
reader = easyocr.Reader(['en'], gpu=False)

frame_original = cv2.imread(FOTO_PATH)
lecturas = [] # Aquí guardaremos las 20 lecturas

print("\n--- INICIANDO SIMULACIÓN DE 20 FRAMES DE VIDEO ---")
# Simular 20 frames de video
for i in range(1, 21):
    frame_simulado = simular_movimiento_camara(frame_original)
    
    vehicle_results = vehicle_model(frame_simulado, classes=[2, 3, 5, 7], verbose=False)
    texto_leido = None
    
    for result in vehicle_results:
        for box in result.boxes:
            x1, y1, x2, y2 = map(int, box.xyxy[0])
            vehicle_crop = frame_simulado[y1:y2, x1:x2]
            if vehicle_crop.size == 0: continue
                
            plate_results = plate_model(vehicle_crop, verbose=False)
            for p_result in plate_results:
                for p_box in p_result.boxes:
                    px1, py1, px2, py2 = map(int, p_box.xyxy[0])
                    plate_crop = vehicle_crop[py1:py2, px1:px2]
                    
                    if plate_crop.size > 0:
                        plate_clean = limpiar_placa_cruda(plate_crop)
                        ocr_results = reader.readtext(plate_clean, allowlist='ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')
                        texto_leido = " ".join([t for (b, t, p) in ocr_results]).strip()
                        break
                if texto_leido: break
            if texto_leido: break
            
    if texto_leido:
        lecturas.append(texto_leido)
        print(f"Frame {i}/20: Detectado -> {texto_leido}")
    else:
        print(f"Frame {i}/20: Nada claro.")

# ==========================================
# ANÁLISIS DE LA CORAZONADA
# ==========================================
print("\n--- RESULTADO DE LA VOTACIÓN (LA CORAZONADA) ---")
if lecturas:
    # Contar cuál fue la lectura que más se repitió
    conteo = Counter(lecturas)
    placa_ganadora = conteo.most_common(1)[0][0]
    repeticiones = conteo.most_common(1)[0][1]
    
    print(f"El sistema se estabilizó en la placa: >>> {placa_ganadora} <<<")
    print(f"Esta placa apareció en {repeticiones} de los {len(lecturas)} frames simulados.")
    print("\nHistorial completo de lo que vió la cámara:")
    for placa, cant in conteo.items():
        print(f" - {placa}: {cant} veces")
else:
    print("El OCR no pudo leer texto en ninguno de los frames simulados.")
