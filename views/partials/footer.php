<?php
$appEnv = defined('APP_ENV') ? strtolower((string)APP_ENV) : 'dev';
$appEnvLabel = $appEnv === 'prod' ? 'PROD' : 'DEV';
$appEnvClass = $appEnv === 'prod' ? 'is_prod' : 'is_dev';
$appVersion = defined('APP_VERSION')
    ? (string)APP_VERSION
    : 'build-' . date('Ymd.Hi', filemtime(__DIR__ . '/../../index.php'));
?>
    <footer class="main_footer">
        <div class="footer_brand">
            <span class="footer_brand_mark" aria-hidden="true">CG</span>
            <div>
                <strong>CKS GO</strong>
                <small>Gestion simple du quotidien.</small>
            </div>
        </div>

        <nav class="footer_links" aria-label="Liens légaux">
            <a href="index.php?controller=home&action=about">À propos</a>
            <a href="index.php?controller=home&action=legal">Mentions légales</a>
            <a href="index.php?controller=home&action=privacy">Confidentialité</a>
        </nav>

        <div class="footer_meta_badge" aria-label="Version de l'application et environnement">
            <span class="footer_meta_version"><?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="footer_meta_env <?= htmlspecialchars($appEnvClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($appEnvLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </footer>

    <?php if (!empty($isStaffContext)): ?>
        <button
                class="staff_scroll_top"
                type="button"
                data-staff-scroll-top
                aria-label="Revenir en haut de la page"
                title="Revenir en haut de la page"
                hidden
        >
            <span class="staff_scroll_top_icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="m6 14 6-6 6 6"></path>
                </svg>
            </span>
            <span class="staff_scroll_top_label">Haut</span>
        </button>
    <?php endif; ?>
</div>
</body>
</html>
