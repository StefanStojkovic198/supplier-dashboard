<?php
/** Dashboard učitava prethodno generisani izvoz.xml. XML obrada i obračun ostaju u PHP modulima. */
declare(strict_types=1);
require_once __DIR__ . '/includes/catalog.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/settings.php';

supplyhub_start_settings_session();
header('Cache-Control: no-store, private');
$csrfToken = supplyhub_csrf_token();
$requiresPassword = !supplyhub_is_local_request();
$settingsVersion = null;

$catalogError = null;
$catalogProducts = [];
$publicRates = null;
try {
    $catalogProducts = supplyhub_read_export_catalog(__DIR__ . '/data/izvoz.xml');
    $pricingSettings = supplyhub_load_pricing_config(__DIR__ . '/config/settings.json');
    $settingsBytes = file_get_contents(__DIR__ . '/config/settings.json');
    if (!is_string($settingsBytes)) {
        throw new RuntimeException('Podešavanja nisu dostupna.');
    }
    $settingsVersion = hash('sha256', $settingsBytes);
    $publicRates = [
        'margins' => [
            'alfa' => $pricingSettings['margins']['alfa'] / 100,
            'beta' => $pricingSettings['margins']['beta'] / 100,
            'gama' => $pricingSettings['margins']['gama'] / 100,
        ],
        'vat' => $pricingSettings['vat'] / 100,
    ];
} catch (Throwable $error) {
    $catalogError = $error->getMessage();
}

$payload = json_encode(
    ['products' => $catalogProducts, 'rates' => $publicRates, 'error' => $catalogError,
     'csrf' => $csrfToken, 'version' => $settingsVersion, 'requiresPassword' => $requiresPassword],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
);
?>
<!DOCTYPE html>

<html lang="sr">

<head>

  <meta charset="UTF-8">

  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <meta name="theme-color" content="#0b1220">

  <meta name="description" content="Katalog proizvoda objedinjen iz XML ponuda dobavljača.">

  <title>SupplyHub — Pregled proizvoda</title>

  <link rel="stylesheet" href="assets/style.css">

</head>

<body>

  <!-- Tamna tema dashboarda; podaci se čitaju iz stvarnog izvoz.xml preko PHP-a. -->

  <svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">

    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>

    <symbol id="i-box" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="M3 8v9l9 5 9-5V8M12 13v9"/></symbol>

    <symbol id="i-truck" viewBox="0 0 24 24"><path d="M3 5h12v12H3zM15 9h4l3 4v4h-7z"/><circle cx="7.5" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></symbol>

    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m6 15 5-5 4 3 6-7"/></symbol>

    <symbol id="i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l-1.8 1.8a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6h-2.6a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-1.8-1.8a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H4v-2.6a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l1.8-1.8a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6h2.6a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l1.8 1.8a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1V14a1.7 1.7 0 0 0-1.6 1Z"/></symbol>

    <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v12m-5-5 5 5 5-5"/><path d="M4 17v4h16v-4"/></symbol>

    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 5 5"/></symbol>

    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></symbol>

    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>

    <symbol id="i-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>

    <symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.6 9a7 7 0 0 1 12-2L20 12M4 12l2.4 5a7 7 0 0 0 12-2"/></symbol>

    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>

    <symbol id="i-x" viewBox="0 0 24 24"><path d="M5 5 19 19M19 5 5 19"/></symbol>

    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></symbol>

    <symbol id="i-calculator" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M7.5 6h9v3h-9zM8 13h1M12 13h1M16 13h1M8 17h1M12 17h1M16 17h1"/></symbol>

    <symbol id="i-code" viewBox="0 0 24 24"><path d="m8 7-5 5 5 5M16 7l5 5-5 5m-3-14-2 18"/></symbol>

    <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 0 1 6 0c0 2-3 2.5-3 5m0 3h.01"/></symbol>

    <symbol id="i-headphones" viewBox="0 0 24 24"><path d="M4 13v-2a8 8 0 0 1 16 0v2"/><rect x="2" y="12" width="5" height="8" rx="2"/><rect x="17" y="12" width="5" height="8" rx="2"/></symbol>

    <symbol id="i-display" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="15" rx="2"/><path d="M8 22h8m-4-4v4"/></symbol>

    <symbol id="i-mouse" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="6"/><path d="M12 2v8"/></symbol>

    <symbol id="i-harddrive" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="12" rx="2"/><path d="M6 15h.01M10 15h.01M3 11h18"/></symbol>

    <symbol id="i-keyboard" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M6 9h.01M10 9h.01M14 9h.01M18 9h.01M6 12h.01M10 12h.01M14 12h.01M18 12h.01M7 16h10"/></symbol>

    <symbol id="i-printer" viewBox="0 0 24 24"><path d="M6 8V3h12v5M6 17H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6zM18 11h.01"/></symbol>

    <symbol id="i-wifi" viewBox="0 0 24 24"><path d="M2 8a16 16 0 0 1 20 0M5 12a11 11 0 0 1 14 0M8 16a6 6 0 0 1 8 0M12 20h.01"/></symbol>

    <symbol id="i-camera" viewBox="0 0 24 24"><path d="M4 7h4l2-3h4l2 3h4a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/><circle cx="12" cy="13" r="4"/></symbol>

    <symbol id="i-watch" viewBox="0 0 24 24"><rect x="6" y="7" width="12" height="10" rx="3"/><path d="m9 7 1-5h4l1 5M9 17l1 5h4l1-5m-3-7v3h2"/></symbol>

    <symbol id="i-check" viewBox="0 0 24 24"><path d="m4 12 5 5 11-11"/></symbol>

  </svg>

  <div class="overlay" id="mobileOverlay"></div>

  <div class="app">

    <aside class="sidebar" id="sidebar" aria-label="Glavna navigacija">

      <div class="brand"><div class="brand-mark"><svg class="icon"><use href="#i-box"/></svg></div><div><div class="brand-name">Supply<span>Hub.</span></div><div class="brand-sub">PRODUCT MANAGEMENT</div></div></div>

      <nav class="nav-block" aria-label="Meni">

        <div class="nav-label">RADNI PROSTOR</div>

        <button class="nav-button" data-scroll="overviewSection"><svg class="icon"><use href="#i-grid"/></svg>Kontrolna tabla</button>

        <button class="nav-button active" data-scroll="productsSection"><svg class="icon"><use href="#i-box"/></svg>Proizvodi<span class="nav-count" id="navCount">—</span></button>

        <button class="nav-button" data-scroll="suppliersSection"><svg class="icon"><use href="#i-truck"/></svg>Dobavljači</button>

        <div class="nav-label" style="margin-top:27px">ALATI</div>

        <button class="nav-button" id="sidebarExport"><svg class="icon"><use href="#i-download"/></svg>Izvoz XML-a</button>

        <button class="nav-button" id="sidebarSettings"><svg class="icon"><use href="#i-settings"/></svg>Podešavanja</button>

      </nav>

      <div class="sidebar-foot"><span class="sidebar-status-dot<?= $catalogError !== null ? ' error' : '' ?>" aria-hidden="true"></span><div><div class="profile-name"><?= $catalogError === null ? 'XML katalog' : 'Proveri XML katalog' ?></div><div class="profile-role">PHP obračun · SupplyHub</div></div></div>

    </aside>

    <main class="main">

      <header class="topbar">
        <div class="topbar-left"><button class="circle-btn mobile-menu" id="menuToggle" aria-label="Otvori navigaciju" aria-expanded="false"><svg class="icon"><use href="#i-menu"/></svg></button><div class="crumb"><span>Radni prostor</span><svg class="icon"><use href="#i-chevron"/></svg><strong>Katalog proizvoda</strong></div></div>
        <div class="top-right"><span class="env"><span class="dot"></span> XML KATALOG</span></div>
      </header>

      <div class="container">
        <div id="catalogError" class="catalog-error" role="alert" hidden></div>

        <section id="overviewSection" class="overview-section">
          <div class="hero"><div class="hero-main"><div class="eyebrow"><span class="eyebrow-mark"></span> SUPPLYHUB / CENTRALIZOVANI KATALOG</div><h1>Upravljanje <span>proizvodima.</span></h1><p class="subtitle">Sve ponude dobavljača, jedna najbolja cena za svaki EAN. Pregled, obračun i izvoz na jednom mestu.</p><div class="hero-proof"><span class="proof-dot"></span> Podaci iz generisanog XML kataloga <span class="proof-divider"></span> Obračun na PHP serveru</div></div><div class="actions"><button class="btn" id="refreshButton"><svg class="icon"><use href="#i-refresh"/></svg>Osveži podatke</button><button class="btn btn-primary" id="exportButton"><svg class="icon"><use href="#i-download"/></svg>Preuzmi XML</button></div></div>

          <div class="overview">

            <article class="stat"><div class="stat-top"><div class="stat-label">Ukupno proizvoda</div><div class="stat-icon"><svg class="icon"><use href="#i-box"/></svg></div></div><div class="stat-num" id="metricProducts">—</div><div class="stat-bottom"><span class="stat-pill">EAN unikati</span> bez duplikata</div></article>

            <article class="stat"><div class="stat-top"><div class="stat-label">Aktivni dobavljači</div><div class="stat-icon mint"><svg class="icon"><use href="#i-truck"/></svg></div></div><div class="stat-num" id="metricSuppliers">3</div><div class="stat-bottom">Alfa · Beta · Gama</div></article>

            <article class="stat"><div class="stat-top"><div class="stat-label">Prosečna zarada / kom</div><div class="stat-icon violet"><svg class="icon"><use href="#i-chart"/></svg></div></div><div class="stat-num" id="metricProfit">—</div><div class="stat-bottom">Bez PDV-a, iz generisanog XML-a</div></article>

            <article class="stat"><div class="stat-top"><div class="stat-label">Ukupan lager</div><div class="stat-icon amber"><svg class="icon"><use href="#i-grid"/></svg></div></div><div class="stat-num" id="metricStock">—</div><div class="stat-bottom">Ukupno jedinica u katalogu</div></article>

          </div>

        </section>

        <div class="workspace">

          <section class="panel suppliers-overview" id="suppliersSection">
            <div class="suppliers-summary"><div class="table-eyebrow">PREGLED MREŽE</div><h2 class="side-title">Dobavljači u katalogu</h2><p class="side-desc">Udeo dobavljača u odabranim ponudama.</p></div>
            <div id="supplierDistribution" class="supplier-list"></div>
            <div class="supplier-foot"><strong id="supplierFootCount">— aktivna</strong><span>dobavljača</span></div>
          </section>

          <section class="panel catalog-panel" id="productsSection"><div class="panel-head"><div><div class="table-eyebrow">OBJEDINJENI REZULTATI</div><h2 class="panel-title">Katalog proizvoda</h2><p class="panel-subtitle">Najpovoljnija ponuda po EAN-u, sa obračunatom prodajnom cenom i dostupnim lagerom.</p></div><span class="small-tag" id="tableCount">— proizvoda</span></div>

            <div class="toolbar"><label class="search"><svg class="icon"><use href="#i-search"/></svg><input id="searchInput" type="search" placeholder="Pretraži naziv ili EAN kod..." aria-label="Pretraga proizvoda"></label><div class="select-wrap"><select id="supplierFilter" aria-label="Filter dobavljača"><option value="">Svi dobavljači</option><option value="alfa">Alfa Distribucija</option><option value="beta">Beta Trade</option><option value="gama">Gama Import</option></select><svg class="icon"><use href="#i-down"/></svg></div><button class="filter-reset" id="clearFiltersButton" type="button" hidden>Očisti filtere <svg class="icon"><use href="#i-x"/></svg></button></div>

            <div class="table-wrap"><table class="products"><thead><tr><th>SLIKA</th><th>NAZIV</th><th>EAN KOD</th><th>DOBAVLJAČ</th><th>NABAVNA</th><th>PRODAJNA</th><th>ZARADA</th><th>LAGER</th></tr></thead><tbody id="productRows"></tbody></table><div id="emptyState" class="empty-state" hidden><strong>Nema rezultata</strong>Promeni upit ili izaberi drugog dobavljača.</div></div>

            <div class="table-footer"><div id="paginationInfo">Prikazano 0 proizvoda</div><nav class="pager" aria-label="Stranice kataloga"><button type="button" class="page-btn page-arrow" id="prevPage" aria-label="Prethodna stranica"><svg class="icon" style="transform:rotate(180deg);width:15px;height:15px"><use href="#i-chevron"/></svg></button><div id="pageNumbers" class="page-numbers" aria-label="Brojevi stranica"></div><button type="button" class="page-btn page-arrow" id="nextPage" aria-label="Sledeća stranica"><svg class="icon" style="width:15px;height:15px"><use href="#i-chevron"/></svg></button></nav></div>

          </section>

        </div>

        <footer class="bottom-caption"><span>© 2026 SupplyHub · Product Intelligence</span><span><span class="foot-dot"></span> PHP obračun · XML katalog</span></footer>

      </div>

    </main>

  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>

  <div class="modal-overlay" id="settingsOverlay" role="presentation">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="settingsTitle">
      <div class="modal-title-row"><h2 id="settingsTitle">Podešavanja obračuna</h2><button id="closeSettings" class="circle-btn" aria-label="Zatvori"><svg class="icon"><use href="#i-x"/></svg></button></div>
      <p>Promene se čuvaju na PHP serveru. Aplikacija će ponovo obračunati sve ponude, izabrati najpovoljnije dobavljače i regenerisati <strong>izvoz.xml</strong>.</p>
      <form id="settingsForm">
        <div class="field-row"><label for="marginAlfa">Alfa Distribucija — marža (%)</label><input id="marginAlfa" type="number" min="0" max="100" step="0.01" value="5" required></div>
        <div class="field-row"><label for="marginBeta">Beta Trade — marža (%)</label><input id="marginBeta" type="number" min="0" max="100" step="0.01" value="7" required></div>
        <div class="field-row"><label for="marginGama">Gama Import — marža (%)</label><input id="marginGama" type="number" min="0" max="100" step="0.01" value="0" required></div>
        <div class="field-row"><label for="vatRate">PDV (%)</label><input id="vatRate" type="number" min="0" max="100" step="0.01" value="20" required></div>
        <div id="adminPasswordRow" class="field-row" hidden><label for="adminPassword">Admin lozinka</label><input id="adminPassword" type="password" autocomplete="current-password" placeholder="Za javni server"></div>
        <div id="settingsError" class="settings-error" role="alert" hidden></div>
        <div class="settings-help">Na MAMP localhost-u menjanje je dozvoljeno samo lokalno. Na javnom serveru potrebni su HTTPS i <code>SUPPLYHUB_ADMIN_PASSWORD</code> (server promenljiva, nije deo Gita).</div>
        <div class="modal-actions"><button type="button" class="btn" id="cancelSettings">Otkaži</button><button type="submit" class="btn btn-primary" id="saveSettings"><svg class="icon"><use href="#i-check"/></svg><span id="saveSettingsLabel">Sačuvaj i preračunaj</span></button></div>
      </form>
    </div>
  </div>

  <script type="application/json" id="catalogPayload"><?= $payload ?></script>
  <script src="assets/app.js" defer></script>

</body>

</html>
