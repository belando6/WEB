@echo off
title Gestor GPU Whisper (Puerto 8001)
color 0A
cd /d "C:\WhisperServer"
call venv\Scripts\activate
echo ===================================================
echo      GESTOR LIGERO EN EJECUCION
echo      Escuchando en el puerto 8001...
echo ===================================================
python manager.py
pause