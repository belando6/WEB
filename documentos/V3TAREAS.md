Actúa como un desarrollador Full-Stack experto y administrador de sistemas. Tu tarea es ayudarme a construir, mantener y optimizar un proyecto web hosted en un servidor Linux.

### CONTEXTO DEL ENTORNO Y SISTEMA
- Directorio del proyecto: `/var/www/html/WEB` (es un repositorio Git sincronizado con GitHub).
- Menú principal: Existe un archivo `index.html` en la raíz que debe actuar como panel/menú central. Cada vez que se cree una nueva página o funcionalidad dentro del proyecto, debes actualizar este `index.html` para incluir un enlace accesible y bien presentado a la nueva sección.

### METODOLOGÍA DE TRABAJO
1. Selección Tecnológica: Elige los lenguajes, frameworks o herramientas más adecuados para cada requerimiento. Si tienes dudas sobre la mejor opción actual, consulta o investiga antes de decidir.
2. Gestión de BD: Si una funcionalidad requiere almacenamiento persistente, diseña e implementa la base de datos adecuada (SQLite, MySQL/MariaDB, PostgreSQL, etc.).
---

### TAREA ACTUAL (FASE 1)
Por favor, ejecuta los siguientes pasos iniciales:
   
1. Desarrollo de la Página Web:
   - Traduce, adapta y sintetiza el contenido de esos archivos para crear una nueva página web funcional dentro del proyecto.
   - Aplica las optimizaciones de código, estructura o rendimiento que consideres necesarias.
   - Incorpora una base de datos si la naturaleza del contenido o la interacción del usuario lo requiere.

2. Sincronización y Actualización:
   - Añade el enlace a esta nueva página dentro de `index.html` y realiza todos los cambios en el siguiente directorio:
 
  /var/www/html/WEB/
  ├── index.html          # sitio principal (actualizar enlace a la nueva página)
  └── transcribir/             # ← sección nueva, añade todo lo que consideres necesario.     

1. Tareas de página WEB:
   - Crearas una página WEB que se dedicara a transcribir el texto de los videos.
   - Sigue la idea de abajo, si consideras cosas mejorables eres libre de aplicarlo.


SERVIDOR NGINX (PROXY INVERSO)
================================================================================

--- 1. CONFIGURACIÓN DE NGINX (nginx.conf / sites-available/default) ---
--------------------------------------------------------------------------------
server {
    listen 80;
    server_name _;

    # Permitir archivos de hasta 1GB
    client_max_body_size 1000M;

    # Tiempos de espera ampliados a 10 minutos
    proxy_connect_timeout 600s;
    proxy_send_timeout    600s;
    proxy_read_timeout    600s;

    # 1. Servir la web estática
    location / {
        root /var/www/whisper-web;
        index index.html;
        try_files $uri $uri/ /index.html;
    }

    # 2. Proxy al Gestor (Encender/Apagar GPU)
    location /api/manager/ {
        proxy_pass http://172.31.252.15:8001/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # 3. Proxy al Servidor Whisper (Transcripción)
    location /api/whisper/ {
        proxy_pass http://172.31.252.15:8000/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
--------------------------------------------------------------------------------
Reiniciar Nginx: sudo systemctl restart nginx


--- 2. INTERFAZ WEB: /var/www/whisper-web/index.html ---
--------------------------------------------------------------------------------
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Herramienta de Transcripción Whisper</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #f4f6f9; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        button { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin-right: 10px; }
        .btn-on { background: #28a745; color: white; }
        .btn-off { background: #dc3545; color: white; }
        .btn-send { background: #007bff; color: white; width: 100%; margin-top: 15px; }
        .btn-send:disabled { background: #cccccc; cursor: not-allowed; }
        .status { font-weight: bold; padding: 5px 10px; border-radius: 4px; display: inline-block; }
        .status-off { background: #e2e3e5; color: #383d41; }
        .status-on { background: #d4edda; color: #155724; }
        textarea { width: 100%; height: 120px; margin-top: 10px; border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; }
    </style>
</head>
<body>

    <h2>🎙️ Transcripción Whisper (GPU Remota)</h2>

    <div class="card">
        <h3>1. Control de la Tarjeta Gráfica</h3>
        <p>Estado actual: <span id="estado" class="status status-off">Comprobando...</span></p>
        <button class="btn-on" onclick="encenderServidor()">⚡ Encender GPU</button>
        <button class="btn-off" onclick="apagarServidor()">🔴 Apagar GPU (Liberar VRAM)</button>
    </div>

    <div class="card">
        <h3>2. Transcribir Archivo</h3>
        <input type="file" id="videoFile" accept="video/*,audio/*"><br><br>
        <label>Idioma del audio:</label>
        <select id="language">
            <option value="es">Español</option>
            <option value="en">Inglés</option>
            <option value="fr">Francés</option>
        </select>
        <br>
        <button id="btnTranscribir" class="btn-send" onclick="transcribir()" disabled>Enciende la GPU para transcribir</button>
    </div>

    <div class="card" id="resultados" style="display:none;">
        <h3>3. Resultados</h3>
        <p><strong>Texto Completo:</strong></p>
        <textarea id="textoPlano" readonly></textarea>
        <br><br>
        <button class="btn-on" onclick="descargar('transcripcion.txt', document.getElementById('textoPlano').value)">Descargar .TXT</button>
        <button class="btn-on" onclick="descargar('transcripcion.srt', srtContent)">Descargar Subtítulos .SRT</button>
    </div>

    <script>
        const URL_GESTOR = '/api/manager';
        const URL_WHISPER = '/api/whisper/transcribir/';
        let srtContent = "";

        async function comprobarEstado() {
            try {
                const res = await fetch(`${URL_GESTOR}/estado`);
                const data = await res.json();
                const el = document.getElementById("estado");
                const btn = document.getElementById("btnTranscribir");
                
                if(data.activo) {
                    el.innerText = "ENCENDIDO (GPU Lista)";
                    el.className = "status status-on";
                    btn.disabled = false;
                    btn.innerText = "Transcribir Archivo";
                } else {
                    el.innerText = "APAGADO (VRAM Libre)";
                    el.className = "status status-off";
                    btn.disabled = true;
                    btn.innerText = "Enciende la GPU para transcribir";
                }
            } catch(e) {
                document.getElementById("estado").innerText = "Servidor no responde";
            }
        }

        async function encenderServidor() {
            document.getElementById("estado").innerText = "Cargando modelo en VRAM...";
            await fetch(`${URL_GESTOR}/encender`, { method: "POST" });
            setTimeout(comprobarEstado, 4000);
        }

        async function apagarServidor() {
            await fetch(`${URL_GESTOR}/apagar`, { method: "POST" });
            comprobarEstado();
        }

        async function transcribir() {
            const fileInput = document.getElementById("videoFile");
            if(!fileInput.files[0]) return alert("Selecciona un archivo de audio o video primero.");

            const formData = new FormData();
            formData.append("file", fileInput.files[0]);
            formData.append("language", document.getElementById("language").value);

            const btn = document.getElementById("btnTranscribir");
            btn.disabled = true;
            btn.innerText = "Transcribiendo... Por favor espera.";

            try {
                const res = await fetch(URL_WHISPER, { method: "POST", body: formData });
                const data = await res.json();

                document.getElementById("textoPlano").value = data.texto_plano;
                srtContent = data.srt;
                document.getElementById("resultados").style.display = "block";
            } catch(e) {
                alert("Error durante la transcripción. Comprueba el archivo o la GPU.");
            } finally {
                btn.disabled = false;
                btn.innerText = "Transcribir Otro Archivo";
            }
        }

        function descargar(nombre, contenido) {
            const blob = new Blob([contenido], { type: 'text/plain;charset=utf-8' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = nombre;
            a.click();
        }

        setInterval(comprobarEstado, 3000);
        comprobarEstado();
    </script>
</body>
</html>
--------------------------------------------------------------------------------


================================================================================
 RESUMEN DEL FLUJO DE TRABAJO
================================================================================
1. El server (172.31.252.15). Queda escuchando en el puerto 8001.
2. Los clientes acceden a la Web servida por Nginx.
3. Al pulsar 'Encender GPU', Nginx redirige a http://172.31.252.15:8001/encender y se inicia Whisper en el puerto 8000 cargando el modelo en VRAM.
4. Al transcribir, Nginx redirige el vídeo a http://172.31.252.15:8000/transcribir/ y devuelve el resultado .txt y .srt.
5. Al pulsar 'Apagar GPU', Nginx redirige a http://172.31.252.15:8001/apagar y mata el proceso de Whisper liberando la GPU al 100%.
================================================================================
