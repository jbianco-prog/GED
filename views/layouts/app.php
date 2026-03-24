<?php
// views/layouts/app.php
// Usage: ob_start() / $content = ob_get_clean() puis include ce fichier
// Variables attendues : $title, $content, $currentFolder, $breadcrumb, $sidebarTree
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= H::e(Auth::csrfToken()) ?>">
    <title><?= H::e($title ?? 'GED') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
</head>
<body>

<!-- Topbar -->
<header class="topbar">
    <div class="topbar__logo"><?= APP_NAME ?><span> — GED</span></div>
    <div class="topbar__spacer"></div>
    <div class="topbar__user">
        <div class="topbar__avatar"><?= strtoupper(substr($_SESSION['user_nom'] ?? '?', 0, 2)) ?></div>
        <?= H::e($_SESSION['user_nom'] ?? '') ?>
        <?php if (Auth::isAdmin()): ?>
            <a href="<?= APP_URL ?>/admin.php" class="topbar__btn" style="font-size:12px">⚙ Admin</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/logout.php" class="topbar__btn">Sign out</a>
    </div>
</header>

<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar__section">
            <div class="sidebar__label">Navigation</div>
            <a class="sidebar__item <?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/index.php">
                <span class="ico">📁</span> Documents
            </a>
            <?php if (Auth::isAdmin()): ?>
            <a class="sidebar__item <?= (basename($_SERVER['PHP_SELF']) === 'admin.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/admin.php">
                <span class="ico">⚙</span> Administration
            </a>
            <a class="sidebar__item <?= (basename($_SERVER['PHP_SELF']) === 'logs.php') ? 'active' : '' ?>" href="<?= APP_URL ?>/logs.php">
                <span class="ico">📋</span> Journaux
            </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($sidebarTree)): ?>
        <div class="sidebar__section">
            <div class="sidebar__label">Arborescence</div>
            <?php self::renderTree($sidebarTree, $currentFolder ?? 0); ?>
        </div>
        <?php endif; ?>
    </aside>

    <!-- Main -->
    <div class="main">
        <?php if (!empty($breadcrumb)): ?>
        <nav class="breadcrumb">
            <?php foreach ($breadcrumb as $i => $crumb): ?>
                <?php if ($i < count($breadcrumb) - 1): ?>
                    <a href="<?= APP_URL ?>/index.php?folder=<?= $crumb['id'] ?>"><?= H::e($crumb['nom']) ?></a>
                    <span>›</span>
                <?php else: ?>
                    <span class="current"><?= H::e($crumb['nom']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <div id="flash-container"></div>
        <?php
        $flash = H::getFlash();
        if ($flash): ?>
        <div style="padding: 0 24px 0">
            <div class="alert alert-<?= H::e($flash['type']) ?>"><?= H::e($flash['msg']) ?></div>
        </div>
        <?php endif; ?>

        <?= $content ?>
    </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
</body>
</html>
<?php
// Helper statique pour le rendu de l'arbre sidebar (appelé inline)
function self_renderTree(array $tree, int $currentId, int $depth = 0): void {
    if (empty($tree)) return;
    echo '<ul class="tree" style="padding-left:' . ($depth * 12) . 'px">';
    foreach ($tree as $node) {
        $hasChildren = !empty($node['children']);
        $active      = ($node['id'] == $currentId) ? 'active' : '';
        echo '<li class="tree-item">';
        echo '<div class="tree-item__row ' . $active . '">';
        echo '<span class="tree-item__toggle">' . ($hasChildren ? '▼' : ' ') . '</span>';
        echo '<span class="ico">📁</span>';
        echo '<a href="' . APP_URL . '/index.php?folder=' . $node['id'] . '" style="color:inherit;flex:1">' . H::e($node['nom']) . '</a>';
        echo '</div>';
        if ($hasChildren) {
            echo '<div class="tree-item__children">';
            self_renderTree($node['children'], $currentId, $depth + 1);
            echo '</div>';
        }
        echo '</li>';
    }
    echo '</ul>';
}
