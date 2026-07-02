<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('admin');
$user = getUser();

$pageTitle = __('user_accounts');
$crumb = __('administration') . ' / ' . __('user_accounts');
$message = '';

$allowedRoles = ['admin', 'registrar', 'professor', 'student', 'alumni'];
$currentUserId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = input_enum($_POST, 'action', ['create', 'change_role', 'reset_password'], '');

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? '');

        if ($username === '' || $password === '' || !in_array($role, $allowedRoles, true)) {
            $message = __('fill_user_fields');
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $check->execute([$username]);

            if ($check->fetchColumn()) {
                $message = __('username_exists');
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->execute([$username, $passwordHash, $role]);
                $newUserId = (int)$pdo->lastInsertId();

                logAudit(
                    $pdo,
                    $currentUserId,
                    'USER.CREATE',
                    'users',
                    $newUserId,
                    'Created user: ' . $username . ' role=' . $role
                );

                header('Location: users.php');
                exit;
            }
        }
    }

    if ($action === 'change_role') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $role = trim($_POST['role'] ?? '');

        if ($targetUserId <= 0 || !in_array($role, $allowedRoles, true)) {
            $message = __('cannot_change_role');
        } else {
            $oldStmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ? LIMIT 1");
            $oldStmt->execute([$targetUserId]);
            $oldUser = $oldStmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $targetUserId]);

            logAudit(
                $pdo,
                $currentUserId,
                'USER.ROLE_CHANGE',
                'users',
                $targetUserId,
                'Changed role for ' . ($oldUser['username'] ?? '-') . ' from ' . ($oldUser['role'] ?? '-') . ' to ' . $role
            );

            header('Location: users.php');
            exit;
        }
    }

    if ($action === 'reset_password') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newPassword = trim($_POST['new_password'] ?? '');

        if ($targetUserId <= 0 || $newPassword === '') {
            $message = __('fill_new_password');
        } else {
            $targetStmt = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $targetStmt->execute([$targetUserId]);
            $targetUsername = $targetStmt->fetchColumn();

            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newPasswordHash, $targetUserId]);

            logAudit(
                $pdo,
                $currentUserId,
                'USER.PASSWORD_RESET',
                'users',
                $targetUserId,
                'Reset password for user: ' . ($targetUsername ?: '-')
            );

            header('Location: users.php');
            exit;
        }
    }
}

$usersStmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
$roleCounts = [];
foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $roleCounts[$row['role']] = (int)$row['total'];
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('system_administration') ?></div>
            <div class="hero-title"><?= __('manage_users_title') ?></div>
            <div class="hero-desc"><?= __('manage_users_desc') ?></div>

            <div class="kpi-row">
                <div class="kpi"><div class="kpi-label"><?= __('users_label') ?></div><div class="kpi-value"><?= count($users) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('students') ?></div><div class="kpi-value"><?= (int)($roleCounts['student'] ?? 0) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('professors_label') ?></div><div class="kpi-value"><?= (int)($roleCounts['professor'] ?? 0) ?></div></div>
                <div class="kpi"><div class="kpi-label"><?= __('registrars_label') ?></div><div class="kpi-value"><?= (int)($roleCounts['registrar'] ?? 0) ?></div></div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('user_list') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?= __('username') ?></th>
                                <th><?= __('role') ?></th>
                                <th><?= __('created_at') ?></th>
                                <th><?= __('change_role') ?></th>
                                <th><?= __('reset_password') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) === 0): ?>
                                <tr><td colspan="6"><?= __('no_users') ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($users as $row): ?>
                                <tr>
                                    <td class="mono"><?= (int)$row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><span class="badge badge-blue"><?= htmlspecialchars($row['role']) ?></span></td>
                                    <td class="mono"><?= htmlspecialchars($row['created_at'] ?? '-') ?></td>
                                    <td>
                                        <form method="POST" action="users.php" style="display:flex;gap:6px;margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                            <select name="role" style="padding:6px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                                <?php foreach ($allowedRoles as $role): ?>
                                                    <option value="<?= htmlspecialchars($role) ?>" <?= $row['role'] === $role ? 'selected' : '' ?>><?= htmlspecialchars($role) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('save') ?></button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" action="users.php" style="display:flex;gap:6px;margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                            <input type="password" name="new_password" placeholder="<?= __('new_password_placeholder') ?>" style="width:120px;padding:6px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                            <button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('reset') ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('create_new_user') ?></h3>
                <div class="card">
                    <form method="POST" action="users.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('username') ?></label>
                            <input type="text" name="username" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('password') ?></label>
                            <input type="text" name="password" required placeholder="<?= __('password_example') ?>" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('role') ?></label>
                            <select name="role" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value=""><?= __('select_role') ?></option>
                                <?php foreach ($allowedRoles as $role): ?>
                                    <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn"><?= __('create_user') ?></button>
                    </form>
                </div>

                <h3 class="section-title" style="margin-top:28px;"><?= __('note') ?></h3>
                <div class="card">
                    <p style="margin-top:0;color:#5a4f3a;font-size:13px;">
                        <?= __('users_note_audit') ?>
                    </p>
                    <p style="margin-bottom:0;color:#5a4f3a;font-size:13px;">
                        <?= __('users_note_password') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
