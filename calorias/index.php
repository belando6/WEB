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
                $st = $pdo->prepare("DELETE FROM registro WHERE id=:i");
                $st->execute([':i'=>(int)($_GET['id'] ?? 0)]);
                echo json_encode(['ok'=>true]);
                break;
            case 'reset':
                $pdo->exec("DELETE FROM registro");
                echo json_encode(['ok'=>true]);
                break;
            case 'list':
                $rows = $pdo->query("SELECT id,nombre,gramos,calorias FROM registro ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['items'=>$rows,'total'=>round(array_sum(array_column($rows,'calorias')),1)]);
                break;
            case 'foods_list':
                $rows = $pdo->query("SELECT id,nombre,calorias_por_100g FROM alimentos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($rows);
                break;
            case 'food_add':
                $n=trim($_POST['nombre']??'');$c=(float)($_POST['cal']??0);
                if($n===''||$c<=0){echo json_encode(['ok'=>false,'msg'=>'Nombre y calorias>0 obligatorios']);break;}
                try{$st=$pdo->prepare("INSERT INTO alimentos(nombre,calorias_por_100g)VALUES(:n,:c)");$st->execute([':n'=>$n,':c'=>$c]);echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]);}
                catch(PDOException $e){if($e->getCode()=='23000'){echo json_encode(['ok'=>false,'msg'=>'Ya existe']);break;}throw $e;}
                break;
            case 'food_update':
                $id=(int)($_POST['id']??0);$n=trim($_POST['nombre']??'');$c=(float)($_POST['cal']??0);
                if($id<=0||$n===''||$c<=0){echo json_encode(['ok'=>false,'msg'=>'Datos invalidos']);break;}
                try{$st=$pdo->prepare("UPDATE alimentos SET nombre=:n,calorias_por_100g=:c WHERE id=:i");$st->execute([':n'=>$n,':c'=>$c,':i'=>$id]);echo json_encode(['ok'=>true]);}
                catch(PDOException $e){if($e->getCode()=='23000'){echo json_encode(['ok'=>false,'msg'=>'Nombre duplicado']);break;}throw $e;}
                break;
            case 'food_delete':
                $id=(int)($_GET['id']??0);
                if($id>0){$st=$pdo->prepare("DELETE FROM alimentos WHERE id=:i");$st->execute([':i'=>$id]);}
                echo json_encode(['ok'=>true]);
                break;
        }
    } catch (Exception $e) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    exit;
}

$items = $pdo->query("SELECT id,nombre,gramos,calorias FROM registro ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$total = round(array_sum(array_column($items,'calorias')),1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contador de Calorias</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--accent:#34d399;--text:#e2e8f0;--muted:#94a3b8}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:2rem 1rem}
h1{font-size:1.6rem;margin-bottom:1.5rem;color:var(--accent)}
.card{background:var(--card);border-radius:12px;padding:1.5rem;width:100%;max-width:560px;box-shadow:0 4px 24px rgba(0,0,0,.3)}
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
.del-btn{background:none;color:#ef4444;cursor:pointer;font-size:1.1rem;padding:0;border:none}
.edit-btn{background:none;color:#fbbf24;cursor:pointer;font-size:.95rem;padding:0;border:none}
.total-bar{display:flex;justify-content:space-between;align-items:center;margin-top:1rem;padding:.8rem 1rem;background:#0f172a;border-radius:8px}
.total-val{font-size:1.3rem;color:var(--accent);font-weight:700}
.empty{text-align:center;color:var(--muted);padding:1rem;font-style:italic}
.mgmt-section{margin-top:2rem;max-width:640px;width:100%}
.mgmt-title{color:var(--accent);font-size:1.1rem;margin-bottom:.8rem;display:flex;align-items:center;gap:.5rem}
.food-form{display:flex;gap:.6rem;margin-bottom:1rem}
.btn-mgmt{background:#7c3aed;color:#fff}
</style>
</head>
<body>
<h1>Contador de Calorias</h1>

<div class="card">
  <div class="form-row">
    <input type="text" id="foodInput" placeholder="Buscar alimento..." autocomplete="off">
    <input type="number" id="gramsInput" min="1" step="0.1" placeholder="Gramos">
  </div>
  <button class="btn-add" onclick="addFood()">Anadir</button>
  <ul class="datalist" id="suggestions"></ul>

  <h3 style="margin:1rem 0 .4rem;color:var(--muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.05em">Alimentos registrados</h3>
  <?php if(empty($items)):?>
    <p class="empty">Sin alimentos anadidos aun.</p>
  <?php else:?>
  <table id="foodTable"><thead><tr><th>Alimento</th><th>g</th><th>cal</th><th></th></tr></thead><tbody>
    <?php foreach($items as $it):?>
    <tr data-id="<?= $it['id'] ?>"><td><?= htmlspecialchars($it['nombre']) ?></td><td><?= $it['gramos'] ?></td><td><?= $it['calorias'] ?></td><td><button class="del-btn" onclick="removeFood(<?= $it['id'] ?>)">X</button></td></tr>
    <?php endforeach;?>
  </tbody></table>
  <?php endif; ?>

  <div class="total-bar"><span>Total</span><span class="total-val"><?= $total ?> cal</span></div>
  <button class="btn-reset" onclick="resetAll()">Reiniciar (borrar todo)</button>
</div>

<!-- SECCION: GESTIONAR CATALOGO -->
<div class="card mgmt-section">
  <h2 class="mgmt-title">Gestionar Catalogo de Alimentos</h2>
  <p style="color:var(--muted);font-size:.85rem;margin-bottom:1rem">Aadir, editar o eliminar alimentos del catalogo (calorias por 100g).</p>

  <div class="food-form">
    <input type="text" id="newFoodName" placeholder="Nombre alimento">
    <input type="number" id="newFoodCal" min="1" step="0.1" placeholder="cal/100g" style="max-width:120px">
    <button class="btn-mgmt" onclick="addFoodToCatalog()">Anadir</button>
  </div>

  <table id="catalogTable"><thead><tr><th>Nombre</th><th>cal/100g</th><th></th></tr></thead><tbody id="catalogBody">
    <tr class="empty-row"><td colspan="3" class="empty">Cargando...</td></tr>
  </tbody></table>
</div>

<script>
const sug=document.getElementById('suggestions'),foodInp=document.getElementById('foodInput');
let debounce;
foodInp.addEventListener('input',e=>{clearTimeout(debounce);debounce=setTimeout(()=>fetchSuggestions(e.target.value),150)});
function fetchSuggestions(q){if(!q)return hideSug();fetch('?api=search&q='+encodeURIComponent(q)).then(r=>r.json()).then(list=>{sug.innerHTML=list.map(n=>'<li data-val="'+n+'">'+n+'</li>').join('');sug.style.display='block';}).catch(()=>hideSug());}
function hideSug(){sug.style.display='none'}
document.addEventListener('click',e=>{if(!sug.contains(e.target)&&e.target!==foodInp)hideSug()});
sug.addEventListener('mousedown',e=>{const li=e.target.closest('li');if(li){foodInp.value=li.dataset.val;hideSug()}});

function addFood(){const f=foodInp.value.trim(),g=parseFloat(document.getElementById('gramsInput').value);if(!f||!g||g<=0)return alert('Datos invalidos');fetch('?api=add',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'food='+encodeURIComponent(f)+'&grams='+g}).then(r=>r.json()).then(d=>{if(d.ok){location.reload()}else alert(d.msg)})}
function removeFood(id){fetch('?api=remove&id='+id).then(()=>location.reload())}
function resetAll(){if(!confirm('Borrar todos los alimentos?'))return;fetch('?api=reset').then(()=>location.reload())}

// CATALOG MANAGEMENT
function loadCatalog(){
  fetch('?api=foods_list').then(r=>r.json()).then(rows=>{
    const tbody=document.getElementById('catalogBody');
    if(!rows.length){tbody.innerHTML='<tr><td colspan="3" class="empty">Catalogo vacio</td></tr>';return;}
    tbody.innerHTML=rows.map(f=>'<tr data-id="'+f.id+'"><td>'+escHtml(f.nombre)+'</td><td>'+f.calorias_por_100g+'</td><td><button class="edit-btn" onclick="editFood('+f.id+','+JSON.stringify(JSON.stringify(f)).replace(/"/g,'&quot;')+')">Edit</button> <button class="del-btn" onclick="deleteFood('+f.id+')">X</button></td></tr>').join('');
  });
}
function escHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function addFoodToCatalog(){
  const n=document.getElementById('newFoodName').value.trim();
  const c=parseFloat(document.getElementById('newFoodCal').value);
  if(!n||!c||c<=0)return alert('Nombre y calorias>0 obligatorios');
  fetch('?api=food_add',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'nombre='+encodeURIComponent(n)+'&cal='+c}).then(r=>r.json()).then(d=>{if(d.ok){document.getElementById('newFoodName').value='';document.getElementById('newFoodCal').value='';loadCatalog();}else alert(d.msg)});
}
function editFood(id,jsonStr){
  const f=JSON.parse(jsonStr);
  const newName=prompt('Nuevo nombre:',f.nombre);if(newName===null)return;
  const newCal=prompt('Nuevas cal/100g:',String(f.calorias_por_100g));if(newCal===null)return;
  const c=parseFloat(newCal);if(isNaN(c)||c<=0)return alert('Valor invalido');
  fetch('?api=food_update',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+id+'&nombre='+encodeURIComponent(newName)+'&cal='+c}).then(r=>r.json()).then(d=>{if(d.ok)loadCatalog();else alert(d.msg)});
}
function deleteFood(id){if(!confirm('Eliminar este alimento del catalogo?'))return;fetch('?api=food_delete&id='+id).then(()=>loadCatalog())}

loadCatalog();
</script>
</body></html>
