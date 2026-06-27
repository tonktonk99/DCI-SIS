<?php
$user = getUser();
$currentLang = currentLang();
$currentUri = $_SERVER['REQUEST_URI'] ?? '/dci-sis/';
?>

<header class="topbar">
    <div class="topbar-title">
        <div class="crumb"><?= htmlspecialchars($crumb ?? 'DCI Academic Portal') ?></div>
        <div class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
    </div>

    <div class="search-box">
        <input type="text" placeholder="<?= htmlspecialchars(__('search_placeholder')) ?>">
    </div>

    <div class="lang-switch">
        <?php if ($currentLang === 'th'): ?>
            <a href="/dci-sis/actions/set-lang.php?lang=en&redirect=<?= urlencode($currentUri) ?>" class="lang-btn" title="Switch to English">EN</a>
            <span class="lang-btn lang-active">TH</span>
        <?php else: ?>
            <span class="lang-btn lang-active">EN</span>
            <a href="/dci-sis/actions/set-lang.php?lang=th&redirect=<?= urlencode($currentUri) ?>" class="lang-btn" title="เปลี่ยนเป็นภาษาไทย">TH</a>
        <?php endif; ?>
    </div>

    <div class="topbar-user">
        <?= htmlspecialchars($user['username'] ?? '') ?>
        |
        <a class="logout-link" href="/dci-sis/logout.php"><?= __('sign_out') ?></a>
    </div>
</header>
<?php flash_render(); ?>
