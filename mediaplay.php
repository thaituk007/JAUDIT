<?php
// index.php
// โปรแกรมเล่นไฟล์ MP3/MP4 แบบครบเครื่อง (PHP + HTML + JS)
// - วางไฟล์สื่อไว้ที่โฟลเดอร์ ./media (สร้างโฟลเดอร์เอง)
// - รองรับ mp3, mp4
// - แสดงชื่อเพลง / ศิลปิน (อ่านจากชื่อไฟล์แบบง่ายหรือจาก metadata ถ้ามี)
// - EQ แบบ 3-band (Low / Mid / High)
// - LED แสดงระดับเสียง (visualizer)
// - ใช้ Google Font "Prompt" เพื่อความทันสมัย
// วิธีใช้งานอย่างรวดเร็ว:
// 1) วางไฟล์นี้ในเซิร์ฟเวอร์ที่รองรับ PHP (เช่น XAMPP, LAMP)
// 2) สร้างโฟลเดอร์ "media" ใต้ตำแหน่งเดียวกับไฟล์นี้ และวางไฟล์ MP3/MP4 ลงไป
// 3) เข้าเบราว์เซอร์ไปที่ http://localhost/path/to/index.php

// สร้างรายการไฟล์จากโฟลเดอร์ media
$mediaDir = __DIR__ . DIRECTORY_SEPARATOR . 'media';
$files = [];
if (is_dir($mediaDir)) {
    $allowed = ['mp3','mp4','m4a','wav','ogg','webm'];
    foreach (scandir($mediaDir) as $f) {
        if (in_array(pathinfo($f, PATHINFO_EXTENSION), $allowed)) {
            $files[] = $f;
        }
    }
}
// สร้าง JSON playlist ให้ฝั่ง JS ใช้งาน
$playlist = [];
foreach ($files as $f) {
    // พยายามแยกชื่อไฟล์เป็น Title - Artist.ext ถ้าเป็นไปได้
    $nameOnly = pathinfo($f, PATHINFO_FILENAME);
    $title = $nameOnly;
    $artist = '';
    if (strpos($nameOnly, ' - ') !== false) {
        list($titlePart, $artistPart) = explode(' - ', $nameOnly, 2);
        $title = trim($titlePart);
        $artist = trim($artistPart);
    }
    $playlist[] = [
        'file' => 'media/' . $f,
        'title' => $title,
        'artist' => $artist,
        'raw' => $f
    ];
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PHP Media Player — MP3 / MP4</title>
  <!-- Google Font: Prompt -->
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg: #0f1724; /* navy-ish background */
      --card: linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
      --accent: linear-gradient(90deg,#7b61ff,#00c2ff);
      --glass: rgba(255,255,255,0.04);
      --muted: rgba(255,255,255,0.6);
      --rounded: 16px;
    }
    *{box-sizing:border-box;font-family:'Prompt',system-ui,Segoe UI,Roboto,'Helvetica Neue',Arial}
    body{margin:0;min-height:100vh;background:radial-gradient(circle at 10% 10%, #071027 0%, var(--bg) 40%);color:#e6eef8;display:flex;align-items:center;justify-content:center;padding:28px}

    .player-wrap{width:980px;max-width:96%;background:var(--card);border-radius:18px;padding:22px;box-shadow:0 10px 30px rgba(3,6,23,0.7);border:1px solid rgba(255,255,255,0.04)}
    .header{display:flex;align-items:center;gap:16px}
    .logo{width:74px;height:74px;border-radius:12px;background:linear-gradient(135deg,#7b61ff,#00c2ff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;color:white;box-shadow:0 6px 18px rgba(123,97,255,0.18)}
    .title{flex:1}
    .title h1{margin:0;font-size:20px;font-weight:700}
    .title p{margin:2px 0 0 0;color:var(--muted);font-size:13px}

    .main{display:grid;grid-template-columns:1fr 320px;gap:18px;margin-top:18px}
    .left{background:var(--glass);padding:18px;border-radius:12px}
    .right{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));padding:18px;border-radius:12px}

    /* Player controls */
    .meta{display:flex;align-items:center;gap:12px}
    .meta .info{flex:1}
    .meta .info h2{margin:0;font-size:18px}
    .meta .info p{margin:4px 0 0 0;color:var(--muted);font-size:13px}

    .controls{display:flex;align-items:center;gap:10px;margin-top:12px}
    .btn{background:transparent;border:1px solid rgba(255,255,255,0.06);padding:10px 14px;border-radius:10px;cursor:pointer;color:inherit}
    .btn.play{background:var(--accent);border:0;color:#071027;padding:10px 16px;font-weight:700}

    .seek{width:100%;margin-top:12px}
    input[type=range]{-webkit-appearance:none;background:transparent;width:100%}
    input[type=range]::-webkit-slider-runnable-track{height:8px;border-radius:8px;background:linear-gradient(90deg,#7b61ff,#00c2ff)}
    input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:white;box-shadow:0 2px 6px rgba(0,0,0,0.3);margin-top:-5px}

    /* EQ panel */
    .eq{display:flex;gap:12px;align-items:end;justify-content:center;padding:14px;background:rgba(255,255,255,0.02);border-radius:10px;margin-top:14px}
    .eq .band{display:flex;flex-direction:column;align-items:center;gap:8px}
    .eq label{font-size:12px;color:var(--muted)}
    .eq input[type=range]{transform:rotate(-90deg);width:120px}

    /* LED / visualizer */
    .led-wrap{margin-top:14px}
    canvas{width:100%;height:120px;border-radius:8px;background:linear-gradient(180deg, rgba(11,18,34,0.6), rgba(6,11,20,0.6));display:block}

    /* Playlist */
    .playlist{max-height:380px;overflow:auto;margin-top:12px;padding-right:6px}
    .track{padding:10px;border-radius:10px;margin-bottom:8px;display:flex;gap:10px;align-items:center;cursor:pointer;border:1px solid transparent}
    .track:hover{background:rgba(255,255,255,0.02)}
    .track.active{background:linear-gradient(90deg, rgba(123,97,255,0.12), rgba(0,194,255,0.06));border:1px solid rgba(123,97,255,0.14)}
    .track .meta{flex:1}
    .small{font-size:12px;color:var(--muted)}

    /* right panel */
    .panel h3{margin:0 0 8px 0}
    .upload{display:flex;gap:8px;align-items:center}
    .upload input[type=file]{display:none}
    .drop{border:2px dashed rgba(255,255,255,0.03);padding:12px;border-radius:10px;text-align:center;color:var(--muted)}

    footer{margin-top:14px;text-align:center;color:var(--muted);font-size:13px}

    @media (max-width:880px){
      .main{grid-template-columns:1fr}
      .right{order:2}
    }
  </style>
</head>
<body>
  <div class="player-wrap">
    <div class="header">
      <div class="logo">MP</div>
      <div class="title">
        <h1>PHP Media Player</h1>
        <p>เล่นไฟล์ MP3 / MP4 — มี EQ, LED Visualizer และ Playlist</p>
      </div>
      <div style="text-align:right;color:var(--muted)">เวอร์ชันทดลอง</div>
    </div>

    <div class="main">
      <div class="left">
        <div class="meta">
          <div style="width:72px;height:72px;border-radius:10px;background:linear-gradient(135deg,#1e293b,#0f1724);display:flex;align-items:center;justify-content:center;font-weight:700;color:#cfe8ff">🎵</div>
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
          <button class="btn" id="prevBtn">⏮</button>
          <button class="btn play" id="playBtn">▶️ Play</button>
          <button class="btn" id="nextBtn">⏭</button>
          <div style="flex:1"></div>
          <div class="small">เวลา <span id="curTime">00:00</span> / <span id="dur">00:00</span></div>
        </div>

        <div class="seek">
          <input id="seek" type="range" min="0" max="100" value="0">
        </div>

        <div class="eq" title="3-band EQ">
          <div class="band">
            <label>Low</label>
            <input type="range" id="eq-low" min="-12" max="12" value="0" step="0.5">
            <div class="small">60Hz</div>
          </div>
          <div class="band">
            <label>Mid</label>
            <input type="range" id="eq-mid" min="-12" max="12" value="0" step="0.5">
            <div class="small">1000Hz</div>
          </div>
          <div class="band">
            <label>High</label>
            <input type="range" id="eq-high" min="-12" max="12" value="0" step="0.5">
            <div class="small">8000Hz</div>
          </div>
        </div>

        <div class="led-wrap">
          <canvas id="viz" width="800" height="120"></canvas>
        </div>

        <div class="playlist" id="playlist">
          <!-- รายการเพลงจะถูกเติมด้วย JS -->
        </div>

      </div>

      <div class="right panel">
        <h3>การอัปโหลด / Playlist</h3>
        <div class="drop" id="drop">ลากไฟล์มาที่นี่หรือคลิกเพื่ออัปโหลด (วางไฟล์ลงในโฟลเดอร์ media วิธีง่ายกว่า)</div>
        <div style="height:12px"></div>
        <div class="small">หมายเหตุ: เพื่อความเรียบง่าย ไฟล์ที่อัปโหลดด้วย UI นี้จะถูกบันทึกลงโฟลเดอร์ <code>/media</code> (ถ้าเซิร์ฟเวอร์ของคุณอนุญาต)</div>
        <div style="height:12px"></div>
        <form id="uploadForm" method="post" enctype="multipart/form-data">
          <input type="file" id="fileInput" name="file" accept="audio/*,video/*">
          <label class="btn" for="fileInput">เลือกไฟล์</label>
          <button class="btn" type="submit">อัปโหลด</button>
        </form>
        <div style="height:16px"></div>
        <h3>ตั้งค่า</h3>
        <div class="small">Font: Prompt • Theme: Modern gradient • Layout: Rounded cards</div>
        <footer>สร้างโดย PHP + WebAudio API • วางไฟล์ในโฟลเดอร์ media แล้วรีเฟรช</footer>
      </div>
    </div>

    <!-- element audio จริง (ซ่อน) -->
    <audio id="mediaElem" crossorigin="anonymous"></audio>

    <script>
      // โหลด playlist จาก PHP
      const PLAYLIST = <?php echo json_encode($playlist, JSON_UNESCAPED_UNICODE); ?>;

      // อ้างอิง element
      const audio = document.getElementById('mediaElem');
      const playBtn = document.getElementById('playBtn');
      const prevBtn = document.getElementById('prevBtn');
      const nextBtn = document.getElementById('nextBtn');
      const nowTitle = document.getElementById('nowTitle');
      const nowArtist = document.getElementById('nowArtist');
      const playlistEl = document.getElementById('playlist');
      const vol = document.getElementById('vol');
      const seek = document.getElementById('seek');
      const curTimeEl = document.getElementById('curTime');
      const durEl = document.getElementById('dur');

      let currentIndex = 0;
      let isPlaying = false;

      // สร้าง AudioContext และ nodes สำหรับ EQ และ visualization
      const AudioContextConstructor = window.AudioContext || window.webkitAudioContext;
      const audioCtx = new AudioContextConstructor();
      const mediaSource = audioCtx.createMediaElementSource(audio);

      // EQ: 3 peaking filters (low, mid, high) + master gain
      const low = audioCtx.createBiquadFilter(); low.type = 'lowshelf'; low.frequency.value = 200; // boost low
      const mid = audioCtx.createBiquadFilter(); mid.type = 'peaking'; mid.frequency.value = 1000; mid.Q.value = 1;
      const high = audioCtx.createBiquadFilter(); high.type = 'highshelf'; high.frequency.value = 3000;
      const masterGain = audioCtx.createGain(); masterGain.gain.value = parseFloat(vol.value);

      // Analyser สำหรับ visualizer
      const analyser = audioCtx.createAnalyser(); analyser.fftSize = 256;

      // เชื่อมต่อ: media -> low -> mid -> high -> master -> analyser -> destination
      mediaSource.connect(low);
      low.connect(mid);
      mid.connect(high);
      high.connect(masterGain);
      masterGain.connect(analyser);
      analyser.connect(audioCtx.destination);

      // ตั้งค่าเริ่มต้น EQ จาก input
      document.getElementById('eq-low').addEventListener('input', e => { low.gain.value = parseFloat(e.target.value); });
      document.getElementById('eq-mid').addEventListener('input', e => { mid.gain.value = parseFloat(e.target.value); });
      document.getElementById('eq-high').addEventListener('input', e => { high.gain.value = parseFloat(e.target.value); });

      vol.addEventListener('input', e => { masterGain.gain.value = parseFloat(e.target.value); });

      // สร้าง playlist UI
      function renderPlaylist(){
        playlistEl.innerHTML = '';
        PLAYLIST.forEach((t, idx) => {
          const div = document.createElement('div');
          div.className = 'track' + (idx===currentIndex ? ' active' : '');
          div.dataset.idx = idx;
          div.innerHTML = `<div style=\"width:44px;height:44px;border-radius:8px;background:linear-gradient(135deg,#7b61ff,#00c2ff);display:flex;align-items:center;justify-content:center;font-weight:700;color:white\">${idx+1}</div>` +
                          `<div class=\"meta\"><strong>${escapeHtml(t.title)}</strong><div class=\"small\">${escapeHtml(t.artist || t.raw)}</div></div>`;
          div.addEventListener('click', () => { loadTrack(idx); playMedia(); });
          playlistEl.appendChild(div);
        });
      }
      function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); }

      // โหลด track
      function loadTrack(idx){
        if (idx<0 || idx>=PLAYLIST.length) return;
        currentIndex = idx;
        const t = PLAYLIST[idx];
        audio.src = t.file;
        nowTitle.textContent = t.title || t.raw;
        nowArtist.textContent = t.artist || '-';
        renderPlaylist();
        // reset seek
        seek.value = 0; curTimeEl.textContent = '00:00'; durEl.textContent = '00:00';
      }

      // เล่น / หยุด
      function playMedia(){
        if (audioCtx.state === 'suspended') audioCtx.resume();
        audio.play();
        isPlaying = true; playBtn.textContent = '⏸ Pause'; playBtn.classList.add('playing');
      }
      function pauseMedia(){ audio.pause(); isPlaying = false; playBtn.textContent = '▶️ Play'; playBtn.classList.remove('playing'); }

      playBtn.addEventListener('click', ()=>{
        if (!audio.src && PLAYLIST.length>0) loadTrack(0);
        if (isPlaying) pauseMedia(); else playMedia();
      });
      prevBtn.addEventListener('click', ()=>{ loadTrack((currentIndex-1+PLAYLIST.length)%PLAYLIST.length); if(isPlaying) playMedia(); });
      nextBtn.addEventListener('click', ()=>{ loadTrack((currentIndex+1)%PLAYLIST.length); if(isPlaying) playMedia(); });

      // อัพเดตเวลา
      audio.addEventListener('timeupdate', ()=>{
        if (audio.duration) {
          const pct = (audio.currentTime / audio.duration) * 100;
          seek.value = pct;
          curTimeEl.textContent = formatTime(audio.currentTime);
          durEl.textContent = formatTime(audio.duration);
        }
      });
      seek.addEventListener('input', ()=>{
        if (audio.duration) audio.currentTime = (seek.value/100)*audio.duration;
      });

      audio.addEventListener('ended', ()=>{ nextBtn.click(); });

      function formatTime(s){
        if (!isFinite(s)) return '00:00';
        const m = Math.floor(s/60); const sec = Math.floor(s%60); return String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0');
      }

      // Visualizer: LED / bars
      const canvas = document.getElementById('viz');
      const ctx = canvas.getContext('2d');
      function drawViz(){
        requestAnimationFrame(drawViz);
        const w = canvas.width = canvas.clientWidth * (window.devicePixelRatio||1);
        const h = canvas.height = canvas.clientHeight * (window.devicePixelRatio||1);
        const data = new Uint8Array(analyser.frequencyBinCount);
        analyser.getByteFrequencyData(data);
        ctx.clearRect(0,0,w,h);
        const barCount = 40;
        const step = Math.floor(data.length / barCount);
        for (let i=0;i<barCount;i++){
          const v = data[i*step];
          const vh = (v/255) * h;
          const x = i*(w/barCount) + 4;
          const bw = (w/barCount)-8;
          // gradient color
          const g = ctx.createLinearGradient(0,0,0,h);
          g.addColorStop(0,'#7b61ff'); g.addColorStop(0.5,'#00c2ff'); g.addColorStop(1,'#00ffd4');
          ctx.fillStyle = g;
          ctx.fillRect(x, h-vh, bw, vh);
        }
      }
      drawViz();

      // เริ่มต้น
      if (PLAYLIST.length>0) loadTrack(0);
      renderPlaylist();

      // อัปโหลดไฟล์ (simple) — ส่งเป็น POST ไปที่ไฟล์นี้
      const uploadForm = document.getElementById('uploadForm');
      const fileInput = document.getElementById('fileInput');
      const drop = document.getElementById('drop');

      drop.addEventListener('click', ()=> fileInput.click());
      drop.addEventListener('dragover', e=>{ e.preventDefault(); drop.style.borderColor='rgba(123,97,255,0.4)'; });
      drop.addEventListener('dragleave', e=>{ e.preventDefault(); drop.style.borderColor='rgba(255,255,255,0.03)'; });
      drop.addEventListener('drop', e=>{ e.preventDefault(); drop.style.borderColor='rgba(255,255,255,0.03)'; if(e.dataTransfer.files.length) fileInput.files = e.dataTransfer.files; });

      uploadForm.addEventListener('submit', e=>{
        e.preventDefault();
        if (!fileInput.files.length) return alert('เลือกไฟล์ก่อนอัปโหลด');
        const f = fileInput.files[0];
        const formData = new FormData(); formData.append('file', f);
        fetch(location.href, {method:'POST', body: formData}).then(r=>r.text()).then(txt=>{
          alert(txt);
          // รีเฟรชหน้าเพื่อโหลด playlist ใหม่
          location.reload();
        }).catch(err=>{ alert('อัปโหลดล้มเหลว'); console.error(err); });
      });

      // resume AudioContext on user gesture (autoplay policy)
      document.addEventListener('click', ()=>{ if (audioCtx.state === 'suspended') audioCtx.resume(); }, {once:true});

    </script>

<?php
// ส่วนของ PHP เพื่อรับไฟล์อัพโหลดแบบง่าย ๆ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    // ตรวจสอบและบันทึกไฟล์ไปที่โฟลเดอร์ media
    if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);
    $f = $_FILES['file'];
    $name = basename($f['name']);
    $target = $mediaDir . DIRECTORY_SEPARATOR . $name;
    // ตรวจสอบนามสกุล
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['mp3','mp4','m4a','wav','ogg','webm'];
    if (!in_array($ext, $allowed)) {
        http_response_code(400);
        echo 'นามสกุลไฟล์ไม่รองรับ';
        exit;
    }
    if (move_uploaded_file($f['tmp_name'], $target)) {
        echo 'อัปโหลดสำเร็จ';
    } else {
        http_response_code(500);
        echo 'บันทึกไฟล์ล้มเหลว';
    }
    exit;
}
?>

</body>
</html>
