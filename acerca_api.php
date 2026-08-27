<?php
header('Content-Type: application/json; charset=utf-8');
$db = new PDO("sqlite:".__DIR__."/acerca.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS messages (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, message TEXT, created_at TEXT DEFAULT (datetime('now')))");

if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['name'])) {
    $s=$db->prepare("INSERT INTO messages(name,email,message) VALUES(?,?,?)");
    $s->execute([$_POST['name'],$_POST['email']??'',$_POST['message']??'']);
    echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]);
} else {
    $c=$db->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $m=$db->query("SELECT name,message,created_at FROM messages ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'count'=>$c,'recent'=>$m]);
}
