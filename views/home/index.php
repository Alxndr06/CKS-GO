<?php
require_once __DIR__ . '/../partials/header.php';

$latestNews = $latestNews ?? [];
$latestProducts = $latestProducts ?? [];
$newsCount = count($latestNews);
$featuredNews = $latestNews[0] ?? null;
$secondaryNews = array_slice($latestNews, 1);

$formatDate = static function (?string $value): string {
    if (empty($value)) {
        return '';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('d/m/Y', $timestamp);
};
?>

<main class="main_part home_page home_page_redesign home_news_only">
    <section class="home_v2_news_section home_news_focus">
        <header class="home_v2_section_heading home_news_focus_heading">
            <div>
                <span class="section_kicker">Actualités CKS GO</span>
                <h1>Les dernières nouvelles</h1>
                <p>Les informations importantes de l'équipe, simplement.</p>
            </div>
            <span class="home_v2_news_count">
                <?= $newsCount ?> publication<?= $newsCount > 1 ? 's' : '' ?>
            </span>
        </header>

        <?php if ($featuredNews): ?>
            <?php
            $featuredTitle = trim((string)($featuredNews['title'] ?? 'Annonce'));
            $featuredContent = trim((string)($featuredNews['content'] ?? ''));
            $featuredSummary = trim((string)($featuredNews['summary'] ?? ''));
            $featuredExcerpt = $featuredSummary !== '' ? $featuredSummary : mb_strimwidth($featuredContent, 0, 420, '…');
            $featuredDate = $featuredNews['published_at'] ?? $featuredNews['created_at'] ?? '';
            ?>
            <div class="home_v2_news_layout">
                <article class="home_v2_featured_news">
                    <div class="home_v2_featured_topline">
                        <span class="home_v2_news_badge">À la une</span>
                        <time datetime="<?= htmlspecialchars((string)$featuredDate) ?>">
                            <?= htmlspecialchars($formatDate($featuredDate)) ?>
                        </time>
                    </div>
                    <h2><?= htmlspecialchars($featuredTitle !== '' ? $featuredTitle : 'Annonce') ?></h2>
                    <p><?= nl2br(htmlspecialchars($featuredExcerpt !== '' ? $featuredExcerpt : 'Aucun contenu disponible.')) ?></p>
                    <span class="home_v2_featured_mark" aria-hidden="true">Info</span>
                </article>

                <div class="home_v2_news_list">
                    <?php if (!empty($secondaryNews)): ?>
                        <?php foreach ($secondaryNews as $news): ?>
                            <?php
                            $newsTitle = trim((string)($news['title'] ?? 'Annonce'));
                            $newsContent = trim((string)($news['content'] ?? ''));
                            $newsSummary = trim((string)($news['summary'] ?? ''));
                            $newsExcerpt = $newsSummary !== '' ? $newsSummary : mb_strimwidth($newsContent, 0, 150, '…');
                            $newsDate = $news['published_at'] ?? $news['created_at'] ?? '';
                            ?>
                            <article class="home_v2_news_item">
                                <span class="home_v2_news_item_dot" aria-hidden="true"></span>
                                <div>
                                    <div class="home_v2_news_item_topline">
                                        <h2><?= htmlspecialchars($newsTitle !== '' ? $newsTitle : 'Annonce') ?></h2>
                                        <time datetime="<?= htmlspecialchars((string)$newsDate) ?>">
                                            <?= htmlspecialchars($formatDate($newsDate)) ?>
                                        </time>
                                    </div>
                                    <p><?= htmlspecialchars($newsExcerpt !== '' ? $newsExcerpt : 'Aucun contenu disponible.') ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="home_v2_news_empty_compact">
                            <strong>Vous êtes à jour</strong>
                            <span>Aucune autre annonce récente.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="home_v2_news_empty">
                <span class="home_v2_snapshot_icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M5 4h14v16H5z"></path>
                        <path d="M8 8h8M8 12h8M8 16h5"></path>
                    </svg>
                </span>
                <div>
                    <h2>Aucune annonce pour le moment</h2>
                    <p>Les prochaines informations importantes apparaîtront ici.</p>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!isUserLoggedIn()): ?>
        <section class="home_guest_invite" aria-labelledby="guest-invite-title">
            <span class="home_guest_invite_icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="9" cy="8" r="4"></circle>
                    <path d="M2 21a7 7 0 0 1 14 0"></path>
                    <path d="M19 8v6M16 11h6"></path>
                </svg>
            </span>

            <div class="home_guest_invite_content">
                <span>Nouveau sur CKS GO ?</span>
                <h2 id="guest-invite-title">Créez votre compte en quelques instants.</h2>
                <p>Accédez à la boutique, composez votre panier et retrouvez le suivi de vos commandes.</p>
            </div>

            <div class="home_guest_invite_actions">
                <a class="home_guest_invite_primary" href="index.php?controller=user&action=register">
                    Créer mon compte <span aria-hidden="true">→</span>
                </a>
                <a class="home_guest_invite_secondary" href="index.php?controller=user&action=login">
                    J'ai déjà un compte
                </a>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($latestProducts)): ?>
        <section class="home_latest_products" aria-labelledby="latest-products-title">
            <header class="home_latest_products_heading">
                <div>
                    <span class="section_kicker">Boutique</span>
                    <h2 id="latest-products-title">Derniers produits ajoutés</h2>
                </div>
                <a href="index.php?controller=shop&action=index">Voir toute la boutique <span aria-hidden="true">→</span></a>
            </header>

            <div class="home_latest_products_grid">
                <?php foreach ($latestProducts as $product): ?>
                    <?php
                    $productName = trim((string)($product['name'] ?? 'Produit'));
                    $productUrl = 'index.php?' . http_build_query([
                        'controller' => 'shop',
                        'action' => 'index',
                        'q' => $productName,
                    ]);
                    $minPrice = $product['min_price'] !== null
                            ? number_format((float)$product['min_price'], 2, ',', ' ') . ' €'
                            : null;
                    ?>
                    <a class="home_latest_product" href="<?= htmlspecialchars($productUrl) ?>">
                        <span class="home_latest_product_image">
                            <img
                                src="<?= BASE_URL ?>/public/img/<?= htmlspecialchars(resolvePublicImageFilename($product['image'] ?? null)) ?>"
                                alt=""
                                loading="lazy"
                            >
                        </span>
                        <span class="home_latest_product_content">
                            <small>Ajouté le <?= htmlspecialchars($formatDate($product['created_at'] ?? '')) ?></small>
                            <strong><?= htmlspecialchars($productName !== '' ? $productName : 'Produit') ?></strong>
                            <span>
                                <?= htmlspecialchars((string)($product['category_name'] ?? 'Boutique')) ?>
                                <?php if ($minPrice !== null): ?>
                                    <b>· dès <?= htmlspecialchars($minPrice) ?></b>
                                <?php endif; ?>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
