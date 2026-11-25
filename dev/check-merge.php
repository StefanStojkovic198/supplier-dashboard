<?php
/**
 * Lokalni integracioni test izbora dobavljača.
 * Poziva postojeće: products.php -> pricing.php -> merge.php.
 * Ovu stranicu ukloniti pre objavljivanja na produkcionom hostingu.
 */
declare(strict_types=1);

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Ova test stranica je dostupna samo lokalno.');
}

require_once dirname(__DIR__) . '/includes/products.php';
require_once dirname(__DIR__) . '/includes/pricing.php';
require_once dirname(__DIR__) . '/includes/merge.php';

$error = null;
$merged = null;
$passed = [];
try {
    $base = dirname(__DIR__);
    $loaded = supplyhub_load_offers($base . '/data');
    $config = supplyhub_load_pricing_config($base . '/config/settings.json');
    $priced = supplyhub_calculate_offer_prices($loaded['offers'], $config);
    $merged = supplyhub_merge_priced_offers($priced);
    $stats = $merged['stats'];

    $assert = static function (bool $condition, string $message) use (&$passed): void {
        if (!$condition) {
            throw new RuntimeException('Test nije prošao: ' . $message);
        }
        $passed[] = $message;
    };

    $assert($stats['input_offers'] === 122, 'Učitane su sve 122 ponude.');
    $assert($stats['eligible_offers'] === 118 && $stats['skipped_offers'] === 4, 'Četiri neispravne ponude su posebno evidentirane.');
    $assert($stats['unique_products'] === 52, 'Dobijena su 52 jedinstvena proizvoda.');
    $assert($stats['products_with_multiple_offers'] === 43, 'Prepoznate su grupe sa više dobavljačkih ponuda.');

    $seen = [];
    foreach ($merged['products'] as $p) {
        $ean = (string) $p['ean'];
        $assertEan = preg_match('/^[0-9]{13}$/D', $ean) === 1 && !isset($seen[$ean]);
        if (!$assertEan) {
            throw new RuntimeException('Dupliran ili neispravan EAN u rezultatu: ' . $ean);
        }
        $seen[$ean] = true;
    }
    $passed[] = 'Nijedan validan EAN nije dupliran.';

    $byEan = [];
    foreach ($priced as $offer) {
        if (preg_match('/^[0-9]{13}$/D', (string) $offer['ean']) !== 1 || $offer['nabavna_cena_pare'] <= 0 || $offer['lager'] < 0) {
            continue;
        }
        $byEan[$offer['ean']][] = $offer;
    }
    foreach ($merged['products'] as $winner) {
        $options = $byEan[$winner['ean']];
        usort($options, 'supplyhub_compare_offers');
        if ($options[0]['dobavljac_id'] !== $winner['dobavljac_id'] || $options[0]['sku'] !== $winner['sku']) {
            throw new RuntimeException('Nije odabrana najbolja validna ponuda za EAN: ' . $winner['ean']);
        }
    }
    $passed[] = 'Za svaki EAN je odabrana najniža prodajna cena među validnim ponudama.';

    // Provere ovih naziva i dobavljača važe za podrazumevane marže iz zadatka.
    if ($config['margins'] === ['alfa' => 500, 'beta' => 700, 'gama' => 0] && $config['vat'] === 2000) {
        // EAN je IDENTIFIKATOR, a ne broj. U PHP-u ključ niza poput
        // '8601001507110' postaje int, što kvari strogo poređenje (===).
        // Zato kontrolne primere čuvamo kao listu redova sa tekstualnim EAN-om.
        $expected = [
            ['ean' => '8601001507110', 'supplier' => 'gama'], // Logitech M185 — Gama ima najnižu prodajnu cenu.
            ['ean' => '8601000548046', 'supplier' => 'gama'], // Ugreen — Beta ima cenu 0 i preskače se.
            ['ean' => '8601003562261', 'supplier' => 'gama'], // Baseus — Beta ima lager -3 i preskače se.
        ];
        foreach ($expected as $case) {
            $found = null;
            foreach ($merged['products'] as $p) {
                if ((string) $p['ean'] === $case['ean']) {
                    $found = $p;
                    break;
                }
            }
            $actual = $found === null ? 'nije pronađen' : (string) $found['dobavljac_id'];
            $assert(
                $found !== null && $actual === $case['supplier'],
                'Kontrolni proizvod ' . $case['ean'] . ': očekivan ' . $case['supplier'] . ', dobijen ' . $actual . '.'
            );
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    http_response_code(500);
}

function m_esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function m_money(int $cents): string
{
    return number_format($cents / 100, 2, ',', '.') . ' RSD';
}
?><!doctype html>
<html lang="sr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SupplyHub · Spajanje ponuda</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#0b1525;color:#e9f1fd;font:14px/1.55,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;padding:38px 18px}
main{max-width:1150px;margin:0 auto}h1{margin:0;font-size:29px;letter-spacing:-.7px}h2{font-size:19px;margin:28px 0 12px}p{color:#aabbd2}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:20px 0}.card,.table-box{background:#15243a;border:1px solid #2b405d;border-radius:12px}.card{padding:18px}.card b{display:block;font-size:26px;margin-top:5px}.muted{color:#a1b4cd}.ok{color:#68e6bc}.warn{color:#ffce8c}.error{background:#3a2530;border:1px solid #a84c60;border-radius:10px;padding:16px}.note{border:1px solid #775e32;background:#29281d;color:#efd5a0;padding:13px 16px;border-radius:10px}.table-box{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:820px}td,th{padding:12px 14px;text-align:left;border-bottom:1px solid #2b405d;font-size:12px}th{background:#1d304b;color:#bfd0e7;white-space:nowrap}td{color:#d2dff1}td:first-child{max-width:350px}tr:last-child td{border-bottom:0}.small{font-size:12px}.num{white-space:nowrap;font-variant-numeric:tabular-nums}.tag{font-size:11px;border-radius:8px;background:#204d45;color:#8ff0c7;padding:4px 7px;display:inline-block}.warning-row{padding:11px 14px;background:#29281d;border:1px solid #775e32;border-radius:9px;margin:7px 0;color:#f2d7a8}.tests{padding-left:20px;color:#bcd0e9}.tests li{margin:6px 0}.tests li::marker{color:#68e6bc}
</style></head><body><main>
<h1>SupplyHub · objedinjavanje ponuda po EAN-u</h1>
<p>Razvojna provera: 3 XML fajla → PHP obračun → poređenje cena → jedna najpovoljnija ponuda po EAN-u. <strong>Još ne generišemo izvoz.xml.</strong></p>
<?php if ($error !== null): ?>
<div class="error"><strong>Greška:</strong> <?= m_esc($error) ?></div>
<?php else: ?>
<div class="grid">
  <div class="card"><span class="muted">Ulaznih ponuda</span><b><?= $stats['input_offers'] ?></b></div>
  <div class="card"><span class="muted">Validnih ponuda</span><b><?= $stats['eligible_offers'] ?></b></div>
  <div class="card"><span class="muted">Jedinstvenih proizvoda</span><b class="ok"><?= $stats['unique_products'] ?></b></div>
  <div class="card"><span class="muted">Preskočeno uz objašnjenje</span><b class="warn"><?= $stats['skipped_offers'] ?></b></div>
</div>
<p class="ok"><strong>Automatske provere: <?= count($passed) ?>/<?= count($passed) ?> uspešno.</strong></p>
<h2>Odabrani dobavljači</h2>
<div class="grid">
<?php foreach ($loaded['suppliers'] as $id => $supplier): ?>
  <div class="card"><span class="muted"><?= m_esc($supplier['name']) ?></span><b><?= (int) ($stats['selected_by_supplier'][$id] ?? 0) ?></b><span class="muted">proizvoda u objedinjenom katalogu</span></div>
<?php endforeach; ?>
</div>
<h2>Rezultat objedinjavanja — 52 proizvoda</h2>
<div class="table-box"><table><thead><tr><th>EAN / Proizvod</th><th>Izabrani dobavljač</th><th>Prodajna sa PDV</th><th>Lager</th><th>Upoređenih ponuda</th></tr></thead><tbody>
<?php foreach ($merged['comparisons'] as $row): ?>
<tr>
  <td><strong><?= m_esc($row['naziv']) ?></strong><div class="small muted"><?= m_esc($row['ean']) ?></div></td>
  <td><?= m_esc($row['dobavljac']) ?></td>
  <td class="num ok"><strong><?= m_money($row['prodajna_cena_pare']) ?></strong></td>
  <td class="num"><?= (int) $row['lager'] ?> kom.</td>
  <td><span class="tag"><?= (int) $row['broj_ponuda'] ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<h2>Preskočene ponude (<?= count($merged['skipped']) ?>)</h2>
<p>Ove ponude nisu izbrisane iz originalnog XML-a. Čuvamo jasan trag zašto ne učestvuju u izboru.</p>
<?php foreach ($merged['skipped'] as $row): ?>
<div class="warning-row"><strong><?= m_esc($row['type']) ?></strong> · <?= m_esc($row['supplier']) ?> · <?= m_esc($row['sku']) ?> · <?= m_esc($row['name']) ?></div>
<?php endforeach; ?>
<div class="note"><strong>Pravila:</strong> Nevažeći/prazan EAN, nulta nabavna cena i negativan lager se preskaču. Lager 0 je dozvoljen i ne isključuje proizvod. U slučaju iste najniže prodajne cene, prednost ima veći lager, a zatim stabilan poredak dobavljača. Ova odluka za neispravne ponude nije precizirana u PDF-u; dokumentovaćemo je u README-u.</div>
<h2>Provere (<?= count($passed) ?>)</h2><ul class="tests"><?php foreach ($passed as $label): ?><li><?= m_esc($label) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
</main></body></html>
