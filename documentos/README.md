# WEB

Proyecto web en `/var/www/html/WEB`, sincronizado con GitHub.

## Configuración realizada (24-08-2026)

### 1. Credenciales persistentes de Git
- Email: belando.v2@gmail.com
- Usuario: belando6
- Metodo:git config --global credential.helper store
- Archivo:~/.git-credentials (permisos 600)
- Resultado: cualquier shell nueva puede hacer push/pull sin pedir password.

### 2. Repositorio local
- Ruta: `/var/www/html/WEB
- Branch principal: main
- Comando usado:git init -b main

### 3. Sincronizacion remota (GitHub)
- Repo remoto: https://github.com/belando6/WEB.git
- Remoto configurado como: origin
- Push inicial ejecutado con este README.

## Comandos de uso diario

    cd /var/www/html/WEB
    git add -A
    git commit -m "mensaje"
    git push origin main

    # Para actualizar desde remoto:
    git pull origin main

## Borrar todo y sincronizar exactamente con GitHub
    
 1. Descarga la información más reciente de GitHub
git fetch origin

 2. Fuerza tu rama local a coincidir exactamente con GitHub
git reset --hard origin/main

 3. Elimina archivos y carpetas nuevos que no estén en el repositorio
git clean -fd

## Iniciar IA
cd /var/www/html/WEB/documentos/
./pcmain.py -y
./pcmain.py -y --verbose --session correciones6

## Notas
- El token personal se almacena en ~/.git-credentials (solo lectura del owner).
- Si se rota el token, editar ese archivo o re-ejecutar:
      echo "https://belando6:TOKEN_NUEVO@github.com" > ~/.git-credentials && chmod 600 ~/.git-credentials
