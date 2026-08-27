<?php
require_once __DIR__ . '/../../partials/header.php';

$newsList = is_array($newsList ?? null) ? $newsList : [];
$newsStats = is_array($newsStats ?? null) ? $newsStats : [];
$allowedCategories = is_array($allowedCategories ?? null) ? $allowedCategories : [];
$q = (string)($q ?? '');
$state = (string)($state ?? '');
$category = (string)($category ?? '');
$page = (int)($page ?? 1);
$totalPages = (int)($totalPages ?? 1);
$totalNews = (int)($totalNews ?? count($newsList));

$categoryLabels = [
    'general' => 'Information',
    'shop' => 'Boutique',
    'stock' => 'Stocks',
    'event' => 'Événement',
    'service' => 'Service',
];
$audienceLabels = [
    'all' => 'Tout le monde',
    'authenticated' => 'Utilisateurs connectés',
    'staff' => 'Équipe uniquement',
];
$paginationTemplate = 'index.php?' . http_build_query([
    'controller' => 'admin',
    'action' => 'news',
    'page' => '__PAGE__',
    'q' => $q,
    'state' => $state,
    'category' => $category,
]);
?>

<main class="main_part comms_page news_studio_page">
    <header class="comms_page_header">
        <div>
            <span class="section_kicker">Actualités</span>
            <h1>Studio de publication</h1>
            <p>Prépare, relis et publie les informations affichées sur l’accueil.</p>
        </div>
        <a class="comms_primary_action" href="index.php?controller=admin&action=createNews">
            <?= renderUiIcon('news') ?> Nouvelle actualité
        </a>
    </header>

    <nav class="comms_stat_nav" aria-label="États des actualités">
        <a class="<?= $state === '' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=news">
            <span>Toutes</span><strong><?= (int)($newsStats['total'] ?? 0) ?></strong>
        </a>
        <a class="<?= $state === 'published' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=news&state=published">
            <span>Publiées</span><strong><?= (int)($newsStats['published'] ?? 0) ?></strong>
        </a>
        <a class="<?= $state === 'draft' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=news&state=draft">
            <span>Brouillons</span><strong><?= (int)($newsStats['drafts'] ?? 0) ?></strong>
        </a>
        <a class="<?= $state === 'pinned' ? 'is_active' : '' ?>" href="index.php?controller=admin&action=news&state=pinned">
            <span>À la une</span><strong><?= (int)($newsStats['pinned'] ?? 0) ?></strong>
        </a>
    </nav>

    <section class="comms_filter_bar">
        <form method="GET" action="index.php" class="comms_filter_form" data-auto-filter-form>
            <input type="hidden" name="controller" value="admin">
            <input type="hidden" name="action" value="news">
            <label class="comms_search_field">
                <span>Rechercher</span>
                <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" aria-label="Rechercher dans les actualités" data-auto-filter>
            </label>
            <label>
                <span>État</span>
                <select name="state" data-auto-filter>
                    <option value="">Tous</option>
                    <option value="published" <?= $state === 'published' ? 'selected' : '' ?>>Publiées</option>
                    <option value="draft" <?= $state === 'draft' ? 'selected' : '' ?>>Brouillons</option>
                    <option value="pinned" <?= $state === 'pinned' ? 'selected' : '' ?>>À la une</option>
                </select>
            </label>
            <label>
                <span>Rubrique</span>
                <select name="category" data-auto-filter>
                    <option value="">Toutes</option>
                    <?php foreach ($allowedCategories as $item): ?>
                        <option value="<?= htmlspecialchars($item) ?>" <?= $category === $item ? 'selected' : '' ?>>
                            <?= htmlspecialchars($categoryLabels[$item] ?? $item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Filtrer</button>
            <?php if ($q !== '' || $state !== '' || $category !== ''): ?>
                <a href="index.php?controller=admin&action=news">Effacer</a>
            <?php endif; ?>
        </form>
        <span class="comms_result_count"><?= $totalNews ?> résultat<?= $totalNews > 1 ? 's' : '' ?></span>
    </section>

    <?php if (empty($newsList)): ?>
        <section class="comms_empty_state">
            <?= renderUiIcon('news') ?>
            <h2>Aucune actualité ici</h2>
            <p>Modifie les filtres ou prépare une nouvelle publication.</p>
            <a class="comms_primary_action" href="index.php?controller=admin&action=createNews">Créer une actualité</a>
        </section>
    <?php else: ?>
        <section class="news_studio_grid" aria-label="Actualités">
            <?php foreach ($newsList as $news): ?>
                <?php
                $id = (int)$news['id'];
                $published = (int)$news['is_published'] === 1;
                $pinned = (int)$news['is_pinned'] === 1;
                $excerpt = trim((string)($news['summary'] ?? ''));
                if ($excerpt === '') {
                    $excerpt = mb_strimwidth(trim((string)$news['content']), 0, 220, '…');
                }
                $displayDate = $published ? ($news['published_at'] ?? null) : ($news['updated_at'] ?? $news['created_at']);
                $author = trim((string)($news['author_name'] ?? '')) ?: 'Équipe CKS GO';
                ?>
                <article class="news_studio_card <?= $published ? 'is_published' : 'is_draft' ?> <?= $pinned ? 'is_pinned' : '' ?>">
                    <div class="news_studio_card_top">
                        <div class="news_studio_badges">
                            <span class="comms_badge <?= $published ? 'is_success' : 'is_neutral' ?>"><?= $published ? 'Publiée' : 'Brouillon' ?></span>
                            <?php if ($pinned): ?><span class="comms_badge is_featured">À la une</span><?php endif; ?>
                            <span class="comms_badge is_category"><?= htmlspecialchars($categoryLabels[$news['category']] ?? $news['category']) ?></span>
                        </div>
                        <span class="news_studio_id">#<?= $id ?></span>
                    </div>

                    <div class="news_studio_card_body">
                        <h2><?= htmlspecialchars((string)$news['title']) ?></h2>
                        <p><?= nl2br(htmlspecialchars($excerpt)) ?></p>
                    </div>

                    <dl class="news_studio_meta">
                        <div><dt>Audience</dt><dd><?= htmlspecialchars($audienceLabels[$news['audience']] ?? $news['audience']) ?></dd></div>
                        <div><dt><?= $published ? 'Publication' : 'Dernière édition' ?></dt><dd><?= $displayDate ? date('d/m/Y à H:i', strtotime((string)$displayDate)) : 'Non datée' ?></dd></div>
                        <div><dt>Auteur</dt><dd><?= htmlspecialchars($author) ?></dd></div>
                    </dl>

                    <div class="news_studio_actions">
                        <a href="index.php?controller=admin&action=editNews&id=<?= $id ?>">Modifier</a>
                        <form method="POST" action="index.php?controller=admin&action=toggleNewsPublication">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="published" value="<?= $published ? 0 : 1 ?>">
                            <button type="submit"><?= $published ? 'Dépublier' : 'Publier' ?></button>
                        </form>
                        <form method="POST" action="index.php?controller=admin&action=duplicateNews">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <button type="submit">Dupliquer</button>
                        </form>
                        <form method="POST" action="index.php?controller=admin&action=deleteNews" data-confirm-message="Supprimer définitivement cette actualité ?">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <button type="submit" class="is_danger">Supprimer</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <?php if ($totalPages > 1): ?>
            <?php
            $paginationCurrentPage = $page;
            $paginationTotalPages = $totalPages;
            $paginationLabel = 'Pagination des actualités';
            $paginationPageTemplate = $paginationTemplate;
            require __DIR__ . '/../../partials/admin_pagination.php';
            ?>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
