<?php
/**
 * SupplyHub — lokalni integracioni test XML izvoza, korak 12.
 * Pokreći samo u MAMP-u; ukloniti folder dev pre javnog deploya.
 * Upisuje data/izvoz.xml; NE menja originalna tri XML-a.
 */
declare(strict_types=1);

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Ova test stranica je dostupna samo lokalno.');
}

require_once dirname(__DIR__) . '/includes/products.php';
require_once dirname(__DIR__) . '/includes/pricing.php';
require_once dirname(__DIR__) . '/includes/merge.php';
require_once dirname(__DIR__) . '/includes/export.php';

function sh_esc(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = null;
$checks = [];
$summary = null;
$preview = [];

try {
    $base = dirname(__DIR__);
    $loaded = supplyhub_load_offers($base . '/data');
    $config = supplyhub_load_pricing_config($base . '/config/settings.json');
    $priced = supplyhub_calculate_offer_prices($loaded['offers'], $config);
    $merged = supplyhub_merge_priced_offers($priced);
    $path = $base . '/data/izvoz.xml';
    $written = supplyhub_write_export_xml($merged['products'], $path);

    // Provera stvarno sačuvanog XML-a, nezavisna od buildera.
    if (!function_exists('simplexml_load_file')) {
        throw new RuntimeException('Nedostaje SimpleXML ekstenzija.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $document = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NONET);
        if ($document === false) {
            $xmlErrors = libxml_get_errors();
            throw new RuntimeException('Sačuvani izvoz.xml nije validan XML: ' . ($xmlErrors ? trim($xmlErrors[0]->message) : 'Nepoznata greška'));
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    $check = static function (bool $ok, string $message) use (&$checks): void {
        if (!$ok) {
            throw new RuntimeException('Provera nije prošla: ' . $message);
        }
        $checks[] = $message;
    };

    $entries = $document->proizvod;
    $check($document->getName() === 'proizvodi' && (string) $document['valuta'] === 'RSD', 'Korenski element i valuta RSD su ispravni.');
    $check($merged['stats']['input_offers'] === 122 && $merged['stats']['eligible_offers'] === 118, 'Učitane su 122 ponude; 118 može u izbor.');
    $check($merged['stats']['skipped_offers'] === 4, 'Četiri problematične ponude su evidentirane.');
    $check($written['count'] === 52 && count($entries) === 52, 'Sačuvano je tačno 52 proizvoda.');
    $check(is_file($path) && filesize($path) > 0, 'data/izvoz.xml fizički postoji i nije prazan.');

    $fields = ['ean', 'naziv', 'kategorija', 'brend', 'dobavljac', 'lager', 'nabavna_cena', 'prodajna_cena', 'zarada'];
    $seen = [];
    $fieldOk = true;
    $valuesOk = true;
    $imagesOk = true;
    $priceOk = true;

    foreach ($merged['products'] as $index => $original) {
        $item = $entries[$index];
        $ean = (string) $item->ean;
        if (preg_match('/^[0-9]{13}$/D', $ean) !== 1 || isset($seen[$ean])) {
            throw new RuntimeException('Neispravan ili dupliran EAN u izvoz.xml: ' . $ean);
        }
        $seen[$ean] = true;
        foreach ($fields as $field) {
            if (!isset($item->{$field}) || trim((string) $item->{$field}) === '') {
                $fieldOk = false;
            }
        }
        if ($ean !== (string) $original['ean'] || (string) $item->naziv !== (string) $original['naziv'] ||
            (string) $item->kategorija !== (string) $original['kategorija'] ||
            (string) $item->brend !== (string) $original['brend'] ||
            (string) $item->dobavljac !== (string) $original['dobavljac'] ||
            (string) $item->lager !== (string) $original['lager']) {
            $valuesOk = false;
        }
        if (supplyhub_parse_price_cents((string) $item->nabavna_cena) !== $original['nabavna_cena_pare'] ||
            supplyhub_parse_price_cents((string) $item->prodajna_cena) !== $original['prodajna_cena_pare'] ||
            supplyhub_parse_price_cents((string) $item->zarada) !== $original['zarada_pare']) {
            $priceOk = false;
        }
        $exportedImages = [];
        foreach ($item->slike->slika as $image) {
            $exportedImages[] = (string) $image;
        }
        if ($exportedImages !== $original['slike']) {
            $imagesOk = false;
        }
        if ($index < 5) {
            $preview[] = [
                'ean' => $ean,
                'naziv' => (string) $item->naziv,
                'dobavljac' => (string) $item->dobavljac,
                'cena' => (string) $item->prodajna_cena,
                'slike' => count($exportedImages),
            ];
        }
    }
    $check(count($seen) === 52, 'Nijedan EAN se ne ponavlja.');
    $check($fieldOk, 'Svaki proizvod sadrži svih 9 obaveznih tekstualnih i numeričkih polja.');
    $check($valuesOk, 'Nazivi, EAN kodovi, dobavljači, lager i kategorije su verno preneti.');
    $check($priceOk, 'Sve nabavne/prodajne cene i zarade su tačne do jedne pare.');
    $check($imagesOk, 'Sve fotografije odabranih proizvoda su prenete.');

    $summary = $written;
} catch (Throwable $e) {
    $error = $e->getMessage();
    http_response_code(500);
}
?><!doctype html>
<html lang="sr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SupplyHub · Test XML izvoza</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#0b1525;color:#e9f1fd;font:14px/1.6,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;padding:38px 18px}
main{max-width:1140px;margin:0 auto}h1{font-size:29px;margin:0}h2{font-size:19px;margin:28px 0 12px}p{color:#9fb5cf}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:24px 0}.card,.table{background:#15243a;border:1px solid #2a415f;border-radius:12px}.card{padding:18px}.card b{display:block;font-size:26px;margin-top:6px}.muted{color:#9fb5cf}.success{color:#64eac0}.error{background:#3e252f;border:1px solid #b6576d;padding:16px;border-radius:11px}.note{background:#28271e;border:1px solid #765f34;padding:14px 17px;border-radius:10px;color:#e8d3ae}.table{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:700px}th,td{text-align:left;padding:13px 15px;border-bottom:1px solid #2a415f;font-size:12px}th{background:#1b314d;color:#bed0e9}tr:last-child td{border:0}.checks li{margin:7px 0}.checks li::marker{color:#64eac0}code{font-size:12px;color:#b9d3ff;overflow-wrap:anywhere}
</style>
</head><body><main>
<h1>SupplyHub · generisanje izvoz.xml</h1>
<p>Razvojni test: tri XML kataloga → PHP cene → izbor dobavljača → <strong>data/izvoz.xml</strong>.</p>
<?php if ($error !== null): ?>
<div class="error"><strong>Greška:</strong> <?= sh_esc($error) ?></div>
<?php else: ?>
<div class="grid">
  <div class="card"><span class="muted">Učitano ponuda</span><b>122</b></div>
  <div class="card"><span class="muted">Jedinstvenih proizvoda</span><b class="success"><?= $summary['count'] ?></b></div>
  <div class="card"><span class="muted">Generisan XML</span><b><?= number_format($summary['bytes'] / 1024, 1, ',', '.') ?> KB</b></div>
  <div class="card"><span class="muted">Provere</span><b class="success"><?= count($checks) ?>/<?= count($checks) ?></b></div>
</div>
<p class="success"><strong>Uspešno: data/izvoz.xml je generisan i pročitan nazad. <?= count($checks) ?> provera je prošlo.</strong></p>
<h2>Prvih pet proizvoda iz SAČUVANOG XML-a</h2>
<div class="table"><table><thead><tr><th>EAN</th><th>Proizvod</th><th>Dobavljač</th><th>Prodajna sa PDV-om</th><th>Slike</th></tr></thead><tbody>
<?php foreach ($preview as $p): ?><tr>
  <td><?= sh_esc($p['ean']) ?></td><td><?= sh_esc($p['naziv']) ?></td><td><?= sh_esc($p['dobavljac']) ?></td><td class="success"><?= sh_esc($p['cena']) ?> RSD</td><td><?= (int) $p['slike'] ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<h2>Provere</h2><ol class="checks"><?php foreach ($checks as $message): ?><li><?= sh_esc($message) ?></li><?php endforeach; ?></ol>
<div class="note"><strong>Važno:</strong> Podrazumevani XML je u <code>data/izvoz.xml</code>. Originalni dobavljački fajlovi nisu izmenjeni. <code>data/.htaccess</code> onemogućava direktno preuzimanje preko Apache-a; dugme za preuzimanje dodaćemo zasebno. Ovu test stranicu obriši pre produkcije.</div>
<p class="muted">SHA-256: <code><?= sh_esc($summary['sha256']) ?></code></p>
<?php endif; ?>
</main></body></html>
