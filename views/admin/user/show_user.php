<?php
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/../../../helpers/functions.php';
?>

<main class="main_part dashboard">
    <?php if (!empty($user)):
        $user['fullname'] = $user['firstname'] . ' ' . $user['lastname'];
        ?>
        <h2>Fiche utilisateur de <?= htmlspecialchars($user['fullname']) ?></h2>

        <table class="user-table">
            <tbody>
            <tr><th>ID</th><td><?= (int)$user['id'] ?></td></tr>
            <tr><th>Pseudo</th><td><?= htmlspecialchars($user['username']) ?></td></tr>
            <tr><th>Nom</th><td><?= htmlspecialchars($user['lastname']) ?></td></tr>
            <tr><th>Prénom</th><td><?= htmlspecialchars($user['firstname']) ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
            <tr><th>Rôle</th><td><?= htmlspecialchars($user['role']) ?></td></tr>
            <tr><th>Actif</th><td><?= $user['is_active'] ? 'Oui' : 'Non' ?></td></tr>
            <tr><th>Créé le</th><td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td></tr>
            <tr><th>Note</th><td><?= htmlspecialchars($user['note']) ?></td></tr>
            <tr><th>Gérer</th><td>
                    <form method="GET" action="/user/delete/<?= $user['id'] ?>" class="inline-form">
                        <button type="submit" class="user_action_btn" title="Modifier">✏️</button>
                    </form>

                    <form method="POST" action="../../../index.php" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="id"  value="<?= (int)$user['id'] ?>">
                        <button type="submit" class="user_action_btn" title="<?= $user['locked'] ? 'Déverrouiller' : 'Verrouiller' ?>">
                            <?= $user['locked'] ? '🔓' : '🔒' ?>
                        </button>
                    </form>

                    <form method="POST" action="#<?= $user['id'] ?>" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="id"  value="<?= (int)$user['id'] ?>">
                        <button type="submit" class="user_action_btn" title="Facturer">💶</button>
                    </form>

                    <form method="POST" action="../../../index.php" class="inline-form" onsubmit="return confirm('Confirmer la suppression de <?= htmlspecialchars($user['username'], ENT_QUOTES) ?> ?')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="id"  value="<?= (int)$user['id'] ?>">
                        <button type="submit" class="user_action_btn" title="Supprimer">🗑️</button>
                    </form>
                </td></tr>
            </tbody>
        </table>

    <?php else: ?>
        <p>Utilisateur introuvable.</p>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>

