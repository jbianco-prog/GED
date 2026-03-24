<?php
// public/profile.php — Page de profil utilisateur
require_once __DIR__ . '/../bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['profile_action'] ?? '';
    try {
        if ($act === 'update_info') {
            $nom = trim($_POST['nom'] ?? '');
            if (empty($nom)) throw new Exception('Name cannot be empty.');
            if (mb_strlen($nom) > 100) throw new Exception('Name too long (100 chars max).');
            Database::execute('UPDATE users SET nom=? WHERE id=?', [$nom, Auth::id()]);
            $_SESSION['user_nom'] = $nom;
            AuditLog::log(Auth::id(), 'profile_update_info', 'user', Auth::id());
            H::flash('success', 'Information updated.');

        } elseif ($act === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            $user    = Database::fetchOne('SELECT password FROM users WHERE id=?', [Auth::id()]);
            if (!password_verify($current, $user['password'])) throw new Exception('Current password is incorrect.');
            if (strlen($new) < 8) throw new Exception('New password must be at least 8 characters.');
            if ($new !== $confirm) throw new Exception('Passwords do not match.');
            Database::execute('UPDATE users SET password=? WHERE id=?',
                [password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]), Auth::id()]);
            AuditLog::log(Auth::id(), 'profile_change_password', 'user', Auth::id());
            H::flash('success', 'Password changed successfully.');
        }
    } catch (Exception $e) {
        H::flash('error', $e->getMessage());
    }
    H::redirect(APP_URL . '/profile.php');
}

$user      = Database::fetchOne('SELECT * FROM users WHERE id=?', [Auth::id()]);
$stats     = UserModel::stats(Auth::id());
$lastLogins = Database::fetchAll(
    "SELECT date_action, ip, detail FROM audit_logs
     WHERE user_id=? AND action='login'
     ORDER BY date_action DESC LIMIT 5",
    [Auth::id()]
);

Layout::start('My Profile', 0);
?>

<div class="toolbar">
    <span style="font-weight:600;font-size:14px">👤 My Profile</span>
</div>

<div class="content">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px">

<!-- Colonne gauche : infos + stats -->
<div>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
            <div style="width:56px;height:56px;border-radius:50%;background:var(--primary-light);
                        display:flex;align-items:center;justify-content:center;
                        font-size:20px;font-weight:700;color:var(--primary);flex-shrink:0">
                <?= strtoupper(substr($user['nom'], 0, 2)) ?>
            </div>
            <div>
                <div style="font-size:16px;font-weight:600"><?= H::e($user['nom']) ?></div>
                <div class="text-muted text-small"><?= H::e($user['email']) ?></div>
                <span class="badge <?= $user['role']==='admin' ? 'badge-info' : 'badge-secondary' ?>" style="margin-top:4px">
                    <?= $user['role']==='admin' ? '⚙ Administrateur' : '👤 Utilisateur' ?>
                </span>
            </div>
        </div>
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="profile_action" value="update_info">
            <div class="form-group">
                <label class="form-label">Full name</label>
                <input class="form-control" type="text" name="nom" value="<?= H::e($user['nom']) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label">Email address</label>
                <input class="form-control" type="email" value="<?= H::e($user['email']) ?>" disabled>
                <div class="form-hint">Contact an administrator to change the email.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Member since</label>
                <input class="form-control" value="<?= H::formatDate($user['created_at']) ?>" disabled>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>

    <!-- Stats -->
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:20px;margin-bottom:16px">
        <div class="detail-title">Personal statistics</div>
        <div class="detail-row"><span class="detail-key">Files uploaded</span><span class="detail-val"><strong><?= $stats['nb_fichiers'] ?></strong></span></div>
        <div class="detail-row"><span class="detail-key">Storage used</span><span class="detail-val"><strong><?= H::formatSize($stats['taille_total']) ?></strong></span></div>
        <div class="detail-row"><span class="detail-key">Recorded actions</span><span class="detail-val"><strong><?= $stats['nb_actions'] ?></strong></span></div>
    </div>

    <!-- Recent logins -->
    <?php if (!empty($lastLogins)): ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:20px">
        <div class="detail-title">Recent logins</div>
        <?php foreach ($lastLogins as $l): ?>
        <div class="detail-row">
            <span class="detail-key text-small"><?= H::formatDate($l['date_action']) ?></span>
            <span class="detail-val text-small text-muted"><?= H::e($l['ip'] ?? '—') ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Colonne droite : mot de passe -->
<div>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:4px;padding:20px">
        <div class="detail-title" style="margin-bottom:14px">🔒 Change password</div>
        <form method="POST">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="profile_action" value="change_password">
            <div class="form-group">
                <label class="form-label">Current password</label>
                <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label">New password</label>
                <input class="form-control" type="password" name="new_password" required minlength="8" autocomplete="new-password" id="np">
                <div class="form-hint">Minimum 8 characters.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm</label>
                <input class="form-control" type="password" name="confirm_password" required autocomplete="new-password" id="np2">
                <div id="pw-match" class="form-hint" style="display:none;color:var(--danger)">Passwords do not match.</div>
            </div>
            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </div>
</div>

</div>
</div>

<script>
// Vérification match mot de passe en temps réel
const np  = document.getElementById('np');
const np2 = document.getElementById('np2');
const msg = document.getElementById('pw-match');
[np, np2].forEach(el => el?.addEventListener('input', () => {
    const show = np2.value && np.value !== np2.value;
    msg.style.display = show ? 'block' : 'none';
}));
</script>

<?php Layout::end([['id'=>0,'nom'=>'My Profile']]); ?>
