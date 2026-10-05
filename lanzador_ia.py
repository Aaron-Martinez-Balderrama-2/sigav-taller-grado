import requests
import subprocess
import time
import os

API_CAMARAS = "http://localhost/ai_emotion/BORRAR/taller1/sigav-web/api/camaras_activas.php"
procesos = {}

print("========================================")
print("🚀 LANZADOR MAESTRO SIGAV - CÁMARAS")
print("========================================")

while True:
    try:
        res = requests.get(API_CAMARAS)
        data = res.json()
        
        if data.get("status") == "success":
            camaras = data.get("camaras", [])
            activas_ids = []
            
            for c in camaras:
                cid = c['id']
                activas_ids.append(cid)
                
                # Si esta cámara no está corriendo, la lanzamos
                if cid not in procesos:
                    print(f"[+] Iniciando nueva cámara: {c['nombre']} ({c['ip_url']}) - Sentido: {c['sentido']}")
                    
                    # Comando para ejecutar prueba_ia.py en segundo plano
                    cmd = [
                        "python", "prueba_ia.py",
                        "--camera", c['ip_url'],
                        "--sentido", c['sentido'],
                        "--puerta", c['nombre']
                    ]
                    
                    # Ejecutar sin bloquear
                    p = subprocess.Popen(cmd)
                    procesos[cid] = {"proceso": p, "nombre": c['nombre']}
            
            # Revisar si hay cámaras que fueron desactivadas en la web y debemos matar el proceso
            for cid in list(procesos.keys()):
                if cid not in activas_ids:
                    print(f"[-] Cámara desactivada en la web: {procesos[cid]['nombre']}. Deteniendo proceso...")
                    procesos[cid]["proceso"].terminate()
                    del procesos[cid]
                    
    except Exception as e:
        print(f"[ERROR] No se pudo conectar a la base de datos de cámaras. Revisa que XAMPP esté corriendo. Detalle: {e}")
        
    # Revisar cambios cada 5 segundos
    time.sleep(5)
