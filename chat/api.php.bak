<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', 0);

$DB = new PDO("sqlite:" . __DIR__ . "/chat.db");
$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$DB->exec("CREATE TABLE IF NOT EXISTS sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT DEFAULT (datetime('now')))");
$DB->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, session_id INTEGER NOT NULL, type TEXT DEFAULT 'text', content TEXT, filename TEXT, file_size INTEGER DEFAULT 0, created_at TEXT DEFAULT (datetime('now')), FOREIGN KEY(session_id) REFERENCES sessions(id))");

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($action === 'list_sessions') {
    $stmt = $DB->query("SELECT s.id, s.name, s.created_at, COUNT(m.id) as msg_count FROM sessions s LEFT JOIN messages m ON m.session_id=s.id GROUP BY s.id ORDER BY s.created_at DESC");
    echo json_encode(['ok'=>true,'sessions'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

if ($action === 'create_session') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit; }
    $DB->prepare("INSERT INTO sessions(name) VALUES(?)")->execute([$name]);
    echo json_encode(['ok'=>true,'id'=>$DB->lastInsertId()]); exit;
}

if ($action === 'delete_session') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'ID requerido']); exit; }
    $files = $DB->prepare("SELECT filename FROM messages WHERE session_id=? AND type='file'");
    $files->execute([$id]);
    foreach ($files as $row) @unlink(__DIR__.'/uploads/'.$row['filename']);
    $DB->prepare("DELETE FROM messages WHERE session_id=?")->execute([$id]);
    $DB->prepare("DELETE FROM sessions WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]); exit;
}

if ($action === 'rename_session') {
    $id=(int)($_POST['id']??0); $name=trim($_POST['name']??'');
    if(!$id||!$name){echo json_encode(['ok'=>false,'error'=>'Faltan datos']);exit;}
    $DB->prepare("UPDATE sessions SET name=? WHERE id=?")->execute([$name,$id]);
    echo json_encode(['ok'=>true]); exit;
}

if ($action === 'get_messages') {
    $sid=(int)($_POST['session_id']??0);
    if(!$sid){echo json_encode(['ok'=>false,'error'=>'Session ID requerido']);exit;}
    $stmt=$DB->prepare("SELECT id,type,content,filename,file_size,created_at FROM messages WHERE session_id=? ORDER BY created_at ASC");
    $stmt->execute([$sid]);
    echo json_encode(['ok'=>true,'messages'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

if ($action === 'add_message') {
    $sid=(int)($_POST['session_id']??0); $content=trim($_POST['content']??'');
    if(!$sid||!$content){echo json_encode(['ok'=>false,'error'=>'Faltan datos']);exit;}
    $DB->prepare("INSERT INTO messages(session_id,type,content) VALUES(?, 'text', ?)")->execute([$sid,$content]);
    echo json_encode(['ok'=>true,'id'=>$DB->lastInsertId()]); exit;
}

if ($action === 'upload_file') {
    $sid=(int)($_POST['session_id']??0);
    if(!$sid||!isset($_FILES['file'])){echo json_encode(['ok'=>false,'error'=>'Falta archivo']);exit;}
    $f=$_FILES['file'];
    if($f['error']!==0){echo json_encode(['ok'=>false,'error'=>"Upload err: ".$f['error']]);exit;}
    $dir=__DIR__.'/uploads'; if(!is_dir($dir))mkdir($dir,0755);
    $safe=uniqid('up_').'_'.basename($f['name']);
    move_uploaded_file($f['tmp_name'],"$dir/$safe");
    $DB->prepare("INSERT INTO messages(session_id,type,filename,file_size) VALUES(?, 'file', ?, ?)")->execute([$sid,$safe,$f['size']]);
    echo json_encode(['ok'=>true,'filename'=>$safe]); exit;
}

if ($action === 'delete_message') {
    $mid=(int)($_POST['message_id']??0);
    if(!$mid){echo json_encode(['ok'=>false,'error'=>'ID requerido']);exit;}
    $r=$DB->prepare("SELECT filename FROM messages WHERE id=? AND type='file'");$r->execute([$mid]);
    $row=$r->fetch(); if($row)@unlink(__DIR__.'/uploads/'.$row['filename']);
    $DB->prepare("DELETE FROM messages WHERE id=?")->execute([$mid]);
    echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>false,'error'=>'Acción no válida']);
