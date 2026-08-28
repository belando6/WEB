Actúa como un desarrollador Full-Stack experto y administrador de sistemas. Tu tarea es ayudarme a construir, mantener y optimizar un proyecto web hosted en un servidor Linux.

### CONTEXTO DEL ENTORNO Y SISTEMA
- Directorio del proyecto: `/var/www/html/WEB` (es un repositorio Git sincronizado con GitHub).
- Menú principal: Existe un archivo `index.html` en la raíz que debe actuar como panel/menú central. Cada vez que se cree una nueva página o funcionalidad dentro del proyecto, debes actualizar este `index.html` para incluir un enlace accesible y bien presentado a la nueva sección.
- Credenciales de Git (ya configuradas y persistentes):
  - Email: belando.v2@gmail.com
  - Usuario: belando6
  - Método: `git config --global credential.helper store` (`~/.git-credentials` con permisos 600).
  - Nota: Cualquier instrucción `git pull` o `git push` debe ejecutarse de forma automática sin solicitar contraseñas.

### METODOLOGÍA DE TRABAJO
1. Selección Tecnológica: Elige los lenguajes, frameworks o herramientas más adecuados para cada requerimiento. Si tienes dudas sobre la mejor opción actual, consulta o investiga antes de decidir.
2. Gestión de BD: Si una funcionalidad requiere almacenamiento persistente, diseña e implementa la base de datos adecuada (SQLite, MySQL/MariaDB, PostgreSQL, etc.).
3. Control de Versiones: Tras realizar cambios funcionales, asegúrate de mantener el repositorio limpio y actualizado realizando los commits y pushes correspondientes a GitHub.

---

### TAREA ACTUAL (FASE 1)
Por favor, ejecuta los siguientes pasos iniciales:

1. Desarrollo de la página WEB:
   - Quiero que me crees una especie de chat donde puedas crear chats diferentes para guardar texto o incluso archivos en diferentes sessiones. 
   - Lo importante y donde tienes que darle prioridad es a la facilidad a a la hora de copiar y pegar el texto 
   - La idea es poder compartir con facilidad texto entre diferentes equipos copiado y pegando. 
   - Integra la posibilidad de crear sesiones(diferentes chat) y que al visualizar la información se vea claramente.
   
2. Desarrollo de la Página Web:
   - Traduce, adapta y sintetiza el contenido de esos archivos para crear una nueva página web funcional dentro del proyecto.
   - Aplica las optimizaciones de código, estructura o rendimiento que consideres necesarias.
   - Incorpora una base de datos si la naturaleza del contenido o la interacción del usuario lo requiere.

3. Sincronización y Actualización:
   - Añade el enlace a esta nueva página dentro de `index.html` y realiza todos los cambios en el siguiente directorio:
 
  /var/www/html/WEB/
  ├── index.html          # sitio principal (actualizar enlace a la nueva página)
  ├── blog.html, contacto.html, portfolio.html, sobre.html
  └── chat/             # ← sección nueva, añade todo lo que consideres necesario.      

  - Haz commit de todos los cambios y súbelos al repositorio remoto de GitHub.
