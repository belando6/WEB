<?php
session_start();
$dbPath = __DIR__ . '/alimentos.db';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS registro (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, gramos REAL NOT NULL, calorias REAL NOT NULL)");
} catch (PDOException $e) { die("Error BD: " . htmlspecialchars($e->getMessage())); }

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['api'];
    try {
        switch ($action) {
            case 'search':
                $q = trim($_GET['q'] ?? '');
                if (strlen($q) < 1) { echo "[]"; break; }
                $st = $pdo->prepare("SELECT nombre FROM alimentos WHERE LOWER(nombre) LIKE :q LIMIT 20");
                $st->execute([':q' => '%' . mb_strtolower($q, 'UTF-8') . '%']);
                echo json_encode($st->fetchAll(PDO::FETCH_COLUMN));
                break;
            case 'add':
                $food = trim($_POST['food'] ?? '');
                $grams = (float)($_POST['grams'] ?? 0);
                if ($food === '' || $grams <= 0) { echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); break; }
                $st = $pdo->prepare("SELECT calorias_por_100g FROM alimentos WHERE LOWER(nombre)=LOWER(:f)");
                $st->execute([':f'=>$food]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Alimento no encontrado']); break; }
                $cal = round(($row['calorias_por_100g'] * $grams) / 100, 1);
                $ins = $pdo->prepare("INSERT INTO registro (nombre, gramos, calorias) VALUES (:n,:g,:c)");
                $ins->execute([':n'=>$food,':g'=>$grams,':c'=>$cal]);
                echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId(),'cal'=>$cal]);
                break;
            case 'remove':
                $id = (int)($_GET['id'] ?? 0);
                $st = $pdo->prepare("DELETE FROM registro WHERE id=:i");
                $st->execute([':i'=>$id]);
                echo json_encode(['ok'=>true]);
                break;
            case 'reset':
                $pdo->exec("DELETE FROM registro");
                echo json_encode(['ok'=>true]);
                break;
            case 'list':
                $rows = $pdo->query("SELECT id, nombre, gramos, calorias FROM registro ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
                $total = array_sum(array_column($rows,'calorias'));
                echo json_encode(['items'=>$rows,'total'=>round($total,1)]);
                break;
        }
    } catch (Exception $e) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

$items = $pdo->query("SELECT id, nombre, gramos, calorias FROM registro ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$total = round(array_sum(array_column($items,'calorias')),1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contador de Calorías</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--accent:#34d399;--text:#e2e8f0;--muted:#94a3b8}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:2rem 1rem}
h1{font-size:1.6rem;margin-bottom:1.5rem;color:var(--accent)}
.card{background:var(--card);border-radius:12px;padding:1.5rem;width:100%;max-width:520px;box-shadow:0 4px 24px rgba(0,0,0,.3)}
.form-row{display:flex;gap:.6rem;margin-bottom:.8rem}
input{flex:1;padding:.55rem .7rem;border-radius:8px;border:1px solid #334155;background:#0f172a;color:var(--text);font-size:.95rem}
input:focus{outline:none;border-color:var(--accent)}
button{padding:.55rem 1.2rem;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-size:.9rem}
.btn-add{background:var(--accent);color:#0f172a}
.btn-reset{background:#ef4444;color:#fff;margin-top:1rem;width:100%}
.datalist{position:absolute;z-index:10;background:var(--card);border-radius:8px;max-height:160px;overflow-y:auto;display:none;list-style:none;padding:.3rem 0}
.datalist li{padding:.45rem .7rem;cursor:pointer;font-size:.9rem}
.datalist li:hover{background:#334155}
table{width:100%;border-collapse:collapse;margin-top:1rem}
th,td{text-align:left;padding:.5rem .6rem;border-bottom:1px solid #334155;font-size:.9rem}
th{color:var(--accent);font-weight:600}
.del-btn{background:none;color:#ef4444;cursor:pointer;font-size:1.1rem;padding:0}
.total-bar{display:flex;justify-content:space-between;align-items:center;margin-top:1rem;padding:.8rem 1rem;background:#0f172a;border-radius:8px}
.total-val{font-size:1.3rem;color:var(--accent);font-weight:700}
.empty{text-align:center;color:var(--muted);padding:1.5rem;font-style:italic}
</style>
</head>
<body>
<h1>🔥 Contador de Calorías</h1>
<div class="card">
  <div class="form-row">
    <input type="text" id="foodInput" placeholder="Buscar alimento…" autocomplete="off">
    <input type="number" id="gramsInput" min="1" step="0.1" placeholder="Gramos">
  </div>
  <button class="btn-add" onclick="addFood()">Añadir</button>
  <ul class="datalist" id="suggestions"></ul>
  <h3 style="margin:1rem 0 .4rem;color:var(--muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Alimentos registrados</h3>
  <?php if (empty($items)): ?>
    <p class="empty">Sin alimentos añadidos aún.</p>
  <?php else: ?>
  <table id="foodTable"><thead><tr><th>Alimento</th><th>g</th><th>cal</th><th></th></tr></thead><tbody>
    <?php foreach($items as $it): ?>
    <tr data-id="<?= $it['id'] ?>"><td><?= htmlspecialchars($it['nombre']) ?></td><td><?= $it['gramos'] ?></td><td><?= $it['calorias'] ?></td><td><button class="del-btn" onclick="removeFood(<?= $it['id'] ?>)">✕</button></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
  <div class="total-bar"><span>Total</span><span class="total-val"><?= $total ?> cal</span></div>
  <button class="btn-reset" onclick="resetAll()">🗑 Reiniciar (borrar todo)</button>
</div>
<script>
const sug=document.getElementById('suggestions'),foodInp=document.getElementById('foodInput');
let debounce;
foodInp.addEventListener('input',e=>{clearTimeout(debounce);debounce=setTimeout(()=>fetchSuggestions(e.target.value),150)});
function fetchSuggestions(q){if(!q)return hideSug();fetch('?api=search&q='+encodeURIComponent(q)).then(r=>r.json()).then(list=>{sug.innerHTML=list.map(n=>'<li data-val="'+n+'">'+n+'</li>').join('');sug.style.display='block';}).catch(()=>hideSug());}
function hideSug(){sug.style.display='none'}
document.addEventListener('click',e=>{if(!sug.contains(e.target)&&e.target!==foodInp)hideSug()});
sug.addEventListener('mousedown',e=>{const li=e.target.closest('li');if(li){foodInp.value=li.dataset.val;hideSug()}});
function addFood(){const f=foodInp.value.trim(),g=parseFloat(document.getElementById('gramsInput').value);if(!f||!g||g<=0)return alert('Datos inválidos');fetch('?api=add',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'food='+encodeURIComponent(f)+'&grams='+g}).then(r=>r.json()).then(d=>{if(d.ok){location.reload()}else alert(d.msg)})}
function removeFood(id){fetch('?api=remove&id='+id).then(()=>location.reload())}
function resetAll(){if(!confirm('¿Borrar todos los alimentos?'))return;fetch('?api=reset').then(()=>location.reload())}
</script>
</body></html>
