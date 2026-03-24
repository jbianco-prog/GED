<?php
// public/admin.php — Tableau de bord administrateur
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAdmin();

// ── Actions POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['admin_action'] ?? '';
    try {
        if ($act === 'create_user') {
            $nom   = trim($_POST['nom']   ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass  = $_POST['password']   ?? '';
            $role  = in_array($_POST['role']??'', ['admin','user']) ? $_POST['role'] : 'user';
            if (!$nom || !$email || !$pass) throw new Exception('All fields are required.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email.');
            if (Database::fetchOne('SELECT id FROM users WHERE email=?',[$email])) throw new Exception("Email already in use.");
            if (strlen($pass) < 8) throw new Exception('Password: minimum 8 characters.');
            $uid = Database::insert('INSERT INTO users (nom,email,password,role) VALUES (?,?,?,?)',
                [$nom, $email, password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]), $role]);
            AuditLog::log(Auth::id(), 'admin_create_user', 'user', $uid, "Email: $email");
            H::flash('success', "User $nom created.");

        } elseif ($act === 'toggle_user') {
            $uid  = (int)($_POST['user_id'] ?? 0);
            if ($uid === Auth::id()) throw new Exception('Cannot disable your own account.');
            $user = Database::fetchOne('SELECT * FROM users WHERE id=?',[$uid]);
            if (!$user) throw new Exception('Utilisateur introuvable.');
            $new  = $user['actif'] ? 0 : 1;
            Database::execute('UPDATE users SET actif=? WHERE id=?', [$new, $uid]);
            AuditLog::log(Auth::id(), 'admin_toggle_user', 'user', $uid, $new ? 'Enabled' : 'Disabled');
            H::flash('success', 'Account '.($new ? 'enabled' : 'disabled').'.');

        } elseif ($act === 'change_role') {
            $uid  = (int)($_POST['user_id'] ?? 0);
            $role = in_array($_POST['role']??'', ['admin','user']) ? $_POST['role'] : 'user';
            if ($uid === Auth::id()) throw new Exception('Cannot change your own role.');
            Database::execute('UPDATE users SET role=? WHERE id=?', [$role, $uid]);
            AuditLog::log(Auth::id(), 'admin_change_role', 'user', $uid, "New role: $role");
            H::flash('success', 'Role updated.');

        } elseif ($act === 'delete_user') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid === Auth::id()) throw new Exception('Cannot delete your own account.');
            Database::execute('DELETE FROM users WHERE id=?', [$uid]);
            AuditLog::log(Auth::id(), 'admin_delete_user', 'user', $uid);
            H::flash('success', 'User deleted.');

        } elseif ($act === 'toggle_dlp') {
            $enable = (bool)(int)($_POST['dlp_value'] ?? 0);
            Settings::setDlp($enable, Auth::id());
            H::flash('success', 'DLP filtering ' . ($enable ? 'enabled' : 'disabled') . '.');

        } elseif ($act === 'reanalyze_file') {
            $fid  = (int)($_POST['file_id'] ?? 0);
            $file = FileModel::find($fid);
            if (!$file) throw new Exception('File not found.');
            ClassificationEngine::run($fid, $file['chemin_stockage'], $file['extension']);
            H::flash('success', 'Re-analysis started for '.$file['nom_courant']);
        }
    } catch (Exception $e) {
        H::flash('error', $e->getMessage());
    }
    H::redirect(APP_URL . '/admin.php');
}

// ── État DLP ──────────────────────────────────────────────────────────────────
$dlpEnabled = Settings::dlpEnabled();

// ── Statistiques ───────────────────────────────────────────────────────────────
$stats = [
    'nb_fichiers'        => (int)(Database::fetchOne('SELECT COUNT(*) c FROM files')['c']??0),
    'nb_dossiers'        => (int)(Database::fetchOne('SELECT COUNT(*) c FROM folders')['c']??0),
    'nb_users'           => (int)(Database::fetchOne('SELECT COUNT(*) c FROM users')['c']??0),
    'nb_sensibles'       => (int)(Database::fetchOne("SELECT COUNT(*) c FROM file_analysis WHERE niveau_sensibilite IN ('sensible','sensible_eleve')")['c']??0),
    'nb_sensibles_eleve' => (int)(Database::fetchOne("SELECT COUNT(*) c FROM file_analysis WHERE niveau_sensibilite='sensible_eleve'")['c']??0),
    'nb_non_analyses'    => (int)(Database::fetchOne("SELECT COUNT(*) c FROM file_analysis WHERE niveau_sensibilite='non_analyse'")['c']??0),
    'taille_totale'      => (int)(Database::fetchOne('SELECT COALESCE(SUM(taille),0) c FROM files')['c']??0),
    'nb_bloquees'        => (int)(Database::fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE action='file_upload_blocked'")['c']??0),
];

$sensibleFiles = Database::fetchAll(
    "SELECT f.id, f.nom_courant, f.extension, f.created_at,
            u.nom as uploaded_by_nom,
            fa.niveau_sensibilite, fa.score_ia, fa.raisons, fa.resume_ai
     FROM files f
     JOIN file_analysis fa ON fa.file_id = f.id
     LEFT JOIN users u ON u.id = f.uploaded_by
     WHERE fa.niveau_sensibilite IN ('sensible','sensible_eleve')
     ORDER BY f.created_at DESC LIMIT 20"
);

$users = Database::fetchAll('SELECT * FROM users ORDER BY created_at DESC');

Layout::start('Administration', 0);
?>

<div class="toolbar">
    <span style="font-weight:600;font-size:14px">⚙ Administration</span>
    <div class="toolbar__sep"></div>

    <!-- Bouton toggle DLP -->
    <form method="POST" style="display:inline-flex;align-items:center;gap:8px">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="admin_action" value="toggle_dlp">
        <input type="hidden" name="dlp_value" value="<?= $dlpEnabled ? '0' : '1' ?>">
        <button type="submit" class="btn <?= $dlpEnabled ? 'btn-dlp-on' : 'btn-dlp-off' ?>"
                title="<?= $dlpEnabled ? 'Click to disable DLP filtering' : 'Click to enable DLP filtering' ?>">
            <?php if ($dlpEnabled): ?>
                🛡️ DLP active
            <?php else: ?>
                ⚠ DLP inactive
            <?php endif; ?>
        </button>
    </form>

    <div class="ml-auto">
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-new-user')">+ New user</button>
    </div>
</div>

<div class="content">

    <!-- Statistiques -->
    <div class="stat-grid" style="margin-bottom:24px">
        <div class="stat-card">
            <div class="stat-card__label">Total files</div>
            <div class="stat-card__value"><?= $stats['nb_fichiers'] ?></div>
            <div class="stat-card__sub"><?= H::formatSize($stats['taille_totale']) ?> used</div>
        </div>
        <div class="stat-card stat-card--dlp <?= $dlpEnabled ? 'dlp-on' : 'dlp-off' ?>">
            <div class="stat-card__label">DLP Filtering</div>
            <div class="stat-card__value" style="font-size:18px;display:flex;align-items:center;gap:6px">
                <?php if ($dlpEnabled): ?>
                    <span style="color:#107c10">🛡️ Active</span>
                <?php else: ?>
                    <span style="color:#a4262c">⚠ Inactive</span>
                <?php endif; ?>
            </div>
            <div class="stat-card__sub">User restrictions</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Folders</div>
            <div class="stat-card__value"><?= $stats['nb_dossiers'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Users</div>
            <div class="stat-card__value"><?= $stats['nb_users'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Sensitive files</div>
            <div class="stat-card__value" style="color:var(--warning)"><?= $stats['nb_sensibles'] ?></div>
            <div class="stat-card__sub"><?= $stats['nb_sensibles_eleve'] ?> high risk</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Blocked uploads</div>
            <div class="stat-card__value" style="color:var(--danger)"><?= $stats['nb_bloquees'] ?></div>
            <div class="stat-card__sub">Sensitive documents rejected</div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Not analyzed</div>
            <div class="stat-card__value"><?= $stats['nb_non_analyses'] ?></div>
            <div class="stat-card__sub"><a href="#" onclick="relancerTous()">Relancer →</a></div>
        </div>
    </div>

    <!-- Sensitive files -->
    <h2 style="font-size:15px;font-weight:600;margin-bottom:10px">⚠ Stored sensitive files</h2>
    <?php if (empty($sensibleFiles)): ?>
        <div class="alert alert-success" style="margin-bottom:20px">✓ No sensitive files in the DMS.</div>
    <?php else: ?>
    <div style="margin-bottom:24px">
        <table class="table">
            <thead><tr>
                <th></th><th>File</th><th>Status</th><th style="width:300px;white-space:nowrap">AI Summary</th><th>Score</th><th>Reasons</th><th>Uploaded by</th><th>Date</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($sensibleFiles as $f): ?>
            <tr>
                <td style="width:28px"><span class="file-icon <?= H::fileIconClass($f['extension']) ?>"><?= H::fileIcon($f['extension']) ?></span></td>
                <td><a href="file.php?id=<?= $f['id'] ?>"><?= H::e($f['nom_courant']) ?></a></td>
                <td><?= H::sensitivityBadge($f['niveau_sensibilite']) ?></td>
                <td>
                    <?php if (!empty($f['resume_ai'])): ?>
                        <?php foreach (array_map('trim', explode(',', $f['resume_ai'])) as $mot): ?>
                        <span style="display:inline-block;background:var(--primary-light);color:var(--primary);border-radius:10px;padding:2px 9px;font-size:11px;font-weight:600;margin:2px;white-space:nowrap"><?= H::e($mot) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-small"><?= $f['score_ia'] !== null ? round($f['score_ia']).'%' : '—' ?></td>
                <td class="text-small text-muted" style="max-width:180px">
                    <span class="truncate" title="<?= H::e($f['raisons']??'') ?>"><?= H::e(mb_substr($f['raisons']??'—', 0, 60)) ?></span>
                </td>
                <td class="text-small"><?= H::e($f['uploaded_by_nom']??'—') ?></td>
                <td class="text-small"><?= H::formatDate($f['created_at']) ?></td>
                <td>
                    <form method="POST" style="display:inline">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="admin_action" value="reanalyze_file">
                        <input type="hidden" name="file_id" value="<?= $f['id'] ?>">
                        <button class="btn btn-secondary btn-sm" title="Relancer l'analyse">🔄</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Users -->
    <h2 style="font-size:15px;font-weight:600;margin-bottom:10px">👥 Users</h2>
    <table class="table" style="margin-bottom:24px">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= H::e($u['nom']) ?></td>
            <td class="text-small"><?= H::e($u['email']) ?></td>
            <td><span class="badge <?= $u['role']==='admin' ? 'badge-info' : 'badge-secondary' ?>"><?= $u['role'] ?></span></td>
            <td><span class="badge <?= $u['actif'] ? 'badge-success' : 'badge-danger' ?>"><?= $u['actif'] ? 'Active' : 'Disabled' ?></span></td>
            <td class="text-small"><?= H::formatDate($u['created_at']) ?></td>
            <td style="display:flex;gap:4px;flex-wrap:wrap">
                <?php if ($u['id'] !== Auth::id()): ?>
                <form method="POST" style="display:inline">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="admin_action" value="toggle_user">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button class="btn btn-secondary btn-sm"><?= $u['actif'] ? '🔒 Disable' : '🔓 Enable' ?></button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Change role?')">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="admin_action" value="change_role">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <input type="hidden" name="role" value="<?= $u['role']==='admin' ? 'user' : 'admin' ?>">
                    <button class="btn btn-secondary btn-sm">⇄ <?= $u['role']==='admin' ? 'user' : 'admin' ?></button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Permanently delete?')">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="admin_action" value="delete_user">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button class="btn btn-danger btn-sm">🗑</button>
                </form>
                <?php else: ?>
                    <span class="text-muted text-small">(yourself)</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal : Nouvel utilisateur -->
<div class="modal-overlay hidden" id="modal-new-user">
    <div class="modal">
        <div class="modal__header">
            <span class="modal__title">Create user</span>
            <button class="modal__close" onclick="closeModal('modal-new-user')">×</button>
        </div>
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="admin_action" value="create_user">
            <div class="form-group">
                <label class="form-label">Full name</label>
                <input class="form-control" type="text" name="nom" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email address</label>
                <input class="form-control" type="email" name="email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input class="form-control" type="password" name="password" required minlength="8">
                <div class="form-hint">Minimum 8 characters.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-control" name="role">
                    <option value="user">Standard user</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-new-user')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<script>
function relancerTous() {
    if (!confirm('Re-run analysis on all unanalyzed files?')) return;
    window.location.href = '<?= APP_URL ?>/admin.php?action=reanalyze_all';
}
</script>

<?php Layout::end([['id'=>0,'nom'=>'Administration']]); ?>
