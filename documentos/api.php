<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$dir = __DIR__;
$action = $_GET['action'] ?? '';

// Sanitize filename - prevent path traversal
function safe_name(string $name): string {
    return basename($name);
}

if ($action === 'list') {
    $files = glob("$dir/*.{md,txt}", GLOB_BRACE);
    $result = [];
    foreach ($files as $f) {
        if (is_file($f)) {
            $info = pathinfo($f, PATHINFO_FILENAME);
            $ext  = pathinfo($f, PATHINFO_EXTENSION);
            $size = filesize($f);
            $mod  = date('Y-m-d H:i', filemtime($f));
            $result[] = [
                'name' => basename($f),
                'ext'  => $ext,
                'size' => $size,
                'modified' => $mod,
                'lines'    => count(file($f)) ?: 0
            ];
        }
    }
    usort($result, fn($a,$b) => strcasecmp($a['name'],$b['name']));
    echo json_encode(['ok'=>true,'files'=>$result]);
}

elseif ($action === 'read') {
    $file = safe_name($_GET['file'] ?? '');
    if (!in_array(pathinfo($file, PATHINFO_EXTENSION), ['md','txt'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Ext no permitida']); exit; }
    $path = "$dir/$file";
    if (!is_file($path)) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'No existe']); exit; }
    echo json_encode(['ok'=>true,'name'=>$file,'content'=>file_get_contents($path)]);
}

elseif ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $file  = safe_name($input['file'] ?? '');
    if (!in_array(pathinfo($file, PATHINFO_EXTENSION), ['md','txt'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Ext no permitida']); exit; }
    $path = "$dir/$file";
    file_put_contents($path, $input['content'] ?? '');
    chmod($path, 0644);
    echo json_encode(['ok'=>true,'saved'=>$file]);
}

elseif ($action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $file  = safe_name($input['file'] ?? '');
    if (!in_array(pathinfo($file, PATHINFO_EXTENSION), ['md','txt'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Ext no permitida']); exit; }
    $path = "$dir/$file";
    if (is_file($path)) { http_response_code(409); echo json_encode(['ok'=>false,'error'=>'Ya existe']); exit; }
    file_put_contents($path, $input['content'] ?? '');
    chmod($path, 0644);
    echo json_encode(['ok'=>true,'created'=>$file]);
}

elseif ($action === 'delete') {
    $file = safe_name($_GET['file'] ?? '');
    if (!in_array(pathinfo($file, PATHINFO_EXTENSION), ['md','txt'])) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Ext no permitida']); exit; }
    $path = "$dir/$file";
    if (!is_file($path)) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'No existe']); exit; }
    unlink($path);
    echo json_encode(['ok'=>true,'deleted'=>$file]);
}

else {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Acción inválida']);
}
