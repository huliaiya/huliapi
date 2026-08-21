<?php
$yn_token = $yn_token ?? '';
?>
<!-- 原耽悬浮音乐播放器 -->
<div id="yn-player" class="yn-collapsed">
  <div class="yn-toggle" id="ynToggle">
    <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55C7.79 13 6 14.79 6 17s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
  </div>
  <div class="yn-panel" id="ynPanel">
    <div class="yn-header">
      <span class="yn-title" id="ynTitle">原耽音乐</span>
      <button class="yn-close" id="ynClose"><svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
    </div>
    <div class="yn-body">
      <div class="yn-cover-wrap">
        <img class="yn-cover" id="ynCover" src="" alt="">
      </div>
      <div class="yn-info">
        <div class="yn-song" id="ynSong">加载中...</div>
        <div class="yn-artist" id="ynArtist"></div>
      </div>
      <div class="yn-controls">
        <button class="yn-btn" id="ynPrev"><svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg></button>
        <button class="yn-btn yn-play-btn" id="ynPlay"><svg id="ynPlayIcon" viewBox="0 0 24 24" fill="currentColor" width="22" height="22"><path d="M8 5v14l11-7z"/></svg></button>
        <button class="yn-btn" id="ynNext"><svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg></button>
      </div>
    </div>
  </div>
</div>
<audio id="ynAudio" preload="none"></audio>
<style>
#yn-player{position:fixed;bottom:24px;right:24px;z-index:9999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;}
#yn-player *{box-sizing:border-box;margin:0;padding:0;}
.yn-toggle{
  width:52px;height:52px;border-radius:50%;
  background:linear-gradient(135deg,#4a90e2,#6ab0f3);
  color:#fff;display:flex;align-items:center;justify-content:center;
  cursor:pointer;box-shadow:0 4px 16px rgba(74,144,226,.4);
  transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .25s;
  position:relative;
}
.yn-toggle:hover{transform:scale(1.08);}
.yn-collapsed .yn-panel{opacity:0;pointer-events:none;transform:translateY(12px) scale(.92);}
.yn-expanded .yn-toggle{opacity:0;pointer-events:none;transform:scale(.8);}
.yn-panel{
  position:absolute;bottom:0;right:0;
  width:280px;background:rgba(255,255,255,.88);
  backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
  border-radius:18px;border:1px solid rgba(255,255,255,.7);
  box-shadow:0 8px 32px rgba(0,0,0,.1);
  transition:all .3s cubic-bezier(.34,1.56,.64,1);
  overflow:hidden;
}
.yn-header{display:flex;align-items:center;justify-content:space-between;padding:12px 16px 8px;}
.yn-title{font-size:13px;font-weight:700;color:#1a2b4a;}
.yn-close{
  width:28px;height:28px;border-radius:50%;border:none;background:rgba(0,0,0,.06);color:#64748b;
  cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;
}
.yn-close:hover{background:rgba(0,0,0,.12);}
.yn-body{padding:0 16px 14px;}
.yn-cover-wrap{width:100%;aspect-ratio:4/1;border-radius:12px;overflow:hidden;margin-bottom:10px;background:rgba(74,144,226,.1);}
.yn-cover{width:100%;height:100%;object-fit:cover;display:block;}
.yn-info{margin-bottom:10px;}
.yn-song{font-size:14px;font-weight:700;color:#1a2b4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.yn-artist{font-size:12px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.yn-controls{display:flex;align-items:center;justify-content:center;gap:14px;}
.yn-btn{
  width:36px;height:36px;border-radius:50%;border:none;background:rgba(74,144,226,.1);color:#4a90e2;
  cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;
}
.yn-btn:hover{background:rgba(74,144,226,.2);}
.yn-play-btn{width:44px;height:44px;background:linear-gradient(135deg,#4a90e2,#6ab0f3);color:#fff;box-shadow:0 4px 12px rgba(74,144,226,.3);}
.yn-play-btn:hover{background:linear-gradient(135deg,#3a7bd5,#5a9fe0);}
@media(max-width:480px){
  #yn-player{bottom:16px;right:16px;}
  .yn-panel{width:260px;right:0;}
  .yn-toggle{width:48px;height:48px;}
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

var playlist = [], currentIdx = -1, isPlaying = false;
var audio = document.getElementById('ynAudio');
var player = document.getElementById('yn-player');
var toggle = document.getElementById('ynToggle');
var panel = document.getElementById('ynPanel');
var closeBtn = document.getElementById('ynClose');
var playBtn = document.getElementById('ynPlay');
var playIcon = document.getElementById('ynPlayIcon');
var prevBtn = document.getElementById('ynPrev');
var nextBtn = document.getElementById('ynNext');
var titleEl = document.getElementById('ynSong');
var artistEl = document.getElementById('ynArtist');
var coverEl = document.getElementById('ynCover');

toggle.addEventListener('click',function(){player.classList.remove('yn-collapsed');player.classList.add('yn-expanded');});
closeBtn.addEventListener('click',function(){player.classList.remove('yn-expanded');player.classList.add('yn-collapsed');});

function fmt(s){if(!s||isNaN(s))return'0:00';var m=Math.floor(s/60),sec=Math.floor(s%60);return m+':'+sec.toString().padStart(2,'0');}
function parseName(fn){var n=fn.replace(/\.[^.]+$/,'');if(n.includes(' - ')){var p=n.split(' - ');return{title:p[1].trim(),artist:p[0].trim()};}return{title:n,artist:'原耽'};}

function loadTrack(idx,auto){
  if(idx<0||idx>=playlist.length)return;
  currentIdx=idx;var t=playlist[idx];
  audio.src=t.url;coverEl.src=t.cover;titleEl.textContent=t.title;artistEl.textContent=t.artist;
  if(auto)audio.play().catch(function(){});
}

function getNext(){return(currentIdx+1)%playlist.length;}
function getPrev(){return(currentIdx-1+playlist.length)%playlist.length;}
function togglePlay(){
  if(audio.paused)audio.play().catch(function(){});else audio.pause();
}

playBtn.addEventListener('click',togglePlay);
prevBtn.addEventListener('click',function(){loadTrack(getPrev(),true);});
nextBtn.addEventListener('click',function(){loadTrack(getNext(),true);});

audio.addEventListener('play',function(){isPlaying=true;playIcon.innerHTML='<path d=\"M6 19h4V5H6v14zm8-14v14h4V5h-4z\"/>';});
audio.addEventListener('pause',function(){isPlaying=false;playIcon.innerHTML='<path d=\"M8 5v14l11-7z\"/>';});
audio.addEventListener('ended',function(){loadTrack(getNext(),true);});
audio.addEventListener('error',function(){setTimeout(function(){loadTrack(getNext(),true);},2000);});

async function fetchPlaylist(){
  try{
    var headers={'Accept':'application/vnd.github.v3+json'};
    if(token)headers['Authorization']='token '+token;
    var resp=await fetch(API_URL,{headers:headers});
    if(!resp.ok)throw new Error('HTTP '+resp.status);
    var data=await resp.json();
    if(!Array.isArray(data))throw new Error('bad response');
    playlist=data.filter(function(f){var e=f.name.split('.').pop().toLowerCase();return EXTS.indexOf(e)!==-1;}).map(function(f){
      var info=parseName(f.name);return{name:f.name,title:info.title,artist:info.artist,url:CDN+encodeURIComponent(f.name).replace(/%2F/g,'/'),cover:COVER};
    });
    if(playlist.length===0)throw new Error('no music');
    var ri=Math.floor(Math.random()*playlist.length);
    loadTrack(ri,false);
  }catch(e){titleEl.textContent='未加载';artistEl.textContent='点击重试';
    toggle.addEventListener('click',function(){fetchPlaylist();},{once:true});
  }
}
fetchPlaylist();
})();
</script>