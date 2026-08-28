<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors', 0);
$DB = new PDO("sqlite:" . __DIR__ . "/contacto.db");
$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$DB->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT DEFAULT '', message TEXT NOT NULL, created_at TEXT DEFAULT (datetime('now')))");
$action = $_POST['action'] ?? '';
if ($action === 'send') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $msg = trim($_POST['message'] ?? '');
    if (!$name || !$msg) { echo json_encode(['ok'=>false,'error'=>'Nombre y mensaje requeridos']); exit; }
    $DB->prepare("INSERT INTO messages(name,email,message) VALUES(?,?,?)")->execute([$name,$email,$msg]);
    echo json_encode(['ok'=>true]); exit;
}
if ($action === 'list') {
    $rows = $DB->query("SELECT name, email, message, created_at FROM messages ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'messages'=>$rows]); exit;
}
echo json_encode(['ok'=>false,'error'=>'Acción no válida']);
