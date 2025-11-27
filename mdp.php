<?php
$mediaDir = __DIR__ . '/media';
if(!is_dir($mediaDir)) mkdir($mediaDir,0755,true);

$allowedExt = ['mp3','wav','m4a','ogg','mp4','webm'];
$files = [];
foreach(scandir($mediaDir) as $f){
    if($f[0]=='.') continue;
    $ext = strtolower(pathinfo($f,PATHINFO_EXTENSION));
    if(in_array($ext,$allowedExt)) $files[] = $f;
}
$playlist = [];
foreach($files as $f){
    $title=pathinfo($f,PATHINFO_FILENAME); $artist='';
    if(strpos($title,' - ')!==false) list($title,$artist)=array_map('trim',explode(' - ',$title,2));
    $playlist[]=['file'=>'media/'.rawurlencode($f),'title'=>$title,'artist'=>$artist,'raw'=>$f];
}

// Upload handler with Auto-Play
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['file'])){
    $f = $_FILES['file'];
    $safeName = preg_replace('/[^A-Za-z0-9\p{L}_\-\. ]/u','_',$f['name']);
    $target = $mediaDir.'/'.time().'_'.$safeName;
    $ext = strtolower(pathinfo($safeName,PATHINFO_EXTENSION));
    if(!in_array($ext,$allowedExt)){ http_response_code(400); echo json_encode(['success'=>false,'msg'=>'นามสกุลไฟล์ไม่รองรับ']); exit; }
    $type = mime_content_type($f['tmp_name']);
    if(!preg_match('/^(audio|video)\//',$type)){ http_response_code(400); echo json_encode(['success'=>false,'msg'=>'ไฟล์ไม่ใช่ audio/video']); exit; }
    if(move_uploaded_file($f['tmp_name'],$target)) {
        echo json_encode(['success'=>true,'file'=>'media/'.rawurlencode(basename($target))]);
    } else { http_response_code(500); echo json_encode(['success'=>false,'msg'=>'บันทึกไฟล์ล้มเหลว']); }
    exit;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PHP DJ Studio Neon Player Pro</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#0f1724;--card:rgba(15,23,36,0.85);--accent:#7b61ff;--accent2:#00f0ff;--muted:rgba(255,255,255,0.6);}
body{margin:0;min-height:100vh;background:radial-gradient(circle,#071027 0%,var(--bg) 80%);font-family:'Prompt',sans-serif;color:#cfe8ff;display:flex;justify-content:center;padding:20px;}
.player-wrap{width:980px;max-width:96%;background:var(--card);border-radius:18px;padding:18px;box-shadow:0 10px 30px rgba(3,6,23,0.7);border:1px solid rgba(255,255,255,0.04);}
.header{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;}
.logo{width:64px;height:64px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;color:white;}
.title h1{margin:0;font-size:18px;}
.title p{margin:4px 0 0 0;color:var(--muted);font-size:13px;}
.main{display:grid;grid-template-columns:1fr 320px;gap:14px;}
.left{background:rgba(255,255,255,0.02);padding:14px;border-radius:12px;}
.right{background:rgba(255,255,255,0.02);padding:14px;border-radius:12px;}
.meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.meta .info{flex:1;}
.meta .info h2{margin:0;font-size:16px;}
.meta .info p{margin:3px 0 0 0;color:var(--muted);font-size:13px;}
.controls{display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;}
.btn{background:transparent;border:1px solid rgba(255,255,255,0.06);padding:8px 12px;border-radius:10px;cursor:pointer;color:inherit;}
.btn.play{background:var(--accent);border:0;color:#071027;padding:8px 12px;font-weight:700;}
input[type=range]{-webkit-appearance:none;background:transparent;width:100%;}
input[type=range]::-webkit-slider-runnable-track{height:8px;border-radius:8px;background:linear-gradient(90deg,var(--accent),var(--accent2));}
input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;border-radius:50%;background:white;margin-top:-4px;}
canvas{width:100%;height:140px;border-radius:8px;background:linear-gradient(180deg, rgba(11,18,34,0.6), rgba(6,11,20,0.6));display:block;}
.playlist{max-height:320px;overflow:auto;margin-top:12px;padding-right:6px;}
.track{padding:8px;border-radius:10px;margin-bottom:8px;display:flex;gap:8px;align-items:center;cursor:pointer;border:1px solid transparent;transition:0.2s;}
.track:hover{background:rgba(255,255,255,0.02);transform:translateX(2px);}
.track.active{background:linear-gradient(90deg, rgba(123,97,255,0.12), rgba(0,240,255,0.06));border:1px solid rgba(123,97,255,0.14);}
.eq-mixer{display:flex;gap:14px;justify-content:center;align-items:end;padding:10px;background:rgba(0,0,0,0.1);border-radius:12px;margin-top:12px;}
.eq-mixer .band{display:flex;flex-direction:column;align-items:center;position:relative;width:50px;}
.eq-mixer .band input[type=range]{-webkit-appearance:none;width:50px;height:180px;writing-mode:bt-lr;transform:rotate(-90deg);background:linear-gradient(90deg,#444,#222);border-radius:6px;}
.eq-mixer .band input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;border-radius:50%;background:var(--accent);cursor:pointer;border:2px solid #fff;}
.eq-mixer .band .label{margin-top:6px;text-align:center;font-size:12px;color:#cfe8ff;}
.eq-mixer .band .gain{font-weight:700;color:var(--accent);}
.eq-mixer .band .meter{position:absolute;bottom:30px;width:8px;height:160px;background:rgba(255,255,255,0.05);border-radius:4px;overflow:hidden;box-shadow:0 0 6px rgba(123,97,255,0.3) inset;}
.eq-mixer .band .meter-fill{position:absolute;bottom:0;width:100%;height:0%;background:linear-gradient(to top,var(--accent),var(--accent2));border-radius:4px;transition:height 0.05s, box-shadow 0.05s;}
.preset-row{display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap;}
.drop{border:2px dashed rgba(255,255,255,0.03);padding:12px;border-radius:10px;text-align:center;color:var(--muted);cursor:pointer;}
.modes{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;}
@media (max-width:880px){.main{grid-template-columns:1fr}.right{order:2}}
</style>
</head>
<body>
<div class="player-wrap">
  <div class="header">
    <div class="logo">DJ</div>
    <div class="title">
      <h1>PHP DJ Neon Player Pro</h1>
      <p>Mixer EQ • Neon Meter • Visualizer • Playlist</p>
    </div>
  </div>
  <div class="main">
    <div class="left">
      <div class="meta">
        <div style="width:64px;height:64px;border-radius:10px;background:linear-gradient(135deg,#1e293b,#0f1724);display:flex;align-items:center;justify-content:center;font-weight:700;color:#cfe8ff">🎵</div>
        <div class="info">
          <h2 id="nowTitle">- ไม่มีเพลง -</h2>
          <p id="nowArtist">-</p>
        </div>
        <div style="text-align:right">
          <div class="small">Volume</div>
          <input id="vol" type="range" min="0" max="1" step="0.01" value="0.9" style="width:120px">
        </div>
      </div>
      <div class="controls">
        <button class="btn" id="prevBtn">⏮ Prev</button>
        <button class="btn play" id="playBtn">▶️ Play</button>
        <button class="btn" id="nextBtn">Next ⏭</button>
        <button class="btn" id="clearBtn">🗑 Clear Playlist</button>
        <div style="flex:1"></div>
        <div class="small">เวลา <span id="curTime">00:00</span> / <span id="dur">00:00</span></div>
      </div>
      <div style="margin-top:10px">
        <input id="seek" type="range" min="0" max="100" value="0">
      </div>

      <!-- Mixer EQ + Neon Meter -->
      <div class="eq-mixer">
        <div class="band" data-band="low"><div class="meter"><div class="meter-fill"></div></div><input type="range" min="-12" max="12" value="0" step="0.5"><div class="label">Low<br><span class="gain">0</span>dB</div></div>
        <div class="band" data-band="lowMid"><div class="meter"><div class="meter-fill"></div></div><input type="range" min="-12" max="12" value="0" step="0.5"><div class="label">LowMid<br><span class="gain">0</span>dB</div></div>
        <div class="band" data-band="mid"><div class="meter"><div class="meter-fill"></div></div><input type="range" min="-12" max="12" value="0" step="0.5"><div class="label">Mid<br><span class="gain">0</span>dB</div></div>
        <div class="band" data-band="highMid"><div class="meter"><div class="meter-fill"></div></div><input type="range" min="-12" max="12" value="0" step="0.5"><div class="label">HighMid<br><span class="gain">0</span>dB</div></div>
        <div class="band" data-band="high"><div class="meter"><div class="meter-fill"></div></div><input type="range" min="-12" max="12" value="0" step="0.5"><div class="label">High<br><span class="gain">0</span>dB</div></div>
      </div>

      <div class="preset-row">
        <div class="small">Preset EQ:</div>
        <select id="presetSelect" class="btn">
          <option value="flat">Flat</option>
          <option value="rock">Rock</option>
          <option value="pop">Pop</option>
          <option value="jazz">Jazz</option>
          <option value="classical">Classical</option>
          <option value="electronic">Electronic</option>
          <option value="hiphop">HipHop</option>
          <option value="vocal">Vocal</option>
        </select>
        <div style="flex:1"></div>
        <div class="small">Visualizer:</div>
        <div class="modes">
          <button class="btn" data-mode="bars">Bars</button>
          <button class="btn" data-mode="wave">Waveform</button>
          <button class="btn" data-mode="circle">Circle</button>
        </div>
      </div>
      <canvas id="viz" width="800" height="140"></canvas>
      <div class="playlist" id="playlist"></div>
    </div>

    <div class="right panel">
      <h3>Upload / Playlist</h3>
      <div class="drop" id="drop">ลากไฟล์มาที่นี่หรือคลิกเพื่ออัปโหลด</div>
      <form id="uploadForm" method="post" enctype="multipart/form-data">
        <input type="file" id="fileInput" name="file" accept=".mp3,.wav,.m4a,.ogg,.mp4,.webm,audio/*,video/*">
        <label class="btn" for="fileInput">เลือกไฟล์</label>
        <button class="btn" type="submit">อัปโหลด</button>
      </form>
      <footer style="margin-top:12px">สร้างโดย PHP + WebAudio API + Neon Glow 🎶</footer>
    </div>
  </div>
  <audio id="mediaElem" crossorigin="anonymous"></audio>

<script>
// ================== JS Core + Neon Visualizer ==================
const PLAYLIST=<?php echo json_encode($playlist,JSON_UNESCAPED_UNICODE); ?>;
const audio=document.getElementById('mediaElem');
let currentIndex=0,isPlaying=false,vizMode='bars';
const playBtn=document.getElementById('playBtn');
const prevBtn=document.getElementById('prevBtn');
const nextBtn=document.getElementById('nextBtn');
const clearBtn=document.getElementById('clearBtn');
const nowTitle=document.getElementById('nowTitle');
const nowArtist=document.getElementById('nowArtist');
const playlistEl=document.getElementById('playlist');
const vol=document.getElementById('vol');
const seek=document.getElementById('seek');
const curTimeEl=document.getElementById('curTime');
const durEl=document.getElementById('dur');
const presetSelect=document.getElementById('presetSelect');
const vizCanvas=document.getElementById('viz');
const vizCtx=vizCanvas.getContext('2d');

const audioCtx=new (window.AudioContext||window.webkitAudioContext)();
const mediaSource=audioCtx.createMediaElementSource(audio);
const masterGain=audioCtx.createGain(); masterGain.gain.value=parseFloat(vol.value);
const analyser=audioCtx.createAnalyser(); analyser.fftSize=2048;

// EQ
const eqNodes={};
eqNodes.low=audioCtx.createBiquadFilter(); eqNodes.low.type='lowshelf'; eqNodes.low.frequency.value=100;
eqNodes.lowMid=audioCtx.createBiquadFilter(); eqNodes.lowMid.type='peaking'; eqNodes.lowMid.frequency.value=250; eqNodes.lowMid.Q.value=1;
eqNodes.mid=audioCtx.createBiquadFilter(); eqNodes.mid.type='peaking'; eqNodes.mid.frequency.value=1000; eqNodes.mid.Q.value=1;
eqNodes.highMid=audioCtx.createBiquadFilter(); eqNodes.highMid.type='peaking'; eqNodes.highMid.frequency.value=4000; eqNodes.highMid.Q.value=1;
eqNodes.high=audioCtx.createBiquadFilter(); eqNodes.high.type='highshelf'; eqNodes.high.frequency.value=8000;

// Connect chain
mediaSource.connect(eqNodes.low);
eqNodes.low.connect(eqNodes.lowMid);
eqNodes.lowMid.connect(eqNodes.mid);
eqNodes.mid.connect(eqNodes.highMid);
eqNodes.highMid.connect(eqNodes.high);
eqNodes.high.connect(masterGain);
masterGain.connect(analyser);
analyser.connect(audioCtx.destination);

// EQ controls + Neon Meter
const eqBands=['low','lowMid','mid','highMid','high'];
const bandElems={};
eqBands.forEach(b=>{
  const band=document.querySelector(`.band[data-band="${b}"]`);
  const input=band.querySelector('input');
  const gainLabel=band.querySelector('.gain');
  const fill=band.querySelector('.meter-fill');
  bandElems[b]={input,gainLabel,fill};
  input.addEventListener('input',()=>{gainLabel.textContent=input.value; eqNodes[b].gain.value=parseFloat(input.value);});
});
vol.addEventListener('input',()=>{masterGain.gain=parseFloat(vol.value);});

// Presets
const PRESETS={
  'flat':[0,0,0,0,0],
  'rock':[5,3,0,3,5],
  'pop':[3,4,0,3,4],
  'jazz':[4,1,0,1,3],
  'classical':[2,0,0,0,3],
  'electronic':[5,3,0,3,5],
  'hiphop':[6,3,0,3,6],
  'vocal':[0,2,0,2,0]
};
function applyPreset(name){
  const v=PRESETS[name]||PRESETS['flat'];
  eqBands.forEach((b,i)=>{bandElems[b].input.value=v[i];bandElems[b].gainLabel.textContent=v[i]; eqNodes[b].gain.value=v[i];});
}
presetSelect.addEventListener('change',e=>applyPreset(e.target.value));

// Playlist functions
function escapeHtml(s){return(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function renderPlaylist(){playlistEl.innerHTML='';PLAYLIST.forEach((t,i)=>{const div=document.createElement('div'); div.className='track'; div.dataset.index=i; div.innerHTML=`<div>${escapeHtml(t.title)}</div><div style="flex:1"></div><div style="color:var(--muted)">${escapeHtml(t.artist)}</div>`; div.addEventListener('click',()=>{currentIndex=i;loadTrack(i);playTrack();}); playlistEl.appendChild(div);});}
function updatePlaylistHighlight(){document.querySelectorAll('.track').forEach((d,i)=>d.classList.toggle('active',i===currentIndex)); const el=document.querySelector(`.track[data-index="${currentIndex}"]`); if(el) el.scrollIntoView({behavior:'smooth',block:'nearest'});}
function loadTrack(i){audio.src=PLAYLIST[i].file; nowTitle.textContent=PLAYLIST[i].title; nowArtist.textContent=PLAYLIST[i].artist; updatePlaylistHighlight();}
function playTrack(){audioCtx.resume(); audio.play(); isPlaying=true; playBtn.textContent='⏸ Pause';}
function pauseTrack(){audio.pause(); isPlaying=false; playBtn.textContent='▶️ Play';}
playBtn.addEventListener('click',()=>{isPlaying?pauseTrack():playTrack();});
prevBtn.addEventListener('click',()=>{currentIndex=(currentIndex-1+PLAYLIST.length)%PLAYLIST.length; loadTrack(currentIndex); playTrack();});
nextBtn.addEventListener('click',()=>{currentIndex=(currentIndex+1)%PLAYLIST.length; loadTrack(currentIndex); playTrack();});
clearBtn.addEventListener('click',()=>{PLAYLIST.length=0;playlistEl.innerHTML='';audio.pause();audio.src='';nowTitle.textContent='- ไม่มีเพลง -'; nowArtist.textContent='-';});

// Time & Seek
audio.addEventListener('timeupdate',()=>{seek.value=audio.currentTime/audio.duration*100||0; curTimeEl.textContent=formatTime(audio.currentTime); durEl.textContent=formatTime(audio.duration);});
seek.addEventListener('input',()=>{audio.currentTime=seek.value/100*audio.duration;});
function formatTime(sec){if(!sec||isNaN(sec))return'00:00'; const m=Math.floor(sec/60),s=Math.floor(sec%60); return`${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`}

// Visualizer Neon
const dataArray=new Uint8Array(analyser.frequencyBinCount);
function drawViz(){
  requestAnimationFrame(drawViz);
  analyser.getByteFrequencyData(dataArray);
  vizCtx.clearRect(0,0,vizCanvas.width,vizCanvas.height);

  if(vizMode==='bars'){
    const barWidth=vizCanvas.width/dataArray.length*2.5;
    dataArray.forEach((v,i)=>{
      const h=v/255*vizCanvas.height;
      vizCtx.shadowColor='#7b61ff';
      vizCtx.shadowBlur=12;
      vizCtx.fillStyle=`hsl(${i/dataArray.length*360},100%,65%)`;
      vizCtx.fillRect(i*barWidth,vizCanvas.height-h,barWidth,h);
    });
  }

  // EQ Neon Meter
  eqBands.forEach((b,i)=>{
    const [fMin,fMax]=[[20,150],[150,500],[500,2000],[2000,6000],[6000,20000]][i];
    const startBin=Math.floor(fMin/analyser.context.sampleRate*analyser.frequencyBinCount);
    const endBin=Math.floor(fMax/analyser.context.sampleRate*analyser.frequencyBinCount);
    let sum=0,count=0;
    for(let j=startBin;j<=endBin;j++){sum+=dataArray[j]; count++;}
    const avg=count>0?sum/count:0;
    const height=Math.min(100,avg/255*100);
    bandElems[b].fill.style.height=height+'%';
    const peakGlow=avg>240?20:Math.min(1,avg/255)*12;
    bandElems[b].fill.style.boxShadow=`0 0 ${peakGlow}px #7b61ff,0 0 ${peakGlow/1.5}px #00f0ff inset`;
  });
}
drawViz();

// Visualizer mode buttons
document.querySelectorAll('.modes button').forEach(b=>{b.addEventListener('click',()=>{vizMode=b.dataset.mode;});});

// Drag & Drop Upload + Auto-Play
const drop=document.getElementById('drop');
const uploadForm=document.getElementById('uploadForm');
const fileInput=document.getElementById('fileInput');
drop.addEventListener('click',()=>fileInput.click());
drop.addEventListener('dragover',e=>{e.preventDefault(); drop.style.background='rgba(255,255,255,0.05)';});
drop.addEventListener('dragleave',e=>{drop.style.background='transparent';});
drop.addEventListener('drop',e=>{e.preventDefault(); fileInput.files=e.dataTransfer.files; uploadForm.dispatchEvent(new Event('submit'));});
uploadForm.addEventListener('submit',e=>{
  e.preventDefault();
  const f=fileInput.files[0]; if(!f) return;
  const data=new FormData(); data.append('file',f);
  fetch('',{method:'POST',body:data}).then(r=>r.json()).then(res=>{
    if(res.success){ PLAYLIST.push({file:res.file,title:res.file,artist:'-'}); renderPlaylist(); currentIndex=PLAYLIST.length-1; loadTrack(currentIndex); playTrack(); }
    else alert(res.msg||'Upload fail');
  }).catch(err=>alert('Upload fail'));
});

// Init
if(PLAYLIST.length>0){ loadTrack(0); renderPlaylist(); }

</script>
</body>
</html>
