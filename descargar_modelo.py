import requests

url = "https://github.com/Chaitanyakota9/license-plate-detection-using-yolov8-and-easyocr/raw/main/best.pt"
filename = "placas_yolov8.pt"

print("Descargando modelo desde GitHub...")
try:
    response = requests.get(url, stream=True)
    if response.status_code == 200:
        with open(filename, 'wb') as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)
        print("¡Descarga completada con éxito!")
    else:
        print(f"Error: {response.status_code}")
except Exception as e:
    print(f"Error: {e}")
