<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/News.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/TicketMessage.php';
require_once __DIR__ . '/../models/Alert.php';

function assertCommunication(bool $condition, string $message): void
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

$adminId = (int)$db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$userId = (int)$db->query("SELECT id FROM users WHERE role = 'user' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$newsIds = [];
$ticketId = 0;
$suffix = bin2hex(random_bytes(5));

if ($adminId <= 0 || $userId <= 0) {
    throw new RuntimeException('Un administrateur et un utilisateur actif sont nécessaires.');
}

try {
    $newsId = News::create([
        'title' => 'Test communication ' . $suffix,
        'summary' => 'Résumé de contrôle',
        'content' => 'Contenu de contrôle du studio éditorial.',
        'category' => 'service',
        'audience' => 'staff',
        'is_published' => 0,
        'is_pinned' => 1,
        'author_id' => $adminId,
    ]);
    $newsIds[] = $newsId;
    assertCommunication(empty(News::findById($newsId)['published_at']), 'Le brouillon possède une date de publication.');

    News::setPublished($newsId, true, $adminId);
    $published = News::findById($newsId);
    assertCommunication(!empty($published['published_at']), 'La publication n’a pas été datée.');
    assertCommunication((int)$published['is_published'] === 1, 'L’actualité n’est pas publiée.');

    $copyId = News::duplicateById($newsId, $adminId);
    $newsIds[] = $copyId;
    $copy = News::findById($copyId);
    assertCommunication((int)$copy['is_published'] === 0 && (int)$copy['is_pinned'] === 0, 'La copie ne revient pas en brouillon neutre.');

    $ticketId = Ticket::create($userId, 'Test support ' . $suffix, 'Premier message utilisateur.', 'high', 'technical');
    $ticket = Ticket::findById($ticketId);
    assertCommunication($ticket['category'] === 'technical', 'La catégorie du ticket est incorrecte.');
    assertCommunication(empty($ticket['last_message_admin_id']), 'Le ticket neuf n’attend pas le support.');

    Ticket::assign($ticketId, $adminId);
    TicketMessage::addAdminMessage($ticketId, $adminId, 'Réponse de contrôle.');
    $ticket = Ticket::findById($ticketId);
    assertCommunication((int)$ticket['assigned_admin_id'] === $adminId, 'Le responsable du ticket est incorrect.');
    assertCommunication(!empty($ticket['first_response_at']), 'La première réponse n’est pas datée.');
    assertCommunication(!empty($ticket['last_message_admin_id']), 'Le ticket ne passe pas en attente utilisateur.');

    TicketMessage::addUserMessage($ticketId, $userId, 'Complément utilisateur.');
    $ticket = Ticket::findById($ticketId);
    assertCommunication(empty($ticket['last_message_admin_id']), 'Le ticket ne repasse pas en attente support.');

    Ticket::close($ticketId, $adminId);
    assertCommunication(Ticket::findById($ticketId)['status'] === 'closed', 'La fermeture du ticket a échoué.');
    Ticket::reopen($ticketId);
    assertCommunication(Ticket::findById($ticketId)['status'] === 'open', 'La réouverture du ticket a échoué.');

    Alert::getDashboardStats();
    Alert::countWorkQueue(null, null, null, null, 'unassigned', $adminId, 'stale');
} finally {
    if ($ticketId > 0) {
        $db->prepare('DELETE FROM tickets WHERE id = ?')->execute([$ticketId]);
    }
    if ($newsIds !== []) {
        $placeholders = implode(',', array_fill(0, count($newsIds), '?'));
        $db->prepare("DELETE FROM news WHERE id IN ({$placeholders})")->execute($newsIds);
    }
    $modelDb->setValue(null, null);
}

echo "CommunicationWorkflowTest: OK\n";
