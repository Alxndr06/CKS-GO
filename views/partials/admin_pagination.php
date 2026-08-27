<?php
if (!function_exists('adminPaginationBuildItems')) {
    function adminPaginationBuildItems(int $currentPage, int $totalPages, int $maxVisible = 5): array
    {
        $currentPage = max(1, $currentPage);
        $totalPages = max(1, $totalPages);
        $maxVisible = max(3, $maxVisible);

        if ($totalPages <= $maxVisible) {
            return range(1, $totalPages);
        }

        $items = [1];
        $innerVisible = max(1, $maxVisible - 2);
        $start = max(2, $currentPage - intdiv($innerVisible, 2));
        $end = $start + $innerVisible - 1;

        if ($end > ($totalPages - 1)) {
            $end = $totalPages - 1;
            $start = max(2, $end - $innerVisible + 1);
        }

        if ($start <= 2) {
            $start = 2;
            $end = min($totalPages - 1, $start + $innerVisible - 1);
        }

        if ($start > 2) {
            $items[] = 'ellipsis';
        }

        for ($i = $start; $i <= $end; $i++) {
            $items[] = $i;
        }

        if ($end < ($totalPages - 1)) {
            $items[] = 'ellipsis';
        }

        $items[] = $totalPages;

        return $items;
    }
}

$paginationCurrentPage = max(1, (int) ($paginationCurrentPage ?? 1));
$paginationTotalPages = max(1, (int) ($paginationTotalPages ?? 1));
$paginationLabel = (string) ($paginationLabel ?? 'Pagination');
$paginationPageTemplate = (string) ($paginationPageTemplate ?? '');
$paginationMaxDesktop = max(3, (int) ($paginationMaxDesktop ?? 5));
$paginationMaxMobile = max(3, (int) ($paginationMaxMobile ?? 3));

if ($paginationTotalPages <= 1 || $paginationPageTemplate === '') {
    return;
}

$paginationItems = adminPaginationBuildItems($paginationCurrentPage, $paginationTotalPages, $paginationMaxDesktop);
?>
<nav
    class="apl_pagination"
    aria-label="<?= htmlspecialchars($paginationLabel) ?>"
    data-pagination="adaptive"
    data-current-page="<?= $paginationCurrentPage ?>"
    data-total-pages="<?= $paginationTotalPages ?>"
    data-max-desktop="<?= $paginationMaxDesktop ?>"
    data-max-mobile="<?= $paginationMaxMobile ?>"
    data-page-template="<?= htmlspecialchars($paginationPageTemplate, ENT_QUOTES, 'UTF-8') ?>"
>
    <?php if ($paginationCurrentPage > 1): ?>
        <a
            class="apl_page_link apl_page_link_nav apl_page_link_prev"
            href="<?= htmlspecialchars(str_replace('__PAGE__', (string) ($paginationCurrentPage - 1), $paginationPageTemplate), ENT_QUOTES, 'UTF-8') ?>"
        >
            Précédent
        </a>
    <?php endif; ?>

    <?php foreach ($paginationItems as $paginationItem): ?>
        <?php if ($paginationItem === 'ellipsis'): ?>
            <span class="apl_page_ellipsis" aria-hidden="true">…</span>
        <?php else: ?>
            <?php $paginationItem = (int) $paginationItem; ?>
            <a
                class="apl_page_link <?= $paginationItem === $paginationCurrentPage ? 'is_active' : '' ?>"
                href="<?= htmlspecialchars(str_replace('__PAGE__', (string) $paginationItem, $paginationPageTemplate), ENT_QUOTES, 'UTF-8') ?>"
            >
                <?= $paginationItem ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($paginationCurrentPage < $paginationTotalPages): ?>
        <a
            class="apl_page_link apl_page_link_nav apl_page_link_next"
            href="<?= htmlspecialchars(str_replace('__PAGE__', (string) ($paginationCurrentPage + 1), $paginationPageTemplate), ENT_QUOTES, 'UTF-8') ?>"
        >
            Suivant
        </a>
    <?php endif; ?>
</nav>
