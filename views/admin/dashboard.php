<?php
$pageTitle = 'Vue d’ensemble | CKS GO';
$pageStylesheets = ['admin-dashboard.css'];
$pageScripts = ['admin-dashboard.js'];

require_once __DIR__ . '/../partials/header.php';

$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$metrics = is_array($dashboard['metrics'] ?? null) ? $dashboard['metrics'] : [];
$priorities = is_array($dashboard['priorities'] ?? null) ? $dashboard['priorities'] : [];
$activity = is_array($dashboard['activity'] ?? null) ? $dashboard['activity'] : [];
$finance = is_array($dashboard['finance'] ?? null) ? $dashboard['finance'] : [];
$quickActions = is_array($dashboard['quick_actions'] ?? null) ? $dashboard['quick_actions'] : [];
$firstName = trim((string)($_SESSION['user']['firstname'] ?? ''));
$greeting = $firstName !== '' ? 'Bonjour ' . $firstName : 'Bonjour';
$moduleCount = (int)($dashboard['module_count'] ?? 0);
$canViewFinance = (bool)($dashboard['can_view_finance'] ?? false);

$escape = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$formatMoney = static fn(float $amount): string => number_format($amount, 2, ',', ' ') . ' €';
$formatChartAmount = static function (float $amount): string {
    $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;
    return number_format($amount, $decimals, ',', ' ') . ' €';
};
$formatRelativeTime = static function (string $value): string {
    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return '';
    }

    $seconds = max(0, time() - $timestamp);

    if ($seconds < 60) {
        return 'À l’instant';
    }

    if ($seconds < 3600) {
        return 'Il y a ' . max(1, (int)floor($seconds / 60)) . ' min';
    }

    if ($seconds < 86400) {
        return 'Il y a ' . (int)floor($seconds / 3600) . ' h';
    }

    if ($seconds < 172800) {
        return 'Hier';
    }

    if ($seconds < 604800) {
        return 'Il y a ' . (int)floor($seconds / 86400) . ' j';
    }

    return date('d/m/Y', $timestamp);
};

$series = is_array($finance['series'] ?? null) ? $finance['series'] : [];
$seriesAmounts = array_map(static fn(array $item): float => (float)($item['amount'] ?? 0), $series);
$maxChartAmount = max(1.0, ...$seriesAmounts);
$chartBaseline = 126;
$chartMaxHeight = 88;
?>

<main class="main_part admin_dashboard_page dashboard_overview" data-admin-dashboard>
    <header class="dashboard_overview_heading">
        <div class="dashboard_overview_title">
            <div class="dashboard_overview_title_line">
                <h1><?= $escape($greeting) ?></h1>
                <span class="dashboard_scope_badge">
                    <span aria-hidden="true"><?= renderUiIcon('orders') ?></span>
                    <?= $moduleCount ?> module<?= $moduleCount === 1 ? '' : 's' ?> accessible<?= $moduleCount === 1 ? '' : 's' ?>
                </span>
            </div>
            <p>Voici ce qui mérite votre attention aujourd’hui.</p>
        </div>

        <button
                class="dashboard_customize_button"
                type="button"
                data-dashboard-customize
                aria-haspopup="dialog"
                aria-controls="dashboard_preferences"
        >
            <span aria-hidden="true"><?= renderUiIcon('settings') ?></span>
            Personnaliser la vue
        </button>
    </header>

    <?php if ($metrics !== []): ?>
        <section class="dashboard_metrics" aria-label="Indicateurs principaux">
            <?php foreach ($metrics as $metric): ?>
                <a
                        class="dashboard_metric is_<?= $escape($metric['tone'] ?? 'sky') ?>"
                        href="<?= $escape($metric['href'] ?? '#') ?>"
                        aria-label="<?= $escape(($metric['label'] ?? '') . ' : ' . ($metric['value'] ?? '')) ?>"
                >
                    <span class="dashboard_metric_icon" aria-hidden="true"><?= renderUiIcon((string)($metric['icon'] ?? 'logs')) ?></span>
                    <span class="dashboard_metric_content">
                        <span><?= $escape($metric['label'] ?? '') ?></span>
                        <strong><?= $escape($metric['value'] ?? '') ?></strong>
                    </span>
                    <?php if (!empty($metric['status'])): ?>
                        <span class="dashboard_metric_status"><?= $escape($metric['status']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="dashboard_primary_grid" aria-label="Pilotage quotidien">
        <article class="dashboard_panel dashboard_priorities_panel">
            <header class="dashboard_panel_heading">
                <span class="dashboard_panel_icon" aria-hidden="true"><?= renderUiIcon('invoice') ?></span>
                <h2>Priorités du jour</h2>
            </header>

            <?php if ($priorities === []): ?>
                <div class="dashboard_empty_state">
                    <strong>Tout est sous contrôle</strong>
                    <p>Aucune action prioritaire pour votre périmètre.</p>
                </div>
            <?php else: ?>
                <ol class="dashboard_priority_list">
                    <?php foreach ($priorities as $index => $priority): ?>
                        <li class="dashboard_priority_item is_<?= $escape($priority['tone'] ?? 'sky') ?>">
                            <span class="dashboard_priority_index" aria-hidden="true"><?= $index + 1 ?></span>
                            <strong><?= $escape($priority['label'] ?? '') ?></strong>
                            <span class="dashboard_status_badge"><?= $escape($priority['status'] ?? '') ?></span>
                            <a href="<?= $escape($priority['href'] ?? '#') ?>">
                                <?= $escape($priority['action'] ?? 'Voir') ?>
                                <span aria-hidden="true">→</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>

        <article class="dashboard_panel dashboard_activity_panel">
            <header class="dashboard_panel_heading">
                <span class="dashboard_panel_icon" aria-hidden="true"><?= renderUiIcon('logs') ?></span>
                <h2>Activité récente</h2>
            </header>

            <?php if ($activity === []): ?>
                <div class="dashboard_empty_state">
                    <strong>Aucune activité récente</strong>
                    <p>Les prochaines actions apparaîtront ici.</p>
                </div>
            <?php else: ?>
                <ol class="dashboard_activity_list">
                    <?php foreach ($activity as $item): ?>
                        <li class="dashboard_activity_item is_<?= $escape($item['tone'] ?? 'sky') ?>">
                            <span class="dashboard_activity_marker" aria-hidden="true"><?= renderUiIcon((string)($item['icon'] ?? 'logs')) ?></span>
                            <a href="<?= $escape($item['href'] ?? '#') ?>">
                                <strong><?= $escape($item['title'] ?? '') ?></strong>
                                <span><?= $escape($item['description'] ?? '') ?></span>
                            </a>
                            <time datetime="<?= $escape($item['timestamp'] ?? '') ?>"><?= $escape($formatRelativeTime((string)($item['timestamp'] ?? ''))) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>
    </section>

    <section class="dashboard_secondary_grid" aria-label="Outils de suivi">
        <?php if ($canViewFinance): ?>
            <article class="dashboard_panel dashboard_finance_panel" data-dashboard-panel="finance">
                <header class="dashboard_panel_heading dashboard_panel_heading_split">
                    <span class="dashboard_panel_icon" aria-hidden="true"><?= renderUiIcon('logs') ?></span>
                    <h2>Suivi financier</h2>
                    <span><?= $escape($finance['period_label'] ?? '7 derniers jours') ?></span>
                </header>

                <div class="dashboard_finance_content">
                    <dl class="dashboard_finance_totals">
                        <div class="is_captured">
                            <dt>Encaissé</dt>
                            <dd><?= $escape($formatMoney((float)($finance['captured_total'] ?? 0))) ?></dd>
                        </div>
                        <div class="is_outstanding">
                            <dt>À encaisser</dt>
                            <dd><?= $escape($formatMoney((float)($finance['outstanding_total'] ?? 0))) ?></dd>
                        </div>
                    </dl>

                    <?php if ($series !== []): ?>
                        <div class="dashboard_chart">
                            <svg viewBox="0 0 620 168" role="img" aria-labelledby="dashboard_chart_title dashboard_chart_description">
                                <title id="dashboard_chart_title">Encaissements des sept derniers jours</title>
                                <desc id="dashboard_chart_description">Graphique en barres des montants encaissés quotidiennement.</desc>
                                <line class="dashboard_chart_axis" x1="24" y1="<?= $chartBaseline ?>" x2="606" y2="<?= $chartBaseline ?>" />
                                <?php foreach ($series as $index => $point): ?>
                                    <?php
                                    $amount = (float)($point['amount'] ?? 0);
                                    $barHeight = $amount > 0 ? max(4, (int)round(($amount / $maxChartAmount) * $chartMaxHeight)) : 3;
                                    $barX = 42 + ($index * 82);
                                    $barY = $chartBaseline - $barHeight;
                                    ?>
                                    <g class="dashboard_chart_point <?= $amount <= 0 ? 'is_empty' : '' ?>">
                                        <text class="dashboard_chart_value" x="<?= $barX + 17 ?>" y="<?= max(14, $barY - 8) ?>" text-anchor="middle"><?= $escape($formatChartAmount($amount)) ?></text>
                                        <rect x="<?= $barX ?>" y="<?= $barY ?>" width="34" height="<?= $barHeight ?>" rx="6" />
                                        <text class="dashboard_chart_label" x="<?= $barX + 17 ?>" y="153" text-anchor="middle"><?= $escape($point['label'] ?? '') ?></text>
                                    </g>
                                <?php endforeach; ?>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>

        <?php if ($quickActions !== []): ?>
            <article class="dashboard_panel dashboard_quick_panel" data-dashboard-panel="quick-actions">
                <header class="dashboard_panel_heading">
                    <span class="dashboard_panel_icon" aria-hidden="true"><?= renderUiIcon('add') ?></span>
                    <h2>Accès rapides</h2>
                </header>

                <nav class="dashboard_quick_actions" aria-label="Accès rapides de gestion">
                    <?php foreach ($quickActions as $action): ?>
                        <a class="is_<?= $escape($action['tone'] ?? 'sky') ?>" href="<?= $escape($action['href'] ?? '#') ?>">
                            <span aria-hidden="true"><?= renderUiIcon((string)($action['icon'] ?? 'add')) ?></span>
                            <strong><?= $escape($action['label'] ?? '') ?></strong>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </article>
        <?php endif; ?>
    </section>

    <dialog class="dashboard_preferences" id="dashboard_preferences" aria-labelledby="dashboard_preferences_title">
        <form method="dialog">
            <header>
                <div>
                    <span>Affichage</span>
                    <h2 id="dashboard_preferences_title">Personnaliser la vue</h2>
                </div>
                <button type="submit" value="close" aria-label="Fermer">×</button>
            </header>

            <div class="dashboard_preferences_options">
                <?php if ($canViewFinance): ?>
                    <label>
                        <span>
                            <strong>Suivi financier</strong>
                            <small>Afficher les encaissements des sept derniers jours.</small>
                        </span>
                        <input type="checkbox" data-dashboard-preference="finance" checked>
                    </label>
                <?php endif; ?>

                <?php if ($quickActions !== []): ?>
                    <label>
                        <span>
                            <strong>Accès rapides</strong>
                            <small>Garder les raccourcis métier visibles.</small>
                        </span>
                        <input type="checkbox" data-dashboard-preference="quick-actions" checked>
                    </label>
                <?php endif; ?>

                <label>
                    <span>
                        <strong>Vue compacte</strong>
                        <small>Réduire les espacements pour afficher plus d’informations.</small>
                    </span>
                    <input type="checkbox" data-dashboard-preference="compact">
                </label>
            </div>

            <footer>
                <button class="dashboard_preferences_reset" type="button" data-dashboard-reset>Réinitialiser</button>
                <button class="dashboard_preferences_done" type="submit" value="close">Terminé</button>
            </footer>
        </form>
    </dialog>
</main>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
