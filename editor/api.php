<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
error_reporting(E_ALL); ini_set('display_errors', 0);

$UPLOADS = __DIR__ . '/uploads';
$OUTPUT  = __DIR__ . '/output';
$DB      = new PDO("sqlite:" . __DIR__ . "/library.db");
$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$DB->exec("CREATE TABLE IF NOT EXISTS media (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, filename TEXT, size_bytes INTEGER, created_at TEXT DEFAULT (datetime('now')))");

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// --- UPLOAD ---
function cleanupUploads(array $files) {
    global $UPLOADS;
    foreach ($files as $f) {
        if ($f && is_file("$UPLOADS/$f")) @unlink("$UPLOADS/$f");
    }
}

if ($action === 'upload') {
    $key = $_POST['key'] ?? '';
    if (!$key || !isset($_FILES[$key])) { echo json_encode(['ok'=>false,'error'=>'Falta archivo']); exit; }
    $f = $_FILES[$key];
    if ($f['error'] !== 0) { echo json_encode(['ok'=>false,'error'=>"Upload error: ".$f['error']]); exit; }
    $safeName = uniqid('up_') . '_' . basename($f['name']);
    move_uploaded_file($f['tmp_name'], "$UPLOADS/$safeName");
    echo json_encode(['ok'=>true,'files'=>[['name'=>$safeName]]]);
    exit;
}

// --- COMBINE (audio + imagen/video → mp4) ---
if ($action === 'combine') {
    $v = $_POST['video'] ?? ''; $a = $_POST['audio'] ?? '';
    if (!$v || !$a) { echo json_encode(['ok'=>false,'error'=>'Faltan archivos']); exit; }
    $userOut = trim($_POST['outName'] ?? '');
    if ($userOut) { $safe = preg_replace('/[^a-zA-Z0-9_\-.]/','',$userOut); $outName = substr($safe,0,150).'.mp4'; } else { $outName = 'combined_' . time() . '.mp4'; }
    $cmd = "ffmpeg -y -i '$OUTPUT/../uploads/$a' -i '$UPLOADS/$v' -c:v libx264 -preset fast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k -shortest '$OUTPUT/$outName' 2>&1";
    // Detectar si es imagen (sin stream de video real) → usar loop
    $probe = shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '$UPLOADS/$v' 2>/dev/null");
    $audioDur = floatval(shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '$OUTPUT/../uploads/$a' 2>/dev/null"));
    if ($probe === '' || trim($probe) === '0' || trim($probe) === 'N/A') {
        // Es imagen: generar video de la duración del audio
        $vw = shell_exec("ffprobe -v error -select_streams v:0 -show_entries stream=width -of default=noprint_wrappers=1:nokey=1 '$UPLOADS/$v' 2>/dev/null");
    $vh = shell_exec("ffprobe -v error -select_streams v:0 -show_entries stream=height -of default=noprint_wrappers=1:nokey=1 '$UPLOADS/$v' 2>/dev/null");
    $vw = (int)$vw; $vh = (int)$vh;
    if ($vw > 0 && $vh > 0) { $vw -= $vw % 2; $vh -= $vh % 2; }
    $cmd = "ffmpeg -y -loop 1 -framerate 30 -i '$UPLOADS/$v' -i '$OUTPUT/../uploads/$a' -vf \"scale=$vw:$vh,format=yuv420p\" -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 192k -t $audioDur '$OUTPUT/$outName' 2>&1";
    }
    $result = shell_exec($cmd);
    cleanupUploads([$v,$a]);
    if (file_exists("$OUTPUT/$outName")) {
        $size = filesize("$OUTPUT/$outName");
        $DB->prepare("INSERT INTO media(type,filename,size_bytes) VALUES('combined',?,?)")->execute([$outName,$size]);
        echo json_encode(['ok'=>true,'url'=>'output/'.$outName,'file'=>$outName]);
    } else {
        echo json_encode(['ok'=>false,'error'=>'FFmpeg falló: '.substr($result,-300)]);
    }
    exit;
}

// --- TRIM AUDIO ---
if ($action === 'trim_audio') {
    $a = $_POST['audio'] ?? ''; $s = floatval($_POST['start'] ?? 0); $e = floatval($_POST['end'] ?? 30);
    if (!$a) { echo json_encode(['ok'=>false,'error'=>'Falta audio']); exit; }
    $userOut = trim($_POST['outName'] ?? '');
    if ($userOut) { $safe = preg_replace('/[^a-zA-Z0-9_\-.]/','',$userOut); $outName = substr($safe,0,150).'.mp3'; } else { $outName = 'trimmed_' . time() . '.mp3'; }
    $cmd = "ffmpeg -y -ss $s -t " . ($e-$s) . " -i '$UPLOADS/$a' -c:a libmp3lame -b:a 192k '$OUTPUT/$outName' 2>&1";
    shell_exec($cmd);
    cleanupUploads([$a]);
    if (file_exists("$OUTPUT/$outName")) {
        $size = filesize("$OUTPUT/$outName");
        $DB->prepare("INSERT INTO media(type,filename,size_bytes) VALUES('trimmed',?,?)")->execute([$outName,$size]);
        echo json_encode(['ok'=>true,'url'=>'output/'.$outName,'file'=>$outName]);
    } else { echo json_encode(['ok'=>false,'error'=>'FFmpeg trim falló']); }
    exit;
}

// --- REMOVE AUDIO ---
if ($action === 'remove_audio') {
    $v = $_POST['video'] ?? '';
    if (!$v) { echo json_encode(['ok'=>false,'error'=>'Falta video']); exit; }
    $userOut = trim($_POST['outName'] ?? '');
    if ($userOut) { $safe = preg_replace('/[^a-zA-Z0-9_\-.]/','',$userOut); $outName = substr($safe,0,150).'.mp4'; } else { $outName = 'noaudio_' . time() . '.mp4'; }
    shell_exec("ffmpeg -y -i '$UPLOADS/$v' -an -c:v copy '$OUTPUT/$outName' 2>&1");
    cleanupUploads([$v]);
    if (file_exists("$OUTPUT/$outName")) {
        $size = filesize("$OUTPUT/$outName");
        $DB->prepare("INSERT INTO media(type,filename,size_bytes) VALUES('no_audio',?,?)")->execute([$outName,$size]);
        echo json_encode(['ok'=>true,'url'=>'output/'.$outName,'file'=>$outName]);
    } else { echo json_encode(['ok'=>false,'error'=>'FFmpeg remove falló']); }
    exit;
}

// --- REPLACE AUDIO ---
if ($action === 'replace_audio') {
    $v = $_POST['video'] ?? ''; $a = $_POST['audio'] ?? '';
    if (!$v || !$a) { echo json_encode(['ok'=>false,'error'=>'Faltan archivos']); exit; }
    $userOut = trim($_POST['outName'] ?? '');
    if ($userOut) { $safe = preg_replace('/[^a-zA-Z0-9_\-.]/','',$userOut); $outName = substr($safe,0,150).'.mp4'; } else { $outName = 'replaced_' . time() . '.mp4'; }
    shell_exec("ffmpeg -y -i '$UPLOADS/$v' -i '$UPLOADS/$a' -map 0:v -map 1:a -c:v copy -c:a aac -b:a 192k -shortest '$OUTPUT/$outName' 2>&1");
    cleanupUploads([$v,$a]);
    if (file_exists("$OUTPUT/$outName")) {
        $size = filesize("$OUTPUT/$outName");
        $DB->prepare("INSERT INTO media(type,filename,size_bytes) VALUES('replaced',?,?)")->execute([$outName,$size]);
        echo json_encode(['ok'=>true,'url'=>'output/'.$outName,'file'=>$outName]);
    } else { echo json_encode(['ok'=>false,'error'=>'FFmpeg replace falló']); }
    exit;
}

// --- LIBRARY ---
if ($action === 'delete') {
    $file = $_POST['file'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9_]+\.(mp4|mp3)$/', $file)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Nombre inválido']); exit; }
    $path = "$OUTPUT/$file";
    if (is_file($path)) @unlink($path);
    $st = $DB->prepare("DELETE FROM media WHERE filename=?");
    $st->execute([$file]);
    echo json_encode(['ok'=>true,'deleted'=>$file]); exit;
}

if ($action === 'library') {
    $rows = $DB->query("SELECT type,filename,size_bytes,created_at FROM media ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'items'=>$rows]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Acción desconocida: '.$action]);
