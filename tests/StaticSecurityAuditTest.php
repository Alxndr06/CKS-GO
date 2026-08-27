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

echo "StaticSecurityAuditTest: OK\n";
