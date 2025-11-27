<?php
// mdp1_hiend.php
// Hi-End single-file PHP web app for EQ + Playlist management
// Features: Modern UI, Dark mode, EQ (10 bands) with real-time UI, Save/Load EQ and Playlists (server & localStorage), Export/Import JSON, error handling
// Requirements: PHP 7+, writable ./data/ directory (the script will try to create it)

// -------------------- Backend API handlers --------------------
$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

function respond_json($ok, $payload = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => $ok], $payload));
    exit;
}

// simple sanitize for filenames
function safe_filename($s) {
    $s = preg_replace('/[^a-zA-Z0-9_\-\. ]+/', '_', $s);
    $s = trim($s);
    if ($s === '') $s = 'file';
    return $s;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    try {
        if ($action === 'save_eq') {
            $name = isset($_POST['name']) ? safe_filename($_POST['name']) : 'eq_' . time();
            $payload = isset($_POST['payload']) ? $_POST['payload'] : null;
            if (!$payload) respond_json(false, ['error' => 'No payload']);
            $fn = "$dataDir/eq_{$name}.json";
            $written = file_put_contents($fn, $payload);
            if ($written === false) respond_json(false, ['error' => 'Failed to write file']);
            respond_json(true, ['file' => basename($fn)]);
        }

        if ($action === 'save_playlist') {
            $name = isset($_POST['name']) ? safe_filename($_POST['name']) : 'playlist_' . time();
            $payload = isset($_POST['payload']) ? $_POST['payload'] : null;
            if (!$payload) respond_json(false, ['error' => 'No payload']);
            $fn = "$dataDir/playlist_{$name}.json";
            $written = file_put_contents($fn, $payload);
            if ($written === false) respond_json(false, ['error' => 'Failed to write file']);
            respond_json(true, ['file' => basename($fn)]);
        }

        if ($action === 'delete_file') {
            $file = isset($_POST['file']) ? basename($_POST['file']) : null;
            if (!$file) respond_json(false, ['error' => 'No file']);
            $path = "$dataDir/" . $file;
            if (file_exists($path)) {
                unlink($path);
                respond_json(true);
            } else respond_json(false, ['error' => 'Not found']);
        }

        // fallback
        respond_json(false, ['error' => 'Unknown action']);

    } catch (Exception $e) {
        respond_json(false, ['error' => $e->getMessage()]);
    }
}

// Serve list of saved files (eq and playlists)
function list_saved() {
    global $dataDir;
    $files = [];
    if (!is_dir($dataDir)) return [];
    $items = scandir($dataDir);
    foreach ($items as $it) {
        if (in_array($it, ['.', '..'])) continue;
        $path = $dataDir . DIRECTORY_SEPARATOR . $it;
        $files[] = [
            'name' => $it,
            'mtime' => filemtime($path),
            'size' => filesize($path),
        ];
    }
    usort($files, function($a,$b){return $b['mtime'] - $a['mtime'];});
    return $files;
}

$savedFiles = list_saved();

// -------------------- Frontend HTML --------------------
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MDP1 Hi-End Studio — EQ & Playlist Manager</title>
<style>
:root{--bg:#0f1724;--panel:#0b1220;--muted:#9aa7b2;--accent:#62d0ff;--glass:rgba(255,255,255,0.04)}
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;color:#e6eef6;background:linear-gradient(180deg,#071021 0%, #071525 100%);padding:18px}
.container{max-width:1200px;margin:0 auto}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
.brand{display:flex;gap:12px;align-items:center}
.logo{width:56px;height:56px;border-radius:12px;background:linear-gradient(135deg,#2b6cff,#2ec4b6);display:flex;align-items:center;justify-content:center;font-weight:700;color:white}
.h1{font-size:20px}
.controls{display:flex;gap:8px;align-items:center}
.btn{background:var(--glass);border:1px solid rgba(255,255,255,0.04);padding:8px 12px;border-radius:10px;color:var(--muted);cursor:pointer}
.btn.primary{background:linear-gradient(90deg,#38bdf8,#60a5fa);color:#051123;border:none}
.grid{display:grid;grid-template-columns:1fr 380px;gap:16px}
.card{background:rgba(255,255,255,0.03);border-radius:12px;padding:14px;box-shadow:0 6px 18px rgba(2,6,23,0.6);border:1px solid rgba(255,255,255,0.02)}
.eq-sliders{display:grid;grid-template-columns:repeat(10,1fr);gap:8px;padding:8px}
.slider-wrap{display:flex;flex-direction:column;align-items:center}
.slider{writing-mode: bt-lr; -webkit-appearance: none; width:120px; height:6px; transform: rotate(-90deg); margin-bottom:6px}
.slider::-webkit-slider-thumb{ -webkit-appearance: none; width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.4)}
.slider-label{font-size:12px;color:var(--muted)}
.player{display:flex;flex-direction:column;gap:8px}
.playlist{max-height:360px;overflow:auto}
.playlist-item{display:flex;align-items:center;gap:8px;padding:8px;border-radius:8px;margin-bottom:6px;background:rgba(255,255,255,0.02)}
.small{font-size:12px;color:var(--muted)}
.footer{margin-top:16px;text-align:center;color:var(--muted);font-size:13px}
.file-list{max-height:160px;overflow:auto}
.switch{display:inline-flex;align-items:center;gap:8px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="brand">
      <div class="logo">MD</div>
      <div>
        <div class="h1">MDP1 Hi‑End Studio</div>
        <div class="small">Studio UI • EQ Focus • Save / Load / Export / Import</div>
      </div>
    </div>

    <div class="controls">
      <label class="switch small"><input id="darkToggle" type="checkbox"> Dark Mode</label>
      <button class="btn" id="btnReset">Reset EQ</button>
      <button class="btn primary" id="btnSaveEq">Save EQ</button>
    </div>
  </div>

  <div class="grid">
    <div>
      <div class="card">
        <h3>Equalizer — 10 bands</h3>
        <div class="eq-sliders" id="eqContainer"></div>
        <div style="display:flex;justify-content:space-between;margin-top:10px">
          <div class="small">Drag sliders to adjust gain. Values shown in dB.</div>
          <div class="small">Preset: <select id="presetSelect"><option value="flat">Flat</option><option value="rock">Rock</option><option value="bass">Boost Bass</option><option value="vocal">Vocal</option></select></div>
        </div>
      </div>

      <div class="card" style="margin-top:12px">
        <h3>Live Player & Playlist</h3>
        <div class="player">
          <audio id="audio" controls style="width:100%"></audio>
          <div style="display:flex;gap:8px;margin-top:6px">
            <input id="fileInput" type="file" accept="audio/*" class="btn" style="padding:6px">
            <input id="trackTitle" placeholder="Track title (optional)" style="flex:1;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,0.04);background:transparent;color:inherit">
            <button class="btn primary" id="btnAddTrack">Add</button>
          </div>

          <div class="playlist" id="playlist"></div>

          <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
            <button class="btn" id="btnSavePlaylist">Save Playlist (Server)</button>
            <button class="btn" id="btnExportPlaylist">Export JSON</button>
            <input id="importPlaylistFile" type="file" accept="application/json" style="display:none">
            <button class="btn" id="btnImportPlaylist">Import JSON</button>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:12px">
        <h3>Saved Files on Server</h3>
        <div class="file-list" id="fileList">
          <?php foreach ($savedFiles as $f): ?>
            <div style="display:flex;justify-content:space-between;gap:8px;padding:8px;border-radius:8px;margin-bottom:6px;background:rgba(255,255,255,0.02)">
              <div>
                <div style="font-weight:600"><?=htmlspecialchars($f['name'])?></div>
                <div class="small"><?=date('Y-m-d H:i:s', $f['mtime'])?> • <?=round($f['size']/1024,2)?> KB</div>
              </div>
              <div style="display:flex;flex-direction:column;gap:6px">
                <a class="btn" href="data/<?=rawurlencode($f['name'])?>" download>Download</a>
                <button class="btn" data-file="<?=htmlspecialchars($f['name'])?>" onclick="deleteFile(this)">Delete</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <div>
      <div class="card">
        <h3>Quick Controls</h3>
        <div style="display:flex;flex-direction:column;gap:8px">
          <div class="small">Master gain</div>
          <input id="masterGain" type="range" min="0" max="200" value="100" class="slider" style="transform:none;width:100%">
          <div style="display:flex;gap:8px;align-items:center">
            <button class="btn" id="btnLoadEq">Load EQ From Server</button>
            <button class="btn" id="btnLoadEqLocal">Load EQ From Local</button>
          </div>

          <hr>
          <h4>Export / Import</h4>
          <div style="display:flex;gap:8px">
            <button class="btn" id="btnExportEq">Export EQ JSON</button>
            <input id="importEqFile" type="file" accept="application/json" style="display:none">
            <button class="btn" id="btnImportEq">Import EQ JSON</button>
          </div>

          <hr>
          <div class="small">Notes</div>
          <div class="small">Server saves are stored in <code>/data</code> folder. Ensure it is writable by PHP.</div>
        </div>
      </div>

      <div class="card" style="margin-top:12px">
        <h3>About</h3>
        <div class="small">MDP1 Hi‑End Studio — single-file upgrade. Responsive UI, local/session saving, server-side saves, export/import JSON. Recommended PHP 7.4+</div>
      </div>
    </div>
  </div>

  <div class="footer">Built for high-end studio workflows • Make backups before writing to server</div>
</div>

<script>
// -------------------- UI Logic --------------------
const bands = [31, 62, 125, 250, 500, 1000, 2000, 4000, 8000, 16000];
const eqContainer = document.getElementById('eqContainer');
const presetSelect = document.getElementById('presetSelect');
const audio = document.getElementById('audio');
const playlistEl = document.getElementById('playlist');
let playlist = JSON.parse(localStorage.getItem('mdp_playlist') || '[]');
let eqState = JSON.parse(localStorage.getItem('mdp_eq') || 'null') || bands.map(b=>({f:b,g:0}));

function createSliders(){
  eqContainer.innerHTML='';
  eqState.forEach((band, idx)=>{
    const wrap = document.createElement('div'); wrap.className='slider-wrap';
    const input = document.createElement('input'); input.type='range'; input.min='-12'; input.max='12'; input.value=band.g; input.step='0.5'; input.className='slider';
    input.dataset.idx = idx;
    input.addEventListener('input', onSliderChange);
    const lbl = document.createElement('div'); lbl.className='slider-label'; lbl.innerText = bands[idx] + ' Hz (' + band.g + ' dB)';
    wrap.appendChild(input); wrap.appendChild(lbl);
    eqContainer.appendChild(wrap);
  });
}

function onSliderChange(e){
  const idx = +e.target.dataset.idx;
  const val = parseFloat(e.target.value);
  eqState[idx].g = val;
  e.target.nextSibling.innerText = bands[idx] + ' Hz (' + val + ' dB)';
  saveEqLocal();
}

function saveEqLocal(){ localStorage.setItem('mdp_eq', JSON.stringify(eqState)); }

function resetEq(){ eqState = bands.map(b=>({f:b,g:0})); createSliders(); saveEqLocal(); }

presetSelect.addEventListener('change', ()=>{
  const p = presetSelect.value;
  if (p === 'flat') resetEq();
  if (p === 'rock') { eqState = eqState.map((b,i)=>({f:bands[i], g: [4,3,2,0,-1,-1,1,2,3,4][i] })); }
  if (p === 'bass') { eqState = eqState.map((b,i)=>({f:bands[i], g: i<3?5:0})); }
  if (p === 'vocal') { eqState = eqState.map((b,i)=>({f:bands[i], g: [ -2,-1,0,1,3,4,3,1,-1,-2 ][i] })); }
  createSliders(); saveEqLocal();
});

// Playlist UI
function renderPlaylist(){
  playlistEl.innerHTML='';
  playlist.forEach((t, i)=>{
    const it = document.createElement('div'); it.className='playlist-item';
    const left = document.createElement('div'); left.style.flex='1';
    left.innerHTML = `<div style="font-weight:600">${t.title||t.name}</div><div class="small">${t.name}</div>`;
    const right = document.createElement('div');
    const btnPlay = document.createElement('button'); btnPlay.className='btn'; btnPlay.innerText='Play'; btnPlay.onclick=()=>playTrack(i);
    const btnRemove = document.createElement('button'); btnRemove.className='btn'; btnRemove.innerText='Remove'; btnRemove.onclick=()=>{playlist.splice(i,1); savePlaylistLocal(); renderPlaylist();};
    right.appendChild(btnPlay); right.appendChild(btnRemove);
    it.appendChild(left); it.appendChild(right);
    playlistEl.appendChild(it);
  });
}

function playTrack(i){ const t = playlist[i]; if (!t) return; if (t.url) { audio.src = t.url; audio.play(); } else if (t.file) { // blob URL
    audio.src = t.file; audio.play(); }
}

function savePlaylistLocal(){ localStorage.setItem('mdp_playlist', JSON.stringify(playlist)); }

// add track (from file input)
document.getElementById('btnAddTrack').addEventListener('click', ()=>{
  const input = document.getElementById('fileInput');
  if (!input.files || input.files.length === 0) { alert('กรุณาเลือกไฟล์เสียง'); return; }
  const f = input.files[0];
  const title = document.getElementById('trackTitle').value || f.name;
  const url = URL.createObjectURL(f);
  playlist.push({name: f.name, title, url, size: f.size});
  savePlaylistLocal(); renderPlaylist(); input.value=''; document.getElementById('trackTitle').value='';
});

// export playlist to JSON
document.getElementById('btnExportPlaylist').addEventListener('click', ()=>{
  const blob = new Blob([JSON.stringify(playlist, null, 2)], {type:'application/json'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'playlist_' + Date.now() + '.json'; a.click();
});

// import playlist JSON
document.getElementById('btnImportPlaylist').addEventListener('click', ()=>document.getElementById('importPlaylistFile').click());
document.getElementById('importPlaylistFile').addEventListener('change', (ev)=>{
  const f = ev.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = ()=>{
    try { const data = JSON.parse(r.result); if (!Array.isArray(data)) throw 'Invalid format';
      // Map imported items to local structure (can't import blob URLs)
      const mapped = data.map(it=>({name: it.name||it.title||'track', title: it.title||it.name||'track', url: it.url||null, size: it.size||0}));
      playlist = playlist.concat(mapped); savePlaylistLocal(); renderPlaylist();
    } catch (e) { alert('Invalid JSON: '+e); }
  }; r.readAsText(f);
});

// Server save playlist
document.getElementById('btnSavePlaylist').addEventListener('click', async ()=>{
  const name = prompt('ชื่อไฟล์ที่ต้องการบันทึกบนเซิร์ฟเวอร์ (ไม่ใส่เพื่อใช้ timestamp)');
  // create payload but strip blob URLs to avoid large data; only include name/title and if a remote url exists
  const payload = playlist.map(p=>({name:p.name,title:p.title,url:p.url && p.url.startsWith('blob:')?null:p.url,size:p.size}));
  const form = new FormData(); form.append('action','save_playlist'); form.append('name', name || 'playlist_' + Date.now()); form.append('payload', JSON.stringify(payload));
  const res = await fetch(location.href, {method:'POST', body: form});
  const j = await res.json(); if (j.ok) { alert('Saved to server: ' + j.file); location.reload(); } else alert('Save failed: ' + (j.error||'unknown'));
});

// Save EQ to server
document.getElementById('btnSaveEq').addEventListener('click', async ()=>{
  const name = prompt('ชื่อ EQ (server)');
  const form = new FormData(); form.append('action','save_eq'); form.append('name', name || 'eq_' + Date.now()); form.append('payload', JSON.stringify(eqState));
  const res = await fetch(location.href, {method:'POST', body: form});
  const j = await res.json(); if (j.ok) { alert('EQ saved: ' + j.file); location.reload(); } else alert('Save failed: ' + (j.error||'unknown'));
});

// Export EQ JSON locally
document.getElementById('btnExportEq').addEventListener('click', ()=>{
  const blob = new Blob([JSON.stringify(eqState, null, 2)], {type:'application/json'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'eq_' + Date.now() + '.json'; a.click();
});

// Import EQ
document.getElementById('btnImportEq').addEventListener('click', ()=>document.getElementById('importEqFile').click());
document.getElementById('importEqFile').addEventListener('change', (ev)=>{
  const f = ev.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = ()=>{
    try { const data = JSON.parse(r.result); if (!Array.isArray(data)) throw 'Invalid EQ format'; eqState = data; createSliders(); saveEqLocal(); } catch (e) { alert('Invalid EQ JSON: '+e); }
  }; r.readAsText(f);
});

// Load EQ from localStorage
document.getElementById('btnLoadEqLocal').addEventListener('click', ()=>{
  const local = JSON.parse(localStorage.getItem('mdp_eq') || 'null'); if (!local) return alert('No EQ saved locally'); eqState = local; createSliders();
});

// Dark mode
const darkToggle = document.getElementById('darkToggle');
darkToggle.addEventListener('change', ()=>{
  if (darkToggle.checked) document.body.style.background = 'linear-gradient(180deg,#000814 0%, #071525 100%)'; else document.body.style.background = 'linear-gradient(180deg,#071021 0%, #071525 100%)';
});

document.getElementById('btnReset').addEventListener('click', ()=>{ if (confirm('Reset EQ to flat?')) { resetEq(); }});

document.getElementById('btnLoadEq').addEventListener('click', ()=>{
  const serverFile = prompt('ระบุชื่อไฟล์ EQ บนเซิร์ฟเวอร์ (เช่น eq_myname.json)');
  if (!serverFile) return;
  fetch('data/' + encodeURIComponent(serverFile)).then(r=>r.json()).then(json=>{ if (!Array.isArray(json)) return alert('ไฟล์ไม่ถูกต้อง'); eqState = json; createSliders(); saveEqLocal(); }).catch(e=>alert('Load failed: '+e));
});

// delete file
async function deleteFile(btn){
  if (!confirm('Delete file?')) return; const file = btn.dataset.file; const form = new FormData(); form.append('action','delete_file'); form.append('file', file);
  const r = await fetch(location.href, {method:'POST', body: form}); const j = await r.json(); if (j.ok) location.reload(); else alert('Delete failed: '+(j.error||'unknown'));
}

// initialize
createSliders(); renderPlaylist();

// master gain simple UI (no audio graph processing here)
document.getElementById('masterGain').addEventListener('input', (e)=>{ document.querySelector('.small').innerText = 'Master gain: ' + (e.target.value - 100) + ' % (UI only)'; });

// load saved eq to UI on start
(function(){
  // ensure eqState length
  if (!Array.isArray(eqState) || eqState.length !== bands.length) eqState = bands.map(b=>({f:b,g:0}));
  createSliders();
})();
</script>
</body>
</html>
