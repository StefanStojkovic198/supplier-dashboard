<?php
/**
 * SupplyHub — prikaz SAČUVANOG izvoz.xml u dashboardu.
 * Ne obračunava ponovo cene. To je već završeno na serveru u pricing/merge/export modulima.
 */
declare(strict_types=1);

/** Cene su RSD sa tačno dve decimale; u frontend šaljemo iznos u parama. */
function supplyhub_catalog_cents(string $value, string $field): int
{
    if (preg_match('/^([0-9]{1,12})\.([0-9]{2})$/D', $value, $parts) !== 1) {
        throw new RuntimeException('Neispravan iznos u izvoz.xml: ' . $field);
    }
    return ((int) $parts[1] * 100) + (int) $parts[2];
}

/** @return array<int,array<string,mixed>> */
function supplyhub_read_export_catalog(string $xmlPath): array
{
    if (!is_file($xmlPath) || !is_readable($xmlPath)) {
        throw new RuntimeException('Nedostaje data/izvoz.xml. Prvo pokreni lokalni test generisanja XML-a.');
    }
    if (!function_exists('simplexml_load_file')) {
        throw new RuntimeException('PHP nema uključenu SimpleXML ekstenziju.');
    }

    $prior = libxml_use_internal_errors(true);
    try {
        // Ne učitavamo spoljne entitete i ne dozvoljavamo DTD.
        $raw = file_get_contents($xmlPath);
        if ($raw === false || stripos($raw, '<!DOCTYPE') !== false || stripos($raw, '<!ENTITY') !== false) {
            throw new RuntimeException('Neispravan ili nebezbedan XML dokument.');
        }
        $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET);
        if ($xml === false || $xml->getName() !== 'proizvodi' || (string) $xml['valuta'] !== 'RSD') {
            throw new RuntimeException('izvoz.xml nema očekivanu strukturu ili valutu RSD.');
        }

        $supplierIds = [
            'Alfa Distribucija d.o.o.' => 'alfa',
            'Beta Trade d.o.o.' => 'beta',
            'Gama Import d.o.o.' => 'gama',
        ];
        $products = [];
        $seen = [];
        foreach ($xml->proizvod as $product) {
            $ean = trim((string) $product->ean);
            $name = trim((string) $product->naziv);
            $category = trim((string) $product->kategorija);
            $brand = trim((string) $product->brend);
            $supplierName = trim((string) $product->dobavljac);
            $stockText = trim((string) $product->lager);

            if (preg_match('/^[0-9]{13}$/D', $ean) !== 1 || isset($seen[$ean])) {
                throw new RuntimeException('Neispravan ili dupliran EAN u izvoz.xml.');
            }
            if ($name === '' || $category === '' || $brand === '' || !isset($supplierIds[$supplierName])) {
                throw new RuntimeException('Nedostaju podaci o proizvodu ili dobavljaču za EAN ' . $ean);
            }
            if (preg_match('/^[0-9]{1,9}$/D', $stockText) !== 1) {
                throw new RuntimeException('Neispravan lager za EAN ' . $ean);
            }
            $seen[$ean] = true;
            $images = [];
            foreach ($product->slike->slika as $image) {
                $url = trim((string) $image);
                if (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) {
                    $images[] = $url;
                }
            }

            $products[] = [
                'ean' => $ean,
                'name' => $name,
                'category' => $category,
                'brand' => $brand,
                'supplier' => $supplierIds[$supplierName],
                'supplierName' => $supplierName,
                'stock' => (int) $stockText,
                'costCents' => supplyhub_catalog_cents(trim((string) $product->nabavna_cena), 'nabavna_cena'),
                'saleCents' => supplyhub_catalog_cents(trim((string) $product->prodajna_cena), 'prodajna_cena'),
                'profitCents' => supplyhub_catalog_cents(trim((string) $product->zarada), 'zarada'),
                'image' => $images[0] ?? null,
            ];
        }
        if ($products === []) {
            throw new RuntimeException('izvoz.xml ne sadrži proizvode.');
        }
        return $products;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($prior);
    }
}
