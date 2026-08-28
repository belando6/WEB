<?php
/**
 * NEXUS File Manager — API Backend
 * Gestiona operaciones sobre /root/compartido/
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

const BASE_DIR = '/mnt/compartido/';
const MAX_UPLOAD_BYTES = 5368709120; // 5 GB

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function json_out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Resuelve una ruta relativa segura dentro de BASE_DIR */
function safe_path(string $rel): ?string {
    $rel = ltrim($rel, '/');
    if ($rel === '') return BASE_DIR;
    if (strpos($rel, '..') !== false) return null;
    return BASE_DIR . $rel;
}

/** Formatea bytes a cadena legible */
function fmt_bytes(int $b): string {
    $u = ['B','KB','MB','GB','TB'];
    for ($i=0; $b >= 1024 && $i < 4; $i++) { $b /= 1024; }
    return round($b, 1) . ' ' . $u[$i];
}

/** Extension segura para Content-Type */
function mime_for(string $ext): string {
    $map = [
        'html'=>'text/html','css'=>'text/css','js'=>'application/javascript',
        'json'=>'application/json','xml'=>'application/xml',
        'txt'=>'text/plain','md'=>'text/markdown','csv'=>'text/csv',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
        'gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml',
        'bmp'=>'image/bmp','ico'=>'image/x-icon',
        'pdf'=>'application/pdf','zip'=>'application/zip','rar'=>'application/vnd.rar',
        '7z'=>'application/x-7z-compressed','tar'=>'application/x-tar','gz'=>'application/gzip',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','flac'=>'audio/flac',
        'aac'=>'audio/aac','m4a'=>'audio/mp4',
        'mp4'=>'video/mp4','webm'=>'video/webm','mkv'=>'video/x-matroska',
        'avi'=>'video/x-msvideo','mov'=>'video/quicktime','wmv'=>'video/x-ms-wmv',
        'php'=>'text/plain','py'=>'text/x-python','sh'=>'application/x-sh',
        'yml'=>'text/yaml','yaml'=>'text/yaml','ini'=>'text/plain','conf'=>'text/plain',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'exe'=>'application/x-msdownload','msi'=>'application/x-msdownload',
        'nsp'=>'application/octet-stream','p12'=>'application/x-pkcs12',
    ];
    $ext = strtolower($ext);
    return $map[$ext] ?? 'application/octet-stream';
}

/** Icono emoji por tipo de archivo */
function icon_for(string $name): string {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg','bmp','ico'])) return '🖼️';
    if (in_array($ext, ['mp3','wav','ogg','flac','aac','m4a'])) return '🎵';
    if (in_array($ext, ['mp4','webm','mkv','avi','mov','wmv'])) return '🎬';
    if ($ext === 'pdf') return '📕';
    if (in_array($ext, ['zip','rar','7z','tar','gz'])) return '🗜️';
    if (in_array($ext, ['php','py','js','ts','html','css','sh','yml','yaml','json','xml','ini','conf'])) return '💻';
    if (in_array($ext, ['docx','xlsx','pptx'])) return '📄';
    if (in_array($ext, ['exe','msi','nsp','p12'])) return '⚙️';
    if ($ext === 'txt' || $ext === 'md') return '📝';
    return '📁';
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ─── LIST ────────────────────────────────────────────────
if ($action === 'list') {
    $relPath = $_GET['path'] ?? '';
    $dir = safe_path($relPath);
    if (!$dir || !is_dir($dir)) json_out(['error'=>'Ruta inválida'], 400);

    $items = [];
    $dh = opendir($dir);
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $full = $dir . '/' . $entry;
        $isDir = is_dir($full);
        $items[] = [
            'name'    => $entry,
            'type'    => $isDir ? 'dir' : 'file',
            'size'    => $isDir ? 0 : filesize($full),
            'modified'=> date('Y-m-d H:i:s', filemtime($full)),
            'icon'    => $isDir ? '📂' : icon_for($entry),
        ];
    }
    // Ordenar: carpetas primero, luego por nombre
    usort($items, fn($a,$b) => ($a['type']===$b['type'])
        ? strcasecmp($a['name'],$b['name'])
        : ($a['type']==='dir' ? -1 : 1));

    json_out(['ok'=>true,'path'=>$relPath,'items'=>$items]);
}

// ─── UPLOAD ──────────────────────────────────────────────
if ($action === 'upload' && $method === 'POST') {
    $targetRel = $_POST['path'] ?? '';
    $dir = safe_path($targetRel);
    if (!$dir) json_out(['error'=>'Ruta inválida'], 403);

    $results = [];
    $count = count($_FILES['files']['name']);
    for ($i = 0; $i < $count; $i++) {
        $origName = $_FILES['files']['name'][$i];
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            $results[] = ['file'=>$origName,'ok'=>false,'error'=>'Error de subida ('.$_FILES['files']['error'][$i].')'];
            continue;
        }
        if ($_FILES['files']['size'][$i] > MAX_UPLOAD_BYTES) {
            $results[] = ['file'=>$origName,'ok'=>false,'error'=>'Excede 5 GB'];
            continue;
        }
        $destPath = "$dir/$origName";
        if (file_exists($destPath)) {
            $base = pathinfo($origName, PATHINFO_FILENAME);
            $ext  = pathinfo($origName, PATHINFO_EXTENSION);
            $suffix = $ext ? "." . $ext : "";
            for ($j = 1; file_exists("$dir/$base($j)$suffix"); $j++) {}
            $destPath = "$dir/$base($j)$suffix";
        }
        if (file_put_contents($destPath, file_get_contents($_FILES['files']['tmp_name'][$i])) !== false) {
            $results[] = ['file'=>basename($destPath),'ok'=>true,'size'=>$_FILES['files']['size'][$i]];
        } else {
            $results[] = ['file'=>$origName,'ok'=>false,'error'=>'Fallo al mover'];
        }
    }
    json_out(['ok'=>true,'uploaded'=>$results]);
}

// ─── DOWNLOAD ────────────────────────────────────────────
if ($action === 'download') {
    $rel = $_GET['path'] ?? '';
    $full = safe_path($rel);
    if (!$full) json_out(['error'=>'Ruta inválida'], 403);
    // CIFS reparse=nfs: is_file falla, usamos fopen directamente
    if (@fopen($full, 'rb') === false) {
        $dir = dirname($full);
        $base = basename($full);
        $found = null;
        foreach (scandir($dir) as $entry) {
            if ($entry === "." || $entry === "..") continue;
            if (strpos($entry, $base) === 0) { $found = $entry; break; }
        }
        if (!$found) json_out(['error'=>'Archivo no encontrado'], 404);
        $full = $dir . "/" . $found;
    }

    header('Content-Type: ' . mime_for(pathinfo($full, PATHINFO_EXTENSION)));
    header('Content-Disposition: attachment; filename="' . basename($full) . '"');
    header('Content-Length: ' . filesize($full));
    readfile($full);
    exit;
}

// ─── DELETE ──────────────────────────────────────────────
if ($action === 'delete') {
    $rel = $_POST['path'] ?? '';
    $full = safe_path($rel);
    if (!$full || $full === BASE_DIR) json_out(['error'=>'Ruta inválida o protegida'], 403);

    if (is_dir($full)) {
        // Borrar recursivo
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getPathName()) : unlink($item->getPathName());
        }
        rmdir($full);
    } else {
        @unlink($full);
    }
    json_out(['ok'=>true,'deleted'=>$rel]);
}

// ─── MKDIR ───────────────────────────────────────────────
if ($action === 'mkdir') {
    $rel = $_POST['path'] ?? '';
    $full = safe_path($rel);
    if (!$full || file_exists($full)) json_out(['error'=>'Ya existe o ruta inválida'], 409);
    mkdir($full, 0770, true);
    chown($full, 'www-data');
    json_out(['ok'=>true,'created'=>$rel]);
}

// ─── RENAME ──────────────────────────────────────────────
if ($action === 'rename') {
    $oldRel = $_POST['path'] ?? '';
    $newName = basename($_POST['name'] ?? '');
    if (!$newName || preg_match('/[\/\\\\]/', $newName)) json_out(['error'=>'Nombre inválido'], 400);

    $fullOld = safe_path($oldRel);
    $parent  = dirname($fullOld);
    $fullNew = $parent . '/' . $newName;

    if (!$fullOld || !file_exists($fullOld) || file_exists($fullNew)) {
        json_out(['error'=>'Origen no existe o destino ocupado'], 409);
    }
    rename($fullOld, $fullNew);
    json_out(['ok'=>true,'renamed'=>$newName]);
}

json_out(['error'=>'Acción desconocida: '.$action], 400);
