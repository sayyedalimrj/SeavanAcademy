(function(){
'use strict';
function boot(){
const filter=document.getElementById('sam-filter');
if(filter)filter.addEventListener('input',()=>{
 const q=filter.value.trim().toLocaleLowerCase();
 document.querySelectorAll('.sam-chapter').forEach(ch=>{
  let n=0; ch.querySelectorAll('.sam-lesson').forEach(a=>{const ok=(a.dataset.title||'').toLocaleLowerCase().includes(q);a.hidden=!ok;if(ok)n++;});
  ch.hidden=!n;if(q&&n)ch.open=true;
 });
});
const v=document.getElementById('sam-player');if(!v)return;
let saving=false,last=0,dirty=false,pending=false,timer=null;
const status=document.getElementById('sam-state'),pct=document.getElementById('sam-percent');
const fa=n=>Number(n).toLocaleString('fa-IR');
function payload(){
 if(!Number.isFinite(v.duration)||v.duration<=0)return null;
 let ranges=[];for(let i=0;i<Math.min(v.played.length,1000);i++)ranges.push([v.played.start(i),v.played.end(i)]);
 return new URLSearchParams({id:v.dataset.course,file:v.dataset.file,sesskey:v.dataset.sesskey,duration:String(v.duration),position:String(v.currentTime),ranges:JSON.stringify(ranges)});
}
async function save(force){
 if(saving){pending=true;return;} if(!dirty&&!force)return;
 const data=payload();if(!data)return;saving=true;dirty=false;
 try{
  const r=await fetch(v.dataset.endpoint,{method:'POST',body:data,credentials:'same-origin'});
  if(!r.ok)throw new Error('save');
  const j=await r.json();if(typeof j.percent!=='number')throw new Error('response');
  pct.textContent=fa(j.percent)+'٪';status.textContent='ذخیره شد';last=Date.now();
 }catch(e){dirty=true;status.textContent='ذخیره ناموفق؛ تلاش مجدد';}
 finally{saving=false;if(pending){pending=false;setTimeout(()=>save(false),300);}}
}
v.addEventListener('loadedmetadata',()=>{let pos=Number(v.dataset.position);if(pos>2&&pos<v.duration-2){v.currentTime=pos;}});
v.addEventListener('timeupdate',()=>{if(!v.paused){dirty=true;if(Date.now()-last>12000)save(false);}});
v.addEventListener('pause',()=>save(true));v.addEventListener('ended',()=>save(true));
v.addEventListener('seeked',()=>{dirty=true;clearTimeout(timer);timer=setTimeout(()=>save(false),1000);});
setInterval(()=>{if(dirty)save(false);},15000);
function flush(){if(!dirty&&!saving)return;const d=payload();if(d&&navigator.sendBeacon)navigator.sendBeacon(v.dataset.endpoint,d);}
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')flush();});
window.addEventListener('pagehide',flush);
const on=(id,fn)=>{let el=document.getElementById(id);if(el)el.addEventListener('click',fn);};
on('sam-back',()=>{v.currentTime=Math.max(0,v.currentTime-10);});
on('sam-forward',()=>{v.currentTime=Math.min(v.duration||0,v.currentTime+10);});
on('sam-restart',()=>{v.currentTime=0;});
const speed=document.getElementById('sam-speed');if(speed){let r=localStorage.getItem('seavan-media-speed');if(r&&[...speed.options].some(o=>o.value===r))speed.value=r;v.playbackRate=+speed.value;speed.addEventListener('change',()=>{v.playbackRate=+speed.value;localStorage.setItem('seavan-media-speed',speed.value);});}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
