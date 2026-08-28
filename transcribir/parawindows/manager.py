import os
import subprocess
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
import uvicorn

app = FastAPI(title="Gestor GPU Whisper")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

whisper_process = None
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PYTHON_EXE = os.path.join(SCRIPT_DIR, "venv", "Scripts", "python.exe")

@app.post("/encender")
def encender():
    global whisper_process
    if whisper_process is None or whisper_process.poll() is not None:
        cmd = [PYTHON_EXE, "-m", "uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8000"]
        whisper_process = subprocess.Popen(cmd, cwd=SCRIPT_DIR)
        return {"status": "ok", "mensaje": "Iniciando Whisper en la GPU..."}
    return {"status": "ok", "mensaje": "El servidor ya está encendido."}

@app.post("/apagar")
def apagar():
    global whisper_process
    if whisper_process and whisper_process.poll() is None:
        subprocess.run(["taskkill", "/F", "/T", "/PID", str(whisper_process.pid)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        whisper_process = None
        return {"status": "ok", "mensaje": "Servidor detenido. Memoria VRAM liberada."}
    return {"status": "ok", "mensaje": "El servidor ya está apagado."}

@app.get("/estado")
def estado():
    global whisper_process
    activo = whisper_process is not None and whisper_process.poll() is None
    return {"activo": activo}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8001)