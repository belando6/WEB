#!/usr/bin/env python3
import os
import sys
import json
import glob
import logging # NUEVO: Importamos el sistema de logs nativo

# 1. Forzar variables de entorno de timeouts ANTES de cargar las librerías
os.environ["LITELLM_LOCAL_MODEL_TIMEOUT"] = "3600"
os.environ["HTTPX_TIMEOUT"] = "3600"
os.environ["REQUEST_TIMEOUT"] = "3600"
os.environ["ANONYMIZED_TELEMETRY"] = "False"

# 2. Detectar si el usuario pide modo verbose/debug (-v, -verbose o --verbose)
is_verbose = any(arg in sys.argv for arg in ["-v", "-verbose", "--verbose"])

if is_verbose:
    # Le decimos a LiteLLM que genere los logs
    os.environ["LITELLM_LOG"] = "DEBUG"
    
    # FORZAMOS a Python a imprimir todos los logs de depuración en la pantalla
    logging.basicConfig(
        level=logging.DEBUG,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
        handlers=[logging.StreamHandler(sys.stdout)]
    )
    
    # Opcional: También activamos los logs de la librería HTTP para ver el tráfico crudo de red
    logging.getLogger("httpx").setLevel(logging.DEBUG)
    logging.getLogger("httpcore").setLevel(logging.DEBUG)

from interpreter import interpreter

# 3. Configuración de conexión a tu servidor local
interpreter.llm.api_base = "http://172.31.252.15:1234/v1"
interpreter.llm.model = "openai/qwen3.8-27b@q2_k_xl"
interpreter.llm.api_key = "sk-lm-a4GXSDoO:MWvkm3Lp5GwfrF4pgbBu"
interpreter.llm.context_window = 27000
interpreter.llm.max_tokens = 8000

# Timeout nativo inyectado directamente en los argumentos de la API
interpreter.llm.api_kwargs = {"timeout": 3600.0}

# 4. Configuración de pantalla de Open Interpreter
interpreter.max_output = 10000

# Activar auto-ejecución (-y) y verbose en Open Interpreter
if "-y" in sys.argv:
    interpreter.auto_run = True

if is_verbose:
    # Le decimos al propio Open Interpreter que no oculte nada
    interpreter.verbose = True
    interpreter.debug = True

# 5. Cargar prompt de administrador de sistemas
try:
    with open('/var/www/html/WEB/documentos/sysadmin_prompt.txt', 'r') as f:
        interpreter.system_message = f.read()
except FileNotFoundError:
    print("Advertencia: No se encontró /var/www/html/WEB/documentos/sysadmin_prompt.txt")

# ==========================================
# GESTOR DE MÚLTIPLES HISTORIALES
# ==========================================
SESSIONS_DIR = "/root/interpreter_sessions"
os.makedirs(SESSIONS_DIR, exist_ok=True)

# Opción A: Listar los chats existentes
if "--list" in sys.argv:
    print("\n📂 Chats guardados disponibles:")
    archivos = glob.glob(os.path.join(SESSIONS_DIR, "*.json"))
    if not archivos:
        print("  (Ninguno todavía)")
    else:
        for archivo in archivos:
            nombre = os.path.basename(archivo).replace(".json", "")
            print(f"  - {nombre}")
    print("\nPara abrir uno, usa: ./pcmain.py --session nombre_del_chat\n")
    sys.exit(0)

# Opción B: Comprobar si se ha solicitado una sesión específica
session_name = None
HISTORY_FILE = None

if "--session" in sys.argv:
    try:
        idx = sys.argv.index("--session")
        session_name = sys.argv[idx + 1]
        HISTORY_FILE = os.path.join(SESSIONS_DIR, f"{session_name}.json")
        
        # Intentar cargar el chat si ya existe
        if os.path.exists(HISTORY_FILE):
            with open(HISTORY_FILE, 'r') as f:
                interpreter.messages = json.load(f)
            print(f"✅ Historial '{session_name}' cargado.")
        else:
            print(f"🆕 Iniciando un nuevo chat llamado: '{session_name}'")
            
    except IndexError:
        print("⚠️ Error: Debes proporcionar un nombre. Ejemplo: --session proyecto_nginx")
        sys.exit(1)
else:
    print("🆕 Iniciando chat sin historial (sesión efímera. No se guardará).")

# ==========================================

# 6. Lanzar la interfaz
modo_texto = "[MODO DEPURACIÓN EXTREMA]" if is_verbose else "[MODO NORMAL]"
print(f"Iniciando IA optimizada {modo_texto} (Timeout: 1 hora)...")

try:
    interpreter.chat()
except KeyboardInterrupt:
    pass
finally:
    # Solo guardamos si el usuario especificó una sesión
    if HISTORY_FILE and session_name:
        with open(HISTORY_FILE, 'w') as f:
            json.dump(interpreter.messages, f)
        print(f"\n💾 Chat '{session_name}' guardado correctamente.")
    else:
        print("\n🚫 Chat efímero finalizado. El historial no ha sido guardado.")
