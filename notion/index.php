<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>NEXUS · Notion</title>
<style>
:root{--bg:#ffffff;--panel:#f7f7f5;--text:#37352F;--muted:#9B9A97;--border:#E9E9E7;--accent:#2383E2;--hover:#EFEFEF}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;background:var(--bg);color:var(--text)}
.app{display:flex;height:100vh;overflow:hidden}
.sidebar{width:260px;background:var(--panel);border-right:1px solid var(--border);display:flex;flex-direction:column;transition:margin .25s ease}
.sidebar.hidden{margin-left:-260px}
.side-head{padding:.75rem;display:flex;align-items:center;gap:.5rem;border-bottom:1px solid var(--border)}
.side-head b{font-size:.9rem;font-weight:600;color:var(--text);flex:1}
.icon-btn{width:28px;height:28px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;background:transparent;border:none;cursor:pointer;color:var(--muted);font-size:.95rem;text-decoration:none}
.icon-btn:hover{background:var(--hover);color:var(--text)}
.new-page{margin:.5rem .75rem;padding:.45rem .6rem;display:flex;align-items:center;gap:.5rem;border-radius:6px;background:transparent;border:none;color:var(--text);cursor:pointer;font-size:.9rem}
.new-page:hover{background:var(--hover)}
.page-list{flex:1;overflow-y:auto;padding:.25rem .5rem 1rem}
.page-item{display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;border-radius:6px;cursor:pointer;font-size:.9rem;color:var(--text);user-select:none}
.page-item:hover{background:var(--hover)}
.page-item.active{background:#E0E0DF;font-weight:500}
.page-item .ic{width:18px;text-align:center;font-size:.95rem;flex-shrink:0}
.page-item .ttl{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.page-item .del{opacity:0;color:var(--muted);font-size:.75rem;padding:0 4px;border-radius:4px;background:none;border:none;cursor:pointer}
.page-item:hover .del{opacity:1}
.page-item .del:hover{background:#ffd9d6;color:#e03e2f}
.main{flex:1;display:flex;flex-direction:column;background:var(--bg);overflow:hidden}
.topbar{height:44px;border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 1rem;gap:.5rem}
.crumb{font-size:.85rem;color:var(--muted)}
.spacer{flex:1}
.badge{font-size:.72rem;background:#EFEFEF;border-radius:99px;padding:.15rem .5rem;color:var(--muted)}
.editor-wrap{flex:1;overflow-y:auto;padding:3rem 0 8rem}
.editor{max-width:860px;margin:0 auto;padding:0 2.4rem}
.title-row{display:flex;align-items:center;gap:.5rem;margin-bottom:1.2rem}
h1.doc-title{flex:1;font-size:2.6rem;font-weight:700;outline:none;border:none;background:transparent;color:var(--text);width:100%}
.meta{color:var(--muted);font-size:.85rem;margin-bottom:1.4rem;display:flex;gap:1rem}
.block{position:relative;padding:.15rem .25rem;border-radius:4px;margin:-.15rem 0}
.block:hover{background:#FAFAF9}
.block-content{min-height:1.6em;white-space:pre-wrap;word-break:break-word;font-size:1rem;line-height:1.55;outline:none}
.block[data-type="h1"] .block-content{font-size:1.8rem;font-weight:700;margin-top:.4em}
.block[data-type="h2"] .block-content{font-size:1.35rem;font-weight:600;margin-top:.3em}
.block[data-type="h3"] .block-content{font-size:1.1rem;font-weight:600;margin-top:.25em}
.block[data-type="quote"] .block-content{border-left:3px solid var(--text);padding-left:.8rem;color:#5a5957;font-style:italic}
.block[data-type="code"] .block-content{font-family:'SF Mono',Menlo,Consolas,monospace;background:#F4F3F1;padding:.6rem .8rem;border-radius:6px;font-size:.9rem;white-space:pre-wrap}
.block[data-type="todo"]{display:flex;gap:.55rem;align-items:flex-start}
.block[data-type="todo"] input[type=checkbox]{margin-top:.4em;width:15px;height:15px;accent-color:var(--accent)}
.block[data-type="todo"].done .block-content{text-decoration:line-through;color:var(--muted)}
.block[data-type="file"]{display:flex;align-items:center;gap:.6rem;background:#F4F3F1;border-radius:8px;padding:.7rem 1rem;margin:.25rem 0}
.file-ic{font-size:1.4rem}
.file-meta{flex:1;display:flex;flex-direction:column;font-size:.9rem}
.file-name{color:var(--text);font-weight:500}
.file-dl{text-decoration:none;color:var(--accent);font-size:.85rem;padding:.25rem .6rem;border-radius:4px;background:#fff}
.file-dl:hover{background:#EAF3FE}
.slash{position:absolute;z-index:50;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.08);width:320px;max-height:320px;overflow-y:auto}
.slash-item{display:flex;gap:.7rem;padding:.55rem .8rem;cursor:pointer;font-size:.9rem}
.slash-item:hover,.slash-item.sel{background:#EFEFEF}
.slash-ic{width:26px;height:26px;border-radius:4px;background:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.slash-tt b{display:block;font-weight:500;color:var(--text)}
.slash-tt span{color:var(--muted);font-size:.78rem}
.dropzone{border:2px dashed var(--accent);border-radius:12px;padding:2.4rem;text-align:center;color:var(--accent);background:#F5FAFF;margin-top:1rem;display:none}
.dropzone.on{display:block}
.empty{color:var(--muted);font-size:.95rem;font-style:italic}
.block .bx{position:absolute;top:-.5rem;right:-1.2rem;width:22px;height:22px;border-radius:4px;background:#fff;border:1px solid var(--border);color:var(--muted);font-size:.7rem;cursor:pointer;display:none;align-items:center;justify-content:center;line-height:1}
.block:hover .bx{display:inline-flex}
.bx:hover{background:#ffd9d6;color:#e03e2f;border-color:#ffb4ad}
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#37352F;color:#fff;padding:.6rem 1rem;border-radius:8px;font-size:.85rem;z-index:99;opacity:0;transition:opacity .2s}
.toast.on{opacity:1}
@media(max-width:760px){.sidebar{position:absolute;height:100%;z-index:10}.editor{padding:0 1.2rem}}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="side-head">
      <button class="icon-btn" title="Contraer" onclick="toggleSide()">&lang;</button>
      <b>NEXUS · Notion</b>
      <a href="../index.html" class="icon-btn" title="Volver al portal">&#8962;</a>
    </div>
    <button class="new-page" onclick="newPage()">＋ Nueva página</button>
    <div class="page-list" id="pageList"></div>
  </aside>
  <main class="main">
    <div class="topbar">
      <span class="crumb" id="crumb">Notion / —</span>
      <div class="spacer"></div>
      <span class="badge" id="saveState">Guardado</span>
    </div>
    <div class="editor-wrap">
      <div class="editor" id="editor">
        <div class="title-row">
          <input class="title-icon" id="docIcon" maxlength="2" value="📄" style="font-size:2.4rem;border:none;background:transparent;outline:none;width:1.6em">
          <h1 class="doc-title" id="docTitle" contenteditable="true" spellcheck="false"></h1>
        </div>
        <div class="meta"><span id="mCreated"></span><span id="mUpdated"></span></div>
        <div id="blocks"></div>
        <div class="dropzone" id="dz">Arrastra archivos aquí para adjuntar</div>
      </div>
    </div>
  </main>
</div>
<div class="toast" id="toast"></div>

<script>
const API = new URL("api.php", window.location.href).pathname;
let state={pages:[],currentId:null,blocks:[]};
let saveTimer=null;

function toast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('on');setTimeout(()=>t.classList.remove('on'),1800);}
async function api(path,opts={}){
  const url=API+'?r='+encodeURIComponent(path);
  const res=await fetch(url,{method:opts.method||'GET',headers:{'Content-Type':'application/json'},body:opts.body?JSON.stringify(opts.body):undefined});
  if(!res.ok) throw new Error((await res.json()).error||'error');
  return res.json();
}

async function loadPages(){
  const d=await api('/pages'); state.pages=d.pages; renderList();
  if(state.currentId==null && state.pages.length) openPage(state.pages[0].id);
  else if(state.currentId!=null && !state.pages.find(p=>p.id==state.currentId)){ state.currentId=null; showEmpty(); }
}

function renderList(){
  const el=document.getElementById('pageList'); el.innerHTML='';
  for(const p of state.pages){
    const div=document.createElement('div');
    div.className='page-item'+(p.id==state.currentId?' active':'');
    div.innerHTML='<span class="ic">'+(p.icon||'📄')+'</span><span class="ttl"></span><button class="del" title="Eliminar">✕</button>';
    div.querySelector('.ttl').textContent=p.title||'Sin título';
    div.onclick=()=>openPage(p.id);
    div.querySelector('.del').onclick=(e)=>{ e.stopPropagation(); delPage(p.id); };
    el.appendChild(div);
  }
}

async function newPage(){
  const r=await api('/pages',{method:'POST',body:{title:'Sin título',icon:'📄'}});
  state.currentId=r.id; toast('Página creada'); loadPages(); openPage(r.id);
}

async function delPage(id){
  if(!confirm('¿Eliminar esta página?')) return;
  await api('/pages/'+id,{method:'DELETE'});
  state.currentId=null; await loadPages(); showEmpty();
}

function showEmpty(){
  document.getElementById('docTitle').textContent='';
  document.getElementById('blocks').innerHTML='<div class="empty">Crea una página desde el panel lateral.</div>';
  document.getElementById('crumb').textContent='Notion / —';
}

async function openPage(id){
  state.currentId=id; renderList();
  const d=await api('/pages/'+id);
  const p=d.page, bs=d.blocks.slice().sort((a,b)=>(a.position-b.position));
  document.getElementById('docTitle').textContent=p.title||'Sin título';
  document.getElementById('docIcon').value=p.icon||'📄';
  document.getElementById('mCreated').textContent='Creado '+p.created_at;
  document.getElementById('mUpdated').textContent='Editado '+p.updated_at;
  document.getElementById('crumb').textContent='Notion / '+(p.title||'Sin título');
  state.blocks=bs; renderBlocks();
  if(!state.blocks.length){ api('/pages/'+id+'/blocks',{method:'POST',body:{type:'text',content:''}}).then(r=>{ state.blocks.push({id:r.id,type:'text',content:'',file_path:'',position:r.position}); renderBlocks(); const el=document.querySelector('.block[data-id="'+r.id+'"] .block-content'); if(el) el.focus(); }); }
}
function blockEl(b){
  const div=document.createElement('div');
  div.className='block'; div.dataset.type=b.type; div.dataset.id=b.id;
  if(b.type==='todo'){
    const cb=document.createElement('input'); cb.type='checkbox';
    cb.onchange=()=>{ div.classList.toggle('done',cb.checked); };
    const span=document.createElement('div'); span.className='block-content'; span.contentEditable='true'; span.textContent=b.content;
    span.addEventListener('input',()=>debSaveBlock(b.id,'todo',span.textContent));
    div.appendChild(cb); div.appendChild(span);
  } else if(b.type==='file'){
    const a=document.createElement('a'); a.className='file-dl'; a.href='./'+b.file_path; a.download=true; a.textContent='Descargar';
    const meta=document.createElement('div'); meta.className='file-meta';
    const nm=document.createElement('span'); nm.className='file-name'; nm.textContent=b.content||'archivo';
    meta.appendChild(nm);
    const ic=document.createElement('div'); ic.className='file-ic'; ic.textContent=fileIcon(b.file_path);
    div.append(ic,meta,a);
  } else {
    const span=document.createElement('div'); span.className='block-content'; span.contentEditable='true'; span.dataset.type=b.type; span.textContent=b.content||'';
    span.addEventListener('input',()=>{ const idx=state.blocks.findIndex(x=>x.id==b.id); if(idx>=0) state.blocks[idx].content=span.textContent; debSaveBlock(b.id,b.type,span.textContent); });
    span.addEventListener('keydown',onKey);
    div.appendChild(span);
  }
  const bx=document.createElement('button'); bx.className='bx'; bx.textContent='✕'; bx.title='Eliminar bloque'; bx.onclick=()=>deleteBlock(+div.dataset.id); div.appendChild(bx);
  return div;
}

function renderBlocks(){
  const c=document.getElementById('blocks'); c.innerHTML='';
  if(!state.blocks.length){ const e=document.createElement('div'); e.className='empty'; e.textContent='Escribe / para insertar bloques, o arrastra archivos.'; c.appendChild(e); return; }
  for(const b of state.blocks) c.appendChild(blockEl(b));
}

function onKey(ev){
  if(ev.key==='/'){ ev.preventDefault(); openSlash(ev.target); }
  if(ev.key==='Enter' && !ev.shiftKey){ ev.preventDefault(); splitBlock(ev.target); }
}

function splitBlock(span){
  const bId=+span.parentElement.dataset.id;
  const curType=span.dataset.type||'text';
  // Crear local optimista (id temporal negativo)
  const tmpId=-Date.now();
  state.blocks.push({id:tmpId,type:curType,content:'',file_path:'',position:9999});
  renderBlocks();
  requestAnimationFrame(()=>{ const el=document.querySelector('.block[data-id="'+tmpId+'"] .block-content'); if(el){ el.focus(); } });
  // Guardar en servidor (no bloquea UI)
  api('/pages/'+state.currentId+'/blocks',{method:'POST',body:{type:curType,content:''}}).then(r=>{
    const b=state.blocks.find(x=>x.id===tmpId); if(b){ b.id=r.id; b.position=r.position; }
  });
}

let slashSel=0;
function openSlash(span){
  closeSlash();
  const items=[
    ['text','Texto','Párrafo normal'],
    ['h1','Encabezado 1','# Título grande'],
    ['h2','Encabezado 2','## Sección'],
    ['h3','Encabezado 3','### Subsección'],
    ['quote','Cita','> Texto destacado'],
    ['code','Código','bloque monoespaciado'],
    ['todo','Lista de tareas','☐ elemento accionable']
  ];
  const menu=document.createElement('div'); menu.className='slash';
  items.forEach((it,i)=>{
    const d=document.createElement('div'); d.className='slash-item'+(i===0?' sel':'');
    d.innerHTML='<span class="slash-ic">'+iconFor(it[0])+'</span><span class="slash-tt"><b>'+it[1]+'</b><span>'+it[2]+'</span></span>';
    d.onclick=()=>applySlash(span,it[0]); menu.appendChild(d);
  });
  const r=span.getBoundingClientRect();
  menu.style.left=r.left+'px'; menu.style.top=(r.bottom+4)+'px';
  document.body.appendChild(menu); window._slash={menu,items};
}
function closeSlash(){ if(window._slash){ window._slash.menu.remove(); window._slash=null; } }
document.addEventListener('click',e=>{ if(!window._slash) return; if(!e.target.closest('.slash')) closeSlash(); });

function applySlash(span,type){
  const bId=+span.parentElement.dataset.id;
  // Optimista: aplicar local al instante
  const b=state.blocks.find(x=>x.id===bId); if(b) b.type=type;
  closeSlash(); renderBlocks();
  // Persistir sin bloquear UI
  api('/pages/'+state.currentId+'/blocks/'+bId,{method:'PUT',body:{type,content:span.textContent}}).catch(()=>{});
}

function iconFor(t){ return {text:'¶',h1:'#',h2:'##',h3:'###',quote:'❝',code:'</>',todo:'☐'}[t]||'¶'; }
function fileIcon(p){ const e=(p.split('.').pop()||'').toLowerCase(); if(['png','jpg','jpeg','gif','webp','svg'].includes(e)) return '🖼️'; if(['mp3','wav'].includes(e)) return '🎵'; if(['mp4','mov'].includes(e)) return '🎬'; if(e==='pdf') return '📕'; if(e==='zip') return '🗜️'; return '📄'; }

async function deleteBlock(id){
  const idx=state.blocks.findIndex(b=>b.id==id); if(idx<0) return;
  await api('/pages/'+state.currentId+'/blocks/'+id,{method:'DELETE'});
  state.blocks.splice(idx,1); renderBlocks();
  if(!state.blocks.length){
    api('/pages/'+state.currentId+'/blocks',{method:'POST',body:{type:'text',content:''}}).then(r=>{
      state.blocks.push({id:r.id,type:'text',content:'',file_path:'',position:r.position}); renderBlocks();
      const el=document.querySelector('.block[data-id="'+r.id+'"] .block-content'); if(el) el.focus();
    });
  } else {
    const prev=state.blocks[idx-1];
    const el=document.querySelector('.block[data-id="'+prev.id+'"] .block-content'); if(el){ el.focus(); const r=document.createRange(); r.collapse(false); const s=getSelection(); s.removeAllRanges(); s.addRange(r); }
  }
}

function debSaveBlock(id,type,content){ clearTimeout(saveTimer); setSave('Guardando…'); saveTimer=setTimeout(()=>{ api('/pages/'+state.currentId+'/blocks/'+id,{method:'PUT',body:{type,content}}).then(()=>setSave('Guardado')); },400); }
function setSave(t){ document.getElementById('saveState').textContent=t; }

const dz=document.getElementById('dz'); const ed=document.getElementById('editor');
ed.addEventListener('dragover',e=>{ e.preventDefault(); dz.classList.add('on'); });
ed.addEventListener('dragleave',()=>dz.classList.remove('on'));
ed.addEventListener('drop',async e=>{
  e.preventDefault(); dz.classList.remove('on');
  for(const f of [...e.dataTransfer.files]){
    const fd=new FormData(); fd.append('file',f);
    try {
      const res=await fetch(API+'?r=/upload',{method:'POST',body:fd});
      const d=await res.json(); if(!res.ok) throw new Error(d.error||'error');
      await api('/pages/'+state.currentId+'/blocks',{method:'POST',body:{type:'file',content:f.name,file_path:d.file_path}});
    } catch(err){ toast('Error: '+err.message); return; }
  }
  openPage(state.currentId);
});

document.getElementById('docTitle').addEventListener('input',e=>{ clearTimeout(saveTimer); saveTimer=setTimeout(()=>api('/pages/'+state.currentId,{method:'PUT',body:{title:e.target.textContent,icon:document.getElementById('docIcon').value}}).then(()=>setSave('Guardado')),500); });
document.getElementById('docTitle').addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); api('/pages/'+state.currentId+'/blocks',{method:'POST',body:{type:'text',content:''}}).then(r=>{ state.blocks.push({id:r.id,type:'text',content:'',file_path:'',position:r.position}); renderBlocks(); const el=document.querySelector('.block[data-id="'+r.id+'"] .block-content'); if(el) el.focus(); }); } });
document.getElementById('docIcon').addEventListener('change',e=>{ api('/pages/'+state.currentId,{method:'PUT',body:{title:document.getElementById('docTitle').textContent,icon:e.target.value}}).then(()=>setSave('Guardado')); });

function toggleSide(){ document.getElementById('sidebar').classList.toggle('hidden'); }

loadPages().then(()=>{ if(!state.currentId) showEmpty(); });
</script>
</body>
</html>
