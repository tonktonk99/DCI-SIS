<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('course_catalog');
$crumb = __('office_of_registrar') . ' / ' . __('academic_setup');
$message = '';

$programStmt = $pdo->query("SELECT * FROM programs WHERE status = 'active' ORDER BY program_name_th ASC");
$programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $program_id = (int)($_POST['program_id'] ?? 0);
    $course_code = trim($_POST['course_code'] ?? '');
    $course_name_th = trim($_POST['course_name_th'] ?? '');
    $course_name_en = trim($_POST['course_name_en'] ?? '');
    $credits = (int)($_POST['credits'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = input_enum($_POST, 'status', ['active', 'inactive'], 'active');

    if ($course_code === '' || $course_name_th === '') {
        $message = __('fill_course_code_name');
    } else {
        $check = $pdo->prepare("SELECT id FROM courses WHERE course_code = ? LIMIT 1");
        $check->execute([$course_code]);

        if ($check->fetchColumn()) {
            $message = __('duplicate_course_code') . ': ' . $course_code;
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO courses (program_id, course_code, course_name_th, course_name_en, credits, description, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $program_id > 0 ? $program_id : null,
                    $course_code,
                    $course_name_th,
                    $course_name_en ?: null,
                    $credits,
                    $description ?: null,
                    $status
                ]);
                header('Location: courses.php');
                exit;
            } catch (PDOException $e) {
                $message = __('save_failed_duplicate_course');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT status FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();

        if ($currentStatus) {
            $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
            $update = $pdo->prepare("UPDATE courses SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $id]);
        }
    }
    header('Location: courses.php');
    exit;
}

$stmt = $pdo->query("SELECT courses.*, programs.program_code, programs.program_name_th FROM courses LEFT JOIN programs ON programs.id = courses.program_id ORDER BY courses.id DESC");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('academic_setup') ?></div>
            <div class="hero-title"><?= __('manage_courses') ?></div>
            <div class="hero-desc"><?= __('manage_courses_desc') ?></div>
        </div>

        <?php if ($message): ?><div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('course_list') ?></h3>
                <div class="card">
                    <table class="table">
                        <thead><tr><th><?= __('course_code') ?></th><th><?= __('course_title') ?></th><th><?= __('program_label') ?></th><th><?= __('credits') ?></th><th><?= __('status') ?></th><th><?= __('manage') ?></th></tr></thead>
                        <tbody>
                            <?php if (count($courses) === 0): ?><tr><td colspan="6"><?= __('no_course_records') ?></td></tr><?php endif; ?>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td class="mono"><?= htmlspecialchars($course['course_code']) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($course['course_name_th']) ?></div>
                                        <?php if (!empty($course['course_name_en'])): ?><div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($course['course_name_en']) ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($course['program_code'])): ?>
                                            <span class="mono"><?= htmlspecialchars($course['program_code']) ?></span>
                                            <div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($course['program_name_th']) ?></div>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td class="mono"><?= (int)$course['credits'] ?></td>
                                    <td><?= $course['status'] === 'active' ? '<span class="badge badge-green">' . __('active') . '</span>' : '<span class="badge badge-blue">' . __('inactive') . '</span>' ?></td>
                                    <td><form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= (int)$course['id'] ?>"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('toggle') ?></button></form></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('add_course') ?></h3>
                <div class="card">
                    <form method="POST" action="courses.php">
                        <?= csrf_field() ?>
                        <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('program_label') ?></label><select name="program_id" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"><option value=""><?= __('no_program_specified') ?></option><?php foreach ($programs as $program): ?><option value="<?= (int)$program['id'] ?>"><?= htmlspecialchars($program['program_code']) ?> - <?= htmlspecialchars($program['program_name_th']) ?></option><?php endforeach; ?></select></div>
                        <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('course_code') ?></label><input type="text" name="course_code" placeholder="<?= __('placeholder_course_code') ?>" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div>
                        <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('course_name_th') ?></label><input type="text" name="course_name_th" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div>
                        <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('course_name_en') ?></label><input type="text" name="course_name_en" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div>
                        <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('credits') ?></label><input type="number" name="credits" value="3" min="0" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div>
                        <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('course_description') ?></label><textarea name="description" rows="4" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></textarea></div>
                        <div style="margin-bottom:18px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('status') ?></label><select name="status" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"><option value="active"><?= __('active') ?></option><option value="inactive"><?= __('inactive') ?></option></select></div>
                        <button type="submit" class="btn"><?= __('save_course') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
