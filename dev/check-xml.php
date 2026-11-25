<?php
/**
 * Privremena, lokalna kontrolna stranica za KORAK 06.
 * Služi samo za MAMP testiranje; ne objavljuj je na produkcionom serveru.
 */
declare(strict_types=1);

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Ova test stranica dostupna je samo lokalno.');
}

require_once dirname(__DIR__) . '/includes/products.php';

try {
    $result = supplyhub_load_offers(dirname(__DIR__) . '/data');
    $error = null;
} catch (Throwable $exception) {
    $result = null;
    $error = $exception->getMessage();
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt(int $number): string
{
    return number_format($number, 0, ',', '.');
}

?><!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SupplyHub · XML provera</title>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:36px 18px;background:#0c1524;color:#e8effb;font:15px/1.55 system-ui,Arial,sans-serif}
        main{max-width:1040px;margin:auto}h1{font-size:29px;margin:0 0 5px}p{color:#a6b5c9}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:24px 0}
        .card{background:#162439;border:1px solid #2a3d57;border-radius:14px;padding:19px}.card b{display:block;font-size:28px;margin:4px 0}.muted{color:#97a9c1}
        table{width:100%;border-collapse:collapse;margin:14px 0 26px;min-width:660px}th,td{text-align:left;padding:11px 13px;border-bottom:1px solid #2a3b53;font-size:13px}th{color:#9eb5d6;background:#16243a}
        .scroll{overflow-x:auto;background:#132034;border:1px solid #2a3d57;border-radius:14px}h2{margin:27px 0 10px;font-size:19px}
        .warning{background:#372a1d;border:1px solid #9d6b33;padding:13px;border-radius:10px;color:#ffe0ae;margin:8px 0}
        .error{background:#3a2227;border:1px solid #b34d60;padding:16px;border-radius:10px;color:#ffd9df}
        .ok{color:#6addaf}.tag{display:inline-block;padding:3px 9px;border-radius:8px;background:#183e38;color:#74e0b8;font-size:12px}
    </style>
</head>
<body><main>
    <h1>SupplyHub · provera XML učitavanja</h1>
    <p>Razvojni test · Ovaj ekran ne menja postojeći dashboard i ne generiše izvoz.xml.</p>
    <?php if ($error !== null): ?>
        <div class="error"><strong>Greška:</strong> <?= esc($error) ?></div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($result['suppliers'] as $supplier): ?>
                <div class="card"><span class="muted"><?= esc($supplier['name']) ?></span><b><?= fmt($supplier['count']) ?></b><span class="muted">ponuda · <?= esc($supplier['file']) ?></span></div>
            <?php endforeach; ?>
            <div class="card"><span class="muted">Ukupno učitano</span><b><?= fmt(count($result['offers'])) ?></b><span class="tag">Bez objedinjavanja</span></div>
        </div>
        <h2>Prvih pet učitanih ponuda</h2>
        <div class="scroll"><table>
            <thead><tr><th>EAN</th><th>Naziv</th><th>Dobavljač</th><th>Nabavna cena</th><th>Lager</th><th>Slika</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($result['offers'], 0, 5) as $item): ?>
                <tr><td><?= esc($item['ean']) ?></td><td><?= esc($item['naziv']) ?></td><td><?= esc($item['dobavljac']) ?></td><td><?= esc(number_format($item['nabavna_cena'], 2, ',', '.')) ?> RSD</td><td><?= fmt($item['lager']) ?></td><td><?= count($item['slike']) ?> URL</td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <h2>Upozorenja (<?= count($result['warnings']) ?>)</h2>
        <?php if (count($result['warnings']) === 0): ?><p class="ok">Nema upozorenja.</p><?php endif; ?>
        <?php foreach ($result['warnings'] as $warning): ?>
            <div class="warning"><strong><?= esc($warning['type']) ?></strong> · <?= esc($warning['supplier']) ?> · SKU <?= esc($warning['sku']) ?> — <?= esc($warning['message']) ?></div>
        <?php endforeach; ?>
        <p>Ovo je samo potvrda da PHP ispravno čita ulazne podatke. Obračun, izbor dobavljača i izvoz slede.</p>
    <?php endif; ?>
</main></body></html>
