<?php
$yn_token = $yn_token ?? '';
?>
<div id="yn-player" class="yn-collapsed">
  <div class="yn-toggle" id="ynToggle">
    <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
  </div>
  <div class="yn-panel" id="ynPanel">
    <div class="yn-panel-header">
      <span class="yn-panel-title" id="ynTitle">原耽音乐</span>
      <button class="yn-close" id="ynClose"><svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
    </div>
    <div class="yn-cover-wrap">
      <img class="yn-cover" id="ynCover" src="" alt="">
    </div>
    <div class="yn-song" id="ynSong">加载中...</div>
    <div class="yn-artist" id="ynArtist"></div>
    <div class="yn-controls">
      <button class="yn-btn" id="ynPrev"><svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg></button>
      <button class="yn-btn yn-play-btn" id="ynPlay"><svg id="ynPlayIcon" viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M8 5v14l11-7z"/></svg></button>
      <button class="yn-btn" id="ynNext"><svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg></button>
    </div>
  </div>
</div>
<audio id="ynAudio" preload="none"></audio>
<style>
#yn-player{position:fixed;bottom:24px;right:24px;z-index:9999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;user-select:none;touch-action:none;}
#yn-player *{box-sizing:border-box;margin:0;padding:0;}
.yn-toggle{
  width:50px;height:50px;border-radius:50%;
  background:rgba(255,255,255,.2);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border:1px solid rgba(255,255,255,.5);
  color:#fff;display:flex;align-items:center;justify-content:center;
  cursor:grab;box-shadow:0 4px 20px rgba(0,0,0,.1);
  transition:transform .3s cubic-bezier(.34,1.56,.64,1),opacity .3s;
}
.yn-toggle:hover{transform:scale(1.06);}
.yn-toggle:active{cursor:grabbing;}
.yn-collapsed .yn-panel{opacity:0;pointer-events:none;transform:translateY(12px) scale(.92);}
.yn-expanded .yn-toggle{opacity:0;pointer-events:none;transform:scale(.8);}
.yn-panel{
  position:absolute;bottom:0;right:0;
  width:270px;background:rgba(255,255,255,.18);
  backdrop-filter:blur(36px);-webkit-backdrop-filter:blur(36px);
  border-radius:20px;border:1px solid rgba(255,255,255,.5);
  box-shadow:0 8px 32px rgba(0,0,0,.08);
  transition:all .3s cubic-bezier(.34,1.56,.64,1);
  overflow:hidden;padding:14px 16px 16px;
}
.yn-panel-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.yn-panel-title{font-size:13px;font-weight:700;color:rgba(255,255,255,.9);text-shadow:0 1px 2px rgba(0,0,0,.15);}
.yn-close{
  width:26px;height:26px;border-radius:50%;border:1px solid rgba(255,255,255,.4);background:rgba(255,255,255,.15);
  color:rgba(255,255,255,.8);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;
}
.yn-close:hover{background:rgba(255,255,255,.3);}
.yn-cover-wrap{width:100%;aspect-ratio:4/1;border-radius:12px;overflow:hidden;margin-bottom:10px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);}
.yn-cover{width:100%;height:100%;object-fit:cover;display:block;}
.yn-song{font-size:15px;font-weight:700;color:rgba(255,255,255,.95);text-shadow:0 1px 3px rgba(0,0,0,.2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;}
.yn-artist{font-size:12px;color:rgba(255,255,255,.7);text-shadow:0 1px 2px rgba(0,0,0,.12);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:10px;}
.yn-controls{display:flex;align-items:center;justify-content:center;gap:16px;}
.yn-btn{
  width:34px;height:34px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:rgba(255,255,255,.9);
  cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
}
.yn-btn:hover{background:rgba(255,255,255,.25);}
.yn-play-btn{width:42px;height:42px;background:rgba(255,255,255,.2);border-color:rgba(255,255,255,.5);box-shadow:0 4px 12px rgba(0,0,0,.1);}
.yn-play-btn:hover{background:rgba(255,255,255,.35);}
@media(max-width:480px){
  #yn-player{bottom:16px;right:16px;}
  .yn-panel{width:250px;}
  .yn-toggle{width:46px;height:46px;}
}
</style>
<script>
(function(){
var token = <?php echo json_encode($yn_token); ?>;
var REPO = 'huliaiya/huliaiya.github.io', BRANCH = 'main', DIR = 'yn';
var CDN = 'https://cdn.jsdelivr.net/gh/' + REPO + '@' + BRANCH + '/' + DIR + '/';
var API_URL = 'https://api.github.com/repos/' + REPO + '/contents/' + DIR + '?ref=' + BRANCH;
var COVER = 'https://cdn.jsdelivr.net/gh/huliaiya/huliaiya.github.io@main/assets/yn.png';
var EXTS = ['mp3','wav','ogg','m4a','flac','aac','opus','webm'];

var playlist = [], currentIdx = -1, wasDragged = false;
var audio = document.getElementById('ynAudio');
var player = document.getElementById('yn-player');
var toggle = document.getElementById('ynToggle');
var closeBtn = document.getElementById('ynClose');
var playBtn = document.getElementById('ynPlay');
var playIcon = document.getElementById('ynPlayIcon');
var prevBtn = document.getElementById('ynPrev');
var nextBtn = document.getElementById('ynNext');
var titleEl = document.getElementById('ynSong');
var artistEl = document.getElementById('ynArtist');
var coverEl = document.getElementById('ynCover');

function parseName(fn){
  var n = fn.replace(/\.[^.]+$/, '');
  if (n.includes(' - ')) { var p = n.split(' - '); return { title: p[1].trim(), artist: p[0].trim() }; }
  return { title: n, artist: '原耽' };
}

function loadTrack(idx, auto){
  if (idx < 0 || idx >= playlist.length) return;
  currentIdx = idx; var t = playlist[idx];
  audio.src = t.url; coverEl.src = t.cover; titleEl.textContent = t.title; artistEl.textContent = t.artist;
  audio.load();
  if (auto) { audio.play().catch(function(){}); }
}

function getNext() { return (currentIdx + 1) % playlist.length; }
function getPrev() { return (currentIdx - 1 + playlist.length) % playlist.length; }

function togglePlay(){
  if (audio.paused) { audio.play().catch(function(){}); } else { audio.pause(); }
}

toggle.addEventListener('click', function(){
  if (wasDragged) { wasDragged = false; return; }
  player.classList.remove('yn-collapsed'); player.classList.add('yn-expanded');
});
closeBtn.addEventListener('click', function(){
  player.classList.remove('yn-expanded'); player.classList.add('yn-collapsed');
});
playBtn.addEventListener('click', togglePlay);
prevBtn.addEventListener('click', function(){ loadTrack(getPrev(), true); });
nextBtn.addEventListener('click', function(){ loadTrack(getNext(), true); });

audio.addEventListener('play', function(){ playIcon.innerHTML = '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>'; });
audio.addEventListener('pause', function(){ playIcon.innerHTML = '<path d="M8 5v14l11-7z"/>'; });
audio.addEventListener('ended', function(){ loadTrack(getNext(), true); });
audio.addEventListener('error', function(){ setTimeout(function(){ loadTrack(getNext(), true); }, 2000); });

async function fetchPlaylist(){
  try{
    var headers = { 'Accept': 'application/vnd.github.v3+json' };
    if (token) headers['Authorization'] = 'token ' + token;
    var resp = await fetch(API_URL, { headers: headers });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);
    var data = await resp.json();
    if (!Array.isArray(data)) throw new Error('bad response');
    playlist = data.filter(function(f){
      var e = f.name.split('.').pop().toLowerCase();
      return EXTS.indexOf(e) !== -1;
    }).map(function(f){
      var info = parseName(f.name);
      return { name: f.name, title: info.title, artist: info.artist, url: CDN + encodeURIComponent(f.name).replace(/%2F/g,'/'), cover: COVER };
    });
    if (playlist.length === 0) throw new Error('no music');
    var ri = Math.floor(Math.random() * playlist.length);
    loadTrack(ri, false);
  } catch(e) {
    var msg = e.message || '加载失败';
    titleEl.textContent = '加载失败'; artistEl.textContent = msg;
  }
}
fetchPlaylist();

// 拖拽
(function(){
  var startX, startY, origX, origY, dragging = false, moved = false;
  var style = player.style;

  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

  function onStart(cx, cy){
    dragging = true; moved = false;
    startX = cx; startY = cy;
    var r = player.getBoundingClientRect();
    origX = r.left; origY = r.top;
  }
  function onMove(cx, cy){
    if (!dragging) return;
    var dx = cx - startX, dy = cy - startY;
    if (Math.abs(dx) > 4 || Math.abs(dy) > 4) moved = true;
    var nx = origX + dx, ny = origY + dy;
    var pw = player.offsetWidth, ph = player.offsetHeight;
    nx = clamp(nx, 0, window.innerWidth - pw);
    ny = clamp(ny, 0, window.innerHeight - ph);
    style.left = nx + 'px'; style.top = ny + 'px';
    style.bottom = 'auto'; style.right = 'auto';
  }
  function onEnd(){
    if (!dragging) return;
    dragging = false;
    if (moved) wasDragged = true;
  }

  toggle.addEventListener('mousedown', function(e){ onStart(e.clientX, e.clientY); e.preventDefault(); });
  document.addEventListener('mousemove', function(e){ onMove(e.clientX, e.clientY); });
  document.addEventListener('mouseup', onEnd);
  toggle.addEventListener('touchstart', function(e){ var t = e.touches[0]; onStart(t.clientX, t.clientY); }, { passive: true });
  document.addEventListener('touchmove', function(e){ if (!dragging) return; var t = e.touches[0]; onMove(t.clientX, t.clientY); }, { passive: true });
  document.addEventListener('touchend', onEnd);
  document.addEventListener('touchcancel', onEnd);
})();
})();
</script>