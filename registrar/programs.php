<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('degree_programs');
$crumb = __('office_of_registrar') . ' / ' . __('academic_setup');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $program_code = trim($_POST['program_code'] ?? '');
    $program_name_th = trim($_POST['program_name_th'] ?? '');
    $program_name_en = trim($_POST['program_name_en'] ?? '');
    $degree_level = trim($_POST['degree_level'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($program_code === '' || $program_name_th === '') {
        $message = __('fill_program_code_name');
    } else {
        $check = $pdo->prepare("SELECT id FROM programs WHERE program_code = ? LIMIT 1");
        $check->execute([$program_code]);

        if ($check->fetchColumn()) {
            $message = __('duplicate_program_code') . ': ' . $program_code;
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO programs (program_code, program_name_th, program_name_en, degree_level, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $program_code,
                    $program_name_th,
                    $program_name_en ?: null,
                    $degree_level ?: null,
                    $status
                ]);

                header('Location: programs.php');
                exit;
            } catch (PDOException $e) {
                $message = __('save_failed_duplicate_program');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT status FROM programs WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();

        if ($currentStatus) {
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            $update = $pdo->prepare("UPDATE programs SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $id]);
        }
    }
    header('Location: programs.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM programs ORDER BY id DESC");
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('academic_setup') ?></div>
            <div class="hero-title"><?= __('manage_programs') ?></div>
            <div class="hero-desc"><?= __('manage_programs_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('program_list') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('code') ?></th>
                                <th><?= __('program_name') ?></th>
                                <th><?= __('degree_level') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('manage') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($programs) === 0): ?>
                                <tr><td colspan="5"><?= __('no_program_records') ?></td></tr>
                            <?php endif; ?>

                            <?php foreach ($programs as $program): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($program['program_code']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($program['program_name_th']) ?>
                                        <?php if (!empty($program['program_name_en'])): ?>
                                            <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($program['program_name_en']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($program['degree_level'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($program['status'] === 'active'): ?>
                                            <span class="badge badge-green"><?= __('active') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-blue"><?= __('inactive') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$program['id'] ?>"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('toggle') ?></button></form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('add_program') ?></h3>
                <div class="card">
                    <form method="POST" action="programs.php">
                        <?= csrf_field() ?>
                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('program_code_label') ?></label>
                            <input type="text" name="program_code" placeholder="<?= __('placeholder_program_code') ?>" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('program_name_th_label') ?></label>
                            <input type="text" name="program_name_th" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('program_name_en_label') ?></label>
                            <input type="text" name="program_name_en" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('degree_level') ?></label>
                            <input type="text" name="degree_level" placeholder="<?= __('placeholder_degree_level') ?>" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('status') ?></label>
                            <select name="status" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;">
                                <option value="active"><?= __('active') ?></option>
                                <option value="inactive"><?= __('inactive') ?></option>
                            </select>
                        </div>

                        <button type="submit" class="btn"><?= __('save_program') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
