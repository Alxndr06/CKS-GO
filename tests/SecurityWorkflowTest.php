<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Log.php';

function securityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$databaseName = trim((string)getenv('CKSGO_TEST_DB'));
if ($databaseName === '' || $databaseName === DB_NAME) {
    throw new RuntimeException('CKSGO_TEST_DB doit cibler une base de test distincte de la base active.');
}

$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . $databaseName . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$modelDb = new ReflectionProperty(Model::class, 'db');
$modelDb->setAccessible(true);
$modelDb->setValue(null, $db);

$suffix = strtolower(bin2hex(random_bytes(6)));
$userId = 0;
$logId = 0;

try {
    securityAssert(validatePasswordPolicy('TropCourt123!') !== null, 'Un mot de passe de moins de 15 caractères est accepté.');
    securityAssert(validatePasswordPolicy('password123456') !== null, 'Un mot de passe courant est accepté.');
    securityAssert(validatePasswordPolicy(str_repeat('a', 129)) !== null, 'Un mot de passe excessivement long est accepté.');
    securityAssert(validatePasswordPolicy('Phrase de passe sûre 2026', 'Phrase différente 2026') !== null, 'Une confirmation différente est acceptée.');
    securityAssert(validatePasswordPolicy('Phrase de passe sûre 2026', 'Phrase de passe sûre 2026') === null, 'Une phrase de passe valide est refusée.');

    $password = 'Phrase de passe unique ' . $suffix;
    $passwordHash = hashPassword($password);
    securityAssert(password_verify($password, $passwordHash), 'Le hash sécurisé ne peut pas être vérifié.');
    securityAssert(($hashInfo = password_get_info($passwordHash))['algoName'] === 'argon2id', 'Argon2id n’est pas utilisé alors qu’il est disponible.');
    securityAssert(!passwordHashNeedsUpgrade($passwordHash), 'Un hash Argon2id fraîchement créé est considéré obsolète.');

    $oversizedSearch = str_repeat('é', 150);
    securityAssert(getPasswordLength(normalizeSearchQuery($oversizedSearch)) === 100, 'Une recherche excessive n’est pas bornée côté serveur.');
    securityAssert(normalizeSearchQuery("  produit test  ") === 'produit test', 'La normalisation d’une recherche valide est incorrecte.');

    $fallback = 'index.php?controller=home&action=index';
    securityAssert(
        sanitizeInternalRedirect('index.php?controller=shop&action=cart#summary') === 'index.php?controller=shop&action=cart#summary',
        'Une redirection interne valide est altérée.'
    );
    foreach (['https://evil.example/', '//evil.example/path', '/index.php?controller=admin', "index.php\r\nLocation: https://evil.example"] as $unsafeRedirect) {
        securityAssert(sanitizeInternalRedirect($unsafeRedirect, $fallback) === $fallback, 'Une redirection externe ou injectée est acceptée.');
    }

    securityAssert(userHasPermission('support.manage', 'assistant'), 'Le rôle assistant a perdu son accès support.');
    securityAssert(!userHasPermission('billing.manage', 'assistant'), 'Le rôle assistant dispose indûment des paiements.');
    securityAssert(userHasPermission('settings.manage', 'admin'), 'Le rôle admin n’a pas tous les droits.');
    securityAssert(!userHasPermission('settings.manage', 'responsable'), 'Le rôle responsable dispose indûment des réglages système.');

    $activationToken = bin2hex(random_bytes(32));
    $created = User::create([
        'username' => '__security_' . $suffix,
        'firstname' => 'Sécurité',
        'lastname' => 'Test',
        'email' => '__security_' . $suffix . '@example.test',
        'unit' => 'mineurs',
        'password_hash' => $passwordHash,
        'is_active' => 1,
        'is_locked' => 0,
        'activation_token' => $activationToken,
        'email_verified_at' => date('Y-m-d H:i:s'),
    ]);
    securityAssert($created, 'Le compte de test sécurité n’a pas été créé.');
    $userId = (int)$db->lastInsertId();

    $storedActivationToken = (string)$db->query('SELECT activation_token FROM users WHERE id = ' . $userId)->fetchColumn();
    securityAssert($storedActivationToken === hash('sha256', $activationToken), 'Le jeton d’activation est stocké en clair.');
    securityAssert((int)(User::findByActivationToken($activationToken)['id'] ?? 0) === $userId, 'Le jeton d’activation valide n’est pas reconnu.');
    securityAssert(User::markEmailAsVerified($userId), 'La validation de l’adresse e-mail a échoué.');
    securityAssert(User::findByActivationToken($activationToken) === null, 'Le jeton d’activation reste utilisable après validation.');

    $loginAttemptState = [];
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $loginAttemptState = User::registerFailedLoginAttempt($userId, 5, 15, 15);
    }
    securityAssert(!empty($loginAttemptState['is_locked']), 'Cinq échecs de connexion ne verrouillent pas temporairement le compte.');
    securityAssert(User::isLoginTemporarilyLocked(User::findByID($userId) ?: []), 'Le verrouillage de connexion n’est pas persisté.');
    User::resetFailedLoginAttempts($userId);
    securityAssert(!User::isLoginTemporarilyLocked(User::findByID($userId) ?: []), 'Le verrouillage de connexion ne peut pas être réinitialisé.');

    $reset = User::createPasswordResetTokenForUser($userId, 60, 300);
    securityAssert(!empty($reset['issued']) && strlen((string)$reset['token']) === 64, 'Le jeton de réinitialisation n’est pas aléatoire sur 256 bits.');
    $rawResetToken = (string)$reset['token'];
    $storedResetHash = (string)$db->query('SELECT password_reset_token_hash FROM users WHERE id = ' . $userId)->fetchColumn();
    securityAssert($storedResetHash === hash('sha256', $rawResetToken), 'Le jeton de réinitialisation est stocké en clair.');
    securityAssert(User::findByPasswordResetToken($rawResetToken) !== null, 'Un jeton valide n’est pas reconnu.');

    $cooldown = User::createPasswordResetTokenForUser($userId, 60, 300);
    securityAssert(empty($cooldown['issued']) && ($cooldown['reason'] ?? '') === 'cooldown', 'Le cooldown de réinitialisation est contournable.');

    $db->prepare('UPDATE users SET password_reset_expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE id = ?')->execute([$userId]);
    securityAssert(User::findByPasswordResetToken($rawResetToken) === null, 'Un jeton expiré reste utilisable.');

    $db->prepare('UPDATE users SET password_reset_requested_at = NULL WHERE id = ?')->execute([$userId]);
    $secondReset = User::createPasswordResetTokenForUser($userId, 60, 0);
    securityAssert(!empty($secondReset['issued']), 'Un second jeton de test n’a pas été émis.');
    securityAssert(User::updatePasswordById($userId, hashPassword($password . ' renouvelée')), 'La mise à jour du mot de passe a échoué.');
    securityAssert(User::findByPasswordResetToken((string)$secondReset['token']) === null, 'Un jeton reste valide après changement du mot de passe.');

    $invalidColumnRejected = false;
    try {
        User::checkUnicity('role OR 1=1', 'admin');
    } catch (InvalidArgumentException) {
        $invalidColumnRejected = true;
    }
    securityAssert($invalidColumnRejected, 'La liste blanche des colonnes SQL est contournable.');

    $variantId = (int)$db->query('SELECT id FROM product_variants ORDER BY id ASC LIMIT 1')->fetchColumn();
    $negativeStockRejected = false;
    try {
        $db->prepare('UPDATE product_variants SET stock_quantity = -1 WHERE id = ?')->execute([$variantId]);
    } catch (PDOException) {
        $negativeStockRejected = true;
    }
    securityAssert($negativeStockRejected, 'La base accepte un stock négatif malgré la contrainte d’intégrité.');

    $_SERVER['REMOTE_ADDR'] = '192.0.2.88';
    $_SERVER['HTTP_USER_AGENT'] = 'CKS-GO-Security-Test/1.0';
    $_SERVER['CKSGO_REQUEST_ID'] = str_repeat('a', 32);
    securityAssert(Log::admin($userId, 'security_audit_test', 'Traçabilité automatisée'), 'Le journal d’audit n’enregistre pas l’événement.');
    $logId = (int)$db->lastInsertId();
    $logRow = $db->query('SELECT ip_address, user_agent, request_id FROM logs WHERE id = ' . $logId)->fetch(PDO::FETCH_ASSOC) ?: [];
    securityAssert(($logRow['ip_address'] ?? '') === '192.0.2.88', 'L’adresse IP n’est pas journalisée.');
    securityAssert(($logRow['user_agent'] ?? '') === 'CKS-GO-Security-Test/1.0', 'Le client HTTP n’est pas journalisé.');
    securityAssert(($logRow['request_id'] ?? '') === str_repeat('a', 32), 'L’identifiant de requête n’est pas journalisé.');

    echo "SecurityWorkflowTest: OK\n";
} finally {
    if ($logId > 0) {
        $db->prepare('DELETE FROM logs WHERE id = ?')->execute([$logId]);
    }
    if ($userId > 0) {
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }

    $modelDb->setValue(null, null);
}
