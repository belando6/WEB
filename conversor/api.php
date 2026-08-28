<?php
// NEXUS Conversor API v1.0
header('Content-Type: application/json; charset=utf-8');

$uploadDir = __DIR__ . '/uploads';
$dbFile    = __DIR__ . '/conversor.db';

// --- SQLite: historial de conversiones ---
function db_init() {
    global $dbFile;
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversiones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT, origen TEXT, destino TEXT, fecha TEXT, exito INTEGER, error TEXT
    )");;
    return $pdo;
}

function db_log($ip,$origen,$destino,$exito,$error='') {
    global $dbFile;
    $pdo = new PDO("sqlite:$dbFile");
    $st  = $pdo->prepare("INSERT INTO conversiones (ip,origen,destino,fecha,exito,error) VALUES (?,?,?,?,?,?)");
    $st->execute([$ip,$origen,$destino,date('Y-m-d H:i:s'),$exito?1:0,$error]);
}

// --- Validación de extensiones permitidas ---
function ext_ok($f){
    $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    return in_array($e, ['txt','md','csv','html','htm','doc','docx','odt','rtf','xls','xlsx','ods','ppt','pptx','odp',
        'jpg','jpeg','png','gif','webp','bmp','tif','tiff','ico','svg','pdf',
        'mp3','wav','ogg','flac','m4a','aac','wma','opus',
        'mp4','avi','mkv','mov','wmv','flv','webm','ts','m4v']);
}

// --- Mapa de conversiones soportadas (origen => [destinos]) ---
function mapa() {
    return [
        // Documentos → PDF / otros formatos
        'txt'  => ['pdf','docx','odt','html'],
        'md'   => ['pdf','docx','odt','html'],
        'csv'  => ['xlsx','ods','pdf','html'],
        'html' => ['pdf','docx','odt','txt'],
        'htm'  => ['pdf','docx','odt','txt'],
        'rtf'  => ['pdf','docx','odt','txt'],
        'doc'  => ['pdf','docx','odt','txt','html'],
        'docx' => ['pdf','odt','txt','html','rtf'],
        'odt'  => ['pdf','docx','txt','html'],
        'xls'  => ['xlsx','csv','ods','pdf'],
        'xlsx' => ['csv','ods','pdf','xls'],
        'ods'  => ['xlsx','csv','pdf'],
        'ppt'  => ['pdf','png'],
        'pptx' => ['pdf','png'],
        'odp'  => ['pdf','png'],

        // Imágenes ↔
        'jpg'  => ['jpeg','png','webp','bmp','gif','tiff','ico','svg','pdf'],
        'jpeg' => ['jpg','png','webp','bmp','gif','tiff','ico','svg','pdf'],
        'png'  => ['jpg','jpeg','webp','bmp','gif','tiff','ico','svg','pdf'],
        'gif'  => ['mp4','webm','png','jpg','apng'],
        'webp' => ['png','jpg','jpeg','gif','bmp'],
        'bmp'  => ['png','jpg','jpeg','webp','gif'],
        'tif'  => ['png','jpg','jpeg','webp','tiff'],
        'tiff' => ['png','jpg','jpeg','webp','tif'],
        'ico'  => ['png','jpg','svg'],
        'svg'  => ['png','jpg','jpeg','gif','bmp','pdf'],

        // PDF → Imagen / Texto
        'pdf'  => ['png','jpg','txt','docx'],

        // Audio
        'mp3'  => ['wav','ogg','flac','m4a','aac','opus','wma'],
        'wav'  => ['mp3','ogg','flac','m4a','aac','opus'],
        'ogg'  => ['mp3','wav','flac','m4a','aac','opus'],
        'flac' => ['mp3','wav','ogg','m4a','aac'],
        'm4a'  => ['mp3','wav','ogg','flac','aac'],
        'aac'  => ['mp3','wav','m4a','ogg'],
        'wma'  => ['mp3','wav','m4a','ogg'],
        'opus' => ['mp3','wav','ogg','flac','m4a'],

        // Video
        'mp4'  => ['avi','mkv','mov','webm','wmv','flv','ts','gif','mp3','wav','m4a'],
        'avi'  => ['mp4','mkv','mov','webm','wmv','flv'],
        'mkv'  => ['mp4','avi','mov','webm','mp3','wav'],
        'mov'  => ['mp4','avi','mkv','webm','gif'],
        'wmv'  => ['mp4','avi','mkv','mov'],
        'flv'  => ['mp4','avi','mkv','mov','webm'],
        'ts'   => ['mp4','mkv','avi','mov'],
        'm4v'  => ['mp4','avi','mkv','mov'],
    ];
}

// --- Ejecutar conversión ---
function convertir($src, $dstExt) {
    global $uploadDir;
    $origen = pathinfo($src, PATHINFO_EXTENSION);
    $base   = pathinfo($src, PATHINFO_FILENAME);
    $out    = "$uploadDir/$base.$dstExt";

    // Si ya existe, añadir sufijo
    if (file_exists($out)) {
        $i = 1;
        while (file_exists("$uploadDir/${base}_$i.$dstExt")) $i++;
        $out = "$uploadDir/${base}_$i.$dstExt";
    }

    // --- LibreOffice: documentos ofimáticos ---
    if (in_array($origen, ['txt','md','csv','html','htm','rtf','doc','docx','odt','xls','xlsx','ods','ppt','pptx','odp'])) {
        $cmd = "libreoffice --headless --convert-to $dstExt --outdir '$uploadDir' '$src' 2>&1";
    }
    // --- FFmpeg: audio y video ---
    elseif (in_array($origen, ['mp3','wav','ogg','flac','m4a','aac','wma','opus',
                                'mp4','avi','mkv','mov','wmv','flv','ts','m4v','gif'])) {
        // GIF → video: usar loop para evitar parpadeo
        if ($origen === 'gif' && in_array($dstExt, ['mp4','webm','avi','mkv','mov'])) {
            $cmd = "ffmpeg -y -loop 1 -i '$src' -c:v libx264 -t 5 '$out' 2>&1";
        } else {
            $cmd = "ffmpeg -y -i '$src' '$out' 2>&1";
        }
    }
    // --- ImageMagick: imágenes y PDF→imagen ---
    elseif (in_array($origen, ['jpg','jpeg','png','gif','webp','bmp','tif','tiff','ico','svg']) || $origen === 'pdf') {
        if ($dstExt === 'pdf' && in_array($origen, ['jpg','jpeg','png','webp','bmp','tif','tiff'])) {
            // Imagen → PDF (una sola página)
            $cmd = "convert '$src' -background white '$out' 2>&1";
        } elseif ($dstExt === 'png' || $dstExt === 'jpg') {
            if ($origen === 'pdf') {
                // PDF → Imagen: todas las páginas, densidad 150 DPI
                $cmd = "convert -density 150 '$src'[0] '$out' 2>&1";
            } else {
                $cmd = "convert '$src' '$out' 2>&1";
            }
        } elseif ($dstExt === 'svg') {
            $cmd = "convert '$src' -background none '$out' 2>&1";
        } else {
            $cmd = "convert '$src' '$out' 2>&1";
        }
    }
    // --- Fallback: no soportado ---
    else {
        return ['ok'=>false,'error'=>"Conversión no soportada: $origen → $dstExt"];
    }

    exec($cmd, $output, $rc);
    if ($rc !== 0) {
        // LibreOffice a veces devuelve rc=0 pero el archivo tiene otro nombre
        return ['ok'=>false,'error'=>implode("\n",array_slice($output,-5))];
    }

    // Verificar que se generó el archivo (LibreOffice puede renombrar)
    if (!file_exists($out)) {
        // Buscar en uploads cualquier archivo nuevo con la extensión destino
        $files = glob("$uploadDir/*.$dstExt");
        if ($files && count($files) > 0) {
            // Tomar el más reciente
            usort($files, fn($a,$b) => filemtime($b) - filemtime($a));
            rename($files[0], $out);
        } else {
            return ['ok'=>false,'error'=>'El archivo de salida no se generó. Salida: '.implode("\n",$output)];
        }
    }

    return ['ok'=>true,'file'=>$out];
}

// --- API REST ---
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // GET /api.php?action=historial  → últimas 20 conversiones
    if (isset($_GET['action']) && $_GET['action'] === 'historial') {
        $pdo = db_init();
        $rows = $pdo->query("SELECT * FROM conversiones ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok'=>true,'data'=>$rows]);
        exit;
    }
    // GET /api.php?action=mapa  → mapa de conversiones
    if (isset($_GET['action']) && $_GET['action'] === 'mapa') {
        echo json_encode(['ok'=>true,'data'=>mapa()]);
        exit;
    }
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Método no soportado']);
    exit;
}

if ($method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'No se recibió archivo']);
        exit;
    }

    $tmp = $_FILES['archivo']['tmp_name'];
    $name = $_FILES['archivo']['name'];
    $dstExt = strtolower($_POST['destino'] ?? '');

    if (!ext_ok($name)) {
        db_log($ip,$name,'',0,'Extensión no permitida');
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>'Formato de origen no soportado']);
        exit;
    }

    $origen = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mapa   = mapa();

    if (!isset($mapa[$origen]) || !in_array($dstExt, $mapa[$origen])) {
        db_log($ip,$name,$dstExt,0,"$origen→$dstExt no soportado");
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>"La conversión $origen → $dstExt no está disponible"]);
        exit;
    }

    // Mover a uploads con nombre seguro
    $safe = preg_replace('/[^a-zA-Z0-9._-]/','_',$name);
    $dest = "$uploadDir/$safe";
    if (!move_uploaded_file($tmp, $dest)) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'Error al guardar el archivo temporal']);
        exit;
    }

    // Ejecutar conversión
    $res = convertir($dest, $dstExt);

    if ($res['ok']) {
        db_log($ip,$name,$dstExt,1);
        // Borrar origen (ya no se necesita)
        @unlink($dest);
        echo json_encode(['ok'=>true,'file'=>$res['file'],'origen'=>$name]);
    } else {
        db_log($ip,$name,$dstExt,0,$res['error']);
        @unlink($dest);
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$res['error']]);
    }
}
