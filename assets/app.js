'use strict';

// Podaci stižu iz SAČUVANOG izvoz.xml preko PHP-a. Ovde nema obračuna cena.
const payloadElement = document.getElementById('catalogPayload');
let payload;
try {
  payload = JSON.parse(payloadElement?.textContent || '{}');
} catch (error) {
  payload = { products: [], error: 'Neuspešno čitanje podataka dashboarda.' };
}

const catalog = Array.isArray(payload.products) ? payload.products : [];
const rates = payload.rates || null;
const serverError = payload.error || null;
const names = {
  alfa: 'Alfa Distribucija',
  beta: 'Beta Trade',
  gama: 'Gama Import',
};
const state = { search: '', supplier: '', page: 1, pageSize: 7 };
const number = n => new Intl.NumberFormat('sr-RS').format(n);
const money = cents => new Intl.NumberFormat('sr-RS', {
  minimumFractionDigits: 2, maximumFractionDigits: 2
}).format(cents / 100) + ' RSD';

const safeText = value => String(value ?? '').replace(/[&<>"']/g, character => ({
  '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
})[character]);

function safeImageUrl(value) {
  if (typeof value !== 'string') return null;
  try {
    const url = new URL(value);
    return ['https:', 'http:'].includes(url.protocol) ? url.href : null;
  } catch (_) { return null; }
}

function iconForCategory(category) {
  const c = String(category || '').toLocaleLowerCase('sr');
  if (/mis|miš/.test(c)) return ['i-mouse', 'blue'];
  if (/slusal|sluša|head|audio/.test(c)) return ['i-headphones', 'purple'];
  if (/ssd|disk|memor|kartic/.test(c)) return ['i-harddrive', 'green'];
  if (/monitor|ekran/.test(c)) return ['i-display', 'blue'];
  if (/tastatur/.test(c)) return ['i-keyboard', 'purple'];
  if (/stamp|štamp|printer/.test(c)) return ['i-printer', 'orange'];
  if (/ruter|router|mrež/.test(c)) return ['i-wifi', 'green'];
  if (/kamer/.test(c)) return ['i-camera', 'orange'];
  if (/sat|watch/.test(c)) return ['i-watch', 'purple'];
  return ['i-box', 'blue'];
}

const ico = name => `<svg class="icon" aria-hidden="true"><use href="#${name}"></use></svg>`;
function productThumb(product) {
  const [icon, tone] = iconForCategory(product.category);
  const image = safeImageUrl(product.image);
  return `<div class="prod-thumb ${tone}">${ico(icon)}${image ?
    `<img class="product-image" alt="" loading="lazy" src="${safeText(image)}">` : ''}</div>`;
}

function render() {
  const filtered = catalog.filter(p =>
    (!state.search || `${p.name} ${p.ean}`.toLocaleLowerCase('sr').includes(state.search)) &&
    (!state.supplier || p.supplier === state.supplier)
  );
  const pages = Math.max(1, Math.ceil(filtered.length / state.pageSize));
  state.page = Math.min(Math.max(state.page, 1), pages);
  const visible = filtered.slice((state.page - 1) * state.pageSize, state.page * state.pageSize);

  document.getElementById('metricProducts').textContent = serverError ? '—' : number(catalog.length);
  document.getElementById('metricSuppliers').textContent = serverError ? '—' : number(new Set(catalog.map(p => p.supplier)).size);
  document.getElementById('metricProfit').textContent = serverError ? '—' : money(Math.round(catalog.reduce((sum, p) => sum + p.profitCents, 0) / Math.max(1, catalog.length)));
  document.getElementById('metricStock').textContent = serverError ? '—' : number(catalog.reduce((sum, p) => sum + p.stock, 0));
  document.getElementById('tableCount').textContent = `${number(filtered.length)} proizvoda`;
  document.getElementById('navCount').textContent = number(catalog.length);

  document.getElementById('productRows').innerHTML = visible.map(p =>
    `<tr>
      <td data-label="Slika">${productThumb(p)}</td>
      <td data-label="Naziv"><div class="prod-title">${safeText(p.name)}</div><div class="prod-meta">${safeText(p.brand)} · ${safeText(p.category)}</div></td>
      <td data-label="EAN kod" class="ean">${safeText(p.ean)}</td>
      <td data-label="Dobavljač"><span class="supplier"><span class="supplier-dot ${p.supplier}"></span>${safeText(names[p.supplier] || p.supplierName)}</span></td>
      <td data-label="Nabavna cena" class="money">${money(p.costCents)}</td>
      <td data-label="Prodajna cena" class="money">${money(p.saleCents)}</td>
      <td data-label="Zarada" class="profit">${money(p.profitCents)}</td>
      <td data-label="Lager"><span class="stock ${p.stock <= 15 ? 'low' : ''}">${number(p.stock)} kom.</span></td>
    </tr>`
  ).join('');

  document.querySelectorAll('img.product-image').forEach(img => {
    const loaded = () => { img.classList.add('is-loaded'); };
    img.addEventListener('load', loaded);
    img.addEventListener('error', () => { img.hidden = true; });
    if (img.complete && img.naturalWidth > 0) loaded();
    if (img.complete && img.naturalWidth === 0) img.hidden = true;
  });

  const noResults = document.getElementById('emptyState');
  noResults.hidden = filtered.length !== 0 || Boolean(serverError);
  document.getElementById('paginationInfo').textContent = filtered.length
    ? `Prikazano ${number((state.page - 1) * state.pageSize + 1)}–${number(Math.min(state.page * state.pageSize, filtered.length))} od ${number(filtered.length)}`
    : 'Prikazano 0 proizvoda';
  document.getElementById('pageNumber').textContent = state.page;
  document.getElementById('prevPage').disabled = state.page <= 1;
  document.getElementById('nextPage').disabled = state.page >= pages;

  document.getElementById('supplierDistribution').innerHTML = Object.entries(names).map(([id, label]) => {
    const count = catalog.filter(p => p.supplier === id).length;
    const width = catalog.length ? (100 * count / catalog.length) : 0;
    return `<div><div class="s-line"><span class="supplier"><span class="supplier-dot ${id}"></span>${safeText(label)}</span><span class="s-count">${number(count)}</span></div><div class="track"><div class="fill ${id}" style="width:${width}%"></div></div></div>`;
  }).join('');
  document.getElementById('supplierFootCount').textContent = `${number(new Set(catalog.map(p => p.supplier)).size)} aktivna`;
}

const errorBanner = document.getElementById('catalogError');
if (serverError) {
  errorBanner.textContent = serverError;
  errorBanner.hidden = false;
}

const toastEl = document.getElementById('toast');
let toastTimer;
function toast(msg) {
  toastEl.textContent = msg;
  toastEl.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toastEl.classList.remove('show'), 3400);
}

// Pretraga i filter su isključivo prikaz — ništa se ne preračunava u browseru.
document.getElementById('searchInput').addEventListener('input', e => {
  state.search = e.target.value.trim().toLocaleLowerCase('sr');
  state.page = 1;
  render();
});
document.getElementById('supplierFilter').addEventListener('change', e => {
  state.supplier = e.target.value;
  state.page = 1;
  render();
});
document.getElementById('prevPage').addEventListener('click', () => { state.page--; render(); });
document.getElementById('nextPage').addEventListener('click', () => { state.page++; render(); });

function downloadXml() {
  if (serverError) {
    toast('XML fajl nije spreman za preuzimanje.');
    return;
  }
  window.location.assign('download.php');
}
document.getElementById('exportButton').addEventListener('click', downloadXml);
document.getElementById('sidebarExport').addEventListener('click', downloadXml);
document.getElementById('refreshButton').addEventListener('click', () => window.location.reload());
document.getElementById('helpButton').addEventListener('click', () => toast('Prikaz iz data/izvoz.xml. Obračun i izbor dobavljača obavljeni su na PHP serveru.'));

// Podešavanja su privremeno samo za pregled. Serversko snimanje dolazi u sledećoj fazi.
const modal = document.getElementById('settingsOverlay');
function openSettings() {
  if (rates) {
    document.getElementById('marginAlfa').value = rates.margins.alfa;
    document.getElementById('marginBeta').value = rates.margins.beta;
    document.getElementById('marginGama').value = rates.margins.gama;
    document.getElementById('vatRate').value = rates.vat;
  }
  modal.classList.add('show');
  document.getElementById('closeSettings').focus();
  closeMenu();
}
function closeSettings() { modal.classList.remove('show'); }
document.getElementById('sidebarSettings').addEventListener('click', openSettings);
document.getElementById('closeSettings').addEventListener('click', closeSettings);
document.getElementById('cancelSettings').addEventListener('click', closeSettings);
document.getElementById('settingsForm').addEventListener('submit', e => e.preventDefault());
modal.addEventListener('click', e => { if (e.target === modal) closeSettings(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeSettings(); closeMenu(); } });

const sidebar = document.getElementById('sidebar');
const mobileOverlay = document.getElementById('mobileOverlay');
const toggle = document.getElementById('menuToggle');
function closeMenu() { sidebar.classList.remove('open'); mobileOverlay.classList.remove('show'); toggle.setAttribute('aria-expanded', 'false'); }
toggle.addEventListener('click', () => {
  const open = sidebar.classList.toggle('open');
  mobileOverlay.classList.toggle('show', open);
  toggle.setAttribute('aria-expanded', String(open));
});
mobileOverlay.addEventListener('click', closeMenu);
document.querySelectorAll('[data-scroll]').forEach(btn => btn.addEventListener('click', () => {
  document.getElementById(btn.dataset.scroll).scrollIntoView({ behavior: 'smooth', block: 'start' });
  document.querySelectorAll('.nav-button').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  closeMenu();
}));

render();
