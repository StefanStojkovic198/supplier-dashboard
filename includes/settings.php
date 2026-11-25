<?php
/** SupplyHub — bezbedna promena procenata i ponovni obračun na PHP serveru. */
declare(strict_types=1);

/** Lokalni pristup mora dolaziti SA loopback adrese I na localhost domen. */
function supplyhub_is_local_request(): bool
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    return in_array($remote, ['127.0.0.1', '::1'], true)
        && preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/iD', $host) === 1;
}

/** Session-based CSRF protection; no passwords/tokens in Git. */
function supplyhub_start_settings_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (session_status() !== PHP_SESSION_NONE) {
        throw new RuntimeException('Session nije dostupna.');
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Neuspešno pokretanje sesije.');
    }
}

function supplyhub_csrf_token(): string
{
    supplyhub_start_settings_session();
    if (!isset($_SESSION['supplyhub_csrf']) || !is_string($_SESSION['supplyhub_csrf'])) {
        $_SESSION['supplyhub_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['supplyhub_csrf'];
}

/** Bez uređivanja izvornog koda: zaštita na serveru putem env promenljive. */
function supplyhub_settings_auth_allowed(mixed $providedPassword): bool
{
    if (supplyhub_is_local_request()) {
        return true;
    }
    // Lozinku ne slati bez HTTPS. Reverse proxy mora ispravno proslediti HTTPS informaciju PHP-u.
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        return false;
    }
    $configured = getenv('SUPPLYHUB_ADMIN_PASSWORD');
    if (!is_string($configured) || strlen($configured) < 16 || !is_string($providedPassword)) {
        return false;
    }
    return hash_equals($configured, $providedPassword);
}

/** Dozvoljene su vrednosti od 0 do 100 sa najviše dve decimale. */
function supplyhub_validate_ui_rate(mixed $value, string $field): int
{
    if (!is_int($value) && !is_float($value)) {
        throw new InvalidArgumentException($field . ': procenat mora biti broj.');
    }
    if (!is_finite((float) $value) || $value < 0 || $value > 100) {
        throw new InvalidArgumentException($field . ': dozvoljeni raspon je 0–100%.');
    }
    $bps = (int) round($value * 100, 0, PHP_ROUND_HALF_UP);
    if (abs($bps - $value * 100) > 0.000001) {
        throw new InvalidArgumentException($field . ': dozvoljene su najviše dve decimale.');
    }
    return $bps;
}

/** @return array{margins:array<string,int>,vat:int} */
function supplyhub_validate_settings_input(mixed $input): array
{
    if (!is_array($input) || !isset($input['margins']) || !is_array($input['margins'])) {
        throw new InvalidArgumentException('Nedostaju marže dobavljača.');
    }
    $marginInputs = $input['margins'];
    $margins = [];
    foreach (['alfa', 'beta', 'gama'] as $id) {
        $margins[$id] = supplyhub_validate_ui_rate($marginInputs[$id] ?? null, 'Marža ' . $id);
    }
    return ['margins' => $margins, 'vat' => supplyhub_validate_ui_rate($input['vat'] ?? null, 'PDV')];
}

/** @param array{margins:array<string,int>,vat:int} $bps */
function supplyhub_public_rates(array $bps): array
{
    return [
        'margins' => [
            'alfa' => $bps['margins']['alfa'] / 100,
            'beta' => $bps['margins']['beta'] / 100,
            'gama' => $bps['margins']['gama'] / 100,
        ],
        'vat' => $bps['vat'] / 100,
    ];
}

/** Priprema PRIVREMENOG fajla u istom folderu radi atomarnog rename(). */
function supplyhub_prepare_temp(string $destination, string $contents): string
{
    $folder = dirname($destination);
    if (!is_dir($folder) || !is_writable($folder)) {
        throw new RuntimeException('PHP nema dozvolu za upis u ' . basename($folder) . '/ folder.');
    }
    $temp = tempnam($folder, '.supplyhub-tmp-');
    if ($temp === false) {
        throw new RuntimeException('Neuspešno kreiranje privremenog fajla.');
    }
    try {
        $bytes = file_put_contents($temp, $contents, LOCK_EX);
        if ($bytes === false || $bytes !== strlen($contents) || !chmod($temp, 0644)) {
            throw new RuntimeException('Neuspešno čuvanje privremenog fajla.');
        }
        return $temp;
    } catch (Throwable $error) {
        @unlink($temp);
        throw $error;
    }
}

/**
 * Izvršava celu operaciju pod jednim lock-om. U slučaju greške pre vraćanja odgovora
 * pokušava vraćanje oba originalna fajla. Ulazni XML katalog se NIKADA ne menja.
 * @param array{margins:array<string,int>,vat:int} $newRatesBps
 * @return array{products:array<int,array<string,mixed>>,rates:array<string,mixed>,version:string,count:int,skipped:int}
 */
function supplyhub_apply_settings(string $root, array $newRatesBps, string $expectedVersion): array
{
    require_once $root . '/includes/products.php';
    require_once $root . '/includes/pricing.php';
    require_once $root . '/includes/merge.php';
    require_once $root . '/includes/export.php';
    require_once $root . '/includes/catalog.php';

    $settingsFile = $root . '/config/settings.json';
    $exportFile = $root . '/data/izvoz.xml';
    $lockFile = $root . '/config/.settings.lock';
    $lock = fopen($lockFile, 'c');
    if ($lock === false) {
        throw new RuntimeException('PHP ne može da napravi lock fajl u config/.');
    }
    $settingsTmp = null;
    $exportTmp = null;
    $oldSettings = null;
    $oldExport = null;
    $settingsCommitted = false;
    $exportCommitted = false;
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Ne mogu zaključati podešavanja za upis.');
        }
        $oldSettings = file_get_contents($settingsFile);
        $oldExport = file_get_contents($exportFile);
        if (!is_string($oldSettings) || !is_string($oldExport) || $oldExport === '') {
            throw new RuntimeException('Konfiguracija ili postojeći XML nisu dostupni.');
        }
        if (!preg_match('/^[0-9a-f]{64}$/D', $expectedVersion) || !hash_equals(hash('sha256', $oldSettings), $expectedVersion)) {
            throw new DomainException('Podešavanja su u međuvremenu promenjena. Osveži stranicu i probaj ponovo.');
        }

        $offers = supplyhub_load_offers($root . '/data');
        $priced = supplyhub_calculate_offer_prices($offers['offers'], $newRatesBps);
        $merged = supplyhub_merge_priced_offers($priced);
        if (count($merged['products']) === 0) {
            throw new RuntimeException('Nijedan validan proizvod nije dostupan za izvoz.');
        }
        $xml = supplyhub_build_export_xml($merged['products']);
        $public = supplyhub_public_rates($newRatesBps);
        $json = json_encode($public, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        $settingsTmp = supplyhub_prepare_temp($settingsFile, $json);
        $exportTmp = supplyhub_prepare_temp($exportFile, $xml);

        // Proveri NOVI XML pre promene postojećih datoteka.
        $products = supplyhub_read_export_catalog($exportTmp);
        if (count($products) !== count($merged['products'])) {
            throw new RuntimeException('Kontrolno čitanje novog XML-a nije uspelo.');
        }

        // Oba privremena fajla su već pripremljena i validirana.
        if (!rename($exportTmp, $exportFile)) {
            throw new RuntimeException('Neuspešno ažuriranje XML izvoza.');
        }
        $exportTmp = null;
        $exportCommitted = true;
        if (!rename($settingsTmp, $settingsFile)) {
            throw new RuntimeException('Neuspešno čuvanje novih podešavanja.');
        }
        $settingsTmp = null;
        $settingsCommitted = true;
        return [
            'products' => $products,
            'rates' => $public,
            'version' => hash('sha256', $json),
            'count' => count($products),
            'skipped' => count($merged['skipped']),
        ];
    } catch (Throwable $error) {
        // Ako drugi rename ne uspe, vratiti XML na staru verziju.
        // Ako dođe do neočekivane greške posle upisa, vratiti i JSON.
        if ($exportCommitted && is_string($oldExport)) {
            try {
                $recovery = supplyhub_prepare_temp($exportFile, $oldExport);
                if (!rename($recovery, $exportFile)) {
                    @unlink($recovery);
                    error_log('SupplyHub: CRITICAL — XML rollback nije uspeo!');
                }
            } catch (Throwable $rollbackError) {
                error_log('SupplyHub: CRITICAL — XML rollback: ' . $rollbackError->getMessage());
            }
        }
        if ($settingsCommitted && is_string($oldSettings)) {
            try {
                $recovery = supplyhub_prepare_temp($settingsFile, $oldSettings);
                if (!rename($recovery, $settingsFile)) {
                    @unlink($recovery);
                    error_log('SupplyHub: CRITICAL — settings rollback nije uspeo!');
                }
            } catch (Throwable $rollbackError) {
                error_log('SupplyHub: CRITICAL — settings rollback: ' . $rollbackError->getMessage());
            }
        }
        throw $error;
    } finally {
        if (is_string($settingsTmp) && is_file($settingsTmp)) {
            @unlink($settingsTmp);
        }
        if (is_string($exportTmp) && is_file($exportTmp)) {
            @unlink($exportTmp);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
