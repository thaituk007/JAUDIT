<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DJ Studio Pro - Enhanced V3</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0a0e1a;
  --card:#111a24;
  --accent:#7b61ff;
  --accent2:#00f0ff;
  --success:#00ff88;
  --warning:#ffaa00;
  --danger:#ff0044;
  --muted:rgba(255,255,255,0.6);
  --border:rgba(255,255,255,0.15);
  --text:#e0f0ff;
  --button-bg:linear-gradient(45deg,var(--accent),var(--accent2));
}

*, *::before, *::after{box-sizing:border-box;}
body{
  margin:0; min-height:100vh; background:var(--bg); font-family:'Prompt',sans-serif; color:var(--text); padding:20px; overflow-x: hidden;
}

button {
  padding: 10px 20px; border: none; border-radius: 8px; background: var(--button-bg); color: #fff; font-family: 'Prompt', sans-serif; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 15px rgba(123, 97, 255, 0.3);
}
button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(123, 97, 255, 0.5); }
button:active { transform: translateY(0); }
button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

.container{
  max-width:1400px; margin:0 auto; display: grid; gap: 20px;
  grid-template-areas:
    "header header header"
    "audio-panel audio-panel scratchpad"
    "controls playlist effects";
  grid-template-columns: 2fr 1fr 1fr;
}

.header{
  grid-area: header; display:flex; align-items:center; justify-content:space-between; padding:20px; background:var(--card); border-radius:16px; border:1px solid var(--border); box-shadow:0 8px 32px rgba(0,0,0,0.2); position: relative; overflow: hidden;
}
.header::before {
  content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--button-bg);
}
.logo{width:70px;height:70px;border-radius:16px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:grid;place-items:center;font-size:32px;box-shadow:0 4px 20px rgba(123,97,255,0.4); animation: pulse 3s ease-in-out infinite;}
@keyframes pulse { 0%, 100% { box-shadow: 0 4px 20px rgba(123,97,255,0.4); } 50% { box-shadow: 0 8px 30px rgba(0,240,255,0.6); }}
.title h1{margin:0;font-size:clamp(1.5rem, 4vw, 2rem);font-weight:700; background: linear-gradient(45deg, var(--accent2), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;}
.title p{margin:6px 0 0;color:var(--muted);font-size:0.9rem;}

.stats-bar {
  display: flex; gap: 20px; align-items: center;
}
.stat-item {
  display: flex; flex-direction: column; align-items: center; padding: 8px 16px; background: rgba(0,0,0,0.3); border-radius: 8px; border: 1px solid var(--border);
}
.stat-label { font-size: 0.7rem; color: var(--muted); text-transform: uppercase; }
.stat-value { font-size: 1.2rem; font-weight: 700; color: var(--accent2); }

.audio-panel{
  grid-area: audio-panel; display: flex; background: var(--card); border-radius: 12px; padding: 15px; gap: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 1px solid var(--border);
}

.eq-section {
  display: flex; flex-direction: column; gap: 10px; flex-shrink: 0; min-width: 420px;
}

.eq-header {
  display: flex; justify-content: space-between; align-items: center; padding: 0 5px;
}
.eq-header h3 { margin: 0; font-size: 0.9rem; color: var(--accent2); font-weight: 600; }
.eq-reset { padding: 4px 12px; font-size: 0.75rem; background: rgba(255,255,255,0.1); border: 1px solid var(--border); }

.eq-container{
  padding: 10px 5px; border-radius: 8px; display: flex; justify-content: flex-start; align-items: center; height: 200px; overflow-x: auto; box-shadow: inset 0 0 10px rgba(0,0,0,0.5); background: rgba(0,0,0,0.3);
}

.eq-band-wrapper{
  display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 2px; height: 100%; margin: 0 4px;
}

.eq-controls-zone {
  display: flex; flex-direction: column; align-items: center; position: relative; height: 110px; justify-content: flex-end; width: 35px;
}

.eq-slider-zone { height: 90px; width: 90px; display: flex; align-items: center; justify-content: center; position: relative; }
.eq-gain-label{ font-size: 0.7rem; font-weight: 600; color: var(--accent2); height: 1rem; margin-bottom: 2px; text-align: center; white-space: nowrap; text-shadow: 0 0 5px rgba(0,240,255,0.5); }
.eq-freq-label{ font-size: 0.65rem; color: var(--muted); user-select: none; white-space: nowrap; margin-top: 6px; font-weight: 500; }

input[type=range].eq-slider {
  -webkit-appearance: none; width: 85px; height: 10px; margin: 0; cursor: pointer; background: transparent; transform: rotate(270deg); position: absolute;
}
input[type=range].eq-slider::-webkit-slider-runnable-track { width: 100%; height: 6px; background: #1a2a40; border-radius: 3px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.6); }
input[type=range].eq-slider::-webkit-slider-thumb { -webkit-appearance: none; height: 14px; width: 14px; border-radius: 50%; background: var(--accent2); border: 2px solid var(--card); box-shadow: 0 0 8px rgba(0,240,255,0.8); margin-top: -4px; cursor: grab; }
input[type=range].eq-slider:active::-webkit-slider-thumb { cursor: grabbing; }

.eq-meter-bar {
  width: 12px; height: 75px; background: linear-gradient(to top, #001a00, #000); border: 1px solid var(--border); border-radius: 3px; position: relative; overflow: hidden; box-shadow: inset 0 0 5px rgba(0,0,0,0.8), 0 0 5px rgba(0,255,136,0.2); margin-bottom: 5px;
}
.meter-fill { position: absolute; bottom: 0; width: 100%; background: linear-gradient(to top, var(--success), #66ffaa); box-shadow: 0 0 8px var(--success); transition: height 0.05s linear; }
.meter-peak { position: absolute; bottom: 0; width: 100%; height: 3px; background: var(--danger); box-shadow: 0 0 6px var(--danger); opacity: 0; transition: bottom 0.1s linear, opacity 0.15s linear; }

.visualizer-section { flex-grow: 1; display: flex; flex-direction: column; gap: 10px; min-width: 300px; max-width: 600px; }
.viz-header { display: flex; justify-content: space-between; align-items: center; padding: 0 5px; gap: 10px; }
.viz-header h3 { margin: 0; font-size: 0.9rem; color: var(--accent2); font-weight: 600; }
.viz-controls { display: flex; gap: 8px; align-items: center; }

.channel-toggle { display: flex; gap: 4px; background: rgba(0,0,0,0.3); padding: 4px; border-radius: 6px; border: 1px solid var(--border); }
.channel-btn { padding: 4px 10px; font-size: 0.7rem; border-radius: 4px; background: transparent; color: var(--muted); border: 1px solid transparent; cursor: pointer; transition: all 0.2s; font-weight: 600; box-shadow: none; }
.channel-btn:hover { transform: none; color: var(--text); background: rgba(255,255,255,0.05); }
.channel-btn.active { background: var(--button-bg); color: var(--text); border-color: var(--accent2); box-shadow: 0 0 10px rgba(123,97,255,0.4); }

#vizMode { width: auto; padding: 6px 30px 6px 12px; font-size: 0.8rem; border-radius: 6px; }
#visualizer{ width: 100%; height: 200px; display: block; background: #000; border-radius: 8px; box-shadow: inset 0 0 15px rgba(0,240,255,0.4); border: 1px solid var(--border); }

.scratchpad {
  grid-area: scratchpad; background: var(--card); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); border: 1px solid var(--border);
}

.turntable {
  width: 140px; height: 140px; border-radius: 50%; background: radial-gradient(circle at 35% 35%, #555, #222, #000); border: 6px solid #1a1a1a; box-shadow: inset 0 0 20px rgba(0,0,0,0.9), 0 0 10px var(--accent), 0 0 20px rgba(123,97,255,0.3); cursor: grab; user-select: none; transition: all 0.15s; position: relative;
}
.turntable::before { content: '♪'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 2.5rem; color: var(--accent2); opacity: 0.6; text-shadow: 0 0 10px var(--accent2); }
.turntable:hover { box-shadow: inset 0 0 20px rgba(0,0,0,0.9), 0 0 15px var(--accent2), 0 0 25px rgba(0,240,255,0.4); transform: scale(1.05); }
.turntable:active, .turntable.scratch-active { cursor: grabbing; box-shadow: inset 0 0 25px rgba(0,0,0,1), 0 0 20px var(--accent2), 0 0 30px rgba(0,240,255,0.6); transform: scale(0.98); }

.rpm-display {
  margin-top: 15px; font-size: 1.5rem; font-weight: 700; color: var(--accent2); text-shadow: 0 0 10px rgba(0,240,255,0.5);
}
.scratchpad p { margin: 8px 0 0; font-size: 0.75rem; color: var(--muted); text-align: center; }
.scratchpad .hotkey { color: var(--accent2); font-weight: 700; }

.controls{
  grid-area: controls; display:flex; flex-direction:column; gap:15px; padding: 20px; background:var(--card); border-radius:12px; border: 1px solid var(--border); align-self: start; position: sticky; top: 20px; max-height: calc(100vh - 40px); overflow-y: auto;
}

.progress-section { display: flex; flex-direction: column; gap: 8px; }
.time-display { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--muted); font-weight: 600; }

.progress-bar-container {
  width: 100%; height: 10px; background: #1a2a40; border-radius: 5px; cursor: pointer; position: relative; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.6);
}
.progress-bar-fill { height: 100%; background: var(--button-bg); width: 0%; transition: width 0.1s linear; box-shadow: 0 0 10px rgba(0,240,255,0.5); position: relative; }
.progress-bar-fill::after {
  content: ''; position: absolute; right: 0; top: 0; bottom: 0; width: 4px; background: rgba(255,255,255,0.8); box-shadow: 0 0 8px rgba(255,255,255,0.6);
}

.player-controls { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; }
.player-controls button { flex: 1; min-width: 80px; font-size: 0.85rem; padding: 8px 12px; }

.control-group { display: flex; flex-direction: column; gap: 8px; }
.control-group label { color: var(--accent2); font-weight: 600; font-size: 0.85rem; }

.volume-control { display: flex; align-items: center; gap: 10px; }
.volume-icon { font-size: 1.2rem; cursor: pointer; user-select: none; transition: transform 0.2s; }
.volume-icon:hover { transform: scale(1.1); }

input[type=range].volume-slider {
  -webkit-appearance: none; flex: 1; height: 6px; background: #1a2a40; border-radius: 3px; outline: none; cursor: pointer;
}
input[type=range].volume-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 16px; height: 16px; border-radius: 50%; background: var(--accent2); cursor: pointer; box-shadow: 0 0 8px rgba(0,240,255,0.8); border: 2px solid var(--card); transition: transform 0.2s; }
input[type=range].volume-slider::-webkit-slider-thumb:hover { transform: scale(1.15); }

.crossfade-control { display: flex; align-items: center; gap: 10px; }
.crossfade-control input[type=range] { -webkit-appearance: none; flex: 1; height: 6px; background: #1a2a40; border-radius: 3px; cursor: pointer; }
.crossfade-control input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; width: 14px; height: 14px; border-radius: 50%; background: var(--accent); cursor: pointer; box-shadow: 0 0 6px rgba(123,97,255,0.8); }
.crossfade-value { min-width: 35px; text-align: right; font-weight: 600; color: var(--accent2); }

.eq-save-load { display: flex; gap: 8px; }
.eq-save-load button { flex: 1; padding: 8px 12px; font-size: 0.8rem; }

select{
  width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: 'Prompt', sans-serif; font-weight: 500; appearance: none; background-repeat: no-repeat; background-position: right 10px center; background-size: 14px; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23e0f0ff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); cursor: pointer; transition: border-color 0.2s;
}
select:hover { border-color: var(--accent2); }

.playlist{
  grid-area: playlist; background: var(--card); border-radius: 12px; padding: 15px; border: 1px solid var(--border); box-shadow: inset 0 0 10px rgba(0,0,0,0.5); display: flex; flex-direction: column; align-self: start; position: sticky; top: 20px; max-height: calc(100vh - 40px);
}

.playlist-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.playlist-header h3 { margin: 0; font-size: 0.9rem; color: var(--accent2); font-weight: 600; }
.playlist-count { font-size: 0.75rem; color: var(--muted); background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 12px; border: 1px solid var(--border); }

.track-list { list-style: none; padding: 0; margin: 0; overflow-y: auto; flex: 1; }
.track-list::-webkit-scrollbar { width: 8px; }
.track-list::-webkit-scrollbar-track { background: rgba(0,0,0,0.3); border-radius: 4px; }
.track-list::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }
.track-list::-webkit-scrollbar-thumb:hover { background: var(--accent2); }

.track-list li {
  padding: 10px 12px; border-radius: 8px; margin-bottom: 5px; cursor: pointer; transition: all 0.2s; font-weight: 500; border: 1px solid transparent; display: flex; justify-content: space-between; align-items: center;
}
.track-list li:hover{ background: rgba(255,255,255,0.1); transform: translateX(3px); border-color: var(--border); }
.track-list li.active{ background: var(--button-bg); color: var(--text); font-weight: 700; box-shadow: 0 2px 10px rgba(123,97,255,0.4); border-color: var(--accent2); }

.track-info { flex: 1; overflow: hidden; }
.track-name { font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.track-duration { font-size: 0.7rem; color: var(--muted); margin-top: 2px; }
.track-remove {
  opacity: 0; background: var(--danger); color: #fff; border: none; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; cursor: pointer; transition: opacity 0.2s;
}
.track-list li:hover .track-remove { opacity: 1; }

.effects {
  grid-area: effects; background: var(--card); border-radius: 12px; padding: 15px; border: 1px solid var(--border); display: flex; flex-direction: column; gap: 15px; align-self: start; position: sticky; top: 20px; max-height: calc(100vh - 40px); overflow-y: auto;
}

.effects h3 { margin: 0 0 10px 0; font-size: 0.9rem; color: var(--accent2); font-weight: 600; padding-bottom: 10px; border-bottom: 1px solid var(--border); }

.effect-item {
  display: flex; flex-direction: column; gap: 8px; padding: 12px; background: rgba(0,0,0,0.3); border-radius: 8px; border: 1px solid var(--border);
}

.effect-header {
  display: flex; justify-content: space-between; align-items: center;
}

.effect-label { font-size: 0.85rem; font-weight: 600; color: var(--text); }
.effect-toggle {
  width: 50px; height: 26px; background: rgba(255,255,255,0.1); border-radius: 13px; position: relative; cursor: pointer; transition: background 0.3s; border: 1px solid var(--border);
}
.effect-toggle.active { background: var(--button-bg); }
.effect-toggle::after {
  content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: transform 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}
.effect-toggle.active::after { transform: translateX(24px); }

.effect-control { display: flex; align-items: center; gap: 10px; }
.effect-slider {
  -webkit-appearance: none; flex: 1; height: 4px; background: #1a2a40; border-radius: 2px; cursor: pointer;
}
.effect-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 12px; height: 12px; border-radius: 50%; background: var(--accent2); cursor: pointer; box-shadow: 0 0 6px rgba(0,240,255,0.8); }
.effect-value { min-width: 40px; text-align: right; font-size: 0.8rem; font-weight: 600; color: var(--accent2); }

.toast {
  position: fixed; bottom: 20px; right: 20px; background: var(--card); color: var(--text); padding: 12px 20px; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 10px; opacity: 0; transform: translateY(20px); transition: all 0.3s; z-index: 1000;
}
.toast.show { opacity: 1; transform: translateY(0); }
.toast.success { border-left: 4px solid var(--success); }
.toast.error { border-left: 4px solid var(--danger); }
.toast.warning { border-left: 4px solid var(--warning); }

.bpm-detector {
  padding: 12px; background: rgba(0,0,0,0.3); border-radius: 8px; border: 1px solid var(--border); text-align: center;
}
.bpm-value { font-size: 2rem; font-weight: 700; color: var(--accent2); margin: 5px 0; text-shadow: 0 0 10px rgba(0,240,255,0.5); }
.bpm-label { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; }

@media (max-width: 1200px) {
  .container {
    grid-template-areas:
      "header header"
      "scratchpad scratchpad"
      "audio-panel audio-panel"
      "controls playlist"
      "effects effects";
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 800px) {
  .container {
    grid-template-areas:
      "header"
      "scratchpad"
      "audio-panel"
      "controls"
      "playlist"
      "effects";
    grid-template-columns: 1fr;
  }
  .audio-panel { flex-direction: column; }
  .eq-section, .visualizer-section { width: 100%; min-width: auto; }
  .controls, .playlist, .effects { position: static; max-height: none; }
  .stats-bar { flex-wrap: wrap; gap: 10px; }
}
</style>
</head>
<body>
<div class="container">
  <header class="header">
    <div style="display:flex;align-items:center;gap:16px">
      <div class="logo">🎵</div>
      <div class="title">
        <h1>DJ Studio Pro - Enhanced V3</h1>
        <p>Professional Audio Control Panel</p>
      </div>
    </div>
    <div class="stats-bar">
      <div class="stat-item">
        <span class="stat-label">BPM</span>
        <span class="stat-value" id="bpmStat">--</span>
      </div>
      <div class="stat-item">
        <span class="stat-label">Tracks</span>
        <span class="stat-value" id="tracksStat">0</span>
      </div>
      <div class="stat-item">
        <span class="stat-label">Time</span>
        <span class="stat-value" id="timeStat">0:00</span>
      </div>
    </div>
  </header>

  <div class="audio-panel">
    <div class="eq-section">
      <div class="eq-header">
        <h3>10-Band EQ</h3>
        <button class="eq-reset" id="resetEQ">Reset</button>
      </div>
      <div class="eq-container" id="eqContainer"></div>
    </div>

    <div class="visualizer-section">
      <div class="viz-header">
        <h3>Audio Visualizer</h3>
        <div class="viz-controls">
          <div class="channel-toggle">
            <button class="channel-btn active" data-channel="stereo">Stereo</button>
            <button class="channel-btn" data-channel="left">L</button>
            <button class="channel-btn" data-channel="right">R</button>
          </div>
          <select id="vizMode">
            <option value="bars">Bars</option>
            <option value="wave">Waveform</option>
            <option value="circle">Circle</option>
            <option value="spectrum">Spectrum</option>
          </select>
        </div>
      </div>
      <canvas id="visualizer"></canvas>
    </div>
  </div>

  <div class="scratchpad">
    <div class="turntable" id="turntable"></div>
    <div class="rpm-display" id="rpmDisplay">33 RPM</div>
    <p>Virtual Turntable</p>
    <p>Drag or press <span class="hotkey">S</span> to scratch</p>
  </div>

  <div class="controls">
    <div class="progress-section">
      <div class="time-display">
        <span id="currentTime">0:00</span>
        <span id="totalTime">0:00</span>
      </div>
      <div class="progress-bar-container" id="progressBar">
        <div class="progress-bar-fill" id="progressFill"></div>
      </div>
    </div>

    <div class="player-controls">
      <button id="prevBtn" title="Previous Track (A)">⏮</button>
      <button id="playBtn" title="Play/Pause (Space)">▶️</button>
      <button id="nextBtn" title="Next Track (D)">⏭</button>
      <button id="addBtn" title="Add Audio (F)">➕</button>
      <button id="loopBtn" title="Loop (L)">🔁</button>
      <input type="file" id="fileInput" accept="audio/*" hidden multiple>
    </div>

    <div class="control-group">
      <label>🔊 Master Volume</label>
      <div class="volume-control">
        <span class="volume-icon" id="volumeIcon">🔊</span>
        <input type="range" id="volumeSlider" class="volume-slider" min="0" max="100" value="50">
        <span id="volumeValue" style="min-width: 35px; text-align: right; font-weight: 600; color: var(--accent2);">100%</span>
      </div>
    </div>

    <div class="control-group">
      <label>🎚️ Crossfade</label>
      <div class="crossfade-control">
        <input type="range" id="crossfadeSlider" min="0" max="5000" step="100" value="1000">
        <span class="crossfade-value" id="crossfadeValue">1.0s</span>
      </div>
    </div>

    <div class="control-group">
      <label>EQ Presets</label>
      <select id="presetEQ"></select>
      <div class="eq-save-load">
        <button id="saveEQBtn">💾 Save</button>
        <button id="loadEQBtn">📂 Load</button>
      </div>
    </div>
  </div>

  <div class="playlist">
    <div class="playlist-header">
      <h3>Playlist</h3>
      <div style="display: flex; gap: 8px; align-items: center;">
        <span class="playlist-count" id="playlistCount">0 tracks</span>
        <button id="clearBtn" style="padding: 4px 8px; font-size: 0.7rem;" title="Clear All">🗑</button>
      </div>
    </div>
    <ul class="track-list" id="playlist-list"></ul>
  </div>

  <div class="effects">
    <h3>Audio Effects</h3>

    <div class="bpm-detector">
      <div class="bpm-label">BPM Detector</div>
      <div class="bpm-value" id="bpmValue">--</div>
      <button id="detectBPM" style="width: 100%; margin-top: 8px; padding: 6px; font-size: 0.8rem;">🎵 Detect BPM</button>
    </div>

    <div class="effect-item">
      <div class="effect-header">
        <span class="effect-label">🎤 Reverb</span>
        <div class="effect-toggle" id="reverbToggle"></div>
      </div>
      <div class="effect-control">
        <input type="range" class="effect-slider" id="reverbAmount" min="0" max="100" value="30" disabled>
        <span class="effect-value" id="reverbValue">30%</span>
      </div>
    </div>

    <div class="effect-item">
      <div class="effect-header">
        <span class="effect-label">⚡ Distortion</span>
        <div class="effect-toggle" id="distortionToggle"></div>
      </div>
      <div class="effect-control">
        <input type="range" class="effect-slider" id="distortionAmount" min="0" max="100" value="20" disabled>
        <span class="effect-value" id="distortionValue">20%</span>
      </div>
    </div>

    <div class="effect-item">
      <div class="effect-header">
        <span class="effect-label">🌀 Delay</span>
        <div class="effect-toggle" id="delayToggle"></div>
      </div>
      <div class="effect-control">
        <input type="range" class="effect-slider" id="delayTime" min="0" max="1000" value="300" disabled>
        <span class="effect-value" id="delayValue">300ms</span>
      </div>
    </div>

    <div class="effect-item">
      <div class="effect-header">
        <span class="effect-label">🎛️ Filter</span>
        <div class="effect-toggle" id="filterToggle"></div>
      </div>
      <div class="effect-control">
        <input type="range" class="effect-slider" id="filterFreq" min="200" max="8000" value="1000" disabled>
        <span class="effect-value" id="filterValue">1.0kHz</span>
      </div>
    </div>

    <div class="effect-item">
      <div class="effect-header">
        <span class="effect-label">🔊 Compressor</span>
        <div class="effect-toggle" id="compressorToggle"></div>
      </div>
      <div class="effect-control">
        <input type="range" class="effect-slider" id="compressorThreshold" min="-50" max="0" value="-24" disabled>
        <span class="effect-value" id="compressorValue">-24dB</span>
      </div>
    </div>
  </div>

  <audio id="audio"></audio>
</div>

<div class="toast" id="toast">
  <span id="toastMessage"></span>
</div>

<script>
const GAIN_RANGE = 12;
const ISO_10_FREQS = [31, 63, 125, 250, 500, 1000, 2000, 4000, 8000, 16000];
const EQ_BANDS = ISO_10_FREQS.map(f => `band_${f}Hz`);

const PRESETS = [
  { name:"Manual", genre:"Custom", eq:[0,0,0,0,0,0,0,0,0,0] },
  { name:"Flat", genre:"Monitor", eq:[0,0,0,0,0,0,0,0,0,0] },
  { name:"Dolby Enhance", genre:"Cinema", eq:[5,3,1,0,0,1,2,4,3,2] },
  { name:"Pop", genre:"Music", eq:[1,0,0,0,1,2,3,2,1,0] },
  { name:"Rock", genre:"Music", eq:[3,2,1,0,0,1,2,3,2,1] },
  { name:"EDM", genre:"Electronic", eq:[6,4,2,0,1,2,3,4,3,2] },
  { name:"Hip-Hop", genre:"Urban", eq:[7,5,3,1,0,0,1,2,1,0] },
  { name:"Vocal", genre:"Speech", eq:[-4,-3,-1,2,4,4,3,1,0,-1] },
  { name:"Jazz", genre:"Music", eq:[2,0,-1,0,1,3,3,2,0,-1] },
  { name:"Bass Boost", genre:"Effect", eq:[9,7,5,2,0,-1,-2,-2,0,0] },
  { name:"Treble Boost", genre:"Effect", eq:[0,0,0,0,1,2,4,6,8,9] },
];

function showToast(message, type = 'success') {
  const toast = document.getElementById('toast');
  const toastMessage = document.getElementById('toastMessage');
  toastMessage.textContent = message;
  toast.className = `toast ${type}`;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}

class DJStudioEngine {
    constructor(audioElement) {
        this.audio = audioElement;
        this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        this.analyser = this.audioCtx.createAnalyser();
        this.analyserLeft = this.audioCtx.createAnalyser();
        this.analyserRight = this.audioCtx.createAnalyser();
        this.splitter = this.audioCtx.createChannelSplitter(2);
        this.masterGain = this.audioCtx.createGain();
        this.source = this.audioCtx.createMediaElementSource(this.audio);
        this.filters = this._setupFilters();

        this.convolver = this.audioCtx.createConvolver();
        this.reverbGain = this.audioCtx.createGain();
        this.reverbGain.gain.value = 0;

        this.waveshaper = this.audioCtx.createWaveShaper();
        this.distortionGain = this.audioCtx.createGain();
        this.distortionGain.gain.value = 0;

        this.delay = this.audioCtx.createDelay();
        this.delayFeedback = this.audioCtx.createGain();
        this.delayGain = this.audioCtx.createGain();
        this.delayGain.gain.value = 0;
        this.delay.delayTime.value = 0.3;
        this.delayFeedback.gain.value = 0.4;

        this.filter = this.audioCtx.createBiquadFilter();
        this.filter.type = 'lowpass';
        this.filter.frequency.value = 8000;
        this.filterGain = this.audioCtx.createGain();
        this.filterGain.gain.value = 0;

        this.compressor = this.audioCtx.createDynamicsCompressor();
        this.compressorEnabled = false;

        this.analyser.fftSize = 4096;
        this.analyserLeft.fftSize = 4096;
        this.analyserRight.fftSize = 4096;

        this._createImpulseResponse();
        this._createDistortionCurve(20);
        this._connectNodes();

        this.isScratching = false;
        this.audio.playbackRate = 1.0;
        this.crossfadeDuration = 1000;
        this.isCrossfading = false;
        this.loopEnabled = false;

        this.scratchVelocity = 0;
        this.lastScratchTime = Date.now();
    }

    _setupFilters() {
        const filters = {};
        ISO_10_FREQS.forEach((freq, index) => {
            const bandName = EQ_BANDS[index];
            const filter = this.audioCtx.createBiquadFilter();
            filter.type = 'peaking';
            filter.frequency.value = freq;
            filter.Q.value = 1.0;
            filter.gain.value = 0;
            filters[bandName] = filter;
        });
        return filters;
    }

    _createImpulseResponse() {
        const length = this.audioCtx.sampleRate * 2;
        const impulse = this.audioCtx.createBuffer(2, length, this.audioCtx.sampleRate);
        for (let channel = 0; channel < 2; channel++) {
            const channelData = impulse.getChannelData(channel);
            for (let i = 0; i < length; i++) {
                channelData[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / length, 2);
            }
        }
        this.convolver.buffer = impulse;
    }

    _createDistortionCurve(amount) {
        const samples = 44100;
        const curve = new Float32Array(samples);
        const deg = Math.PI / 180;
        const k = amount;
        for (let i = 0; i < samples; i++) {
            const x = (i * 2) / samples - 1;
            curve[i] = ((3 + k) * x * 20 * deg) / (Math.PI + k * Math.abs(x));
        }
        this.waveshaper.curve = curve;
    }

    _connectNodes() {
        let currentNode = this.source;

        for (const bandName of EQ_BANDS) {
            currentNode.connect(this.filters[bandName]);
            currentNode = this.filters[bandName];
        }

        const effectsMerge = this.audioCtx.createGain();

        currentNode.connect(effectsMerge);

        currentNode.connect(this.convolver);
        this.convolver.connect(this.reverbGain);
        this.reverbGain.connect(effectsMerge);

        currentNode.connect(this.waveshaper);
        this.waveshaper.connect(this.distortionGain);
        this.distortionGain.connect(effectsMerge);

        currentNode.connect(this.delay);
        this.delay.connect(this.delayFeedback);
        this.delayFeedback.connect(this.delay);
        this.delay.connect(this.delayGain);
        this.delayGain.connect(effectsMerge);

        currentNode.connect(this.filter);
        this.filter.connect(this.filterGain);
        this.filterGain.connect(effectsMerge);

        if (this.compressorEnabled) {
            effectsMerge.connect(this.compressor);
            this.compressor.connect(this.masterGain);
        } else {
            effectsMerge.connect(this.masterGain);
        }

        this.masterGain.connect(this.analyser);
        this.masterGain.connect(this.splitter);
        this.splitter.connect(this.analyserLeft, 0);
        this.splitter.connect(this.analyserRight, 1);
        this.analyser.connect(this.audioCtx.destination);
    }

    setGain(bandName, gainValue) {
        if (this.filters[bandName]) {
            this.filters[bandName].gain.value = Math.max(-GAIN_RANGE, Math.min(GAIN_RANGE, gainValue));
        }
    }

    setMasterVolume(value) {
        this.masterGain.gain.value = value;
    }

    setCrossfadeDuration(ms) {
        this.crossfadeDuration = ms;
    }

    setReverb(enabled, amount) {
        this.reverbGain.gain.value = enabled ? amount / 100 : 0;
    }

    setDistortion(enabled, amount) {
        this.distortionGain.gain.value = enabled ? amount / 100 : 0;
        if (enabled) this._createDistortionCurve(amount);
    }

    setDelay(enabled, time) {
        this.delayGain.gain.value = enabled ? 0.5 : 0;
        this.delay.delayTime.value = time / 1000;
    }

    setFilter(enabled, freq) {
        this.filterGain.gain.value = enabled ? 1 : 0;
        this.filter.frequency.value = freq;
    }

    setCompressor(enabled, threshold) {
        this.compressorEnabled = enabled;
        if (enabled) {
            this.compressor.threshold.value = threshold;
            this.compressor.knee.value = 30;
            this.compressor.ratio.value = 12;
            this.compressor.attack.value = 0.003;
            this.compressor.release.value = 0.25;
        }
    }

    async crossfadeToNext(onComplete) {
        if (this.isCrossfading || this.crossfadeDuration === 0) {
            if (onComplete) onComplete();
            return;
        }

        this.isCrossfading = true;
        const duration = this.crossfadeDuration / 1000;
        const currentTime = this.audioCtx.currentTime;
        const currentVolume = this.masterGain.gain.value;

        this.masterGain.gain.cancelScheduledValues(currentTime);
        this.masterGain.gain.setValueAtTime(currentVolume, currentTime);
        this.masterGain.gain.linearRampToValueAtTime(0, currentTime + duration);

        setTimeout(() => {
            if (onComplete) onComplete();
            const newCurrentTime = this.audioCtx.currentTime;
            this.masterGain.gain.cancelScheduledValues(newCurrentTime);
            this.masterGain.gain.setValueAtTime(0, newCurrentTime);
            this.masterGain.gain.linearRampToValueAtTime(currentVolume, newCurrentTime + duration);
            setTimeout(() => { this.isCrossfading = false; }, duration * 1000);
        }, duration * 1000);
    }

    applyPreset(presetName) {
        const preset = PRESETS.find(p => p.name === presetName);
        if (!preset) return;
        const gains = preset.eq;
        EQ_BANDS.forEach((bandName, index) => {
            if (this.filters[bandName]) {
                this.filters[bandName].gain.value = gains[index];
            }
        });
        return gains;
    }

    getCurrentEQ() {
        return EQ_BANDS.map(bandName => this.filters[bandName].gain.value);
    }

    startScratch() {
        this.isScratching = true;
        this.scratchVelocity = 0;
        this.audio.pause();
    }

    scratch(delta) {
        if (!this.isScratching) return;
        const now = Date.now();
        const timeDelta = (now - this.lastScratchTime) / 1000;
        this.lastScratchTime = now;

        this.scratchVelocity = delta / (timeDelta || 0.016);
        let newRate = 1.0 + delta * 0.008;
        newRate = Math.max(-2.0, Math.min(3.0, newRate));
        this.audio.playbackRate = newRate;
    }

    stopScratch(isPlaying) {
        if (!this.isScratching) return;
        this.isScratching = false;
        this.audio.playbackRate = 1.0;
        this.scratchVelocity = 0;
        if (isPlaying) this.audio.play();
    }

    getScratchVelocity() {
        return this.scratchVelocity;
    }

    resume() {
        if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
    }

    async detectBPM() {
        const bufferLength = this.analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        const samples = [];

        for (let i = 0; i < 100; i++) {
            this.analyser.getByteFrequencyData(dataArray);
            const sum = dataArray.reduce((a, b) => a + b, 0);
            samples.push(sum / bufferLength);
            await new Promise(resolve => setTimeout(resolve, 20));
        }

        const peaks = [];
        for (let i = 1; i < samples.length - 1; i++) {
            if (samples[i] > samples[i - 1] && samples[i] > samples[i + 1] && samples[i] > 128) {
                peaks.push(i);
            }
        }

        if (peaks.length < 2) return null;

        const intervals = [];
        for (let i = 1; i < peaks.length; i++) {
            intervals.push(peaks[i] - peaks[i - 1]);
        }

        const avgInterval = intervals.reduce((a, b) => a + b, 0) / intervals.length;
        const bpm = Math.round((60 / (avgInterval * 0.02)));

        return bpm > 60 && bpm < 200 ? bpm : null;
    }

    getAnalyser() { return this.analyser; }
    getAnalyserLeft() { return this.analyserLeft; }
    getAnalyserRight() { return this.analyserRight; }
    getAudioContext() { return this.audioCtx; }
}

class SliderManager {
    constructor(eqContainer, engine, presetEQ) {
        this.eqContainer = eqContainer;
        this.engine = engine;
        this.presetEQ = presetEQ;
        this.sliders = {};
        this.gainLabels = {};
        this.meters = {};
        this.peakIndicators = {};

        this._createBands();
        this._setupListeners();
        this._updateSliders(this.engine.applyPreset(this.presetEQ.value || PRESETS[1].name));
    }

    _createBands() {
        this.eqContainer.innerHTML = '';
        ISO_10_FREQS.forEach((freq, index) => {
            const bandName = EQ_BANDS[index];
            const wrapper = document.createElement('div');
            wrapper.className = 'eq-band-wrapper';

            const controlsZone = document.createElement('div');
            controlsZone.className = 'eq-controls-zone';

            const gainLabel = document.createElement('div');
            gainLabel.className = 'eq-gain-label';
            gainLabel.textContent = '0';
            this.gainLabels[bandName] = gainLabel;

            const sliderZone = document.createElement('div');
            sliderZone.className = 'eq-slider-zone';

            const slider = document.createElement('input');
            slider.type = 'range';
            slider.className = 'eq-slider';
            slider.id = bandName;
            slider.min = -GAIN_RANGE;
            slider.max = GAIN_RANGE;
            slider.step = 0.5;
            slider.value = 0;
            this.sliders[bandName] = slider;
            sliderZone.appendChild(slider);

            const freqLabel = document.createElement('div');
            freqLabel.className = 'eq-freq-label';
            freqLabel.textContent = `${freq >= 1000 ? (freq / 1000).toFixed(1) + 'k' : freq}Hz`;

            const meterBar = document.createElement('div');
            meterBar.className = 'eq-meter-bar';

            const meterFill = document.createElement('div');
            meterFill.className = 'meter-fill';
            this.meters[bandName] = meterFill;

            const meterPeak = document.createElement('div');
            meterPeak.className = 'meter-peak';
            this.peakIndicators[bandName] = meterPeak;

            meterBar.append(meterFill, meterPeak);
            controlsZone.append(gainLabel, sliderZone);
            wrapper.append(meterBar, controlsZone, freqLabel);
            this.eqContainer.appendChild(wrapper);
        });
    }

    _setupListeners() {
        this.presetEQ.addEventListener("change", (e) => {
            const gains = this.engine.applyPreset(e.target.value);
            this._updateSliders(gains);
            showToast(`Preset applied: ${e.target.value}`);
        });

        EQ_BANDS.forEach(bandName => {
            this.sliders[bandName].addEventListener('input', () => {
                const gainValue = parseFloat(this.sliders[bandName].value);
                this.engine.setGain(bandName, gainValue);
                this.gainLabels[bandName].textContent = `${gainValue > 0 ? '+' : ''}${gainValue}`;
                this.presetEQ.value = 'Manual';
            });
        });
    }

    _updateSliders(gains) {
        EQ_BANDS.forEach((bandName, index) => {
            const gainValue = gains[index];
            this.sliders[bandName].value = gainValue;
            this.gainLabels[bandName].textContent = `${gainValue > 0 ? '+' : ''}${gainValue}`;
        });
    }

    reset() {
        this._updateSliders(Array(10).fill(0));
        EQ_BANDS.forEach(bandName => {
            this.engine.setGain(bandName, 0);
        });
        this.presetEQ.value = 'Flat';
        showToast('EQ reset to flat');
    }

    saveEQ() {
        const currentEQ = this.engine.getCurrentEQ();
        const eqData = {
            gains: currentEQ,
            timestamp: Date.now(),
            presets: ISO_10_FREQS.map((freq, i) => ({ freq, gain: currentEQ[i] }))
        };
        const dataStr = JSON.stringify(eqData, null, 2);
        const dataBlob = new Blob([dataStr], { type: 'application/json' });
        const url = URL.createObjectURL(dataBlob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `DJ_Studio_EQ_${new Date().toISOString().slice(0,10)}.json`;
        a.click();
        URL.revokeObjectURL(url);
        showToast('EQ settings saved');
    }

    loadEQ() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'application/json';
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (event) => {
                try {
                    const eqData = JSON.parse(event.target.result);
                    if (eqData.gains && Array.isArray(eqData.gains) && eqData.gains.length === 10) {
                        this._updateSliders(eqData.gains);
                        EQ_BANDS.forEach((bandName, index) => {
                            this.engine.setGain(bandName, eqData.gains[index]);
                        });
                        this.presetEQ.value = 'Manual';
                        showToast('EQ settings loaded');
                    }
                } catch (err) {
                    showToast('Invalid EQ file format', 'error');
                }
            };
            reader.readAsText(file);
        };
        input.click();
    }
}

class PlayerManager {
    constructor(elements, engine) {
        const { audio, playBtn, prevBtn, nextBtn, addBtn, loopBtn, fileInput, playlistList, presetEQ } = elements;

        this.audio = audio;
        this.engine = engine;
        this.playlist = [];
        this.currentIndex = -1;
        this.isPlaying = false;
        this.playBtn = playBtn;
        this.loopBtn = loopBtn;
        this.playlistList = playlistList;
        this.fileInput = fileInput;
        this.useCrossfade = true;

        this._populateEQDropdown(presetEQ);
        this._setupListeners();
        this._setupHotkeys();
        this._setupProgressBar();
    }

    _populateEQDropdown(selectElement) {
        selectElement.innerHTML = '';
        PRESETS.forEach(p => {
            const option = document.createElement('option');
            option.value = p.name;
            option.textContent = `${p.name} (${p.genre})`;
            selectElement.appendChild(option);
        });
        selectElement.value = PRESETS[1].name;
    }

    _setupProgressBar() {
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');
        const currentTimeEl = document.getElementById('currentTime');
        const totalTimeEl = document.getElementById('totalTime');
        const timeStatEl = document.getElementById('timeStat');

        this.audio.addEventListener('timeupdate', () => {
            if (this.audio.duration) {
                const percent = (this.audio.currentTime / this.audio.duration) * 100;
                progressFill.style.width = `${percent}%`;
                currentTimeEl.textContent = this._formatTime(this.audio.currentTime);
                timeStatEl.textContent = this._formatTime(this.audio.duration - this.audio.currentTime);
            }
        });

        this.audio.addEventListener('loadedmetadata', () => {
            totalTimeEl.textContent = this._formatTime(this.audio.duration);
        });

        progressBar.addEventListener('click', (e) => {
            if (!this.audio.duration) return;
            const rect = progressBar.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            this.audio.currentTime = percent * this.audio.duration;
        });
    }

    _formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    _setupListeners() {
        this.playBtn.addEventListener("click", () => this.togglePlayback());
        document.getElementById('prevBtn').addEventListener("click", () => this.prevTrack());
        document.getElementById('nextBtn').addEventListener("click", () => this.nextTrack());
        document.getElementById('addBtn').addEventListener("click", () => this.fileInput.click());
        document.getElementById('clearBtn').addEventListener("click", () => this.clearPlaylist());
        this.loopBtn.addEventListener("click", () => this.toggleLoop());
        this.fileInput.addEventListener("change", (e) => this.addTracks(e.target.files));
        this.audio.addEventListener("ended", () => {
            if (this.engine.loopEnabled) {
                this.audio.currentTime = 0;
                this.audio.play();
            } else {
                this.nextTrack(true);
            }
        });
    }

    _setupHotkeys() {
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;

            if (e.code === 'Space') { e.preventDefault(); this.togglePlayback(); }
            else if (e.key === 'a' || e.key === 'A') { this.prevTrack(); }
            else if (e.key === 'd' || e.key === 'D') { this.nextTrack(); }
            else if (e.key === 'f' || e.key === 'F') { this.fileInput.click(); }
            else if (e.key === 'l' || e.key === 'L') { this.toggleLoop(); }
        });
    }

    toggleLoop() {
        this.engine.loopEnabled = !this.engine.loopEnabled;
        this.loopBtn.style.background = this.engine.loopEnabled ? 'var(--button-bg)' : '';
        showToast(`Loop ${this.engine.loopEnabled ? 'enabled' : 'disabled'}`);
    }

    togglePlayback() {
        this.engine.resume();
        if (this.playlist.length === 0) {
            showToast('Please add audio files first', 'warning');
            return;
        }
        if (this.isPlaying) {
            this.audio.pause();
            this.isPlaying = false;
            this.playBtn.textContent = "▶️";
        } else {
            if (this.audio.src) {
                this.audio.play();
            } else if (this.playlist.length > 0) {
                this.loadTrack(0);
            }
            this.isPlaying = true;
            this.playBtn.textContent = "⏸";
        }
    }

    loadTrack(index, withCrossfade = false) {
        if (index >= 0 && index < this.playlist.length) {
            if (withCrossfade && this.useCrossfade && this.engine.crossfadeDuration > 0) {
                this.engine.crossfadeToNext(() => {
                    this.currentIndex = index;
                    this.audio.src = this.playlist[index].url;
                    this.highlightTrack();
                    this.audio.play().catch(e => console.error("Playback failed:", e));
                });
            } else {
                this.currentIndex = index;
                this.audio.src = this.playlist[index].url;
                this.highlightTrack();
                this.audio.play().catch(e => console.error("Playback failed:", e));
            }
            this.isPlaying = true;
            this.playBtn.textContent = "⏸";
            this.engine.resume();
        }
    }

    nextTrack(autoPlay = false) {
        if (!autoPlay && !this.isPlaying) return;
        const nextIndex = this.currentIndex < this.playlist.length - 1 ? this.currentIndex + 1 : 0;
        this.loadTrack(nextIndex, autoPlay || this.isPlaying);
    }

    prevTrack() {
        const prevIndex = this.currentIndex > 0 ? this.currentIndex - 1 : this.playlist.length - 1;
        this.loadTrack(prevIndex, this.isPlaying);
    }

    addTracks(files) {
        const fileArray = Array.from(files);
        fileArray.forEach(f => {
            const url = URL.createObjectURL(f);
            this.playlist.push({ url, title: f.name, duration: 0 });
        });
        this.renderPlaylist();
        showToast(`Added ${fileArray.length} track${fileArray.length > 1 ? 's' : ''}`);
        if (this.playlist.length > 0 && this.currentIndex === -1) {
            this.loadTrack(0);
        }
    }

    removeTrack(index) {
        if (index === this.currentIndex) {
            this.audio.pause();
            this.audio.src = "";
            this.isPlaying = false;
            this.playBtn.textContent = "▶️";
        }

        URL.revokeObjectURL(this.playlist[index].url);
        this.playlist.splice(index, 1);

        if (index < this.currentIndex) {
            this.currentIndex--;
        } else if (index === this.currentIndex && this.playlist.length > 0) {
            this.currentIndex = Math.min(index, this.playlist.length - 1);
        } else if (this.playlist.length === 0) {
            this.currentIndex = -1;
        }

        this.renderPlaylist();
        showToast('Track removed');
    }

    clearPlaylist() {
        if (this.playlist.length === 0) return;

        this.playlist.forEach(t => URL.revokeObjectURL(t.url));
        this.playlist = [];
        this.currentIndex = -1;
        this.audio.src = "";
        this.isPlaying = false;
        this.playBtn.textContent = "▶️";
        this.renderPlaylist();
        showToast('Playlist cleared');
    }

    renderPlaylist() {
        this.playlistList.innerHTML = "";
        const playlistCount = document.getElementById('playlistCount');
        const tracksStat = document.getElementById('tracksStat');

        playlistCount.textContent = `${this.playlist.length} track${this.playlist.length !== 1 ? 's' : ''}`;
        tracksStat.textContent = this.playlist.length;

        this.playlist.forEach((t, i) => {
            const li = document.createElement("li");

            const trackInfo = document.createElement('div');
            trackInfo.className = 'track-info';

            const trackName = document.createElement('div');
            trackName.className = 'track-name';
            trackName.textContent = t.title;

            trackInfo.appendChild(trackName);

            const removeBtn = document.createElement('button');
            removeBtn.className = 'track-remove';
            removeBtn.textContent = '✕';
            removeBtn.onclick = (e) => {
                e.stopPropagation();
                this.removeTrack(i);
            };

            li.appendChild(trackInfo);
            li.appendChild(removeBtn);
            li.onclick = () => this.loadTrack(i);

            this.playlistList.appendChild(li);
        });
        this.highlightTrack();
    }

    highlightTrack() {
        [...this.playlistList.children].forEach((li, i) => {
            li.classList.toggle("active", i === this.currentIndex);
        });
    }
}

class Visualizer {
    constructor(canvasElement, engine, sliderManager) {
        this.canvas = canvasElement;
        this.ctx = canvasElement.getContext('2d');
        this.analyser = engine.getAnalyser();
        this.analyserLeft = engine.getAnalyserLeft();
        this.analyserRight = engine.getAnalyserRight();
        this.audioCtx = engine.getAudioContext();
        this.sliderManager = sliderManager;
        this.vizMode = "bars";
        this.channelMode = "stereo";
        this.meterTimeout = {};

        this._resizeCanvas();
        window.addEventListener('resize', () => this._resizeCanvas());
    }

    _resizeCanvas() {
        this.canvas.width = this.canvas.offsetWidth;
        this.canvas.height = this.canvas.offsetHeight;
    }

    setMode(mode) { this.vizMode = mode; }
    setChannelMode(mode) { this.channelMode = mode; }

    getVizColor(key) {
        return getComputedStyle(document.body).getPropertyValue(key).trim();
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        const bufferLength = this.analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        this.analyser.getByteFrequencyData(dataArray);

        this._updateNeonMeters(dataArray, bufferLength);
        this._drawVisualizer();
    }

    _getActiveAnalyser() {
        switch(this.channelMode) {
            case 'left': return this.analyserLeft;
            case 'right': return this.analyserRight;
            default: return this.analyser;
        }
    }

    _updateNeonMeters(dataArray, bufferLength) {
        const nyquist = this.audioCtx.sampleRate / 2;
        const meterHeightPx = 75;

        ISO_10_FREQS.forEach((freq, index) => {
            const bandName = EQ_BANDS[index];
            const octaveFactor = 0.707;
            const li = Math.floor(((freq * octaveFactor) / nyquist) * bufferLength);
            const hi = Math.min(Math.floor(((freq / octaveFactor) / nyquist) * bufferLength), bufferLength);

            let sum = 0;
            for (let i = li; i < hi; i++) sum += dataArray[i];

            const count = hi - li;
            const avg = count > 0 ? sum / count : 0;
            const level = (avg / 255) * 100;

            const fillEl = this.sliderManager.meters[bandName];
            const peakEl = this.sliderManager.peakIndicators[bandName];

            if (fillEl && peakEl) {
                fillEl.style.height = `${level}%`;

                const peakLevelPx = (level / 100) * meterHeightPx;
                const currentPeak = parseFloat(peakEl.getAttribute('data-peak-level') || 0);

                if (level > currentPeak - 2) {
                    peakEl.style.opacity = 1;
                    peakEl.style.bottom = `${peakLevelPx}px`;
                    peakEl.setAttribute('data-peak-level', level);

                    clearTimeout(this.meterTimeout[bandName]);
                    this.meterTimeout[bandName] = setTimeout(() => {
                        peakEl.style.opacity = 0;
                        peakEl.setAttribute('data-peak-level', 0);
                    }, 500);
                }
            }
        });
    }

    _drawVisualizer() {
        const activeAnalyser = this._getActiveAnalyser();
        const bufferLength = activeAnalyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        activeAnalyser.getByteFrequencyData(dataArray);

        const accentColor = this.getVizColor('--accent');
        const accent2Color = this.getVizColor('--accent2');

        let channelColor = accent2Color;
        if (this.channelMode === 'left') channelColor = '#ff6b6b';
        if (this.channelMode === 'right') channelColor = '#4ecdc4';

        switch(this.vizMode) {
            case "bars":
                const barWidth = (this.canvas.width / bufferLength) * 2.5;
                let x = 0;
                for (let i = 0; i < bufferLength; i++) {
                    const h = (dataArray[i] / 255) * this.canvas.height * 0.85;
                    const gradient = this.ctx.createLinearGradient(0, this.canvas.height - h, 0, this.canvas.height);

                    if (this.channelMode === 'stereo') {
                        gradient.addColorStop(0, accent2Color);
                        gradient.addColorStop(0.5, accentColor);
                        gradient.addColorStop(1, accent2Color);
                    } else {
                        gradient.addColorStop(0, channelColor);
                        gradient.addColorStop(0.5, accentColor);
                        gradient.addColorStop(1, channelColor);
                    }

                    this.ctx.fillStyle = gradient;
                    this.ctx.fillRect(x, this.canvas.height - h, barWidth, h);
                    x += barWidth + 1;
                }
                break;

            case "wave":
                const tArr = new Uint8Array(activeAnalyser.fftSize);
                activeAnalyser.getByteTimeDomainData(tArr);
                this.ctx.lineWidth = 3;
                const gradient = this.ctx.createLinearGradient(0, 0, this.canvas.width, 0);

                if (this.channelMode === 'stereo') {
                    gradient.addColorStop(0, accentColor);
                    gradient.addColorStop(0.5, accent2Color);
                    gradient.addColorStop(1, accentColor);
                } else {
                    gradient.addColorStop(0, channelColor);
                    gradient.addColorStop(0.5, accentColor);
                    gradient.addColorStop(1, channelColor);
                }

                this.ctx.strokeStyle = gradient;
                this.ctx.beginPath();

                const slice = this.canvas.width / activeAnalyser.fftSize;
                let xW = 0;
                for (let i = 0; i < activeAnalyser.fftSize; i++) {
                    const v = tArr[i] / 128.0;
                    const y = v * this.canvas.height / 2;
                    if (i === 0) this.ctx.moveTo(xW, y);
                    else this.ctx.lineTo(xW, y);
                    xW += slice;
                }
                this.ctx.lineTo(this.canvas.width, this.canvas.height / 2);
                this.ctx.stroke();
                break;

            case "circle":
                const r = Math.min(this.canvas.width, this.canvas.height) * 0.15;
                const cx = this.canvas.width / 2;
                const cy = this.canvas.height / 2;

                for (let i = 0; i < bufferLength; i += 6) {
                    const val = dataArray[i] / 255;
                    const ang = (i / bufferLength) * Math.PI * 2;
                    const rr = r + val * 70;
                    const xC = cx + Math.cos(ang) * rr;
                    const yC = cy + Math.sin(ang) * rr;

                    let fillColor;
                    if (this.channelMode === 'stereo') {
                        const hue = (i / bufferLength) * 360;
                        fillColor = `hsl(${hue}, 100%, 60%)`;
                    } else {
                        fillColor = channelColor;
                    }

                    this.ctx.fillStyle = fillColor;
                    this.ctx.beginPath();
                    this.ctx.arc(xC, yC, 2 + val * 4, 0, 2 * Math.PI);
                    this.ctx.fill();
                }
                break;

            case "spectrum":
                const gradient2 = this.ctx.createLinearGradient(0, 0, 0, this.canvas.height);
                gradient2.addColorStop(0, accent2Color);
                gradient2.addColorStop(0.5, accentColor);
                gradient2.addColorStop(1, '#ff0044');

                this.ctx.fillStyle = gradient2;
                this.ctx.beginPath();
                this.ctx.moveTo(0, this.canvas.height);

                const step = this.canvas.width / bufferLength;
                for (let i = 0; i < bufferLength; i++) {
                    const y = this.canvas.height - (dataArray[i] / 255) * this.canvas.height * 0.85;
                    this.ctx.lineTo(i * step, y);
                }

                this.ctx.lineTo(this.canvas.width, this.canvas.height);
                this.ctx.closePath();
                this.ctx.fill();

                this.ctx.strokeStyle = accent2Color;
                this.ctx.lineWidth = 2;
                this.ctx.beginPath();
                this.ctx.moveTo(0, this.canvas.height - (dataArray[0] / 255) * this.canvas.height * 0.85);
                for (let i = 1; i < bufferLength; i++) {
                    const y = this.canvas.height - (dataArray[i] / 255) * this.canvas.height * 0.85;
                    this.ctx.lineTo(i * step, y);
                }
                this.ctx.stroke();
                break;
        }
    }
}

class ScratchManager {
    constructor(turntableEl, engine, playerManager) {
        this.turntableEl = turntableEl;
        this.engine = engine;
        this.playerManager = playerManager;
        this.isDragging = false;
        this.lastX = 0;
        this.rotation = 0;

        this._setupListeners();
        this._animateRPM();
    }

    _setupListeners() {
        this.turntableEl.addEventListener('mousedown', (e) => this.handleStart(e));
        document.addEventListener('mousemove', (e) => this.handleMove(e));
        document.addEventListener('mouseup', () => this.handleEnd());

        this.turntableEl.addEventListener('touchstart', (e) => {
            e.preventDefault();
            this.handleStart(e.touches[0]);
        });
        document.addEventListener('touchmove', (e) => {
            if (this.isDragging) e.preventDefault();
            this.handleMove(e.touches[0]);
        });
        document.addEventListener('touchend', () => this.handleEnd());

        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
            if (e.key === 's' || e.key === 'S') {
                e.preventDefault();
                if (!this.engine.isScratching) {
                    this.engine.startScratch();
                    this.turntableEl.classList.add('scratch-active');
                }
            }
        });

        document.addEventListener('keyup', (e) => {
            if (e.key === 's' || e.key === 'S') {
                e.preventDefault();
                this.engine.stopScratch(this.playerManager.isPlaying);
                this.turntableEl.classList.remove('scratch-active');
            }
        });
    }

    handleStart(e) {
        if (this.playerManager.audio.src === "") return;
        this.isDragging = true;
        this.lastX = e.clientX || e.pageX;
        this.turntableEl.classList.add('scratch-active');
        this.engine.startScratch();
    }

    handleMove(e) {
        if (!this.isDragging && !this.engine.isScratching) return;
        const currentX = e.clientX || e.pageX;
        const deltaX = currentX - this.lastX;
        this.engine.scratch(deltaX);
        this.rotation += deltaX * 2;
        this.turntableEl.style.transform = `rotate(${this.rotation}deg)`;
        this.lastX = currentX;
    }

    handleEnd() {
        if (!this.isDragging) return;
        this.isDragging = false;
        this.engine.stopScratch(this.playerManager.isPlaying);
        this.turntableEl.classList.remove('scratch-active');
    }

    _animateRPM() {
        const rpmDisplay = document.getElementById('rpmDisplay');
        setInterval(() => {
            const velocity = Math.abs(this.engine.getScratchVelocity());
            const rpm = Math.min(Math.round(velocity / 10), 999);

            if (this.engine.isScratching) {
                rpmDisplay.textContent = `${rpm} RPM`;
                rpmDisplay.style.color = rpm > 100 ? 'var(--warning)' : 'var(--accent2)';
            } else {
                rpmDisplay.textContent = '33 RPM';
                rpmDisplay.style.color = 'var(--accent2)';
            }
        }, 100);
    }
}

class EffectsManager {
    constructor(engine) {
        this.engine = engine;
        this._setupEffectControls();
    }

    _setupEffectControls() {
        const reverbToggle = document.getElementById('reverbToggle');
        const reverbSlider = document.getElementById('reverbAmount');
        const reverbValue = document.getElementById('reverbValue');

        reverbToggle.addEventListener('click', () => {
            reverbToggle.classList.toggle('active');
            const enabled = reverbToggle.classList.contains('active');
            reverbSlider.disabled = !enabled;
            this.engine.setReverb(enabled, parseInt(reverbSlider.value));
        });

        reverbSlider.addEventListener('input', (e) => {
            const value = parseInt(e.target.value);
            reverbValue.textContent = `${value}%`;
            if (reverbToggle.classList.contains('active')) {
                this.engine.setReverb(true, value);
            }
        });

        const distortionToggle = document.getElementById('distortionToggle');
        const distortionSlider = document.getElementById('distortionAmount');
        const distortionValue = document.getElementById('distortionValue');

        distortionToggle.addEventListener('click', () => {
            distortionToggle.classList.toggle('active');
            const enabled = distortionToggle.classList.contains('active');
            distortionSlider.disabled = !enabled;
            this.engine.setDistortion(enabled, parseInt(distortionSlider.value));
        });

        distortionSlider.addEventListener('input', (e) => {
            const value = parseInt(e.target.value);
            distortionValue.textContent = `${value}%`;
            if (distortionToggle.classList.contains('active')) {
                this.engine.setDistortion(true, value);
            }
        });

        const delayToggle = document.getElementById('delayToggle');
        const delaySlider = document.getElementById('delayTime');
        const delayValue = document.getElementById('delayValue');

        delayToggle.addEventListener('click', () => {
            delayToggle.classList.toggle('active');
            const enabled = delayToggle.classList.contains('active');
            delaySlider.disabled = !enabled;
            this.engine.setDelay(enabled, parseInt(delaySlider.value));
        });

        delaySlider.addEventListener('input', (e) => {
            const value = parseInt(e.target.value);
            delayValue.textContent = `${value}ms`;
            if (delayToggle.classList.contains('active')) {
                this.engine.setDelay(true, value);
            }
        });

        const filterToggle = document.getElementById('filterToggle');
        const filterSlider = document.getElementById('filterFreq');
        const filterValue = document.getElementById('filterValue');

        filterToggle.addEventListener('click', () => {
            filterToggle.classList.toggle('active');
            const enabled = filterToggle.classList.contains('active');
            filterSlider.disabled = !enabled;
            this.engine.setFilter(enabled, parseInt(filterSlider.value));
        });

        filterSlider.addEventListener('input', (e) => {
            const value = parseInt(e.target.value);
            filterValue.textContent = value >= 1000 ? `${(value / 1000).toFixed(1)}kHz` : `${value}Hz`;
            if (filterToggle.classList.contains('active')) {
                this.engine.setFilter(true, value);
            }
        });

        const compressorToggle = document.getElementById('compressorToggle');
        const compressorSlider = document.getElementById('compressorThreshold');
        const compressorValue = document.getElementById('compressorValue');

        compressorToggle.addEventListener('click', () => {
            compressorToggle.classList.toggle('active');
            const enabled = compressorToggle.classList.contains('active');
            compressorSlider.disabled = !enabled;
            this.engine.setCompressor(enabled, parseInt(compressorSlider.value));
        });

        compressorSlider.addEventListener('input', (e) => {
            const value = parseInt(e.target.value);
            compressorValue.textContent = `${value}dB`;
            if (compressorToggle.classList.contains('active')) {
                this.engine.setCompressor(true, value);
            }
        });

        document.getElementById('detectBPM').addEventListener('click', async () => {
            const btn = document.getElementById('detectBPM');
            const bpmValue = document.getElementById('bpmValue');
            const bpmStat = document.getElementById('bpmStat');

            btn.disabled = true;
            btn.textContent = 'Detecting...';
            bpmValue.textContent = '...';

            const bpm = await this.engine.detectBPM();

            if (bpm) {
                bpmValue.textContent = bpm;
                bpmStat.textContent = bpm;
                showToast(`BPM detected: ${bpm}`);
            } else {
                bpmValue.textContent = '--';
                showToast('Could not detect BPM', 'warning');
            }

            btn.disabled = false;
            btn.textContent = '🎵 Detect BPM';
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const elements = {
        audio: document.getElementById('audio'),
        playBtn: document.getElementById('playBtn'),
        prevBtn: document.getElementById('prevBtn'),
        nextBtn: document.getElementById('nextBtn'),
        addBtn: document.getElementById('addBtn'),
        loopBtn: document.getElementById('loopBtn'),
        fileInput: document.getElementById('fileInput'),
        playlistList: document.getElementById('playlist-list'),
        visualizerCanvas: document.getElementById('visualizer'),
        presetEQ: document.getElementById('presetEQ'),
        vizMode: document.getElementById('vizMode'),
        eqContainer: document.getElementById('eqContainer'),
        turntable: document.getElementById('turntable'),
    };

    const engine = new DJStudioEngine(elements.audio);
    const playerManager = new PlayerManager(elements, engine);
    const sliderManager = new SliderManager(elements.eqContainer, engine, elements.presetEQ);
    new ScratchManager(elements.turntable, engine, playerManager);
    new EffectsManager(engine);

    const visualizer = new Visualizer(elements.visualizerCanvas, engine, sliderManager);
    visualizer.animate();

    elements.vizMode.addEventListener("change", e => {
        visualizer.setMode(e.target.value);
        showToast(`Visualizer: ${e.target.value}`);
    });

    const channelBtns = document.querySelectorAll('.channel-btn');
    channelBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            channelBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            visualizer.setChannelMode(btn.dataset.channel);
        });
    });

    document.getElementById('resetEQ').addEventListener('click', () => sliderManager.reset());

    const volumeSlider = document.getElementById('volumeSlider');
    const volumeValue = document.getElementById('volumeValue');
    const volumeIcon = document.getElementById('volumeIcon');

    volumeSlider.addEventListener('input', (e) => {
        const vol = parseInt(e.target.value);
        volumeValue.textContent = `${vol}%`;
        engine.setMasterVolume(vol / 100);

        if (vol === 0) volumeIcon.textContent = '🔇';
        else if (vol < 33) volumeIcon.textContent = '🔈';
        else if (vol < 66) volumeIcon.textContent = '🔉';
        else volumeIcon.textContent = '🔊';
    });

    volumeIcon.addEventListener('click', () => {
        if (volumeSlider.value > 0) {
            volumeSlider.setAttribute('data-prev-volume', volumeSlider.value);
            volumeSlider.value = 0;
        } else {
            const prevVol = volumeSlider.getAttribute('data-prev-volume') || 100;
            volumeSlider.value = prevVol;
        }
        volumeSlider.dispatchEvent(new Event('input'));
    });

    const crossfadeSlider = document.getElementById('crossfadeSlider');
    const crossfadeValue = document.getElementById('crossfadeValue');

    crossfadeSlider.addEventListener('input', (e) => {
        const ms = parseInt(e.target.value);
        crossfadeValue.textContent = `${(ms / 1000).toFixed(1)}s`;
        engine.setCrossfadeDuration(ms);
        playerManager.useCrossfade = ms > 0;
    });

    document.getElementById('saveEQBtn').addEventListener('click', () => sliderManager.saveEQ());
    document.getElementById('loadEQBtn').addEventListener('click', () => sliderManager.loadEQ());

    showToast('DJ Studio Pro V3 Ready!');
});
</script>
</body>
</html>
