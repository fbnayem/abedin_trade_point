(function(){
  var msg='Preview site: quote requests are switched off here. They work on the live WordPress site.';
  var bar=document.createElement('div');
  bar.setAttribute('role','note');
  bar.style.cssText='background:#FFF3C4;color:#5c3d00;text-align:center;padding:8px 12px;font:600 14px/1.4 Inter,system-ui,sans-serif;border-bottom:1px solid #F2D48A';
  bar.textContent='Preview of the Abedin Trade Point website. Highlighted [BRACKETS] are details still to be supplied.';
  document.body.insertBefore(bar, document.body.firstChild);
  function toast(){var t=document.createElement('div');t.className='atp-toast';t.textContent=msg;document.body.appendChild(t);setTimeout(function(){t.remove();},4000);}
  document.addEventListener('click',function(e){var b=e.target.closest('[data-atp-add],[data-atp-submit]');if(b){e.preventDefault();e.stopImmediatePropagation();toast();}},true);
  document.addEventListener('submit',function(e){if(e.target.closest('.atp-form,[data-atp-single],form[role=search],.wp-block-search')){e.preventDefault();e.stopImmediatePropagation();toast();}},true);
  window.fetch=(function(f){return function(u,o){if(String(u).indexOf('/wp-json/')>-1){return Promise.resolve(new Response(JSON.stringify({items:[],count:0}),{headers:{'Content-Type':'application/json'}}));}return f.apply(this,arguments);};})(window.fetch);
})();
