import requests
import subprocess
import time
import os
import sys

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

API_CAMARAS = "http://127.0.0.1/ai_emotion/BORRAR/taller1/sigav-web/api/camaras_activas.php"
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
SCRIPT_IA = os.path.join(BASE_DIR, "prueba_ia.py")
procesos = {}

print("========================================")
print("🚀 LANZADOR MAESTRO SIGAV - CÁMARAS")
print("========================================")

while True:
    try:
        res = requests.get(API_CAMARAS, timeout=5)
        data = res.json()
        
        if data.get("status") == "success":
            camaras = data.get("camaras", [])
            activas_ids = []

            # Limpiar procesos que hayan terminado inesperadamente para permitir reconexión
            for cid in list(procesos.keys()):
                if procesos[cid]["proceso"].poll() is not None:
                    print(f"[!] Proceso finalizado para cámara '{procesos[cid]['nombre']}'. Se reintentará si sigue activa.")
                    del procesos[cid]
            
            for c in camaras:
                cid = c['id']
                activas_ids.append(cid)
                
                # Si esta cámara no está corriendo, la lanzamos con el mismo intérprete Python actual
                if cid not in procesos:
                    print(f"[+] Iniciando nueva cámara: {c['nombre']} ({c['ip_url']}) - Sentido: {c['sentido']}")
                    
                    cmd = [
                        sys.executable, SCRIPT_IA,
                        "--camera", str(c['ip_url']),
                        "--sentido", str(c['sentido']),
                        "--puerta", str(c['nombre'])
                    ]
                    
                    # Ejecutar sin bloquear desde el directorio raíz donde residen los modelos ONNX
                    p = subprocess.Popen(cmd, cwd=BASE_DIR)
                    procesos[cid] = {"proceso": p, "nombre": c['nombre']}
            
            # Revisar si hay cámaras que fueron desactivadas en la web y debemos detener el proceso
            for cid in list(procesos.keys()):
                if cid not in activas_ids:
                    print(f"[-] Cámara desactivada en la web: {procesos[cid]['nombre']}. Deteniendo proceso...")
                    procesos[cid]["proceso"].terminate()
                    del procesos[cid]
                    
    except Exception as e:
        print(f"[ERROR] No se pudo conectar a la base de datos de cámaras. Revisa que XAMPP esté corriendo. Detalle: {e}")
        
    # Revisar cambios cada 5 segundos
    time.sleep(5)
