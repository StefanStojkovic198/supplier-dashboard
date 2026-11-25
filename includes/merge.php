<?php
/**
 * SupplyHub — izbor najpovoljnije ponude po EAN kodu (korak 10/11).
 *
 * Ulaz: rezultat supplyhub_calculate_offer_prices() iz pricing.php.
 * Izlaz: jedan proizvod po validnom EAN-u i izveštaj o preskočenim ponudama.
 *
 * Dogovorena politika za posebne slučajeve:
 *  - Prazan/neispravan EAN: preskačemo ponudu, jer identitet nije pouzdan.
 *  - Nabavna cena 0 RSD: preskačemo ponudu, da ne pobedi lažno besplatna roba.
 *  - Negativan lager: preskačemo ponudu, jer količina nije validna.
 *  - Lager 0: zadržavamo ponudu. PDF ne zahteva da roba bude dostupna.
 *  - Ista prodajna cena: biramo veći lager; zatim ID dobavljača i SKU
 *    (radi determinističkog rezultata).
 *
 * Originalni XML fajlovi i izračunate ponude se ne menjaju.
 * U ovom koraku još NE generišemo izvoz.xml.
 */
declare(strict_types=1);

/**
 * Upoređuje DVE validne ponude po pravilima izbora.
 * Negativan rezultat znači da je $a poželjnija od $b.
 * @param array<string,mixed> $a
 * @param array<string,mixed> $b
 */
function supplyhub_compare_offers(array $a, array $b): int
{
    $sale = $a['prodajna_cena_pare'] <=> $b['prodajna_cena_pare'];
    if ($sale !== 0) {
        return $sale;
    }

    // Cene su jednake; prednost ima ponuda sa većim lagerom.
    $stock = $b['lager'] <=> $a['lager'];
    if ($stock !== 0) {
        return $stock;
    }

    $supplier = strcmp((string) $a['dobavljac_id'], (string) $b['dobavljac_id']);
    if ($supplier !== 0) {
        return $supplier;
    }
    return strcmp((string) $a['sku'], (string) $b['sku']);
}

/**
 * @param array<int,array<string,mixed>> $pricedOffers Ponude iz pricing.php
 * @return array{
 *   products:array<int,array<string,mixed>>,
 *   comparisons:array<int,array<string,mixed>>,
 *   skipped:array<int,array<string,string>>,
 *   stats:array<string,mixed>
 * }
 */
function supplyhub_merge_priced_offers(array $pricedOffers): array
{
    $groups = [];
    $skipped = [];
    $eligibleCount = 0;

    foreach ($pricedOffers as $offer) {
        $ean = trim((string) ($offer['ean'] ?? ''));
        $sku = (string) ($offer['sku'] ?? '?');
        $supplier = (string) ($offer['dobavljac'] ?? '?');
        $stock = $offer['lager'] ?? null;
        $cost = $offer['nabavna_cena_pare'] ?? null;
        $sale = $offer['prodajna_cena_pare'] ?? null;

        // Greške strukture obračuna su greške u kodu, ne obična upozorenja.
        if (!is_int($stock) || !is_int($cost) || !is_int($sale)) {
            throw new RuntimeException('Nepotpuno obračunata ponuda za SKU: ' . $sku);
        }

        $reason = null;
        if ($ean === '') {
            $reason = 'PRAZAN_EAN';
        } elseif (!preg_match('/^[0-9]{13}$/D', $ean)) {
            $reason = 'FORMAT_EAN';
        } elseif ($cost <= 0) {
            $reason = 'NULTA_CENA';
        } elseif ($stock < 0) {
            $reason = 'NEGATIVAN_LAGER';
        }

        if ($reason !== null) {
            $skipped[] = [
                'type' => $reason,
                'ean' => $ean,
                'sku' => $sku,
                'supplier' => $supplier,
                'name' => (string) ($offer['naziv'] ?? ''),
            ];
            continue;
        }

        // EAN je tekst: ne pretvarati ga u int (vodeće nule su važne).
        $groups[$ean][] = $offer;
        $eligibleCount++;
    }

    $products = [];
    $comparisons = [];
    $multiOfferProducts = 0;
    $selectedBySupplier = ['alfa' => 0, 'beta' => 0, 'gama' => 0];

    foreach ($groups as $ean => $offersForEan) {
        usort($offersForEan, 'supplyhub_compare_offers');
        $winner = $offersForEan[0];
        $count = count($offersForEan);
        if ($count > 1) {
            $multiOfferProducts++;
        }

        // Polja originalne ponude (uključujući slike, kategoriju i brend) ostaju.
        $winner['broj_validnih_ponuda'] = $count;
        $products[] = $winner;

        $id = (string) $winner['dobavljac_id'];
        $selectedBySupplier[$id] = ($selectedBySupplier[$id] ?? 0) + 1;

        $comparisons[] = [
            'ean' => (string) $ean,
            'naziv' => (string) $winner['naziv'],
            'dobavljac' => (string) $winner['dobavljac'],
            'dobavljac_id' => $id,
            'prodajna_cena_pare' => $winner['prodajna_cena_pare'],
            'lager' => $winner['lager'],
            'broj_ponuda' => $count,
            'ponude' => array_map(static function (array $offer): array {
                return [
                    'dobavljac' => (string) $offer['dobavljac'],
                    'sku' => (string) $offer['sku'],
                    'cena_pare' => $offer['prodajna_cena_pare'],
                    'lager' => $offer['lager'],
                ];
            }, $offersForEan),
        ];
    }

    // Stabilno sortiranje radi čitljivog prikaza i budućeg XML izvoza.
    $byName = static function (array $a, array $b): int {
        $first = strcmp(mb_strtolower((string) $a['naziv'], 'UTF-8'), mb_strtolower((string) $b['naziv'], 'UTF-8'));
        return $first !== 0 ? $first : strcmp((string) $a['ean'], (string) $b['ean']);
    };
    // mbstring nije obavezna ekstenzija na svim hosting paketima.
    $sort = static function (array $a, array $b) use ($byName): int {
        if (function_exists('mb_strtolower')) {
            return $byName($a, $b);
        }
        $first = strcasecmp((string) $a['naziv'], (string) $b['naziv']);
        return $first !== 0 ? $first : strcmp((string) $a['ean'], (string) $b['ean']);
    };
    usort($products, $sort);
    usort($comparisons, $sort);

    return [
        'products' => $products,
        'comparisons' => $comparisons,
        'skipped' => $skipped,
        'stats' => [
            'input_offers' => count($pricedOffers),
            'eligible_offers' => $eligibleCount,
            'skipped_offers' => count($skipped),
            'unique_products' => count($products),
            'products_with_multiple_offers' => $multiOfferProducts,
            'selected_by_supplier' => $selectedBySupplier,
        ],
    ];
}
