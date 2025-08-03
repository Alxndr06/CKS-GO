<?php
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../../helpers/functions.php';
?>

<main class="main_part dashboard">
    <h2>Liste des utilisateurs</h2>

    <?php if (!empty($users)): ?>
        <div class="user-table-wrapper">
            <table class="user-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Pseudo</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Actif</th>
                    <th>Créé le</th>
                    <th>Note</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['lastname']) ?></td>
                        <td><?= htmlspecialchars($user['firstname']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td><?= $user['is_active'] ? 'Oui' : 'Non' ?></td>
                        <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                        <td><?= htmlspecialchars($user['note']) ?></td>
                        <td>
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
