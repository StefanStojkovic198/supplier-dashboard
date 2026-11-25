<?php
/**
 * SupplyHub — učitavanje i normalizacija ulaznih XML kataloga.
 *
 * KORAK 06: Ovaj modul NE obračunava maržu/PDV, NE bira dobavljača
 * i NE generiše izvoz.xml. Time ćemo se baviti u sledećim koracima.
 *
 * Rezultat supplyhub_load_offers():
 *   [
 *      'offers' => [ ... ],
 *      'suppliers' => [ 'alfa' => ['name'=>..., 'count'=>...], ... ],
 *      'warnings' => [ ... ]
 *   ]
 */
declare(strict_types=1);

/** Pretvara cenu u RSD sa tačkom ILI zarezom u celobrojne pare. */
function supplyhub_parse_price_cents(string $raw): int
{
    $value = trim(str_replace(["\u{00A0}", ' '], '', $raw));

    if (!preg_match('/^([0-9]+)(?:[.,]([0-9]{1,2}))?$/D', $value, $match)) {
        throw new InvalidArgumentException('Neispravan format nabavne cene: ' . $raw);
    }

    $dinars = (int) $match[1];
    $decimals = isset($match[2]) ? (int) str_pad($match[2], 2, '0') : 0;
    if ($dinars > intdiv(PHP_INT_MAX - $decimals, 100)) {
        throw new InvalidArgumentException('Nabavna cena je prevelika.');
    }

    return ($dinars * 100) + $decimals;
}

/**
 * Učitava tri katalog-fajla iz data foldera.
 * Ne odbacujemo artikle sa praznim EAN-om ili nultom cenom —
 * evidentiramo upozorenja, pa posebno definišemo pravila u koraku 11.
 *
 * @return array{offers: array<int,array<string,mixed>>, suppliers:array<string,array<string,mixed>>, warnings:array<int,array<string,string>>}
 */
function supplyhub_load_offers(string $dataDirectory): array
{
    if (!function_exists('simplexml_load_file')) {
        throw new RuntimeException('PHP SimpleXML ekstenzija nije omogućena. Uključi je u PHP podešavanjima MAMP-a.');
    }

    $files = [
        'alfa' => 'alfa_distribucija.xml',
        'beta' => 'beta_trade.xml',
        'gama' => 'gama_import.xml',
    ];

    $offers = [];
    $suppliers = [];
    $warnings = [];

    foreach ($files as $supplierId => $filename) {
        $path = rtrim($dataDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('XML fajl nije pronađen ili nije čitljiv: ' . $filename);
        }

        // Ne učitavamo spoljne entitete, čak ni ako XML sadrži DTD.
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NONET);
            if ($xml === false) {
                $errors = libxml_get_errors();
                $detail = $errors ? trim($errors[0]->message) : 'Nepoznata greška';
                throw new RuntimeException('Neispravan XML ' . $filename . ': ' . $detail);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml->getName() !== 'proizvodi') {
            throw new RuntimeException('Neočekivan korenski element u fajlu: ' . $filename);
        }

        $attributes = $xml->attributes();
        $supplierName = trim((string) ($attributes['dobavljac'] ?? ''));
        $currency = strtoupper(trim((string) ($attributes['valuta'] ?? '')));
        if ($supplierName === '') {
            throw new RuntimeException('Nedostaje naziv dobavljača u fajlu: ' . $filename);
        }
        if ($currency !== 'RSD') {
            throw new RuntimeException('Očekivana valuta je RSD u fajlu: ' . $filename);
        }

        $count = 0;
        foreach ($xml->proizvod as $product) {
            $count++;
            $sku = trim((string) $product->sku);
            $eanRaw = (string) $product->ean;
            // EAN ostaje string, kako ne bi došlo do gubitka vodećih nula.
            $ean = preg_replace('/\s+/u', '', trim($eanRaw));
            if ($ean === null) {
                throw new RuntimeException('Greška pri obradi EAN koda u: ' . $filename);
            }

            $name = trim((string) $product->naziv);
            $category = trim((string) $product->kategorija);
            $brand = trim((string) $product->brend);
            $price = supplyhub_parse_price_cents((string) $product->cena);
            $stockRaw = trim((string) $product->lager);
            // U ulaznim podacima postoji i negativan lager (npr. -3).
            // Negativan ceo broj nije greska formata: sacuvamo ga verno i
            // prijavimo upozorenje, umesto da prekinemo obradu sva tri fajla.
            // Pravilo za ukljucivanje takvih ponuda u izbor definisemo kasnije.
            $stock = filter_var($stockRaw, FILTER_VALIDATE_INT);
            if ($stock === false) {
                throw new RuntimeException('Neispravan format lagera za SKU ' . $sku . ' u fajlu ' . $filename);
            }
            if ($stock < 0) {
                $warnings[] = [
                    'type' => 'NEGATIVAN_LAGER',
                    'supplier' => $supplierName,
                    'sku' => $sku,
                    'message' => 'Lager je ' . $stock . ' kom. Izvorna vrednost je sacuvana za kasniju obradu.',
                ];
            }

            $images = [];
            foreach ($product->fotografija->slika ?? [] as $image) {
                $url = trim((string) $image);
                if ($url !== '') {
                    $images[] = $url;
                }
            }

            if ($ean === '') {
                $warnings[] = ['type' => 'PRAZAN_EAN', 'supplier' => $supplierName, 'sku' => $sku, 'message' => 'Proizvod nema EAN kod.'];
            } elseif (!preg_match('/^[0-9]{13}$/D', $ean)) {
                $warnings[] = ['type' => 'FORMAT_EAN', 'supplier' => $supplierName, 'sku' => $sku, 'message' => 'EAN nema 13 cifara.'];
            }
            if ($price === 0) {
                $warnings[] = ['type' => 'NULTA_CENA', 'supplier' => $supplierName, 'sku' => $sku, 'message' => 'Nabavna cena je 0 RSD.'];
            }
            if ($name === '') {
                $warnings[] = ['type' => 'PRAZAN_NAZIV', 'supplier' => $supplierName, 'sku' => $sku, 'message' => 'Proizvod nema naziv.'];
            }

            $offers[] = [
                'ean' => $ean,
                'naziv' => $name,
                'kategorija' => $category,
                'brend' => $brand,
                'dobavljac' => $supplierName,
                'dobavljac_id' => $supplierId,
                'sku' => $sku,
                'lager' => $stock,
                'nabavna_cena_pare' => $price, // tačan celobrojni iznos
                'nabavna_cena' => $price / 100, // pogodnost za prikaz
                'slike' => $images,
            ];
        }

        $suppliers[$supplierId] = [
            'name' => $supplierName,
            'file' => $filename,
            'count' => $count,
        ];
    }

    return [
        'offers' => $offers,
        'suppliers' => $suppliers,
        'warnings' => $warnings,
    ];
}
