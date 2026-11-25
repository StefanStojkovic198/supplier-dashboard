<?php
/** Preuzimanje sačuvanog data/izvoz.xml, bez direktnog otvaranja data/ foldera. */
declare(strict_types=1);

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('Dozvoljeno je samo GET preuzimanje.');
}

$path = __DIR__ . '/data/izvoz.xml';
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('izvoz.xml nije dostupan. Najpre generiši XML.');
}

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="izvoz.xml"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
header('Content-Length: ' . (string) filesize($path));
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    readfile($path);
}
exit;
