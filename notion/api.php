<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$base = __DIR__;
$dbFile = $base . '/notion.db';
$uploadsDir = $base . '/uploads';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0775, true);

try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA journal_mode=WAL");
$pdo->exec("PRAGMA foreign_keys=ON");
} catch (Throwable $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS pages(id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL DEFAULT 'Sin título', icon TEXT DEFAULT '', created_at TEXT DEFAULT (datetime('now')), updated_at TEXT DEFAULT (datetime('now')))");
$pdo->exec("CREATE TABLE IF NOT EXISTS blocks(id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE, parent_id INTEGER DEFAULT NULL, position REAL DEFAULT 0, type TEXT DEFAULT 'text', content TEXT DEFAULT '', file_path TEXT DEFAULT '', created_at TEXT DEFAULT (datetime('now')))");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_blocks_page ON blocks(page_id, position)");

function out($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function err($msg,$c=400){ http_response_code($c); out(['error'=>$msg]); }
function deleteUpload(string $rel): void {
    global $uploadsDir;
    $rel = ltrim($rel, '/');
    if ($rel === '' || strpos($rel, '..') !== false) return;
    $upReal = realpath($uploadsDir);
    $real = realpath($uploadsDir . '/' . basename($rel));
    if ($upReal && $real && strpos($real, $upReal) === 0 && is_file($real)) @unlink($real);
}

$m = $_SERVER['REQUEST_METHOD'];
if ($m === 'OPTIONS') { http_response_code(204); exit; }

// Ruta: ?r=/pages/1/blocks  (server-agnostic)
$route = isset($_GET['r']) ? trim($_GET['r'],'/') : '';
$route = '/' . $route;

// ---- PAGES LIST / CREATE ----
if ($route === '/pages') {
    if ($m === 'GET') {
        $st = $pdo->query("SELECT id,title,icon,created_at,updated_at FROM pages ORDER BY updated_at DESC");
        out(['pages'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($m === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $st = $pdo->prepare("INSERT INTO pages(title, icon) VALUES(?,?)");
        $st->execute([trim($in['title'] ?? 'Sin título'), $in['icon'] ?? '']);
        out(['id'=>(int)$pdo->lastInsertId()]);
    }
}

// ---- PAGE DETAIL / UPDATE / DELETE ----
if (preg_match('#^/pages/(\d+)$#', $route, $mm)) {
    $pid = (int)$mm[1];
    if ($m === 'GET') {
        $p = $pdo->prepare("SELECT * FROM pages WHERE id=?"); $p->execute([$pid]);
        $page = $p->fetch(PDO::FETCH_ASSOC);
        if (!$page) err('no existe', 404);
        $b = $pdo->prepare("SELECT id,type,content,file_path,position,parent_id,done,size FROM blocks WHERE page_id=? ORDER BY position");
        $b->execute([$pid]);
        out(['page'=>$page,'blocks'=>$b->fetchAll(PDO::FETCH_ASSOC)]);
    }
    if ($m === 'PUT') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $st = $pdo->prepare("UPDATE pages SET title=?, icon=?, updated_at=datetime('now') WHERE id=?");
        $st->execute([trim($in['title'] ?? ''), $in['icon'] ?? '', $pid]);
        out(['ok'=>true]);
    }
    if ($m === 'DELETE') {
        $q = $pdo->prepare("SELECT file_path FROM blocks WHERE page_id=?");
        $q->execute([$pid]);
        $files = $q->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec("DELETE FROM pages WHERE id=$pid");
        foreach ($files as $fp) if ($fp !== '') deleteUpload($fp);
        out(['ok'=>true]);
    }
}

// ---- BLOCKS CREATE ----
if (preg_match('#^/pages/(\d+)/blocks$#', $route, $mm)) {
    $pid = (int)$mm[1];
    if ($m === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $chk = $pdo->prepare("SELECT id FROM pages WHERE id=?"); $chk->execute([$pid]);
        if (!$chk->fetch()) err('página no existe', 404);
        $types = ['text','h1','h2','h3','quote','code','todo','file'];
        $type = in_array($in['type'] ?? 'text', $types, true) ? $in['type'] : 'text';
        if ($type === 'file' && empty($in['file_path'])) err('falta file_path', 400);
        if (isset($in['position']) && (float)$in['position'] > 0) {
            $pos = (float)$in['position'];
        } else {
            $mx=$pdo->prepare("SELECT MAX(position) m FROM blocks WHERE page_id=?");
            $mx->execute([$pid]); $r=$mx->fetch();
            $pos=(($r['m']??0)+1);
        }
        $st = $pdo->prepare("INSERT INTO blocks(page_id,parent_id,position,type,content,file_path,size) VALUES(?,?,?,?,?,?,?)");
        $st->execute([$pid, isset($in['parent_id'])?(int)$in['parent_id']:null, $pos, $type, $in['content']??'', $in['file_path']??'', (int)($in['size']??0)]);
        $pdo->exec("UPDATE pages SET updated_at=datetime('now') WHERE id=$pid");
        out(['id'=>(int)$pdo->lastInsertId(),'position'=>$pos]);
    }
}

// ---- BLOCK UPDATE / DELETE ----
if (preg_match('#^/pages/(\d+)/blocks/(\d+)$#', $route, $mm)) {
    $pid=(int)$mm[1]; $bid=(int)$mm[2];
    if ($m === 'PUT') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $types = ['text','h1','h2','h3','quote','code','todo','file'];
        $type = in_array($in['type'] ?? 'text', $types, true) ? $in['type'] : 'text';
        $q = $pdo->prepare("SELECT file_path FROM blocks WHERE id=? AND page_id=?");
        $q->execute([$bid, $pid]);
        $oldFp = (string)$q->fetchColumn();
        $newFp = array_key_exists('file_path', $in) ? (string)($in['file_path'] ?? '') : $oldFp;
        if ($type !== 'file') $newFp = '';
        $done = array_key_exists('done', $in) ? (int)(bool)$in['done'] : null;
        $sz = array_key_exists('size', $in) ? (int)$in['size'] : null;
        $sqlD = "UPDATE blocks SET type=?, content=?, file_path=?, done=?, size=? WHERE id=? AND page_id=?";
        $sqlN = "UPDATE blocks SET type=?, content=?, file_path=?, size=? WHERE id=? AND page_id=?";
        if ($done !== null) {
            $st=$pdo->prepare($sqlD);
            $st->execute([$type, $in['content']??'', $newFp, $done, $sz ?? 0, $bid, $pid]);
        } else {
            $st=$pdo->prepare($sqlN);
            $st->execute([$type, $in['content']??'', $newFp, $sz ?? 0, $bid, $pid]);
        }
        if ($oldFp !== '' && $newFp !== $oldFp) deleteUpload($oldFp);
        $pdo->exec("UPDATE pages SET updated_at=datetime('now') WHERE id=$pid");
        out(['ok'=>true]);
    }
    if ($m === 'DELETE') {
        $q = $pdo->prepare("SELECT file_path FROM blocks WHERE id=? AND page_id=?");
        $q->execute([$bid, $pid]);
        $fp = $q->fetchColumn();
        $pdo->exec("DELETE FROM blocks WHERE id=$bid AND page_id=$pid");
        if ($fp !== false && $fp !== '') deleteUpload($fp);
        $pdo->exec("UPDATE pages SET updated_at=datetime('now') WHERE id=$pid");
        out(['ok'=>true]);
    }
}

// ---- REORDER (bulk) ----
if (preg_match('#^/pages/(\d+)/reorder$#', $route, $mm)) {
    $pid=(int)$mm[1];
    if ($m === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $pdo->beginTransaction();
        $st=$pdo->prepare("UPDATE blocks SET position=? WHERE id=? AND page_id=?");
        foreach (($in['blocks']??[]) as $it) { $st->execute([(float)$it['position'], (int)$it['id'], $pid]); }
        $pdo->commit();
        $pdo->exec("UPDATE pages SET updated_at=datetime('now') WHERE id=$pid");
        out(['ok'=>true]);
    }
}

// ---- UPLOADS ----
if ($route === '/upload') {
    if ($m !== 'POST') err('solo POST', 405);
    if (!isset($_FILES['file'])) err('sin archivo', 400);
    if (($_FILES['file']['size'] ?? 0) > 50 * 1024 * 1024) err('archivo > 50MB', 413);
    $f = $_FILES['file'];
    $name = preg_replace('/[^\w.\-]+/','_', basename($f['name']));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['png','jpg','jpeg','gif','webp','svg','pdf','txt','md','csv','json','zip','docx','xlsx','pptx','mp3','wav','mp4','mov'];
    if (!in_array($ext,$allowed)) err('tipo no permitido', 415);
    $dest = $uploadsDir . '/' . uniqid('u_') . '_' . $name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) err('no se movió el archivo', 500);
    @chmod($dest, 0644);
    out(['file_path'=>'uploads/'.basename($dest),'size'=>$f['size'],'name'=>$name,'mime'=>$f['type']??'']);
}

// ---- FILE SERVE (inline preview) ----
if (preg_match('#^/files/(.+)$#', $route, $fm)) {
    $rel = urldecode($fm[1]);
    $real = realpath($uploadsDir . '/' . basename($rel));
    $upReal = realpath($uploadsDir);
    if (!$real || strpos($real, $upReal) !== 0 || !is_file($real)) err('archivo no existe', 404);
    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    $mimes = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','pdf'=>'application/pdf','mp3'=>'audio/mpeg','wav'=>'audio/wav','mp4'=>'video/mp4','mov'=>'video/quicktime','txt'=>'text/plain','md'=>'text/markdown','csv'=>'text/csv','json'=>'application/json','zip'=>'application/zip'];
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($real));
    header('Cache-Control: private, max-age=3600');
    readfile($real);
    exit;
}

err('ruta no válida: '.$route, 404);
