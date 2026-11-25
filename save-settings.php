<?php
/** POST JSON API: bezbedno čuvanje marži/PDV-a i regenerisanje XML kataloga. */
declare(strict_types=1);
require_once __DIR__ . '/includes/settings.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Referrer-Policy: same-origin');

function supplyhub_api_reply(int $status, array $message): never
{
    http_response_code($status);
    echo json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    supplyhub_api_reply(405, ['ok' => false, 'error' => 'Dozvoljen je samo POST zahtev.']);
}

try {
    supplyhub_start_settings_session();
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if (!is_string($raw) || strlen($raw) > 8192) {
        supplyhub_api_reply(413, ['ok' => false, 'error' => 'Zahtev je prevelik.']);
    }
    $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($body)) {
        throw new InvalidArgumentException('Neispravan JSON zahtev.');
    }
    $csrf = $body['csrf'] ?? null;
    if (!is_string($csrf) || !hash_equals(supplyhub_csrf_token(), $csrf)) {
        supplyhub_api_reply(403, ['ok' => false, 'error' => 'Sesija je istekla. Osveži stranicu i probaj ponovo.']);
    }
    if (!supplyhub_settings_auth_allowed($body['password'] ?? null)) {
        supplyhub_api_reply(403, ['ok' => false, 'error' => 'Izmena nije dozvoljena. Na javnom serveru potrebni su HTTPS i podešena administratorska lozinka.']);
    }
    $parsed = supplyhub_validate_settings_input($body);
    $version = $body['version'] ?? null;
    if (!is_string($version)) {
        throw new InvalidArgumentException('Nedostaje verzija podešavanja. Osveži stranicu.');
    }
    // Sesija više ne mora držati lock tokom obračuna, ali CSRF ostaje validan.
    session_write_close();
    $result = supplyhub_apply_settings(__DIR__, $parsed, $version);
    supplyhub_api_reply(200, ['ok' => true, 'message' => 'Podešavanja i izvoz.xml su sačuvani.', ...$result]);
} catch (DomainException $error) {
    supplyhub_api_reply(409, ['ok' => false, 'error' => $error->getMessage()]);
} catch (InvalidArgumentException | JsonException $error) {
    supplyhub_api_reply(422, ['ok' => false, 'error' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('SupplyHub save-settings: ' . $error->getMessage());
    supplyhub_api_reply(500, [
        'ok' => false,
        'error' => supplyhub_is_local_request()
            ? 'Serverska greška: ' . $error->getMessage()
            : 'Serverska greška pri čuvanju. Pogledaj server log.',
    ]);
}
