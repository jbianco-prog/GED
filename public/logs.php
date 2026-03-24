<?php
// public/logs.php — Journal d'activité
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAdmin();

$perPage     = 50;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$filterUser  = (int)($_GET['user']   ?? 0);
$filterAction = trim($_GET['action'] ?? '');

// Construire la requête avec filtres
$where  = [];
$params = [];
if ($filterUser)   { $where[] = 'l.user_id = ?';  $params[] = $filterUser; }
if ($filterAction) { $where[] = 'l.action = ?';    $params[] = $filterAction; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total      = (int)(Database::fetchOne("SELECT COUNT(*) c FROM audit_logs l $whereClause", $params)['c'] ?? 0);
$pagination = H::paginate($total, $perPage, $currentPage);

$logs = Database::fetchAll(
    "SELECT l.*, u.nom as user_nom, u.email as user_email
     FROM audit_logs l
     LEFT JOIN users u ON u.id = l.user_id
     $whereClause
     ORDER BY l.date_action DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $pagination['offset']])
);

$users = Database::fetchAll('SELECT id, nom, email FROM users ORDER BY nom');

$actionLabels = [
    'login'                => '🔐 Login',
    'login_failed'         => '❌ Login failed',
    'logout'               => '🚪 Logout',
    'file_upload'          => '⬆ Upload',
    'file_upload_blocked'  => '⛔ Upload blocked (sensitive)',
    'file_upload_forced'   => '⚠ Upload forced by admin',
    'download_blocked_dlp' => '🔒 Download blocked (DLP)',
    'dlp_enabled'          => '🛡️ DLP enabled',
    'dlp_disabled'         => '⚠ DLP disabled',
    'file_download'        => '⬇ Download',
    'file_preview'         => '👁 Preview',
    'file_rename'          => '✏ File rename',
    'file_delete'          => '🗑 File delete',
    'file_move'            => '📦 File move',
    'folder_create'        => '📁 Folder create',
    'folder_rename'        => '✏ Folder rename',
    'folder_delete'        => '🗑 Folder delete',
    'search'               => '🔍 Search',
    'admin_create_user'    => '👤 User created',
    'admin_toggle_user'    => '🔄 Enable/disable',
    'admin_change_role'    => '⇄ Role change',
    'admin_delete_user'    => '🗑 User deleted',
    'profile_update_info'  => '✏ Profile update',
    'profile_change_password' => '🔒 Password change',
];

$actionColors = [
    'login'               => 'badge-success',
    'login_failed'        => 'badge-danger',
    'file_upload_blocked' => 'badge-danger',
    'file_upload_forced'  => 'badge-warning',
    'download_blocked_dlp'=> 'badge-danger',
    'dlp_enabled'         => 'badge-success',
    'dlp_disabled'        => 'badge-danger',
    'file_delete'         => 'badge-warning',
    'folder_delete'       => 'badge-warning',
    'admin_delete_user'   => 'badge-danger',
];

Layout::start('Audit Logs', 0);
?>

<div class="toolbar">
    <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select class="form-control" name="user" style="width:180px">
            <option value="">All users</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $filterUser===$u['id'] ? 'selected' : '' ?>>
                <?= H::e($u['nom']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select class="form-control" name="action" style="width:200px">
            <option value="">All actions</option>
            <?php foreach ($actionLabels as $key => $label): ?>
            <option value="<?= $key ?>" <?= $filterAction===$key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if ($filterUser || $filterAction): ?>
        <a href="logs.php" class="btn btn-secondary">✕ Reset</a>
        <?php endif; ?>
        <span class="text-muted text-small ml-auto"><?= $total ?> entry(ies)</span>
    </form>
</div>

<div class="content">
    <table class="table">
        <thead><tr>
            <th style="width:130px">Date</th>
            <th style="width:200px">Action</th>
            <th style="width:160px">User</th>
            <th>Detail</th>
            <th style="width:110px">IP</th>
        </tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td class="text-small text-muted"><?= H::formatDate($log['date_action']) ?></td>
            <td>
                <span class="badge <?= $actionColors[$log['action']] ?? 'badge-secondary' ?>" style="font-size:11px">
                    <?= $actionLabels[$log['action']] ?? H::e($log['action']) ?>
                </span>
            </td>
            <td class="text-small">
                <?= $log['user_nom'] ? H::e($log['user_nom']) : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="text-small text-muted" style="max-width:300px">
                <span class="truncate" title="<?= H::e($log['detail']??'') ?>"><?= H::e($log['detail'] ?? '—') ?></span>
            </td>
            <td class="text-small text-muted"><?= H::e($log['ip'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--gray-400)">No entries.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div style="display:flex;gap:4px;justify-content:center;margin-top:16px;align-items:center">
        <?php if ($pagination['has_prev']): ?>
            <a class="btn btn-secondary btn-sm" href="?page=<?= $pagination['current_page']-1 ?>&user=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>">← Previous</a>
        <?php endif; ?>
        <span class="text-muted text-small">Page <?= $pagination['current_page'] ?> / <?= $pagination['total_pages'] ?></span>
        <?php if ($pagination['has_next']): ?>
            <a class="btn btn-secondary btn-sm" href="?page=<?= $pagination['current_page']+1 ?>&user=<?= $filterUser ?>&action=<?= urlencode($filterAction) ?>">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php Layout::end([['id'=>0,'nom'=>'Audit Logs']]); ?>
