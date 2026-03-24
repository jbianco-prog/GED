<?php
// src/Helpers/Layout.php — Rendu du layout commun (header, sidebar, footer)
// Utilisation :
//   Layout::start($title);      // début du buffer + ouvre le HTML jusqu'au <div class="main">
//   ... contenu HTML ...
//   Layout::end();              // ferme le layout et affiche tout

class Layout {

    private static string $title        = '';
    private static int    $currentFolder = 0;

    public static function start(string $title, int $currentFolderId = 0): void {
        self::$title         = $title;
        self::$currentFolder = $currentFolderId;
        ob_start();
    }

    public static function end(array $breadcrumb = []): void {
        $content     = ob_get_clean();
        $title       = self::$title;
        $currentFold = self::$currentFolder;
        $sidebarTree = Folder::getTree(Folder::root()['id'] ?? 1);
        $flash       = H::getFlash();
        $page        = basename($_SERVER['PHP_SELF'], '.php');

        ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= H::e(Auth::csrfToken()) ?>">
    <title><?= H::e($title) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
</head>
<body>

<header class="topbar">
    <div class="topbar__logo"><?= APP_NAME ?></div>
    <div class="topbar__spacer"></div>
    <form class="topbar__search" action="<?= APP_URL ?>/search.php" method="GET">
        <button type="submit">🔍</button>
        <input type="search" name="q" placeholder="Search…"
               value="<?= isset($_GET['q']) ? H::e($_GET['q']) : '' ?>">
    </form>
    <div class="topbar__user">
        <div class="topbar__avatar"><?= strtoupper(substr($_SESSION['user_nom'] ?? '?', 0, 2)) ?></div>
        <span style="font-size:13px"><?= H::e($_SESSION['user_nom'] ?? '') ?></span>
        <a href="<?= APP_URL ?>/profile.php" class="topbar__btn" style="font-size:12px">👤 Profile</a>
        <?php if (Auth::isAdmin()): ?>
            <a href="<?= APP_URL ?>/admin.php" class="topbar__btn" style="font-size:12px">⚙ Admin</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/logout.php" class="topbar__btn">Sign out</a>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar__section">
            <div class="sidebar__label">Navigation</div>
            <a class="sidebar__item <?= $page === 'index' ? 'active' : '' ?>"
               href="<?= APP_URL ?>/index.php">
                <span class="ico">📁</span> Documents
            </a>
            <?php if (Auth::isAdmin()): ?>
            <a class="sidebar__item <?= $page === 'admin' ? 'active' : '' ?>"
               href="<?= APP_URL ?>/admin.php">
                <span class="ico">⚙</span> Administration
            </a>
            <a class="sidebar__item <?= $page === 'logs' ? 'active' : '' ?>"
               href="<?= APP_URL ?>/logs.php">
                <span class="ico">📋</span> Audit Logs
            </a>
            <?php endif; ?>
            <a class="sidebar__item <?= $page === 'profile' ? 'active' : '' ?>"
               href="<?= APP_URL ?>/profile.php">
                <span class="ico">👤</span> My Profile
            </a>
        </div>

        <?php if (!empty($sidebarTree)): ?>
        <div class="sidebar__section">
            <div class="sidebar__label">Folder Tree</div>
            <?php self::renderTree($sidebarTree, $currentFold); ?>
        </div>
        <?php endif; ?>
    </aside>

    <div class="main">
        <?php if (!Auth::isAdmin() && Settings::dlpEnabled()): ?>
        <div class="dlp-banner">
            🛡️ <strong>DLP filtering active</strong> — Downloading, sharing and uploading sensitive documents is restricted.
        </div>
        <?php endif; ?>
        <?php if (!empty($breadcrumb)): ?>
        <nav class="breadcrumb">
            <?php foreach ($breadcrumb as $i => $crumb): ?>
                <?php if ($i < count($breadcrumb) - 1 && !empty($crumb['id'])): ?>
                    <a href="<?= APP_URL ?>/index.php?folder=<?= $crumb['id'] ?>"><?= H::e($crumb['nom']) ?></a>
                    <span>›</span>
                <?php else: ?>
                    <span class="current"><?= H::e($crumb['nom']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <div id="flash-container">
        <?php if ($flash): ?>
            <div style="padding:8px 24px 0">
                <div class="alert alert-<?= H::e($flash['type']) ?>"><?= H::e($flash['msg']) ?></div>
            </div>
        <?php endif; ?>
        </div>

        <?= $content ?>
    </div><!-- /.main -->
</div><!-- /.layout -->

<script src="<?= APP_URL ?>/js/app.js"></script>
</body>
</html><?php
    }

    private static function renderTree(array $tree, int $currentId, int $depth = 0): void {
        if (empty($tree)) return;
        $pad = $depth * 12;
        echo '<ul class="tree" style="padding-left:' . $pad . 'px">';
        foreach ($tree as $node) {
            $hasChildren = !empty($node['children']);
            $active      = ($node['id'] == $currentId) ? 'active' : '';
            echo '<li class="tree-item">';
            echo '<div class="tree-item__row ' . $active . '">';
            echo '<span class="tree-item__toggle">' . ($hasChildren ? '▼' : '&nbsp;') . '</span>';
            echo '<span style="margin-right:4px;font-size:13px">📁</span>';
            echo '<a href="' . APP_URL . '/index.php?folder=' . $node['id']
                . '" style="color:inherit;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'
                . H::e($node['nom']) . '</a>';
            echo '</div>';
            if ($hasChildren) {
                echo '<div class="tree-item__children">';
                self::renderTree($node['children'], $currentId, $depth + 1);
                echo '</div>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }
}
