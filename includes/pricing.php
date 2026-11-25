<?php
/**
 * SupplyHub — serverski obracun cene i zarade (korak 08/09).
 *
 * Cene se racunaju u celobrojnim parama. Zaokruzivanje je HALF UP
 * na 2 decimale, najpre posle marze, a zatim posle PDV-a.
 * Zarada je prodajna cena BEZ PDV-a minus nabavna cena.
 *
 * Ovaj modul NE menja XML fajlove i NE bira najpovoljnijeg dobavljaca.
 */
declare(strict_types=1);

/** @return array{margins:array<string,int>,vat:int} Stope su u baznim poenima (100 = 1%). */
function supplyhub_load_pricing_config(string $settingsPath): array
{
    if (!is_file($settingsPath) || !is_readable($settingsPath)) {
        throw new RuntimeException('Nije dostupan config/settings.json.');
    }

    try {
        $config = json_decode((string) file_get_contents($settingsPath), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('Neispravan JSON za podesavanja.', 0, $error);
    }

    if (!is_array($config) || !isset($config['margins']) || !is_array($config['margins'])) {
        throw new RuntimeException('Nedostaju margins u podesavanjima.');
    }

    $margins = [];
    foreach (['alfa', 'beta', 'gama'] as $supplierId) {
        $margins[$supplierId] = supplyhub_rate_in_basis_points(
            $config['margins'][$supplierId] ?? null,
            'Marza ' . $supplierId
        );
    }
    $vat = supplyhub_rate_in_basis_points($config['vat'] ?? null, 'PDV');

    return ['margins' => $margins, 'vat' => $vat];
}

/** 1% = 100 baznih poena. Ogranicavamo konfiguraciju na 0-100%. */
function supplyhub_rate_in_basis_points(mixed $rate, string $name): int
{
    if (!is_int($rate) && !is_float($rate)) {
        throw new InvalidArgumentException($name . ' mora biti broj (procenat).');
    }
    if (!is_finite((float) $rate) || $rate < 0 || $rate > 100) {
        throw new InvalidArgumentException($name . ' mora biti izmedju 0 i 100%.');
    }
    return (int) round((float) $rate * 100, 0, PHP_ROUND_HALF_UP);
}

/** Dodaje procenat na iznos u parama, sa ispravnim zaokruzivanjem. */
function supplyhub_add_percent_to_cents(int $cents, int $rateBps): int
{
    if ($cents < 0 || $rateBps < 0 || $rateBps > 10000) {
        throw new InvalidArgumentException('Neispravan iznos ili procenat.');
    }
    $factor = 10000 + $rateBps;
    if ($cents > intdiv(PHP_INT_MAX - 5000, $factor)) {
        throw new OverflowException('Iznos je prevelik za obracun.');
    }
    return intdiv($cents * $factor + 5000, 10000);
}

/**
 * @param array<int,array<string,mixed>> $offers Rezultat supplyhub_load_offers()['offers']
 * @param array{margins:array<string,int>,vat:int} $config
 * @return array<int,array<string,mixed>> Ponude sa obracunatim cenama
 */
function supplyhub_calculate_offer_prices(array $offers, array $config): array
{
    $result = [];
    foreach ($offers as $offer) {
        $supplierId = (string) ($offer['dobavljac_id'] ?? '');
        if (!array_key_exists($supplierId, $config['margins'])) {
            throw new RuntimeException('Nepoznat dobavljac: ' . $supplierId);
        }
        $cost = $offer['nabavna_cena_pare'] ?? null;
        if (!is_int($cost)) {
            throw new RuntimeException('Nedostaje nabavna cena u parama za ponudu.');
        }
        $net = supplyhub_add_percent_to_cents($cost, $config['margins'][$supplierId]);
        $gross = supplyhub_add_percent_to_cents($net, $config['vat']);

        $offer['prodajna_bez_pdv_pare'] = $net;
        $offer['pdv_pare'] = $gross - $net;
        $offer['prodajna_cena_pare'] = $gross;
        $offer['zarada_pare'] = $net - $cost;
        // Za kasniji XML izvoz i frontend, pomocni prikaz u RSD:
        $offer['prodajna_bez_pdv'] = $net / 100;
        $offer['prodajna_cena'] = $gross / 100;
        $offer['zarada'] = ($net - $cost) / 100;
        $result[] = $offer;
    }
    return $result;
}
