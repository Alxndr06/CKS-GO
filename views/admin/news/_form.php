<?php
require_once __DIR__ . '/../../partials/header.php';

$isEdit = ($newsFormMode ?? 'create') === 'edit';
$allowedCategories = is_array($allowedCategories ?? null) ? $allowedCategories : [];
$allowedAudiences = is_array($allowedAudiences ?? null) ? $allowedAudiences : [];
$categoryLabels = ['general' => 'Information', 'shop' => 'Boutique', 'stock' => 'Stocks', 'event' => 'Événement', 'service' => 'Service'];
$audienceLabels = ['all' => 'Tout le monde', 'authenticated' => 'Utilisateurs connectés', 'staff' => 'Équipe uniquement'];
$currentCategory = (string)($news['category'] ?? 'general');
$currentAudience = (string)($news['audience'] ?? 'all');
$published = $isEdit ? !empty($news['is_published']) : false;
?>

<main class="main_part comms_page news_editor_page">
    <header class="comms_page_header is_compact">
        <div>
            <a class="comms_back_link" href="index.php?controller=admin&action=news">← Retour aux actualités</a>
            <h1><?= $isEdit ? 'Modifier la publication' : 'Nouvelle actualité' ?></h1>
            <p><?= $isEdit ? 'Ajuste le contenu et sa visibilité.' : 'Rédige une information claire, courte et immédiatement compréhensible.' ?></p>
        </div>
        <span class="comms_badge <?= $published ? 'is_success' : 'is_neutral' ?>" data-news-state-label>
            <?= $published ? 'Publiée' : 'Brouillon' ?>
        </span>
    </header>

    <form method="POST" action="index.php?controller=admin&action=<?= $isEdit ? 'updateNews' : 'storeNews' ?>" class="news_editor" data-news-editor>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$news['id'] ?>"><?php endif; ?>

        <div class="news_editor_main">
            <section class="news_editor_panel">
                <div class="news_editor_section_head">
                    <span>01</span><div><h2>Le message</h2><p>Ces éléments seront visibles sur la page d’accueil.</p></div>
                </div>

                <label class="news_editor_field">
                    <span>Titre <small><b data-count-for="news-title">0</b>/160</small></span>
                    <input id="news-title" type="text" name="title" maxlength="160" required value="<?= htmlspecialchars((string)($news['title'] ?? '')) ?>">
                    <em>Une phrase courte qui donne immédiatement l’information.</em>
                </label>

                <label class="news_editor_field">
                    <span>Résumé <small><b data-count-for="news-summary">0</b>/280</small></span>
                    <textarea id="news-summary" name="summary" rows="3" maxlength="280"><?= htmlspecialchars((string)($news['summary'] ?? '')) ?></textarea>
                    <em>Facultatif. S’il reste vide, un extrait du contenu sera utilisé.</em>
                </label>

                <label class="news_editor_field">
                    <span>Contenu <small><b data-count-for="news-content">0</b>/12000</small></span>
                    <textarea id="news-content" name="content" rows="13" maxlength="12000" required><?= htmlspecialchars((string)($news['content'] ?? '')) ?></textarea>
                    <em>Les retours à la ligne sont conservés. Aucun code HTML n’est nécessaire.</em>
                </label>
            </section>

            <section class="news_editor_panel">
                <div class="news_editor_section_head">
                    <span>02</span><div><h2>Classement et visibilité</h2><p>Détermine où et pour qui l’information apparaît.</p></div>
                </div>
                <div class="news_editor_field_grid">
                    <label class="news_editor_field">
                        <span>Rubrique</span>
                        <select name="category" id="news-category">
                            <?php foreach ($allowedCategories as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $currentCategory === $item ? 'selected' : '' ?>><?= htmlspecialchars($categoryLabels[$item] ?? $item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="news_editor_field">
                        <span>Audience</span>
                        <select name="audience">
                            <?php foreach ($allowedAudiences as $item): ?>
                                <option value="<?= htmlspecialchars($item) ?>" <?= $currentAudience === $item ? 'selected' : '' ?>><?= htmlspecialchars($audienceLabels[$item] ?? $item) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="news_editor_toggles">
                    <label><input type="checkbox" name="is_pinned" <?= !empty($news['is_pinned']) ? 'checked' : '' ?>><span><strong>Mettre à la une</strong><small>Place cette actualité avant les autres.</small></span></label>
                    <label><input type="checkbox" name="is_published" <?= $published ? 'checked' : '' ?> data-news-published><span><strong>Publier maintenant</strong><small>Décoche pour conserver un brouillon invisible.</small></span></label>
                </div>
            </section>
        </div>

        <aside class="news_editor_preview">
            <span class="section_kicker">Aperçu accueil</span>
            <article>
                <div><span data-news-preview-category><?= htmlspecialchars($categoryLabels[$currentCategory] ?? 'Information') ?></span><time>Aujourd’hui</time></div>
                <h2 data-news-preview-title><?= htmlspecialchars((string)($news['title'] ?? 'Titre de votre actualité')) ?></h2>
                <p data-news-preview-summary><?= htmlspecialchars((string)($news['summary'] ?? 'Le résumé apparaîtra ici pendant la rédaction.')) ?></p>
            </article>
            <div class="news_editor_submit">
                <button type="submit"><?= $isEdit ? 'Enregistrer les modifications' : 'Créer l’actualité' ?></button>
                <a href="index.php?controller=admin&action=news">Annuler</a>
            </div>
        </aside>
    </form>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
