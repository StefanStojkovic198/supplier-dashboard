<?php
/** Lokalna probna stranica: obracun cene/PDV/zarade, bez objedinjavanja i izvoza. */
declare(strict_types=1);

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Ova test stranica je dostupna samo lokalno.');
}

require_once dirname(__DIR__) . '/includes/products.php';
require_once dirname(__DIR__) . '/includes/pricing.php';

$error = null;
try {
    $loaded = supplyhub_load_offers(dirname(__DIR__) . '/data');
    $config = supplyhub_load_pricing_config(dirname(__DIR__) . '/config/settings.json');
    $priced = supplyhub_calculate_offer_prices($loaded['offers'], $config);

    // Integracione provere nad stvarnim dobavljackim fajlovima.
    if (count($priced) !== count($loaded['offers'])) {
        throw new RuntimeException('Broj ponuda je promenjen tokom obracuna.');
    }
    foreach ($priced as $row) {
        if ($row['prodajna_cena_pare'] < $row['prodajna_bez_pdv_pare'] || $row['zarada_pare'] < 0) {
            throw new RuntimeException('Pronasli smo neispravan obracun.');
        }
    }

    // Jednostavne nezavisne provere koje moraju dati tacan rezultat.
    $tests = [
        ['cost' => 100000, 'margin' => 500, 'vat' => 2000, 'net' => 105000, 'gross' => 126000, 'profit' => 5000],
        ['cost' => 100000, 'margin' => 700, 'vat' => 2000, 'net' => 107000, 'gross' => 128400, 'profit' => 7000],
        ['cost' => 100000, 'margin' => 0, 'vat' => 2000, 'net' => 100000, 'gross' => 120000, 'profit' => 0],
        ['cost' => 0, 'margin' => 700, 'vat' => 2000, 'net' => 0, 'gross' => 0, 'profit' => 0],
    ];
    foreach ($tests as $case) {
        $net = supplyhub_add_percent_to_cents($case['cost'], $case['margin']);
        $gross = supplyhub_add_percent_to_cents($net, $case['vat']);
        if ($net !== $case['net'] || $gross !== $case['gross'] || ($net - $case['cost']) !== $case['profit']) {
            throw new RuntimeException('Automatska provera obracuna nije uspela.');
        }
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}
function p_esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function p_money(int $cents): string { return number_format($cents / 100, 2, ',', '.') . ' RSD'; }
?><!doctype html>
<html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SupplyHub · Provera obračuna</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#0b1525;color:#ebf1fa;font:14px/1.6 system-ui,-apple-system,sans-serif;padding:40px 18px}
main{max-width:1100px;margin:0 auto}h1{font-size:29px;margin:0}p{color:#a7b6cc}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:20px 0}.card,.scroll{background:#15243a;border:1px solid #2c405b;border-radius:13px}.card{padding:20px}.card b{display:block;font-size:25px;margin:4px 0}.muted{color:#9bacbf}.ok{color:#6be2b9}.fail{padding:18px;background:#38242d;border:1px solid #a14e60;border-radius:10px}
.scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:900px}td,th{text-align:left;border-bottom:1px solid #2c405b;padding:13px;font-size:12px}th{color:#b9cce6;background:#1b2b43}.price{font-variant-numeric:tabular-nums;white-space:nowrap}.profit{color:#66dcaf}.notice{background:#2d2a1d;border:1px solid #796333;padding:14px 16px;border-radius:10px;color:#e5cca1;margin-top:22px}
</style></head><body><main>
<h1>SupplyHub · PHP obračun cena</h1>
<p>Razvojni test: proračun na serveru na stvarnim ponudama. Još nema izbora dobavljača ni izvoz.xml.</p>
<?php if ($error !== null): ?>
<div class="fail"><strong>Greška:</strong> <?= p_esc($error) ?></div>
<?php else: ?>
<div class="grid">
<div class="card"><span class="muted">Obračunato ponuda</span><b><?= count($priced) ?></b><span class="ok">Sve ponude obrađene</span></div>
<?php foreach ($config['margins'] as $key => $margin): ?>
<div class="card"><span class="muted">Marža <?= p_esc(ucfirst($key)) ?></span><b><?= number_format($margin / 100, 2, ',', '.') ?>%</b><span class="muted">Iz config/settings.json</span></div>
<?php endforeach; ?>
<div class="card"><span class="muted">PDV</span><b><?= number_format($config['vat'] / 100, 2, ',', '.') ?>%</b><span class="muted">Iz config/settings.json</span></div>
</div>
<p class="ok"><strong>Automatske provere: 4/4 prošle.</strong></p>
<h2>Primer obračuna — prvih osam ponuda</h2>
<div class="scroll"><table><thead><tr><th>Naziv</th><th>Dobavljač</th><th>Nabavna</th><th>Marža</th><th>Prodajna bez PDV</th><th>PDV</th><th>Prodajna sa PDV</th><th>Zarada bez PDV</th></tr></thead><tbody>
<?php foreach (array_slice($priced, 0, 8) as $row): ?>
<tr>
<td><?= p_esc($row['naziv']) ?></td><td><?= p_esc($row['dobavljac']) ?></td>
<td class="price"><?= p_money($row['nabavna_cena_pare']) ?></td>
<td><?= number_format($config['margins'][$row['dobavljac_id']] / 100, 2, ',', '.') ?>%</td>
<td class="price"><?= p_money($row['prodajna_bez_pdv_pare']) ?></td>
<td class="price"><?= p_money($row['pdv_pare']) ?></td>
<td class="price"><?= p_money($row['prodajna_cena_pare']) ?></td>
<td class="price profit"><?= p_money($row['zarada_pare']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="notice">Za sada računamo i ponude sa lagerom 0 ili negativnim lagerom i cenom 0, ali izbor najpovoljnije ponude još nije implementiran. Pravilo za te slučajeve rešavamo zasebno u sledećem koraku. Zarada je računata bez PDV-a.</div>
<?php endif; ?>
</main></body></html>
