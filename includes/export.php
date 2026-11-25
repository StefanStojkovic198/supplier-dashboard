<?php
/**
 * SupplyHub — generisanje objedinjenog izvoz.xml (korak 12).
 *
 * Ulaz:  products iz supplyhub_merge_priced_offers()['products'].
 * Izlaz: data/izvoz.xml, jedan <proizvod> po validnom EAN-u.
 *
 * Iznosi u XML-u su u RSD sa tačno dve decimale:
 * - nabavna_cena: bez PDV-a
 * - prodajna_cena: SA PDV-om
 * - zarada: BEZ PDV-a (prodajna bez PDV-a - nabavna)
 *
 * Bez dodatne biblioteke: XML tekst se pravilno escape-uje, pa se
 * ceo dokument upisuje u privremeni fajl i atomarno preimenuje.
 */
declare(strict_types=1);

/** Escape teksta za XML 1.0. */
function supplyhub_xml_escape(string $value): string
{
    if (preg_match('//u', $value) !== 1) {
        throw new InvalidArgumentException('XML sadrži neispravan UTF-8 tekst.');
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
        throw new InvalidArgumentException('XML sadrži nedozvoljen kontrolni karakter.');
    }
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Formatira tačne iznose iz celobrojnih para bez rada sa float tipom. */
function supplyhub_xml_money(int $cents): string
{
    if ($cents < 0) {
        throw new InvalidArgumentException('Negativan iznos nije dozvoljen u izvozu.');
    }
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

/**
 * @param array<int,array<string,mixed>> $products Konačni proizvodi iz merge.php
 */
function supplyhub_build_export_xml(array $products): string
{
    $lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<proizvodi valuta="RSD">',
    ];
    $seen = [];

    foreach ($products as $product) {
        $ean = (string) ($product['ean'] ?? '');
        if (preg_match('/^[0-9]{13}$/D', $ean) !== 1 || isset($seen[$ean])) {
            throw new InvalidArgumentException('Neispravan ili dupliran EAN u izvozu: ' . $ean);
        }
        $seen[$ean] = true;

        foreach (['naziv', 'kategorija', 'brend', 'dobavljac'] as $field) {
            if (!isset($product[$field]) || !is_string($product[$field]) || trim($product[$field]) === '') {
                throw new InvalidArgumentException('Nedostaje obavezno polje ' . $field . ' za EAN ' . $ean);
            }
        }
        $stock = $product['lager'] ?? null;
        if (!is_int($stock) || $stock < 0) {
            throw new InvalidArgumentException('Neispravan lager za EAN ' . $ean);
        }
        foreach (['nabavna_cena_pare', 'prodajna_cena_pare', 'zarada_pare'] as $field) {
            if (!isset($product[$field]) || !is_int($product[$field]) || $product[$field] < 0) {
                throw new InvalidArgumentException('Neispravno polje ' . $field . ' za EAN ' . $ean);
            }
        }
        if ($product['nabavna_cena_pare'] === 0) {
            throw new InvalidArgumentException('Nulta nabavna cena za EAN ' . $ean);
        }
        $images = $product['slike'] ?? null;
        if (!is_array($images)) {
            throw new InvalidArgumentException('Nedostaje lista slika za EAN ' . $ean);
        }

        $lines[] = '  <proizvod>';
        foreach ([
            'ean' => $ean,
            'naziv' => $product['naziv'],
            'kategorija' => $product['kategorija'],
            'brend' => $product['brend'],
            'dobavljac' => $product['dobavljac'],
            'lager' => (string) $stock,
            'nabavna_cena' => supplyhub_xml_money($product['nabavna_cena_pare']),
            'prodajna_cena' => supplyhub_xml_money($product['prodajna_cena_pare']),
            'zarada' => supplyhub_xml_money($product['zarada_pare']),
        ] as $tag => $value) {
            $lines[] = '    <' . $tag . '>' . supplyhub_xml_escape((string) $value) . '</' . $tag . '>';
        }
        $lines[] = '    <slike>';
        foreach ($images as $image) {
            if (!is_string($image) || trim($image) === '') {
                throw new InvalidArgumentException('Neispravna slika za EAN ' . $ean);
            }
            $lines[] = '      <slika>' . supplyhub_xml_escape($image) . '</slika>';
        }
        $lines[] = '    </slike>';
        $lines[] = '  </proizvod>';
    }

    $lines[] = '</proizvodi>';
    return implode("\n", $lines) . "\n";
}

/**
 * Bezbedan upis u odredišni folder. Ne dira originalne XML kataloge.
 * Za novi rezultat koristi standardnu putanju: data/izvoz.xml.
 *
 * @param array<int,array<string,mixed>> $products
 * @return array{count:int,bytes:int,path:string,sha256:string}
 */
function supplyhub_write_export_xml(array $products, string $outputPath): array
{
    if (count($products) === 0) {
        throw new InvalidArgumentException('Nije moguće generisati prazan izvoz.xml.');
    }
    $xml = supplyhub_build_export_xml($products);
    $dir = dirname($outputPath);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Folder za XML izvoz ne postoji ili PHP nema pravo upisa: ' . $dir);
    }

    $tempPath = tempnam($dir, '.supplyhub-export-');
    if ($tempPath === false) {
        throw new RuntimeException('Nije moguće kreirati privremeni XML fajl.');
    }
    try {
        $bytes = file_put_contents($tempPath, $xml, LOCK_EX);
        if ($bytes === false || $bytes !== strlen($xml)) {
            throw new RuntimeException('XML fajl nije u potpunosti upisan.');
        }
        if (!rename($tempPath, $outputPath)) {
            throw new RuntimeException('Neuspešno čuvanje izvoz.xml fajla.');
        }
    } finally {
        if (is_file($tempPath)) {
            unlink($tempPath);
        }
    }

    return [
        'count' => count($products),
        'bytes' => strlen($xml),
        'path' => $outputPath,
        'sha256' => hash('sha256', $xml),
    ];
}
