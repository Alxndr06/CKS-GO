<?php
$root = realpath(__DIR__ . '/..');
$requestPath = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'));
$normalizedPath = str_replace('\\', '/', $requestPath);

if (str_contains($normalizedPath, "\0") || str_contains($normalizedPath, '..')) {
    http_response_code(400);
    exit('Requête invalide.');
}

if ($normalizedPath === '/' || $normalizedPath === '/index.php') {
    require $root . '/index.php';
    return;
}

$candidate = realpath($root . '/' . ltrim($normalizedPath, '/'));
$normalizedRoot = rtrim(str_replace('\\', '/', (string)$root), '/') . '/';
$normalizedCandidate = $candidate !== false ? str_replace('\\', '/', $candidate) : '';

if (
    $candidate !== false
    && is_file($candidate)
    && str_starts_with($normalizedCandidate, $normalizedRoot . 'public/')
) {
    return false;
}

http_response_code(404);
exit('Page introuvable.');
