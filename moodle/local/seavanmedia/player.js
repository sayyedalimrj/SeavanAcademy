(function(){
'use strict';
function boot(){
const filter=document.getElementById('sam-filter'),stateFilter=document.getElementById('sam-lessonstate');
const normalize=s=>String(s).toLocaleLowerCase().replace(/[۰-۹]/g,c=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(c))).replace(/[٠-٩]/g,c=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(c))).replace(/ي/g,'ی').replace(/ك/g,'ک').replace(/‌/g,' ');
function applyFilter(){
 const q=normalize(filter?.value||'').trim(),state=stateFilter?.value||'all';
 document.querySelectorAll('.sam-chapter').forEach(ch=>{
  let n=0; ch.querySelectorAll('.sam-lesson').forEach(a=>{const ok=normalize(a.dataset.title||'').includes(q)&&(state==='all'||a.dataset.state===state);a.hidden=!ok;if(ok)n++;});
  ch.hidden=!n;if((q||state!=='all')&&n)ch.open=true;
 });
}
filter?.addEventListener('input',applyFilter);stateFilter?.addEventListener('change',applyFilter);
const v=document.getElementById('sam-player');if(!v)return;
let saving=false,last=0,dirty=false,pending=false,timer=null;
const status=document.getElementById('sam-state'),pct=document.getElementById('sam-percent');
const fa=n=>Number(n).toLocaleString('fa-IR');
const store={get:k=>{try{return localStorage.getItem(k);}catch(e){return null;}},set:(k,v)=>{try{localStorage.setItem(k,v);}catch(e){}}};
const track=document.getElementById('sam-watched-track');
let known=[];try{known=JSON.parse(v.dataset.ranges||'[]');}catch(e){}
function draw(){
 if(!track||!Number.isFinite(v.duration)||v.duration<=0)return;
 track.replaceChildren();
 for(const r of known){if(!Array.isArray(r)||r.length!==2)continue;const a=Math.max(0,Math.min(100,100*r[0]/v.duration)),b=Math.max(a,Math.min(100,100*r[1]/v.duration));const el=document.createElement('span');el.style.left=a+'%';el.style.width=(b-a)+'%';track.append(el);}
}
const auto=document.getElementById('sam-autonext');
if(auto){auto.checked=store.get('seavan-autonext')==='1';auto.addEventListener('change',()=>store.set('seavan-autonext',auto.checked?'1':'0'));}
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
  pct.textContent=fa(j.percent)+'٪';status.textContent='ذخیره شد';last=Date.now();if(Array.isArray(j.ranges)){known=j.ranges;draw();}const active=document.querySelector('.sam-lesson.active');if(active){active.dataset.state=j.percent>=95?'done':j.percent>0?'started':'unseen';const small=active.querySelector('small');if(small)small.textContent=j.percent>=95?'✓':fa(j.percent)+'٪';}
 }catch(e){dirty=true;status.textContent='ذخیره ناموفق؛ تلاش مجدد';}
 finally{saving=false;if(pending){pending=false;setTimeout(()=>save(false),300);}}
}
v.addEventListener('loadedmetadata',()=>{let pos=Number(v.dataset.position);if(pos>2&&pos<v.duration-2){v.currentTime=pos;}draw();});
v.addEventListener('timeupdate',()=>{if(!v.paused){dirty=true;if(Date.now()-last>12000)save(false);}});
v.addEventListener('pause',()=>save(true));v.addEventListener('ended',async()=>{await save(true);if(auto?.checked&&document.getElementById('sam-next')){flush();window.location.assign(document.getElementById('sam-next').href);}});
v.addEventListener('seeked',()=>{dirty=true;clearTimeout(timer);timer=setTimeout(()=>save(false),1000);});
setInterval(()=>{if(dirty)save(false);},15000);
function flush(){if(!dirty&&!saving)return;const d=payload();if(d&&navigator.sendBeacon)navigator.sendBeacon(v.dataset.endpoint,d);}
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')flush();});
window.addEventListener('pagehide',flush);
const on=(id,fn)=>{let el=document.getElementById(id);if(el)el.addEventListener('click',fn);};
on('sam-back',()=>{v.currentTime=Math.max(0,v.currentTime-10);});
on('sam-forward',()=>{v.currentTime=Math.min(v.duration||0,v.currentTime+10);});
on('sam-restart',()=>{v.currentTime=0;});
v.addEventListener('error',()=>{status.textContent='پخش این فرمت در مرورگر ممکن نیست؛ فایل را دانلود کنید.';});
const speed=document.getElementById('sam-speed');if(speed){let r=store.get('seavan-media-speed');if(r&&[...speed.options].some(o=>o.value===r))speed.value=r;v.playbackRate=+speed.value;speed.addEventListener('change',()=>{v.playbackRate=+speed.value;store.set('seavan-media-speed',speed.value);});}
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
