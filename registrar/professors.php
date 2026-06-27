<?php
require '../includes/auth.php';
require '../config/database.php';
require '../includes/audit.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('faculty_directory');
$crumb = __('office_of_registrar') . ' / ' . __('faculty_directory');
$message = '';
$currentUserId = (int)$user['id'];

$userStmt = $pdo->query("SELECT id, username FROM users WHERE role = 'professor' ORDER BY username ASC");
$professorUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user_id = (int)($_POST['user_id'] ?? 0);
    $staff_code = trim($_POST['staff_code'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $status = input_enum($_POST, 'status', ['active', 'inactive'], 'active');

    if ($staff_code === '' || $first_name === '' || $last_name === '') {
        $message = __('fill_professor_code_name');
    } else {
        $check = $pdo->prepare("SELECT id FROM staff WHERE staff_code = ? LIMIT 1");
        $check->execute([$staff_code]);

        if ($check->fetchColumn()) {
            $message = __('duplicate_professor_code') . ': ' . $staff_code;
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO staff (user_id, staff_code, first_name, last_name, position, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id > 0 ? $user_id : null, $staff_code, $first_name, $last_name, $position ?: null, $status]);
                $newStaffId = (int)$pdo->lastInsertId();
                logAudit($pdo, $currentUserId, 'STAFF.CREATE', 'staff', $newStaffId, 'Created professor: ' . $staff_code . ' ' . $first_name . ' ' . $last_name);
                header('Location: professors.php');
                exit;
            } catch (PDOException $e) {
                $message = __('save_failed_duplicate_professor');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT status FROM staff WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();

        if ($currentStatus) {
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            $update = $pdo->prepare("UPDATE staff SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $id]);
            logAudit($pdo, $currentUserId, 'STAFF.TOGGLE_STATUS', 'staff', $id, 'Status changed from ' . $currentStatus . ' to ' . $newStatus);
        }
    }
    header('Location: professors.php');
    exit;
}

$stmt = $pdo->query("SELECT staff.*, users.username FROM staff LEFT JOIN users ON users.id = staff.user_id ORDER BY staff.id DESC");
$staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
    <div class="content">
        <div class="hero-card"><div class="hero-kicker"><?= __('faculty_directory') ?></div><div class="hero-title"><?= __('manage_professors') ?></div><div class="hero-desc"><?= __('manage_professors_desc') ?></div></div>
        <?php if ($message): ?><div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <div class="grid-2">
            <div><h3 class="section-title"><?= __('professor_list') ?></h3><div class="card"><table class="table"><thead><tr><th><?= __('code') ?></th><th><?= __('name') ?></th><th><?= __('account') ?></th><th><?= __('position') ?></th><th><?= __('status') ?></th><th><?= __('manage') ?></th></tr></thead><tbody>
                <?php if (count($staffList) === 0): ?><tr><td colspan="6"><?= __('no_professor_records') ?></td></tr><?php endif; ?>
                <?php foreach ($staffList as $staff): ?><tr><td class="mono"><?= htmlspecialchars($staff['staff_code']) ?></td><td><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></td><td><?= htmlspecialchars($staff['username'] ?? '-') ?></td><td><?= htmlspecialchars($staff['position'] ?? '-') ?></td><td><?= $staff['status'] === 'active' ? '<span class="badge badge-green">' . __('active') . '</span>' : '<span class="badge badge-blue">' . __('inactive') . '</span>' ?></td><td><form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$staff['id'] ?>"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('toggle') ?></button></form></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
            <div><h3 class="section-title"><?= __('add_professor') ?></h3><div class="card"><form method="POST" action="professors.php">
                <?= csrf_field() ?>
                <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('user_account') ?></label><select name="user_id" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"><option value=""><?= __('no_linked_account') ?></option><?php foreach ($professorUsers as $professorUser): ?><option value="<?= (int)$professorUser['id'] ?>"><?= htmlspecialchars($professorUser['username']) ?></option><?php endforeach; ?></select></div>
                <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('professor_code') ?></label><input type="text" name="staff_code" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;"><div><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('first_name') ?></label><input type="text" name="first_name" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div><div><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('last_name') ?></label><input type="text" name="last_name" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div></div>
                <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('position') ?></label><input type="text" name="position" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div>
                <div style="margin-bottom:18px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('status') ?></label><select name="status" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"><option value="active"><?= __('active') ?></option><option value="inactive"><?= __('inactive') ?></option></select></div>
                <button type="submit" class="btn"><?= __('save_professor') ?></button>
            </form></div></div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
