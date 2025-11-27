<?php
$mediaDir = __DIR__ . '/media';
$files = [];
if(is_dir($mediaDir)){
    $allowedExt = ['mp3','wav','m4a','ogg','mp4'];
    foreach(scandir($mediaDir) as $f){
        if(!$f || $f[0]=='.') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if(in_array($ext,$allowedExt)) $files[] = $f;
    }
}
$playlist = [];
foreach($files as $f){
    $playlist[] = ['file'=>'media/'.rawurlencode($f),'title'=>$f,'artist'=>''];
}

// Upload Handler
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['file'])){
    if(!is_dir($mediaDir)) mkdir($mediaDir,0755,true);
    $f = $_FILES['file'];
    $safeName = preg_replace('/[^A-Za-z0-9\p{L}_\-\. ]/u','_',$f['name']);
    $target = $mediaDir . DIRECTORY_SEPARATOR . time().'_'.$safeName;
    move_uploaded_file($f['tmp_name'],$target);
    exit('อัปโหลดสำเร็จ');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DJ Studio Pro</title>
<style>
body{margin:0;background:#0a0f1f;color:#e6eef8;font-family:sans-serif;display:flex;justify-content:center;padding:20px}
.player{display:flex;gap:24px;flex-wrap:wrap}
.left, .right{background:rgba(255,255,255,0.03);padding:16px;border-radius:12px}
.left{flex:1;position:relative}
.right{width:280px}
canvas{width:100%;height:200px;background:rgba(0,0,0,0.2);border-radius:8px}
.eq-mixer{display:flex;align-items:flex-end;gap:12px;margin-top:12px;height:180px}
.eq-band{display:flex;flex-direction:column;align-items:center;gap:4px}
.eq-band input[type=range]{-webkit-appearance:none;width:36px;height:160px;transform:rotate(-180deg);background:linear-gradient(90deg,#7b61ff,#00c2ff);border-radius:8px}
.eq-band input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:16px;height:16px;background:#fff;border-radius:50%;cursor:pointer}
.peak{width:36px;height:6px;background:#ff00ff;border-radius:3px;margin-bottom:2px;opacity:0;transition:opacity 0.05s linear}
.track{padding:6px;margin:4px 0;border-radius:8px;cursor:pointer}
.track.active{background:rgba(123,97,255,0.12)}
.btn{padding:6px 10px;border-radius:6px;border:none;background:#7b61ff;color:#fff;cursor:pointer;margin:2px}
</style>
</head>
<body>
<div class="player">
  <div class="left">
    <h3>Now Playing</h3>
    <div id="nowTrack">- ไม่มีเพลง -</div>
    <canvas id="viz"></canvas>

    <div class="eq-mixer">
      <div class="eq-band">
        <div class="peak" id="peak0"></div>
        <input type="range" min="-12" max="12" value="0" step="0.1" id="band0">
        <div>60Hz</div>
      </div>
      <div class="eq-band">
        <div class="peak" id="peak1"></div>
        <input type="range" min="-12" max="12" value="0" step="0.1" id="band1">
        <div>170Hz</div>
      </div>
      <div class="eq-band">
        <div class="peak" id="peak2"></div>
        <input type="range" min="-12" max="12" value="0" step="0.1" id="band2">
        <div>1kHz</div>
      </div>
      <div class="eq-band">
        <div class="peak" id="peak3"></div>
        <input type="range" min="-12" max="12" value="0" step="0.1" id="band3">
        <div>3kHz</div>
      </div>
      <div class="eq-band">
        <div class="peak" id="peak4"></div>
        <input type="range" min="-12" max="12" value="0" step="0.1" id="band4">
        <div>6kHz</div>
      </div>
    </div>

    <div style="margin-top:10px">
      <select id="preset">
        <option value="flat">Flat</option>
        <option value="rock">Rock</option>
        <option value="pop">Pop</option>
        <option value="jazz">Jazz</option>
        <option value="classical">Classical</option>
        <option value="electronic">Electronic</option>
        <option value="hiphop">HipHop</option>
        <option value="vocal">Vocal</option>
      </select>
      <button class="btn" id="clearBtn">Clear Playlist</button>
    </div>
  </div>

  <div class="right">
    <h3>Playlist / Upload</h3>
    <div id="playlist"></div>
    <form id="uploadForm" method="post" enctype="multipart/form-data">
      <input type="file" name="file" id="fileInput" accept=".mp3,.wav,.m4a,.ogg,.mp4">
      <button class="btn" type="submit">Upload</button>
    </form>
  </div>
</div>

<audio id="audio" crossorigin="anonymous"></audio>

<script>
const PLAYLIST = <?php echo json_encode($playlist); ?>;
const audio = document.getElementById('audio');
const viz = document.getElementById('viz');
const ctx = viz.getContext('2d');
const nowTrack = document.getElementById('nowTrack');
const bandElems = [...Array(5)].map((_,i)=>document.getElementById('band'+i));
const peakElems = [...Array(5)].map((_,i)=>document.getElementById('peak'+i));
const preset = document.getElementById('preset');
const playlistEl = document.getElementById('playlist');
const clearBtn = document.getElementById('clearBtn');
let currentIndex = 0;

// AudioContext + EQ
const AudioContextConstructor = window.AudioContext || window.webkitAudioContext;
const audioCtx = new AudioContextConstructor();
const source = audioCtx.createMediaElementSource(audio);
const eqFreqs=[60,170,1000,3000,6000];
const eqBands = eqFreqs.map(f=>{
  const b=audioCtx.createBiquadFilter();
  b.type=f<100?'lowshelf':f>2000?'highshelf':'peaking';
  b.frequency.value=f; b.Q.value=1; b.gain.value=0;
  return b;
});
const analyser = audioCtx.createAnalyser();
analyser.fftSize=1024;
source.connect(eqBands[0]);
for(let i=0;i<eqBands.length-1;i++) eqBands[i].connect(eqBands[i+1]);
eqBands[eqBands.length-1].connect(audioCtx.destination);
eqBands[eqBands.length-1].connect(analyser);

// EQ Slider
bandElems.forEach((el,i)=>{
  el.addEventListener('input', e=>{
    eqBands[i].gain.value=parseFloat(e.target.value);
  });
});

// Presets
const PRESETS={
  flat:[0,0,0,0,0],
  rock:[5,3,0,3,5],
  pop:[3,4,0,3,4],
  jazz:[4,1,0,2,3],
  classical:[2,0,0,0,2],
  electronic:[6,3,0,3,6],
  hiphop:[5,4,0,2,5],
  vocal:[3,2,0,2,3]
};
preset.addEventListener('change', e=>{
  const p = PRESETS[e.target.value];
  p.forEach((v,i)=>{bandElems[i].value=v; eqBands[i].gain.value=v;});
});

// Playlist
function renderPlaylist(){
  playlistEl.innerHTML='';
  PLAYLIST.forEach((t,i)=>{
    const div=document.createElement('div');
    div.className='track'+(i===currentIndex?' active':'');
    div.textContent=t.title;
    div.addEventListener('click',()=>playTrack(i));
    playlistEl.appendChild(div);
  });
}
function playTrack(idx){
  if(idx<0) idx=PLAYLIST.length-1;
  if(idx>=PLAYLIST.length) idx=0;
  currentIndex=idx;
  audio.src=PLAYLIST[idx].file;
  nowTrack.textContent=PLAYLIST[idx].title;
  audioCtx.resume(); audio.play();
  renderPlaylist();
}
renderPlaylist();
if(PLAYLIST.length>0) playTrack(0);

// Clear Playlist
clearBtn.addEventListener('click',()=>{PLAYLIST.length=0;renderPlaylist();audio.pause();audio.src='';nowTrack.textContent='- ไม่มีเพลง -';});

// Upload
const uploadForm = document.getElementById('uploadForm');
uploadForm.addEventListener('submit', async e=>{
  e.preventDefault();
  const f=document.getElementById('fileInput').files[0];
  if(!f) return;
  const form=new FormData(); form.append('file',f);
  const res=await fetch('',{method:'POST',body:form});
  alert(await res.text());
  location.reload();
});

// Visualizer + Neon Peak + Auto BPM (simplified)
const dataArray=new Uint8Array(analyser.frequencyBinCount);
let bpm=120,timer=0;
function draw(){
  requestAnimationFrame(draw);
  analyser.getByteFrequencyData(dataArray);
  ctx.clearRect(0,0,viz.width,viz.height);
  const barWidth = viz.width/dataArray.length*2.5;
  for(let i=0;i<dataArray.length;i++){
    const v=dataArray[i];
    const hue=i/dataArray.length*360;
    ctx.fillStyle=`hsl(${hue},100%,50%)`;
    ctx.shadowColor=ctx.fillStyle;
    ctx.shadowBlur=8;
    ctx.fillRect(i*barWidth,viz.height-v,barWidth,v);
  }
  // Peak Neon per EQ
  eqBands.forEach((b,i)=>{
    const idx=Math.floor((b.frequency.value/6000)*dataArray.length);
    const peak=Math.min(dataArray[idx]/255,1);
    peakElems[i].style.opacity=peak>0.7?1:0;
  });
  // BPM Flash
  timer+=1/60;
  if(timer>(60/bpm)){
    viz.style.background='rgba(255,0,255,0.1)';
    setTimeout(()=>{viz.style.background='rgba(0,0,0,0.2)'},40);
    timer=0;
  }
}
draw();
</script>
</body>
</html>
