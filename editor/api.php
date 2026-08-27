<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$base = __DIR__;
$uploadsDir = "$base/uploads";
$outputDir = "$base/output";
$dbFile = "$base/library.db";

// Inicializar SQLite
function getDB() {
    global $dbFile;
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL,
        type TEXT DEFAULT 'unknown',
        size_bytes INTEGER DEFAULT 0,
        duration_sec REAL DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now')),
        status TEXT DEFAULT 'ready'
    )");
    return $pdo;
}

function jsonOut($data) { echo json_encode($data); exit; }

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'upload':
        if (empty($_FILES)) { http_response_code(400); jsonOut(['error'=>'No files']); }
        $results = [];
        foreach ($_FILES as $key => $file) {
            if ($file['error'] !== 0) continue;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4','webm','mkv','avi','mov','mp3','wav','ogg','flac','aac','jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) { jsonOut(['error'=>"Extensión no permitida: .$ext"]); }
            $newName = uniqid('up_') . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], "$uploadsDir/$newName")) {
                $results[] = ['name'=>$newName, 'original'=>$_FILES[$key]['name'] ?? $file['name'], 'size'=>$file['size']];
            }
        }
        jsonOut(['ok'=>true,'files'=>$results]);
        break;

    case 'combine':
        // Combinar audio + video/imagen → mp4
        $videoFile = $_POST['video'] ?? '';
        $audioFile = $_POST['audio'] ?? '';
        if (!$videoFile || !$audioFile) { http_response_code(400); jsonOut(['error'=>'Faltan archivos']); }
        $vPath = "$uploadsDir/$videoFile";
        $aPath = "$uploadsDir/$audioFile";
        if (!file_exists($vPath) || !file_exists($aPath)) { http_response_code(404); jsonOut(['error'=>'Archivo no encontrado']); }

        // Detectar si es imagen (sin duración) → usar loop o 10s por defecto
        $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $vPath);
        $outName = 'combined_' . time() . '.mp4';
        $outPath = "$outputDir/$outName";

        if ($isImage) {
            // Imagen: generar video de 10s (o duración del audio)
            $cmd = "ffmpeg -y -loop 1 -i '$vPath' -i '$aPath' -c:v libx264 -preset fast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k -shortest '$outPath' 2>&1";
        } else {
            // Video: combinar con audio (usar duración del más corto)
            $cmd = "ffmpeg -y -i '$vPath' -i '$aPath' -c:v copy -c:a aac -b:a 192k -shortest '$outPath' 2>&1";
        }

        exec($cmd, $output, $ret);
        if ($ret !== 0 || !file_exists($outPath)) {
            jsonOut(['error'=>'FFmpeg error', 'log'=>implode("\n", array_slice($output,-5))]);
        }
        // Registrar en BD
        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO media (filename, type, size_bytes) VALUES (?,?,?)");
        $stmt->execute([$outName, 'combined', filesize($outPath)]);
        jsonOut(['ok'=>true,'file'=>$outName,'url'=>"output/$outName"]);
        break;

    case 'trim_audio':
        // Recortar audio: start_sec, end_sec (o duration)
        $audioFile = $_POST['audio'] ?? '';
        $start = floatval($_POST['start'] ?? 0);
        $end = floatval($_POST['end'] ?? 0);
        if (!$audioFile) { http_response_code(400); jsonOut(['error'=>'Falta audio']); }
        $aPath = "$uploadsDir/$audioFile";
        if (!file_exists($aPath)) { http_response_code(404); jsonOut(['error'=>'No encontrado']); }

        $outName = 'trimmed_' . time() . '.mp3';
        $outPath = "$outputDir/$outName";
        $cmd = "ffmpeg -y -ss $start -i '$aPath' -t " . ($end > 0 ? ($end - $start) : '') . " -c:a libmp3lame -b:a 192k '$outPath' 2>&1";
        exec($cmd, $output, $ret);
        if ($ret !== 0 || !file_exists($outPath)) { jsonOut(['error'=>'FFmpeg error']); }

        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO media (filename, type, size_bytes) VALUES (?,?,?)");
        $stmt->execute([$outName, 'trimmed', filesize($outPath)]);
        jsonOut(['ok'=>true,'file'=>$outName,'url'=>"output/$outName"]);
        break;

    case 'remove_audio':
        // Quitar audio de un video
        $videoFile = $_POST['video'] ?? '';
        if (!$videoFile) { http_response_code(400); jsonOut(['error'=>'Falta video']); }
        $vPath = "$uploadsDir/$videoFile";
        if (!file_exists($vPath)) { http_response_code(404); jsonOut(['error'=>'No encontrado']); }

        $outName = 'noaudio_' . time() . '.mp4';
        $outPath = "$outputDir/$outName";
        $cmd = "ffmpeg -y -i '$vPath' -an -c:v copy '$outPath' 2>&1";
        exec($cmd, $output, $ret);
        if ($ret !== 0 || !file_exists($outPath)) { jsonOut(['error'=>'FFmpeg error']); }

        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO media (filename, type, size_bytes) VALUES (?,?,?)");
        $stmt->execute([$outName, 'no_audio', filesize($outPath)]);
        jsonOut(['ok'=>true,'file'=>$outName,'url'=>"output/$outName"]);
        break;

    case 'replace_audio':
        // Reemplazar audio de un video con otro track
        $videoFile = $_POST['video'] ?? '';
        $newAudio = $_POST['audio'] ?? '';
        if (!$videoFile || !$newAudio) { http_response_code(400); jsonOut(['error'=>'Faltan archivos']); }
        $vPath = "$uploadsDir/$videoFile";
        $aPath = "$uploadsDir/$newAudio";
        if (!file_exists($vPath) || !file_exists($aPath)) { http_response_code(404); jsonOut(['error'=>'No encontrado']); }

        $outName = 'replaced_' . time() . '.mp4';
        $outPath = "$outputDir/$outName";
        $cmd = "ffmpeg -y -i '$vPath' -i '$aPath' -map 0:v -map 1:a -c:v copy -c:a aac -b:a 192k -shortest '$outPath' 2>&1";
        exec($cmd, $output, $ret);
        if ($ret !== 0 || !file_exists($outPath)) { jsonOut(['error'=>'FFmpeg error']); }

        $pdo = getDB();
        $stmt = $pdo->prepare("INSERT INTO media (filename, type, size_bytes) VALUES (?,?,?)");
        $stmt->execute([$outName, 'replaced', filesize($outPath)]);
        jsonOut(['ok'=>true,'file'=>$outName,'url'=>"output/$outName"]);
        break;

    case 'library':
        $pdo = getDB();
        $rows = $pdo->query("SELECT * FROM media ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        jsonOut(['ok'=>true,'items'=>$rows]);
        break;

    case 'delete':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) { http_response_code(400); jsonOut(['error'=>'ID requerido']); }
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT filename FROM media WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            @unlink("$outputDir/{$row['filename']}");
            $pdo->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
        }
        jsonOut(['ok'=>true]);
        break;

    case 'probe':
        // Obtener metadatos de un archivo (duración, streams)
        $file = $_GET['file'] ?? '';
        if (!$file) { http_response_code(400); jsonOut(['error'=>'Falta file']); }
        $path = "$uploadsDir/$file";
        if (!file_exists($path)) { http_response_code(404); jsonOut(['error'=>'No encontrado']); }
        $cmd = "ffprobe -v quiet -print_format json -show_format -show_streams '$path' 2>&1";
        exec($cmd, $output, $ret);
        $json = implode("\n", $output);
        if ($ret !== 0) { jsonOut(['error'=>'ffprobe falló']); }
        jsonOut(json_decode($json));
        break;

    default:
        http_response_code(400);
        jsonOut(['error'=>"Acción desconocida: $action"]);
}
