<?php
// NEXUS Conversor API v1.1
header('Content-Type: application/json; charset=utf-8');

$uploadDir = __DIR__ . '/uploads';
$dbFile    = __DIR__ . '/conversor.db';

// --- SQLite ---
function db_init() {
    global $dbFile;
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversiones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT, origen TEXT, destino TEXT, fecha TEXT, exito INTEGER, error TEXT
    )");
    return $pdo;
}

function db_log($ip,$origen,$destino,$exito,$error='') {
    global $dbFile;
    $pdo = new PDO("sqlite:$dbFile");
    $st = $pdo->prepare("INSERT INTO conversiones (ip,origen,destino,fecha,exito,error) VALUES (?,?,?,?,?,?)");
    $st->execute([$ip,$origen,$destino,date('Y-m-d H:i:s'),$exito,$error]);
}

function safe_name($n){ 
    $b=basename((string)$n); 
    if($b===''||$b==='.'||$b==='..') return null; 
    if(strpos($b,'/')!==false) return null; 
    if(!preg_match('/^[a-zA-Z0-9._-]+$/', $b)) return null; 
    return $b; 
}

// --- Mapa de conversiones (42 formatos origen) ---
function get_mapa(){
    return [
        'txt'=>['pdf','docx','odt','html'],'md'=>['pdf','docx','odt','html'],
        'csv'=>['xlsx','ods','pdf','html'],'html'=>['pdf','docx','odt','txt'],
        'htm'=>['pdf','docx','odt','txt'],'rtf'=>['pdf','docx','odt','txt'],
        'doc'=>['pdf','docx','odt','txt','html'],'docx'=>['pdf','odt','txt','html','rtf'],
        'odt'=>['pdf','docx','txt','html'],'xls'=>['xlsx','csv','ods','pdf'],
        'xlsx'=>['csv','ods','pdf','xls'],'ods'=>['xlsx','csv','pdf'],
        'ppt'=>['pdf','png'],'pptx'=>['pdf','png'],'odp'=>['pdf','png'],
        'jpg'=>['jpeg','png','webp','bmp','gif','tiff','ico','svg','pdf'],
        'jpeg'=>['jpg','png','webp','bmp','gif','tiff','ico','svg','pdf'],
        'png'=>['jpg','jpeg','webp','bmp','gif','tiff','ico','svg','pdf'],
        'gif'=>['mp4','webm','png','jpg','apng'],'webp'=>['png','jpg','jpeg','gif','bmp'],
        'bmp'=>['png','jpg','jpeg','webp','gif'],'tif'=>['png','jpg','jpeg','webp','tiff'],
        'tiff'=>['png','jpg','jpeg','webp','tif'],'ico'=>['png','jpg','svg'],
        'svg'=>['png','jpg','jpeg','gif','bmp','pdf'],'pdf'=>['png','jpg','txt','docx'],
        'mp3'=>['wav','ogg','flac','m4a','aac','opus','wma'],
        'wav'=>['mp3','ogg','flac','m4a','aac','opus'],
        'ogg'=>['mp3','wav','flac','m4a','aac','opus'],'flac'=>['mp3','wav','ogg','m4a','aac'],
        'm4a'=>['mp3','wav','ogg','flac','aac'],'aac'=>['mp3','wav','m4a','ogg'],
        'wma'=>['mp3','wav','m4a','ogg'],'opus'=>['mp3','wav','ogg','flac','m4a'],
        'mp4'=>['avi','mkv','mov','webm','wmv','flv','ts','gif','mp3','wav','m4a'],
        'avi'=>['mp4','mkv','mov','webm','wmv','flv'],'mkv'=>['mp4','avi','mov','webm','mp3','wav'],
        'mov'=>['mp4','avi','mkv','webm','gif'],'wmv'=>['mp4','avi','mkv','mov'],
        'flv'=>['mp4','avi','mkv','mov','webm'],'ts'=>['mp4','mkv','avi','mov'],
        'm4v'=>['mp4','avi','mkv','mov']
    ];
}

// --- Motor de conversión ---
function convertir($src, $dstExt) {
    $uploadDir = __DIR__ . '/uploads';
    $out = "$uploadDir/" . pathinfo($src, PATHINFO_FILENAME) . ".$dstExt";
    
    if (in_array(pathinfo($src,PATHINFO_EXTENSION), ['txt','md','csv','html','htm','rtf','doc','docx','odt','xls','xlsx','ods','ppt','pptx','odp'])) {
        $cmd = "libreoffice -env:UserInstallation=file:///tmp/lo_$$ --headless --convert-to $dstExt --outdir '$uploadDir' '$src' 2>&1";
    } elseif (in_array(pathinfo($src,PATHINFO_EXTENSION), ['mp3','wav','ogg','flac','m4a','aac','wma','opus'])) {
        if ($dstExt === 'gif') $cmd = "ffmpeg -y -i '$src' -vf \"fps=10,scale=320:-1:flags=lanczos\" '$out' 2>&1";
        else $cmd = "ffmpeg -y -i '$src' '$out' 2>&1";
    } elseif (in_array(pathinfo($src,PATHINFO_EXTENSION), ['jpg','jpeg','png','gif','webp','bmp','tif','tiff','ico','svg']) || pathinfo($src,PATHINFO_EXTENSION) === 'pdf') {
        if ($dstExt === 'png' || $dstExt === 'jpg') {
            if (pathinfo($src,PATHINFO_EXTENSION) === 'pdf')
                $cmd = "pdftoppm -png -r 150 '$src' '$uploadDir/tmp_page' && mv '${out%.png}_1.png' '$out' 2>&1";
            else $cmd = "convert '$src' '$out' 2>&1";
        } elseif ($dstExt === 'svg') {
            $cmd = "inkscape --export-type=svg '$src' -o '$out' 2>&1";
        } else {
            $cmd = "ffmpeg -y -i '$src' '$out' 2>&1";
        }
    } else {
        // Video → imagen animada (GIF) o audio extraído
        if ($dstExt === 'gif') {
            $cmd = "ffmpeg -y -i '$src' -vf \"fps=10,scale=320:-1:flags=lanczos\" '$out' 2>&1";
        } else {
            $cmd = "ffmpeg -y -i '$src' '$out' 2>&1";
        }
    }
    
    exec($cmd, $output, $rc);
    if (file_exists($out)) return ['ok'=>true,'file'=>$out];
    return ['ok'=>false,'error'=>implode("\n",array_slice($output,-5))];
}

$method = $_SERVER['REQUEST_METHOD'];

// ==================== GET ====================
if ($method === 'GET') {
    
    // action=historial → últimas 20 conversiones
    if (isset($_GET['action']) && $_GET['action'] === 'historial') {
        $pdo = db_init();
        $rows = $pdo->query("SELECT origen,destino,fecha,exito FROM conversiones ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok'=>true,'data'=>$rows]); exit;
    }
    
    // action=mapa → mapa de conversiones
    if (isset($_GET['action']) && $_GET['action'] === 'mapa') {
        echo json_encode(['ok'=>true,'data'=>get_mapa()]); exit;
    }
    
    // action=biblioteca → listar archivos generados
    if (isset($_GET['action']) && $_GET['action'] === 'biblioteca') {
        $files = glob("$uploadDir/*") ?: [];
        $out=[];
        foreach($files as $f){ 
            if(is_file($f) && basename($f)!=='.gitkeep'){ 
                $out[]=['name'=>basename($f),'size'=>filesize($f),'mtime'=>filemtime($f)]; 
            } 
        }
        usort($out, fn($a,$b)=>$b['mtime']-$a['mtime']);
        foreach($out as &$r){ $r['fecha']=date('Y-m-d H:i:s',$r['mtime']); unset($r['mtime']); }
        echo json_encode(['ok'=>true,'data'=>$out]); exit;
    }
    
    // action=descargar → servir archivo
    if (isset($_GET['action']) && $_GET['action'] === 'descargar') {
        $n=safe_name($_GET['file']??''); 
        if(!$n){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Nombre inválido']);exit;}
        $f="$uploadDir/$n"; 
        if(!is_file($f)){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'No disponible']);exit;}
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$n.'"');
        header('Content-Length: '.filesize($f));
        readfile($f); exit;
    }
    
    // action=eliminar → borrar archivo de biblioteca
    if (isset($_GET['action']) && $_GET['action'] === 'eliminar') {
        $n=safe_name($_GET['file']??''); 
        if(!$n){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Nombre inválido']);exit;}
        $f="$uploadDir/$n"; 
        if(!is_file($f)){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'No existe']);exit;}
        @unlink($f); echo json_encode(['ok'=>true]); exit;
    }
    
    // GET sin action válida
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Método no soportado']); exit;
}

// ==================== POST ====================
if ($method === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Archivo requerido']); exit;
    }
    
    $name = basename($_FILES['archivo']['name']);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $destino = $_POST['destino'] ?? '';
    
    if (!in_array($ext, array_keys(get_mapa()))) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Formato no soportado: '.$ext]); exit;
    }
    if (!in_array($destino, get_mapa()[$ext])) {
        http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Conversión no válida']); exit;
    }
    
    $dest = "$uploadDir/" . pathinfo($name, PATHINFO_FILENAME) . ".$destino";
    move_uploaded_file($_FILES['archivo']['tmp_name'], $dest);
    
    // Ejecutar conversión
    $res = convertir($dest, $destino);
    
    if ($res['ok']) {
        db_log($ip,$name,$destino,1);
        @unlink($dest);
        $url = '/WEB/conversor/uploads/' . basename($res['file']);
        echo json_encode(['ok'=>true,'file'=>$res['file'],'url'=>$url,'origen'=>$name]);
    } else {
        db_log($ip,$name,$destino,0,$res['error']);
        @unlink($dest);
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>$res['error']]);
    }
}

// Método no soportado (PUT/DELETE/etc)
http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Método HTTP no soportado']);
