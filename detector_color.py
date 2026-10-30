import cv2
import numpy as np

def detectar_color_vehiculo(img_bgr):
    """
    Detecta el color predominante de la carrocería de un vehículo usando OpenCV en espacio HSV.
    Filtra sombras extremas, llantas y reflejos de parabrisas enfocándose en la zona central de la carrocería.
    """
    if img_bgr is None or img_bgr.size == 0:
        return "Desconocido"

    h, w = img_bgr.shape[:2]
    
    # Tomar la región central donde predomina la chapa/carrocería
    y_ini = int(h * 0.20)
    y_fin = int(h * 0.85)
    x_ini = int(w * 0.15)
    x_fin = int(w * 0.85)
    
    roi = img_bgr[y_ini:y_fin, x_ini:x_fin]
    if roi.size == 0:
        roi = img_bgr

    blur = cv2.GaussianBlur(roi, (7, 7), 0)
    hsv = cv2.cvtColor(blur, cv2.COLOR_BGR2HSV)

    H = hsv[:, :, 0]
    S = hsv[:, :, 1]
    V = hsv[:, :, 2]

    # Máscaras de colores en HSV
    mascara_negro = (V < 50) | ((V < 75) & (S < 60))
    mascara_blanco = (S < 35) & (V >= 180)
    mascara_gris = (S < 45) & (V >= 50) & (V < 180)

    mascara_cromatico = ~mascara_negro & ~mascara_blanco & ~mascara_gris & (S >= 40) & (V >= 45)

    conteo_negro = np.count_nonzero(mascara_negro)
    conteo_blanco = np.count_nonzero(mascara_blanco)
    conteo_gris = np.count_nonzero(mascara_gris)

    h_cromatico = H[mascara_cromatico]
    
    conteo_rojo = np.count_nonzero((h_cromatico <= 10) | (h_cromatico >= 165))
    conteo_naranja = np.count_nonzero((h_cromatico > 10) & (h_cromatico <= 25))
    conteo_amarillo = np.count_nonzero((h_cromatico > 25) & (h_cromatico <= 35))
    conteo_verde = np.count_nonzero((h_cromatico > 35) & (h_cromatico <= 85))
    conteo_celeste = np.count_nonzero((h_cromatico > 85) & (h_cromatico <= 100))
    conteo_azul = np.count_nonzero((h_cromatico > 100) & (h_cromatico <= 135))
    conteo_violeta = np.count_nonzero((h_cromatico > 135) & (h_cromatico < 165))

    conteos = {
        "Blanco": conteo_blanco,
        "Gris / Plateado": conteo_gris,
        "Negro": conteo_negro,
        "Rojo": conteo_rojo,
        "Azul": conteo_azul,
        "Verde": conteo_verde,
        "Amarillo": conteo_amarillo,
        "Naranja": conteo_naranja,
        "Celeste": conteo_celeste,
        "Violeta": conteo_violeta
    }

    total_pixeles = roi.shape[0] * roi.shape[1]
    total_cromaticos = len(h_cromatico)

    if total_cromaticos > total_pixeles * 0.20:
        conteos_crom = {k: conteos[k] for k in ["Rojo", "Azul", "Verde", "Amarillo", "Naranja", "Celeste", "Violeta"]}
        color_ganador = max(conteos_crom, key=conteos_crom.get)
        if conteos_crom[color_ganador] > total_pixeles * 0.12:
            return color_ganador

    return max(conteos, key=conteos.get)
