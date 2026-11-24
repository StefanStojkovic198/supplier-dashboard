    'use strict';

    // ISKLJUČIVO DEMO PODACI. U pravoj verziji XML obrada i obračun idu na server.

    const offers = [

      {ean:'8601000000018',name:'MX Master 3S Wireless',brand:'Logitech',category:'Miševi',supplier:'alfa',cost:12490,stock:47,icon:'i-mouse',tone:'blue'},

      {ean:'8601000000018',name:'MX Master 3S Wireless',brand:'Logitech',category:'Miševi',supplier:'beta',cost:12000,stock:26,icon:'i-mouse',tone:'blue'},

      {ean:'8601000000025',name:'Galaxy Buds FE',brand:'Samsung',category:'Slušalice',supplier:'beta',cost:6950,stock:93,icon:'i-headphones',tone:'purple'},

      {ean:'8601000000025',name:'Galaxy Buds FE',brand:'Samsung',category:'Slušalice',supplier:'gama',cost:7500,stock:35,icon:'i-headphones',tone:'purple'},

      {ean:'8601000000032',name:'NV2 1TB M.2 SSD',brand:'Kingston',category:'Memorija',supplier:'gama',cost:5990,stock:58,icon:'i-harddrive',tone:'green'},

      {ean:'8601000000049',name:'TUF Gaming VG27AQ',brand:'ASUS',category:'Monitori',supplier:'alfa',cost:31900,stock:12,icon:'i-display',tone:'blue'},

      {ean:'8601000000049',name:'TUF Gaming VG27AQ',brand:'ASUS',category:'Monitori',supplier:'beta',cost:30600,stock:15,icon:'i-display',tone:'blue'},

      {ean:'8601000000056',name:'K380 Multi-Device',brand:'Logitech',category:'Tastature',supplier:'beta',cost:4750,stock:68,icon:'i-keyboard',tone:'purple'},

      {ean:'8601000000063',name:'LaserJet M110w',brand:'HP',category:'Štampači',supplier:'alfa',cost:15490,stock:8,icon:'i-printer',tone:'orange'},

      {ean:'8601000000070',name:'Archer AX23 AX1800',brand:'TP-Link',category:'Ruteri',supplier:'gama',cost:6450,stock:39,icon:'i-wifi',tone:'green'},

      {ean:'8601000000070',name:'Archer AX23 AX1800',brand:'TP-Link',category:'Ruteri',supplier:'alfa',cost:6400,stock:20,icon:'i-wifi',tone:'green'},

      {ean:'8601000000087',name:'Brio 500 Full HD',brand:'Logitech',category:'Kamere',supplier:'beta',cost:10790,stock:16,icon:'i-camera',tone:'orange'},

      {ean:'8601000000094',name:'Watch Fit 3',brand:'Huawei',category:'Pametni satovi',supplier:'gama',cost:10100,stock:44,icon:'i-watch',tone:'purple'},

      {ean:'8601000000100',name:'WH-CH720N',brand:'Sony',category:'Slušalice',supplier:'alfa',cost:11400,stock:32,icon:'i-headphones',tone:'blue'},

      {ean:'8601000000100',name:'WH-CH720N',brand:'Sony',category:'Slušalice',supplier:'beta',cost:10850,stock:25,icon:'i-headphones',tone:'blue'}

    ];

    const names={alfa:'Alfa Distribucija',beta:'Beta Trade',gama:'Gama Import'};

    const state={margins:{alfa:5,beta:7,gama:0},vat:20,search:'',supplier:'',page:1,pageSize:7};

    const money=n=>new Intl.NumberFormat('sr-RS',{maximumFractionDigits:2,minimumFractionDigits:2}).format(n)+' RSD';

    const number=n=>new Intl.NumberFormat('sr-RS').format(n);

    const ico=(name,style='')=>`<svg class="icon" ${style?`style="${style}"`:''}><use href="#${name}"></use></svg>`;

    let catalog=[];

    function makeCatalog(){

      const map=new Map();

      for(const offer of offers){

        const sale=offer.cost*(1+state.margins[offer.supplier]/100)*(1+state.vat/100);

        const profit=offer.cost*state.margins[offer.supplier]/100;

        const candidate={...offer,sale,profit};

        if(!map.has(offer.ean)||sale<map.get(offer.ean).sale)map.set(offer.ean,candidate);

      }

      return [...map.values()];

    }

    function render(){

      catalog=makeCatalog();

      const filtered=catalog.filter(p=>(!state.search||`${p.name} ${p.ean} ${p.brand}`.toLocaleLowerCase('sr').includes(state.search))&&(!state.supplier||p.supplier===state.supplier));

      const pages=Math.max(1,Math.ceil(filtered.length/state.pageSize));state.page=Math.min(state.page,pages);

      const visible=filtered.slice((state.page-1)*state.pageSize,state.page*state.pageSize);

      document.getElementById('metricProducts').textContent=number(catalog.length);

      document.getElementById('metricSuppliers').textContent=number(new Set(catalog.map(p=>p.supplier)).size);

      document.getElementById('metricProfit').textContent=money(catalog.reduce((sum,p)=>sum+p.profit,0)/Math.max(1,catalog.length));

      document.getElementById('metricStock').textContent=number(catalog.reduce((sum,p)=>sum+p.stock,0));

      document.getElementById('tableCount').textContent=`${number(filtered.length)} proizvoda`;

      document.getElementById('navCount').textContent=catalog.length;

      document.getElementById('productRows').innerHTML=visible.map(p=>`<tr><td data-label="Proizvod"><div class="product-name-cell"><div class="prod-thumb ${p.tone}">${ico(p.icon)}</div><div><div class="prod-title">${p.name}</div><div class="prod-meta">${p.brand} · ${p.category}</div></div></div></td><td data-label="EAN kod" class="ean">${p.ean}</td><td data-label="Dobavljač"><span class="supplier"><span class="supplier-dot ${p.supplier}"></span>${names[p.supplier]}</span></td><td data-label="Nabavna cena" class="money">${money(p.cost)}</td><td data-label="Prodajna cena" class="money">${money(p.sale)}</td><td data-label="Zarada" class="profit">+ ${money(p.profit)}</td><td data-label="Lager"><span class="stock ${p.stock<=15?'low':''}">${p.stock} kom.</span></td></tr>`).join('');

      document.getElementById('emptyState').hidden=filtered.length!==0;

      document.getElementById('paginationInfo').textContent=filtered.length?`Prikazano ${number((state.page-1)*state.pageSize+1)}–${number(Math.min(state.page*state.pageSize,filtered.length))} od ${number(filtered.length)}`:'Prikazano 0 proizvoda';

      document.getElementById('pageNumber').textContent=state.page;

      document.getElementById('prevPage').disabled=state.page<=1;

      document.getElementById('nextPage').disabled=state.page>=pages;

      document.getElementById('supplierDistribution').innerHTML=Object.entries(names).map(([id,label])=>{const count=catalog.filter(p=>p.supplier===id).length;return `<div><div class="s-line"><span class="supplier"><span class="supplier-dot ${id}"></span>${label}</span><span class="s-count">${count}</span></div><div class="track"><div class="fill ${id}" style="width:${catalog.length?100*count/catalog.length:0}%"></div></div></div>`}).join('');

    }

    document.getElementById('searchInput').addEventListener('input',e=>{state.search=e.target.value.trim().toLocaleLowerCase('sr');state.page=1;render()});

    document.getElementById('supplierFilter').addEventListener('change',e=>{state.supplier=e.target.value;state.page=1;render()});

    document.getElementById('prevPage').addEventListener('click',()=>{state.page--;render()});

    document.getElementById('nextPage').addEventListener('click',()=>{state.page++;render()});

    const toastEl=document.getElementById('toast');let toastTimer;

    function toast(msg){toastEl.textContent=msg;toastEl.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toastEl.classList.remove('show'),3400)}

    function exportNotice(){toast('Ovo je samo HTML demo. Pravo generisanje izvoz.xml dodaćemo kroz PHP backend.');}

    document.getElementById('exportButton').addEventListener('click',exportNotice);

    document.getElementById('sidebarExport').addEventListener('click',exportNotice);

    document.getElementById('refreshButton').addEventListener('click',()=>{render();toast('Demo podaci su osveženi. U finalnoj verziji učitavaju se sa servera.')});

    document.getElementById('helpButton').addEventListener('click',()=>toast('Interaktivni HTML koncept. Podaci su izmišljeni; XML obrada i export još nisu povezani.'));

    const modal=document.getElementById('settingsOverlay');

    function openSettings(){document.getElementById('marginAlfa').value=state.margins.alfa;document.getElementById('marginBeta').value=state.margins.beta;document.getElementById('marginGama').value=state.margins.gama;document.getElementById('vatRate').value=state.vat;modal.classList.add('show');document.getElementById('closeSettings').focus();closeMenu()}

    function closeSettings(){modal.classList.remove('show')}

    document.getElementById('sidebarSettings').addEventListener('click',openSettings);

    document.getElementById('closeSettings').addEventListener('click',closeSettings);

    document.getElementById('cancelSettings').addEventListener('click',closeSettings);

    modal.addEventListener('click',e=>{if(e.target===modal)closeSettings()});

    document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeSettings();closeMenu()}});

    document.getElementById('settingsForm').addEventListener('submit',e=>{e.preventDefault();state.margins={alfa:Number(document.getElementById('marginAlfa').value),beta:Number(document.getElementById('marginBeta').value),gama:Number(document.getElementById('marginGama').value)};state.vat=Number(document.getElementById('vatRate').value);state.page=1;render();closeSettings();toast('Demo obračun ažuriran. Promene nisu sačuvane na serveru.')});

    const sidebar=document.getElementById('sidebar'),mobileOverlay=document.getElementById('mobileOverlay'),toggle=document.getElementById('menuToggle');

    function closeMenu(){sidebar.classList.remove('open');mobileOverlay.classList.remove('show');toggle.setAttribute('aria-expanded','false')}

    toggle.addEventListener('click',()=>{const open=sidebar.classList.toggle('open');mobileOverlay.classList.toggle('show',open);toggle.setAttribute('aria-expanded',String(open))});

    mobileOverlay.addEventListener('click',closeMenu);

    document.querySelectorAll('[data-scroll]').forEach(btn=>btn.addEventListener('click',()=>{document.getElementById(btn.dataset.scroll).scrollIntoView({behavior:'smooth',block:'start'});document.querySelectorAll('.nav-button').forEach(b=>b.classList.remove('active'));btn.classList.add('active');closeMenu()}));

    render();
