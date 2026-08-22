<?php
$yn_token = $yn_token ?? '';
?>
<div id="yn-player" class="yn-collapsed">
  <div class="yn-toggle" id="ynToggle">
    <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
  </div>
  <div class="yn-panel" id="ynPanel">
    <div class="yn-panel-header">
      <button class="yn-close" id="ynClose"><svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
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
<audio id="ynAudio" preload="auto"></audio>
<style>
#yn-player{position:fixed;bottom:24px;right:24px;z-index:9999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;user-select:none;touch-action:none;}
#yn-player *{box-sizing:border-box;margin:0;padding:0;}
.yn-toggle{
  width:50px;height:50px;border-radius:50%;
  background:linear-gradient(135deg,#4a90e2,#6ab0f3);
  color:#fff;display:flex;align-items:center;justify-content:center;
  cursor:grab;box-shadow:0 4px 20px rgba(74,144,226,.4);
  transition:transform .3s cubic-bezier(.34,1.56,.64,1),opacity .3s;
}
.yn-toggle:hover{transform:scale(1.06);}
.yn-toggle:active{cursor:grabbing;}
.yn-collapsed .yn-panel{opacity:0;pointer-events:none;transform:translateY(12px) scale(.92);}
.yn-expanded .yn-toggle{opacity:0;pointer-events:none;transform:scale(.8);}
.yn-panel{
  position:absolute;bottom:0;right:0;
  width:220px;background:rgba(255,255,255,.18);
  backdrop-filter:blur(36px);-webkit-backdrop-filter:blur(36px);
  border-radius:20px;border:1px solid rgba(255,255,255,.6);
  box-shadow:0 8px 32px rgba(0,0,0,.08);
  transition:all .3s cubic-bezier(.34,1.56,.64,1);
  overflow:hidden;padding:12px 14px 14px;
}
.yn-panel-header{display:flex;align-items:center;justify-content:flex-end;margin-bottom:6px;}
.yn-close{
  width:26px;height:26px;border-radius:50%;border:1px solid rgba(0,0,0,.1);background:rgba(255,255,255,.5);
  color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;
}
.yn-close:hover{background:rgba(255,255,255,.8);}
.yn-song{font-size:15px;font-weight:700;color:#1a2b4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;}
.yn-artist{font-size:12px;color:#5a6a7e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:10px;}
.yn-controls{display:flex;align-items:center;justify-content:center;gap:16px;}
.yn-btn{
  width:34px;height:34px;border-radius:50%;border:1px solid rgba(0,0,0,.08);background:rgba(255,255,255,.5);color:#4a90e2;
  cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
}
.yn-btn:hover{background:rgba(255,255,255,.8);}
.yn-play-btn{width:42px;height:42px;background:linear-gradient(135deg,#4a90e2,#6ab0f3);color:#fff;border-color:transparent;box-shadow:0 4px 12px rgba(74,144,226,.3);}
.yn-play-btn:hover{background:linear-gradient(135deg,#3a7bd5,#5a9fe0);}
@media(max-width:480px){
  #yn-player{bottom:16px;right:16px;}
  .yn-panel{width:200px;}
  .yn-toggle{width:46px;height:46px;}
}
</style>
<script>
(function(){
var token = <?php echo json_encode($yn_token); ?>;
var REPO = 'huliaiya/huliaiya.github.io', BRANCH = 'main', DIR = 'yn';
var CDN = 'https://cdn.jsdelivr.net/gh/' + REPO + '@' + BRANCH + '/' + DIR + '/';
var API_URL = 'https://api.github.com/repos/' + REPO + '/contents/' + DIR + '?ref=' + BRANCH;
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

function parseName(fn){
  var n = fn.replace(/\.[^.]+$/, '');
  if (n.indexOf(' - ') !== -1) {
    var p = n.split(' - ');
    return { title: p[1].trim(), artist: p[0].trim() };
  }
  return { title: n, artist: '原耽' };
}

function loadTrack(idx, autoPlay){
  if (idx < 0 || idx >= playlist.length) return;
  currentIdx = idx;
  var t = playlist[idx];
  audio.src = t.url;
  titleEl.textContent = t.title;
  artistEl.textContent = t.artist;
  if (autoPlay) {
    audio.play().catch(function(){});
  }
}

function getNext() { return (currentIdx + 1) % playlist.length; }
function getPrev() { return (currentIdx - 1 + playlist.length) % playlist.length; }

function togglePlay(){
  if (audio.paused) {
    audio.play().catch(function(){});
  } else {
    audio.pause();
  }
}

toggle.addEventListener('click', function(){
  if (wasDragged) { wasDragged = false; return; }
  player.classList.remove('yn-collapsed');
  player.classList.add('yn-expanded');
});
closeBtn.addEventListener('click', function(){
  player.classList.remove('yn-expanded');
  player.classList.add('yn-collapsed');
});
playBtn.addEventListener('click', togglePlay);
prevBtn.addEventListener('click', function(){ loadTrack(getPrev(), true); });
nextBtn.addEventListener('click', function(){ loadTrack(getNext(), true); });

audio.addEventListener('play', function(){
  playIcon.innerHTML = '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>';
});
audio.addEventListener('pause', function(){
  playIcon.innerHTML = '<path d="M8 5v14l11-7z"/>';
});
audio.addEventListener('ended', function(){
  loadTrack(getNext(), true);
});

var PL_JSON = 'https://cdn.jsdelivr.net/gh/' + REPO + '@' + BRANCH + '/' + DIR + '/playlist.json';

async function fetchPlaylist(){
  try{
    var tracks;
    try{
      var presp = await fetch(PL_JSON);
      if (presp.ok) {
        var pj = await presp.json();
        if (Array.isArray(pj) && pj.length > 0) {
          tracks = pj.map(function(f){
            var encoded = encodeURIComponent(f.name).replace(/%2F/g, '/');
            return { name: f.name, title: f.title || f.name.replace(/\.[^.]+$/, ''), artist: f.artist || '原耽', url: CDN + encoded };
          });
        }
      }
    } catch(e2){}
    if (!tracks) {
      var headers = { 'Accept': 'application/vnd.github.v3+json' };
      if (token) headers['Authorization'] = 'token ' + token;
      var resp = await fetch(API_URL, { headers: headers });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      var data = await resp.json();
      if (!Array.isArray(data)) throw new Error('bad response');
      tracks = data.filter(function(f){
        var e = f.name.split('.').pop().toLowerCase();
        return EXTS.indexOf(e) !== -1;
      }).map(function(f){
        var info = parseName(f.name);
        var encoded = encodeURIComponent(f.name).replace(/%2F/g, '/');
        return { name: f.name, title: info.title, artist: info.artist, url: CDN + encoded };
      });
    }
    playlist = tracks;
    if (playlist.length === 0) throw new Error('no music');
    var ri = Math.floor(Math.random() * playlist.length);
    loadTrack(ri, false);
  } catch(e) {
    titleEl.textContent = '加载失败';
    artistEl.textContent = e.message || '';
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