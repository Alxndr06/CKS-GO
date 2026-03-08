<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
?>

<main class="main_part dashboard">
    <h2>Liste des utilisateurs</h2>

    <?php if (!empty($users)): ?>
        <div class="user-table-wrapper">
            <a href="#">➕</a>
            <table class="user-table">
                <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-username">Pseudo</th>
                    <th class="col-lastname">Nom</th>
                    <th class="col-firstname">Prénom</th>
                    <th class="col-email">Email</th>
                    <th class="col-role">Rôle</th>
                    <th class="col-active">Actif</th>
                    <th class="col-created">Créé le</th>
                    <th class="col-note">Note</th>
                    <th class="col-actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr class="user-row" data-href="index.php?controller=user&action=show&id=<?= (int)$user['id'] ?>">
                        <td class="col-id"><?= htmlspecialchars($user['id']) ?></td>
                        <td class="col-username"><?= htmlspecialchars($user['username']) ?></td>
                        <td class="col-lastname"><?= htmlspecialchars($user['lastname']) ?></td>
                        <td class="col-firstname"><?= htmlspecialchars($user['firstname']) ?></td>
                        <td class="col-email"><?= htmlspecialchars($user['email']) ?></td>
                        <td class="col-role"><?= htmlspecialchars($user['role']) ?></td>
                        <td class="col-active"><?= $user['is_active'] ? 'Oui' : 'Non' ?></td>
                        <td class="col-created"><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td class="col-note"><?= htmlspecialchars($user['note']) ?></td>
                        <td class="col-actions">
                            <form method="GET" action="/user/delete/<?= $user['id'] ?>" class="inline-form">
                                <button type="submit" class="user_action_btn" title="Modifier">✏️</button>
                            </form>

                            <form method="POST" action="index.php?controller=user&action=lockAction&id=<?= $user['id'] ?>" class="inline-form">
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

                            <form method="POST" action="index.php?controller=user&action=deleteAction&id=<?= $user['id'] ?>" class="inline-form" onsubmit="return confirm('Confirmer la suppression de <?= htmlspecialchars($user['username'], ENT_QUOTES) ?> ?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="id"  value="<?= (int)$user['id'] ?>">
                                <button type="submit" class="user_action_btn" title="Supprimer">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>Aucun utilisateur trouvé.</p>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
