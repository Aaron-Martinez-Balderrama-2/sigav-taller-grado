# Registro de Código Pendiente por Implementar (SIGAV-UAGRM)

> [!IMPORTANT]
> Este archivo recopila todas las funcionalidades y mejoras de código que están especificadas en la documentación y que deben programarse en `sigav-web` una vez finalizada la redacción de la tesis.

---

## 1. Almacenamiento Físico de Evidencias Fotográficas en Disco (RF8)
* **Archivo afectado:** `api/registrar_acceso.php`
* **Directorio de destino:** `assets/img/evidencias/`
* **Estado actual:** El endpoint recibe las capturas de la IA en formato Base64 (`evidencia_ingreso_foto` y `evidencia_ingreso_placa`), pero actualmente no las decodifica ni las guarda en el disco del servidor.
* **Código a implementar:**
  ```php
  // Decodificar Base64 y guardar imagen física en disco
  if (!empty($data['evidencia_ingreso_foto'])) {
      $foto_base64 = preg_replace('#^data:image/\w+;base64,#i', '', $data['evidencia_ingreso_foto']);
      $foto_binaria = base64_decode($foto_base64);
      $nombre_archivo = 'ingreso_' . preg_replace('/[^A-Za-z0-9]/', '', $data['placa']) . '_' . time() . '.jpg';
      $ruta_destino = __DIR__ . '/../assets/img/evidencias/' . $nombre_archivo;
      file_put_contents($ruta_destino, $foto_binaria);
      $evidencia_foto_relativa = 'assets/img/evidencias/' . $nombre_archivo;
  }
  ```

---

## 2. Corrección Manual de Placas por el Guardia (RF12)
* **Archivos afectados:**
  - `views/dashboard.php` (Frontend Monitor en Vivo)
  - `views/bitacora.php` (Frontend Bitácora Histórica)
  - `api/corregir_placa.php` (Nuevo Endpoint Backend)
* **Estado actual:** Las placas se muestran en texto estático de solo lectura. No hay botón de edición ni endpoint de actualización.
* **Código a implementar:**
  1. **Frontend:** Añadir un botón o icono de edición (✏️) junto a cada placa en la tabla que abra un modal simple con un `<input type="text" id="placaCorregidaInput">` y un botón "Guardar Corrección".
  2. **Backend (`api/corregir_placa.php`):**
     ```php
     <?php
     require_once __DIR__ . '/../config/database.php';
     session_start();
     if (!isset($_SESSION['usuario'])) {
         http_response_code(401);
         echo json_encode(['error' => 'No autorizado']);
         exit;
     }

     $data = json_decode(file_get_contents('php://input'), true);
     $id = (int) ($data['id'] ?? 0);
     $placa_nueva = strtoupper(trim($data['placa'] ?? ''));

     if ($id > 0 && !empty($placa_nueva)) {
         $pdo = getDBConnection();
         $stmt = $pdo->prepare("UPDATE bitacora_accesos SET placa = :placa WHERE id = :id");
         $stmt->execute([':placa' => $placa_nueva, ':id' => $id]);
         echo json_encode(['success' => true]);
     } else {
         http_response_code(400);
         echo json_encode(['error' => 'Datos inválidos']);
     }
     ```

---

## 3. Exportación de Reportes a CSV / Excel (RF13)
* **Archivos afectados:**
  - `views/bitacora.php` (Botón en interfaz)
  - `api/exportar_reporte.php` (Nuevo Endpoint Backend)
* **Estado actual:** Solo existe visualización paginada en HTML. No hay opción de descarga.
* **Código a implementar (Nativo en PHP, sin librerías pesadas):**
  1. **En `views/bitacora.php`:** Añadir botón `<a href="../api/exportar_reporte.php?q=..." class="btn btn-primary">📥 Exportar CSV</a>`.
  2. **En `api/exportar_reporte.php`:**
     ```php
     <?php
     require_once __DIR__ . '/../config/database.php';
     session_start();
     if (!isset($_SESSION['usuario'])) { exit('Acceso denegado'); }

     $pdo = getDBConnection();
     $search = trim($_GET['q'] ?? '');

     $sql = "SELECT id, placa, tipo_vehiculo_ia, confianza_ia, hora_ingreso, puerta_ingreso, hora_salida, puerta_salida, alerta_disparada FROM bitacora_accesos";
     $params = [];
     if ($search) {
         $sql .= " WHERE placa LIKE :search";
         $params[':search'] = "%{$search}%";
     }
     $sql .= " ORDER BY id DESC";
     $stmt = $pdo->prepare($sql);
     $stmt->execute($params);

     header('Content-Type: text/csv; charset=utf-8');
     header('Content-Disposition: attachment; filename=reporte_accesos_' . date('Ymd_His') . '.csv');

     $output = fopen('php://output', 'w');
     fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para Excel
     fputcsv($output, ['ID', 'Placa', 'Tipo Vehículo', 'Confianza IA (%)', 'Ingreso', 'Puerta Ingreso', 'Salida', 'Puerta Salida', 'Alerta']);

     while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
         fputcsv($output, [
             $row['id'],
             $row['placa'],
             $row['tipo_vehiculo_ia'],
             $row['confianza_ia'],
             $row['hora_ingreso'],
             $row['puerta_ingreso'],
             $row['hora_salida'] ?: 'Adentro',
             $row['puerta_salida'] ?: '-',
             $row['alerta_disparada'] ? 'ALERTA CRÍTICA' : 'NORMAL'
         ]);
     }
     fclose($output);
     exit;
     ```

---

## 4. Edición de Usuarios y Control de Estado (RF2)
* **Archivo afectado:** `views/usuarios.php`
* **Estado actual:** Solo permite registrar usuarios nuevos y eliminarlos. No tiene formulario de edición ni alternancia de estado activo/inactivo (`estado = 1/0`).
* **Código a implementar:**
  - Agregar botón "Editar" en la tabla de usuarios que cargue los datos en un formulario para actualizar contraseña o rol.
  - Implementar botón para alternar estado (`Activo` / `Inactivo`) mediante consulta:
    ```sql
    UPDATE usuarios SET estado = NOT estado WHERE id = :id;
    ```

---

## 5. Control de Acceso por Rol en Vistas Administrativas (RF1 & RF2)
* **Archivos afectados:**
  - `views/padron.php`
  - `views/camaras.php`
  - `views/usuarios.php`
* **Estado actual:** Si un Guardia de Garita (`id_rol = 2`) tipea la URL directa en el navegador, actualmente puede acceder.
* **Código a implementar:** Al inicio de cada archivo administrativo, antes de renderizar HTML:
  ```php
  if (!isset($_SESSION['id_rol']) || $_SESSION['id_rol'] != 1) {
      header('Location: dashboard.php?error=acceso_denegado');
      exit;
  }
  ```


---

## 6. Sistema de Alertas Visuales Diferenciadas por Color (RF10)
* **Archivos afectados:**
  - `api/registrar_acceso.php`
  - `api/ultimos_accesos.php`
  - `assets/js/dashboard.js`
  - `assets/css/styles.css`
* **Estado actual:** El sistema solo maneja una alerta binaria (`alerta_disparada = 1` o `0`) cuando la placa está en `LISTA_NEGRA`.
* **Especificación documental (Tabla 24):**
  - **Rojo:** Vehículo no empadronado en el padrón institucional.
  - **Naranja:** Vehículo con restricciones o estado `DENEGADO`.
  - **Negro:** Vehículo en `LISTA_NEGRA` (reportado como sospechoso / boletinado).
* **Código a implementar (Súper sencillo, menos de 15 líneas):**
  1. **En `api/registrar_acceso.php`:**
     ```php
     // Clasificación de alerta por niveles
     if (!$padron) {
         $alerta = 1; // 1 = ROJO (No Empadronado)
     } elseif ($padron['estado_acceso'] === 'DENEGADO') {
         $alerta = 2; // 2 = NARANJA (Restringido)
     } elseif ($padron['estado_acceso'] === 'LISTA_NEGRA') {
         $alerta = 3; // 3 = NEGRO (Boletinado)
     } else {
         $alerta = 0; // 0 = VERDE (Permitido)
     }
     ```
  2. **En `assets/js/dashboard.js` (Renderizado de insignias):**
     ```javascript
     let badgeHtml = '<span class="badge badge-green">OK</span>';
     if (row.alerta_disparada == 1) {
         badgeHtml = '<span class="badge badge-red">🚨 NO EMPADRONADO</span>';
         tr.classList.add('alert-red');
     } else if (row.alerta_disparada == 2) {
         badgeHtml = '<span class="badge badge-amber">⚠️ ACCESO DENEGADO</span>';
         tr.classList.add('alert-orange');
     } else if (row.alerta_disparada == 3) {
         badgeHtml = '<span class="badge badge-dark">⛔ LISTA NEGRA</span>';
         tr.classList.add('alert-black');
     }
     ```
  3. **En `assets/css/styles.css`:**
     ```css
     .badge-dark { background-color: #1a1a1a; color: #ff4d4d; border: 1px solid #ff4d4d; }
     .alert-black { background-color: rgba(0, 0, 0, 0.15) !important; animation: pulse-dark 1.5s infinite; }
     ```
