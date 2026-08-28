import os
import subprocess
import tempfile
from fastapi import FastAPI, UploadFile, File, Form
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from faster_whisper import WhisperModel

os.environ["HF_HUB_DISABLE_SYMLINKS_WARNING"] = "1"
os.environ["TRANSFORMERS_VERBOSITY"] = "error"

app = FastAPI(title="Servidor Whisper GPU")

# Habilitar CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

MODEL_SIZE = "large-v3"
print("🧠 Cargando modelo de Whisper en GPU...")
model = WhisperModel(MODEL_SIZE, device="cuda", compute_type="float16")
print("✅ Modelo cargado correctamente en GPU.")

def format_timestamp(seconds: float) -> str:
    hours = int(seconds // 3600)
    minutes = int((seconds % 3600) // 60)
    secs = int(seconds % 60)
    millis = int((seconds - int(seconds)) * 1000)
    return f"{hours:02}:{minutes:02}:{secs:02},{millis:03}"

@app.post("/transcribir/")
async def transcribir_video(
    file: UploadFile = File(...), 
    language: str = Form("es")
):
    with tempfile.NamedTemporaryFile(delete=False, suffix=".mp4") as temp_video:
        temp_video.write(await file.read())
        video_path = temp_video.name
        
    audio_path = video_path + ".wav"

    try:
        # Extraer audio con FFmpeg
        subprocess.run([
            "ffmpeg", "-y", "-i", video_path,
            "-vn", "-ac", "1", "-ar", "16000", "-f", "wav", audio_path
        ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

        # Transcribir
        segments, info = model.transcribe(audio_path, language=language)

        full_text = ""
        srt_content = ""
        srt_counter = 1

        for segment in segments:
            full_text += segment.text.strip() + " "
            start = format_timestamp(segment.start)
            end = format_timestamp(segment.end)
            srt_content += f"{srt_counter}\n{start} --> {end}\n{segment.text.strip()}\n\n"
            srt_counter += 1

        return JSONResponse(content={
            "texto_plano": full_text.strip(),
            "srt": srt_content.strip()
        })

    finally:
        if os.path.exists(video_path):
            os.remove(video_path)
        if os.path.exists(audio_path):
            os.remove(audio_path)