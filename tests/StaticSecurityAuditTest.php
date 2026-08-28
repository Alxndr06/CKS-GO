<?php

function staticSecurityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = realpath(__DIR__ . '/..');
$viewIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/views'));

foreach ($viewIterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    $source = (string)file_get_contents($file->getPathname());

    if (!str_starts_with($relativePath, 'views/emails/')) {
        staticSecurityAssert(
            preg_match('/\son[a-z]+\s*=/i', $source) !== 1,
            $relativePath . ' contient un gestionnaire d’événement inline incompatible avec la CSP.'
        );
        staticSecurityAssert(
            preg_match('/\sstyle\s*=/i', $source) !== 1,
            $relativePath . ' contient un style inline incompatible avec la CSP.'
        );
        staticSecurityAssert(
            preg_match('/<script\b(?![^>]*\bsrc\s*=)/i', $source) !== 1,
            $relativePath . ' contient un script inline incompatible avec la CSP.'
        );
    }

    preg_match_all('/<form\b(?:(?!<\/form>).)*<\/form>/is', $source, $forms);
    foreach ($forms[0] as $form) {
        if (preg_match('/\bmethod\s*=\s*["\']post["\']/i', $form) !== 1) {
            continue;
        }

        staticSecurityAssert(
            preg_match('/\bname\s*=\s*["\']csrf_token["\']/i', $form) === 1,
            $relativePath . ' contient un formulaire POST sans jeton CSRF.'
        );
    }
}

$applicationDirectories = ['controllers', 'core', 'helpers', 'models', 'services'];
$dangerousPattern = '/\b(eval|assert|shell_exec|passthru|proc_open|popen|system|unserialize)\s*\(/i';

foreach ($applicationDirectories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory));

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $source = (string)file_get_contents($file->getPathname());
        staticSecurityAssert(
            preg_match($dangerousPattern, $source) !== 1,
            $relativePath . ' utilise une fonction d’exécution ou de désérialisation dangereuse.'
        );
    }
}

$helperSource = (string)file_get_contents($root . '/helpers/functions.php');
staticSecurityAssert(!str_contains($helperSource, "'unsafe-inline'"), 'La politique CSP contient encore unsafe-inline.');

foreach ([
    "ini_set('session.use_strict_mode', '1')",
    "ini_set('session.use_only_cookies', '1')",
    "ini_set('session.use_trans_sid', '0')",
    "'httponly' => true",
    "'samesite' => 'Lax'",
] as $requiredSessionControl) {
    staticSecurityAssert(
        str_contains($helperSource, $requiredSessionControl),
        'Protection de session absente : ' . $requiredSessionControl
    );
}

$schemaSource = (string)file_get_contents($root . '/database/schema.sql');
$requiredConstraints = [
    'chk_product_variants_stock_nonnegative',
    'chk_product_variants_price_nonnegative',
    'chk_cart_items_quantity_positive',
    'chk_order_items_quantity_positive',
    'chk_order_items_price_nonnegative',
    'chk_order_items_line_source',
    'chk_payments_amount_nonnegative',
    'chk_refunds_quantity_positive',
    'chk_refunds_amount_nonnegative',
    'chk_alert_refunds_quantity_positive',
    'chk_alert_refunds_amount_nonnegative',
];

foreach ($requiredConstraints as $constraint) {
    staticSecurityAssert(
        str_contains($schemaSource, $constraint),
        'Contrainte SQL de sécurité absente : ' . $constraint
    );
}

staticSecurityAssert(
    preg_match('/fk_order_items_variant[^\n]+ON DELETE SET NULL/i', $schemaSource) !== 1,
    'La référence historique d’une variante commandée ne doit pas être effacée par une suppression physique.'
);

$composerLock = json_decode((string)file_get_contents($root . '/composer.lock'), true);
staticSecurityAssert(is_array($composerLock), 'Le verrou Composer est absent ou invalide.');
$phpMailerPackage = null;

foreach (($composerLock['packages'] ?? []) as $package) {
    if (($package['name'] ?? '') === 'phpmailer/phpmailer') {
        $phpMailerPackage = $package;
        break;
    }
}

staticSecurityAssert(is_array($phpMailerPackage), 'PHPMailer n’est pas verrouillé par Composer.');
$phpMailerVersion = ltrim((string)($phpMailerPackage['version'] ?? ''), 'v');
staticSecurityAssert(
    version_compare($phpMailerVersion, '7.1.0', '>='),
    'La version de PHPMailer doit intégrer les durcissements de la branche 7.1.'
);

$shopViewSource = (string)file_get_contents($root . '/views/shop/index.php');
staticSecurityAssert(
    !str_contains($shopViewSource, 'variant-experiment'),
    'La boutique référence encore le prototype de variantes.'
);
staticSecurityAssert(
    preg_match('/data-shop-variant-toggle\s+aria-expanded="false"/', $shopViewSource) === 1,
    'Le volet variantes doit être fermé par défaut.'
);

echo "StaticSecurityAuditTest: OK\n";
