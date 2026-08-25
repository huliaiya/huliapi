<?php
$music_settings = is_array($music_settings ?? null) ? $music_settings : [];
$music_config = [
    'repo' => (string)($music_settings['music_repo'] ?? 'huliaiya/huliaiya.github.io'),
    'branch' => (string)($music_settings['music_branch'] ?? 'main'),
    'directory' => trim((string)($music_settings['music_directory'] ?? 'yn'), '/'),
    'playlistUrl' => (string)($music_settings['music_playlist_url'] ?? ''),
    'cdnBase' => rtrim((string)($music_settings['music_cdn_base'] ?? 'https://cdn.jsdelivr.net/gh/'), '/') . '/',
    'playMode' => ($music_settings['music_play_mode'] ?? 'random') === 'sequential' ? 'sequential' : 'random',
    'autoplay' => ($music_settings['music_autoplay'] ?? '0') === '1',
    'defaultVolume' => max(0, min(1, (float)($music_settings['music_default_volume'] ?? 0.5))),
];
?>
<div id="yn-player" class="yn-collapsed">
  <div class="yn-toggle" id="ynToggle" title="音乐">
    <svg class="yn-toggle-icon" id="ynToggleIcon" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
  </div>
  <div class="yn-panel" id="ynPanel">
    <div class="yn-panel-header">
      <button class="yn-close" id="ynClose"><svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
    </div>
    <div class="yn-song" id="ynSong">加载中...</div>
    <div class="yn-artist" id="ynArtist"></div>
    <div class="yn-progress" id="ynProgress"><div class="yn-progress-bar" id="ynProgressBar"></div></div>
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
  width:54px;height:54px;border-radius:50%;
  background:linear-gradient(135deg,rgba(255,255,255,.5),rgba(255,255,255,.18));
  backdrop-filter:blur(16px) saturate(180%);-webkit-backdrop-filter:blur(16px) saturate(180%);
  border:1px solid rgba(255,255,255,.6);
  color:rgba(30,58,120,.85);
  display:flex;align-items:center;justify-content:center;
  cursor:grab;
  box-shadow:0 8px 32px rgba(31,62,120,.22),inset 0 1px 0 rgba(255,255,255,.7),inset 0 -6px 12px rgba(255,255,255,.25);
  transition:transform .35s cubic-bezier(.34,1.56,.64,1),box-shadow .35s,opacity .3s;
  text-shadow:0 1px 0 rgba(255,255,255,.6);
}
.yn-toggle-icon{display:block;}
.yn-toggle:hover{transform:scale(1.08);box-shadow:0 10px 36px rgba(31,62,120,.3),inset 0 1px 0 rgba(255,255,255,.8);}
.yn-toggle:active{cursor:grabbing;}
#yn-player.yn-playing .yn-toggle-icon{animation:ynIconSpin 6s linear infinite;}
@keyframes ynIconSpin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}
.yn-collapsed .yn-panel{opacity:0;pointer-events:none;transform:translateY(12px) scale(.92);}
.yn-expanded .yn-toggle{opacity:0;pointer-events:none;transform:scale(.7);}
.yn-panel{
  position:absolute;bottom:0;right:0;
  width:228px;background:linear-gradient(160deg,rgba(255,255,255,.42),rgba(255,255,255,.16));
  backdrop-filter:blur(28px) saturate(160%);-webkit-backdrop-filter:blur(28px) saturate(160%);
  border-radius:22px;border:1px solid rgba(255,255,255,.65);
  box-shadow:0 12px 40px rgba(31,62,120,.18),inset 0 1px 0 rgba(255,255,255,.65),inset 0 -10px 20px rgba(255,255,255,.15);
  transition:all .35s cubic-bezier(.34,1.56,.64,1);
  overflow:hidden;padding:14px 16px 16px;
}
.yn-panel-header{display:flex;align-items:center;justify-content:flex-end;margin-bottom:8px;}
.yn-close{
  width:27px;height:27px;border-radius:50%;border:1px solid rgba(255,255,255,.7);background:rgba(255,255,255,.35);
  color:#3b4a63;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s,transform .2s;
  backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
}
.yn-close:hover{background:rgba(255,255,255,.7);transform:rotate(90deg);}
.yn-song{font-size:15px;font-weight:700;color:#1a2b4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px;}
.yn-artist{font-size:12px;color:#5a6a7e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:12px;}
.yn-controls{display:flex;align-items:center;justify-content:center;gap:18px;}
.yn-btn{
  width:36px;height:36px;border-radius:50%;border:1px solid rgba(255,255,255,.8);background:rgba(255,255,255,.42);color:#4a90e2;
  cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .22s;
  backdrop-filter:blur(10px) saturate(140%);-webkit-backdrop-filter:blur(10px) saturate(140%);
  box-shadow:0 4px 12px rgba(31,62,120,.12),inset 0 1px 0 rgba(255,255,255,.7);
}
.yn-btn:hover{background:rgba(255,255,255,.75);transform:translateY(-2px);}
.yn-play-btn{width:46px;height:46px;background:linear-gradient(135deg,rgba(74,144,226,.9),rgba(106,176,243,.85));color:#fff;border-color:rgba(255,255,255,.5);box-shadow:0 6px 18px rgba(74,144,226,.35),inset 0 1px 0 rgba(255,255,255,.35);}
.yn-play-btn:hover{background:linear-gradient(135deg,rgba(58,123,213,.95),rgba(90,159,224,.9));transform:scale(1.06);}
.yn-progress{
  height:8px;border-radius:4px;background:rgba(255,255,255,.55);margin:2px 4px 16px;overflow:visible;position:relative;
  cursor:pointer;box-shadow:inset 0 1px 2px rgba(31,62,120,.15);
}
.yn-progress::after{content:'';position:absolute;left:-4px;right:-4px;top:-5px;bottom:-5px;}
.yn-progress-bar{position:absolute;left:0;top:1px;bottom:1px;width:0%;border-radius:4px;background:linear-gradient(90deg,#4a90e2,#6ab0f3);box-shadow:0 0 6px rgba(74,144,226,.5);transition:width .25s linear;}
.yn-progress-bar::after{content:'';position:absolute;right:-5px;top:50%;transform:translateY(-50%);width:10px;height:10px;border-radius:50%;background:#fff;border:2px solid #4a90e2;box-shadow:0 1px 4px rgba(31,62,120,.3);opacity:0;transition:opacity .2s;}
#yn-player:hover .yn-progress-bar::after{opacity:1;}
@media(max-width:480px){
  #yn-player{bottom:16px;right:16px;}
  .yn-panel{width:206px;}
  .yn-toggle{width:48px;height:48px;}
}
</style>
<script>
(function(){
var MUSIC_CONFIG = <?php echo json_encode($music_config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
var REPO = MUSIC_CONFIG.repo, BRANCH = MUSIC_CONFIG.branch, DIR = MUSIC_CONFIG.directory;
var CDN = MUSIC_CONFIG.cdnBase + REPO + '@' + BRANCH + '/' + (DIR ? DIR + '/' : '');
var API_URL = 'https://api.github.com/repos/' + encodeURIComponent(REPO).replace('%2F', '/') + '/contents/' + DIR.split('/').map(encodeURIComponent).join('/') + '?ref=' + encodeURIComponent(BRANCH);
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
var progressBar = document.getElementById('ynProgressBar');
var progressWrap = document.getElementById('ynProgress');
var errorGuard = 0;
audio.volume = MUSIC_CONFIG.defaultVolume;

function parseName(fn){
  var n = fn.replace(/\.[^.]+$/, '');
  if (n.indexOf(' - ') !== -1) {
    var p = n.split(' - ');
    return { title: p[1].trim(), artist: p[0].trim() };
  }
  return { title: n, artist: '原耽' };
}

function setPlayIcon(state){
  playIcon.innerHTML = state === 'playing'
    ? '<path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>'
    : '<path d="M8 5v14l11-7z"/>';
  if (state === 'playing') player.classList.add('yn-playing');
  else player.classList.remove('yn-playing');
}

function loadTrack(idx, autoPlay){
  if (idx < 0 || idx >= playlist.length) return;
  currentIdx = idx;
  var t = playlist[idx];
  audio.src = t.url;
  titleEl.textContent = t.title;
  artistEl.textContent = t.artist;
  audio.load();
  if (autoPlay) {
    audio.play().catch(function(){});
  }
}

function getNext() {
  if (MUSIC_CONFIG.playMode === 'random' && playlist.length > 1) {
    var nextIdx = currentIdx;
    var guard = 0;
    while (nextIdx === currentIdx && guard++ < playlist.length) nextIdx = Math.floor(Math.random() * playlist.length);
    return nextIdx;
  }
  return (currentIdx + 1) % playlist.length;
}
function getPrev() { return (currentIdx - 1 + playlist.length) % playlist.length; }

function tryPlay(showBlockedMessage) {
  var p = audio.play();
  if (p && p.catch) {
    return p.catch(function(){
      if (showBlockedMessage) {
        artistEl.textContent = '浏览器已阻止自动播放，请点击播放按钮';
      }
    });
  }
  return p;
}

function togglePlay(){
  if (!audio.src) { loadTrack(currentIdx < 0 ? 0 : currentIdx, true); return; }
  if (audio.paused) {
    tryPlay(false).then(function(){
      if (audio.paused) { /* double-check */ }
    }).catch(function(){});
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
playBtn.addEventListener('click', function(){
  if (!playlist.length) return;
  if (!audio.src) { loadTrack(currentIdx < 0 ? 0 : currentIdx, true); return; }
  togglePlay();
});
prevBtn.addEventListener('click', function(){
  if (!playlist.length) return;
  loadTrack(getPrev(), true);
});
nextBtn.addEventListener('click', function(){
  if (!playlist.length) return;
  loadTrack(getNext(), true);
});

audio.addEventListener('play', function(){ setPlayIcon('playing'); });
audio.addEventListener('pause', function(){ setPlayIcon('paused'); });
audio.addEventListener('ended', function(){
  setPlayIcon('paused');
  loadTrack(getNext(), true);
});
audio.addEventListener('error', function(){
  if (playlist.length && errorGuard < playlist.length) {
    errorGuard++;
    artistEl.textContent = '音频加载失败，自动切换下一首';
    loadTrack(getNext(), false);
    audio.play().catch(function(){});
  } else {
    artistEl.textContent = '无法播放当前歌曲';
  }
});
audio.addEventListener('timeupdate', function(){
  if (!isSeeking && !isNaN(audio.duration) && audio.duration > 0) {
    progressBar.style.width = (audio.currentTime / audio.duration * 100).toFixed(2) + '%';
  }
});
audio.addEventListener('loadedmetadata', function(){
  progressBar.style.width = '0%';
});
var isSeeking = false;
function seekTo(clientX){
  if (!isNaN(audio.duration) && audio.duration > 0) {
    var rect = progressWrap.getBoundingClientRect();
    var pct = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
    progressBar.style.width = (pct * 100).toFixed(2) + '%';
    audio.currentTime = pct * audio.duration;
  }
}
if (progressWrap) {
  var clientX = function(e){
    if (e.touches && e.touches.length) return e.touches[0].clientX;
    return e.clientX;
  };
  progressWrap.addEventListener('mousedown', function(e){ e.preventDefault(); isSeeking = true; seekTo(clientX(e)); });
  document.addEventListener('mousemove', function(e){ if (isSeeking) seekTo(clientX(e)); });
  document.addEventListener('mouseup', function(){ isSeeking = false; });
  progressWrap.addEventListener('touchstart', function(e){ if (e.touches.length) { isSeeking = true; seekTo(e.touches[0].clientX); } }, { passive: true });
  document.addEventListener('touchmove', function(e){ if (isSeeking && e.touches.length) seekTo(e.touches[0].clientX); }, { passive: true });
  document.addEventListener('touchend', function(){ isSeeking = false; });
}

var PL_JSON = MUSIC_CONFIG.playlistUrl || (CDN + 'playlist.json');

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
    var firstIndex = MUSIC_CONFIG.playMode === 'random' ? Math.floor(Math.random() * playlist.length) : 0;
    loadTrack(firstIndex, false);
    if (MUSIC_CONFIG.autoplay) tryPlay(true);
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
