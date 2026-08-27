<?php
header('Content-Type: application/json; charset=utf-8');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$dbFile = __DIR__ . '/data/ingles.db';

function getDB() { global $dbFile; return new PDO("sqlite:$dbFile"); }

// Inicializar tablas
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS vocabulario (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    palabra TEXT NOT NULL,
    traduccion TEXT NOT NULL,
    ejemplo TEXT DEFAULT '',
    tipo TEXT DEFAULT 'palabra',
    nivel TEXT NOT NULL,
    fecha TEXT,
    estado TEXT DEFAULT 'pendiente'
)");
$db->exec("CREATE TABLE IF NOT EXISTS listening (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nivel TEXT NOT NULL,
    canal TEXT,
    titulo TEXT NOT NULL,
    url TEXT NOT NULL,
    fallos INTEGER DEFAULT 0,
    pdf TEXT
)");
$db->exec("CREATE TABLE IF NOT EXISTS listening_preguntas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    listening_id INTEGER,
    parte INTEGER,
    num INTEGER,
    pregunta TEXT,
    respuesta TEXT,
    FOREIGN KEY(listening_id) REFERENCES listening(id) ON DELETE CASCADE
)");

switch($action){
case 'vocab_list':
    $nivel = $_GET['nivel'] ?? '';
    $stmt = getDB()->prepare("SELECT * FROM vocabulario WHERE nivel=? ORDER BY id DESC");
    $stmt->execute([$nivel]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    break;

case 'vocab_add':
    $d = json_decode(file_get_contents('php://input'), true);
    $st = getDB()->prepare("SELECT id FROM vocabulario WHERE palabra=? AND nivel=?");
    $st->execute([$d['palabra'], $d['nivel']]);
    if($st->fetch()){ echo json_encode(['ok'=>false,'msg'=>'Ya existe']); break; }
    $st = getDB()->prepare("INSERT INTO vocabulario(palabra,traduccion,ejemplo,tipo,nivel,fecha,estado) VALUES(?,?,?,?,?,?,?)");
    $st->execute([$d['palabra'],$d['traduccion'],$d['ejemplo']??'',$d['tipo'],$d['nivel'],date('Y-m-d'),'pendiente']);
    echo json_encode(['ok'=>true]);
    break;

case 'vocab_delete':
    getDB()->prepare("DELETE FROM vocabulario WHERE id=?")->execute([$_GET['id']]);
    echo json_encode(['ok'=>true]);
    break;

case 'vocab_estado':
    $d = json_decode(file_get_contents('php://input'), true);
    getDB()->prepare("UPDATE vocabulario SET estado=? WHERE id=?")->execute([$d['estado'],$d['id']]);
    echo json_encode(['ok'=>true]);
    break;

case 'listening_list':
    $nivel = $_GET['nivel'] ?? '';
    $st = getDB()->prepare("SELECT * FROM listening WHERE nivel=? ORDER BY id DESC");
    $st->execute([$nivel]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    break;

case 'listening_add':
    $d = json_decode(file_get_contents('php://input'), true);
    $db = getDB();
    $st = $db->prepare("INSERT INTO listening(nivel,canal,titulo,url,fallos,pdf) VALUES(?,?,?,?,0,?)");
    $st->execute([$d['nivel'],$d['canal'],$d['titulo'],$d['url'],$d['pdf']??null]);
    echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]);
    break;

case 'listening_delete':
    getDB()->prepare("DELETE FROM listening WHERE id=?")->execute([$_GET['id']]);
    echo json_encode(['ok'=>true]);
    break;

case 'preguntas_list':
    $lid = $_GET['listening_id'];
    $st = getDB()->prepare("SELECT * FROM listening_preguntas WHERE listening_id=? ORDER BY parte,num");
    $st->execute([$lid]);
    echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));
    break;

case 'preguntas_save':
    $d = json_decode(file_get_contents('php://input'), true);
    getDB()->prepare("DELETE FROM listening_preguntas WHERE listening_id=?")->execute([$d['listening_id']]);
    $st = getDB()->prepare("INSERT INTO listening_preguntas(listening_id,parte,num,pregunta,respuesta) VALUES(?,?,?,?,?)");
    foreach($d['preguntas'] as $p){
        if(!empty($p['respuesta'])) $st->execute([$d['listening_id'],$p['parte'],$p['num'],null,$p['respuesta']]);
    }
    echo json_encode(['ok'=>true]);
    break;

case 'fallos_update':
    getDB()->prepare("UPDATE listening SET fallos=? WHERE id=?")->execute([$_GET['fallos'],$_GET['id']]);
    echo json_encode(['ok'=>true]);
    break;

default:
    echo json_encode(['error'=>'unknown action']);
}
