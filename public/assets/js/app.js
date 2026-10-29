'use strict';
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let toastTimer;
function toast(message) { const el=document.querySelector('#toast'); el.textContent=message; el.classList.add('visible'); clearTimeout(toastTimer); toastTimer=setTimeout(()=>el.classList.remove('visible'),4500); }
document.querySelectorAll('img').forEach(img=>img.addEventListener('error',()=>{if(!img.src.endsWith('placeholder.svg')) img.src='/assets/images/placeholder.svg';},{once:true}));
document.querySelector('[data-menu]')?.addEventListener('click',event=>{ const button=event.currentTarget; const open=button.getAttribute('aria-expanded')!=='true'; button.setAttribute('aria-expanded',String(open)); document.querySelector('#navigation').classList.toggle('open',open); });
document.addEventListener('submit',async event=>{
 const form=event.target;
 if(form.dataset.confirm && !confirm(form.dataset.confirm)){event.preventDefault();return;}
 if(!form.matches('[data-ajax]')) return;
 event.preventDefault();
 const data=new FormData(form); if(event.submitter?.name) data.set(event.submitter.name,event.submitter.value);
 const buttons=[...form.querySelectorAll('button')]; buttons.forEach(b=>b.disabled=true); form.setAttribute('aria-busy','true');
 try {
  const response=await fetch(form.getAttribute('action'),{method:'POST',body:data,headers:{'Accept':'application/json','X-CSRF-Token':csrf}});
  if(response.redirected){location.href=response.url;return;}
  const result=await response.json(); if(!response.ok) throw new Error(result.error||'Please try again.'); toast(result.message||'Saved');
  if(result.count!==undefined) document.querySelectorAll('[data-cart-count]').forEach(e=>e.textContent=result.count);
  if(form.hasAttribute('data-consent')) form.closest('.cookie')?.remove();
  if(form===document.querySelector('#product-form') && data.get('action')==='cart') recordEvent('cart_add',data.get('variant_id'));
  if(form.dataset.buy==='yes'){location.href='/checkout';return;}
  if(form.hasAttribute('data-reload')) location.reload();
 } catch(error){toast(error.message||'Connection lost. Please try again.');}
 finally {buttons.forEach(b=>b.disabled=false);form.removeAttribute('aria-busy');}
});
const variant=document.querySelector('#variant');
variant?.addEventListener('change',()=>{
 const option=variant.selectedOptions[0], stock=Number(option.dataset.stock||0), form=document.querySelector('#product-form');
 document.querySelector('#add-to-cart').disabled=!variant.value||stock<1; document.querySelector('#buy-now').disabled=!variant.value||stock<1;
 const qty=form.querySelector('[name="quantity"]'); qty.max=Math.min(20,stock); if(Number(qty.value)>Number(qty.max)) qty.value=Math.max(1,Number(qty.max));
 document.querySelector('#variant-stock').textContent=variant.value?`${stock} available · Ready for your everyday`:'Select an option to see availability.';
 document.querySelector('#variant-price').textContent='PKR '+((Number(form.dataset.basePrice)+Number(option.dataset.adjustment||0))/100).toLocaleString('en-PK');
});
document.querySelector('#add-to-cart')?.addEventListener('click',()=>document.querySelector('#product-form').dataset.buy='no');
document.querySelector('[data-buy-now]')?.addEventListener('click',event=>{ event.preventDefault();const form=document.querySelector('#product-form');form.dataset.buy='yes';form.requestSubmit(); });
document.querySelectorAll('[data-gallery]').forEach(b=>b.addEventListener('click',()=>document.querySelector('#main-product-image').src=b.dataset.gallery));
document.querySelector('[data-zoom]')?.addEventListener('click',()=>{document.querySelector('#zoom-image').src=document.querySelector('#main-product-image').src;document.querySelector('#zoom-dialog').showModal();});
document.querySelector('[data-close-zoom]')?.addEventListener('click',()=>document.querySelector('#zoom-dialog').close());
document.querySelector('#different-billing')?.addEventListener('change',event=>{ const el=document.querySelector('#billing-fields'); el.hidden=!event.target.checked; el.querySelectorAll('input').forEach(i=>i.required=event.target.checked); });
document.querySelector('#fit-calculator')?.addEventListener('submit',event=>{event.preventDefault();const n=Number(new FormData(event.target).get('chest')); const sizes=[[88,'XS'],[94,'S'],[100,'M'],[106,'L'],[112,'XL'],[118,'XXL']];document.querySelector('#fit-result').textContent=n>118?'Contact us for fit advice.':`Start with ${sizes.find(([max])=>n<=max)[1]}. Compare garment measurements and allow room for your preferred fit.`;});
const filters=document.querySelector('#filters');let searchController;
filters?.addEventListener('submit',async event=>{
 event.preventDefault(); searchController?.abort();searchController=new AbortController();
 const grid=document.querySelector('#product-results'), note=document.querySelector('#results-note'); const params=new URLSearchParams(new FormData(filters));
 grid.setAttribute('aria-busy','true'); grid.replaceChildren();for(let n=0;n<4;n++){const el=document.createElement('div');el.className='skeleton';grid.append(el);} note.textContent='Finding your edit…';
 try {
  const response=await fetch('/api/products?'+params,{signal:searchController.signal}); if(!response.ok) throw new Error('Products could not be loaded.'); const products=await response.json(); grid.replaceChildren();
  for(const p of products){
   const article=document.createElement('article');article.className='product-card';const photo=document.createElement('div');photo.className='product-photo';const link=document.createElement('a');link.href='/product/'+encodeURIComponent(p.slug);const img=document.createElement('img');img.src=p.image||'/assets/images/placeholder.svg';img.alt=p.name;img.loading='lazy';img.width=640;img.height=800;img.addEventListener('error',()=>img.src='/assets/images/placeholder.svg',{once:true});link.append(img);photo.append(link);
   const tag=document.createElement('span');tag.className='product-tag';tag.textContent=p.sale_price!==null?'SPECIAL PRICE':'THE WIKI EDIT';photo.append(tag);article.append(photo);
   const meta=document.createElement('div');meta.className='product-meta';meta.textContent=p.type==='scents'?'EAU DE PARFUM · UNISEX':'EVERYDAY ESSENTIALS';article.append(meta);
   const h=document.createElement('h3');const title=document.createElement('a');title.href=link.href;title.textContent=p.name.replace('WIKI ','');h.append(title);article.append(h);
   const price=document.createElement('p');price.className='product-price';price.textContent='PKR '+(Number(p.sale_price??p.price)/100).toLocaleString('en-PK');article.append(price);grid.append(article);
  }
  note.textContent=`${products.length} pieces in this edit`;document.querySelector('#empty-results').hidden=products.length>0;history.replaceState(null,'',location.pathname+'?'+params);
  const pagination=document.querySelector('.pagination');pagination.replaceChildren();if(products.length===12){const next=document.createElement('a');params.set('page','2');next.href='?'+params;next.className='btn secondary';next.textContent='Next →';pagination.append(next);}
  if(params.get('q')) recordEvent('search',params.get('q'));
 } catch(error){if(error.name!=='AbortError'){note.textContent='Unable to load products. Please try again.';toast(error.message);grid.replaceChildren();}}
 finally{grid.removeAttribute('aria-busy');}
});
function recordEvent(event,detail=''){ const data=new FormData();data.set('action','event');data.set('event',event);data.set('detail',String(detail).slice(0,190));data.set('csrf',csrf);fetch('/api/action',{method:'POST',body:data}).catch(()=>{}); }
recordEvent('page_view',location.pathname);
if(location.pathname.startsWith('/product/')) recordEvent('product_view',location.pathname.split('/').pop());
if(location.pathname==='/checkout') recordEvent('checkout_started');
