<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('student_records');
$crumb = __('office_of_registrar') . ' / ' . __('student_records');
$message = '';

$userStmt = $pdo->query("SELECT id, username FROM users WHERE role = 'student' ORDER BY username ASC");
$studentUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$programStmt = $pdo->query("SELECT * FROM programs WHERE status = 'active' ORDER BY program_name_th ASC");
$programs = $programStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user_id = (int)($_POST['user_id'] ?? 0);
    $student_code = trim($_POST['student_code'] ?? '');
    $program_id = (int)($_POST['program_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $admission_year = trim($_POST['admission_year'] ?? '');
    $study_status = input_enum($_POST, 'study_status', ['studying', 'leave', 'graduated', 'withdrawn', 'suspended'], 'studying');
    $cumulative_gpa = (float)($_POST['cumulative_gpa'] ?? 0);
    $total_credits_earned = (int)($_POST['total_credits_earned'] ?? 0);

    if ($student_code === '' || $first_name === '' || $last_name === '') {
        $message = __('fill_student_code_name');
    } else {
        $check = $pdo->prepare("SELECT id FROM students WHERE student_code = ? LIMIT 1");
        $check->execute([$student_code]);

        if ($check->fetchColumn()) {
            $message = __('duplicate_student_code') . ': ' . $student_code;
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO students (user_id, student_code, program_id, first_name, last_name, admission_year, study_status, cumulative_gpa, total_credits_earned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user_id > 0 ? $user_id : null,
                    $student_code,
                    $program_id > 0 ? $program_id : null,
                    $first_name,
                    $last_name,
                    $admission_year ?: null,
                    $study_status,
                    $cumulative_gpa,
                    $total_credits_earned
                ]);
                header('Location: students.php');
                exit;
            } catch (PDOException $e) {
                $message = __('save_failed_duplicate');
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $allowed = ['studying', 'leave', 'graduated', 'withdrawn', 'suspended'];
    if ($id > 0 && in_array($newStatus, $allowed, true)) {
        $stmt = $pdo->prepare("UPDATE students SET study_status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
    }
    header('Location: students.php');
    exit;
}

$stmt = $pdo->query("SELECT students.*, users.username, programs.program_code, programs.program_name_th FROM students LEFT JOIN users ON users.id = students.user_id LEFT JOIN programs ON programs.id = students.program_id ORDER BY students.id DESC");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
    <div class="content">
        <div class="hero-card"><div class="hero-kicker"><?= __('student_records') ?></div><div class="hero-title"><?= __('manage_students') ?></div><div class="hero-desc"><?= __('manage_students_desc') ?></div></div>
        <?php if ($message): ?><div class="card" style="border-left:4px solid #c89028;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <div class="grid-2">
            <div>
                <h3 class="section-title"><?= __('student_list') ?></h3>
                <div class="card"><table class="table"><thead><tr><th><?= __('code') ?></th><th><?= __('name') ?></th><th><?= __('account') ?></th><th><?= __('program_label') ?></th><th><?= __('year_entered') ?></th><th>GPA</th><th><?= __('credits') ?></th><th><?= __('status') ?></th><th><?= __('manage') ?></th></tr></thead><tbody>
                    <?php if (count($students) === 0): ?><tr><td colspan="9"><?= __('no_student_records') ?></td></tr><?php endif; ?>
                    <?php foreach ($students as $student): ?><tr>
                        <td class="mono"><?= htmlspecialchars($student['student_code']) ?></td>
                        <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                        <td><?= htmlspecialchars($student['username'] ?? '-') ?></td>
                        <td><?php if (!empty($student['program_code'])): ?><span class="mono"><?= htmlspecialchars($student['program_code']) ?></span><div style="font-size:12px;color:#8a7c5e;"><?= htmlspecialchars($student['program_name_th']) ?></div><?php else: ?>-<?php endif; ?></td>
                        <td class="mono"><?= htmlspecialchars($student['admission_year'] ?? '-') ?></td>
                        <td class="mono"><?= number_format((float)$student['cumulative_gpa'], 2) ?></td>
                        <td class="mono"><?= (int)$student['total_credits_earned'] ?></td>
                        <td><span class="badge badge-blue"><?= htmlspecialchars($student['study_status']) ?></span></td>
                        <td><div style="display:flex;gap:6px;flex-wrap:wrap;"><form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= (int)$student['id'] ?>"><input type="hidden" name="status" value="studying"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('status_studying') ?></button></form><form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= (int)$student['id'] ?>"><input type="hidden" name="status" value="leave"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('status_leave') ?></button></form><form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= (int)$student['id'] ?>"><input type="hidden" name="status" value="graduated"><button type="submit" class="btn btn-light" style="padding:6px 10px;font-size:11px;"><?= __('status_graduated') ?></button></form></div></td>
                    </tr><?php endforeach; ?>
                </tbody></table></div>
            </div>
            <div>
                <h3 class="section-title"><?= __('add_student') ?></h3>
                <div class="card"><form method="POST" action="students.php">
                    <?= csrf_field() ?>
                    <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('user_account') ?></label><select name="user_id" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"><option value=""><?= __('no_linked_account') ?></option><?php foreach ($studentUsers as $studentUser): ?><option value="<?= (int)$studentUser['id'] ?>"><?= htmlspecialchars($studentUser['username']) ?></option><?php endforeach; ?></select></div>
                    <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('student_id_label') ?></label><input type="text" name="student_code" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;"><div><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('first_name') ?></label><input type="text" name="first_name" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div><div><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('last_name') ?></label><input type="text" name="last_name" required style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div></div>
                    <div style="margin-bottom:14px;"><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('program_label') ?></label><select name="program_id" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"><option value=""><?= __('no_program_specified') ?></option><?php foreach ($programs as $program): ?><option value="<?= (int)$program['id'] ?>"><?= htmlspecialchars($program['program_code']) ?> - <?= htmlspecialchars($program['program_name_th']) ?></option><?php endforeach; ?></select></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;"><div><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('year_entered') ?></label><input type="text" name="admission_year" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div><div><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('status') ?></label><select name="study_status" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"><option value="studying"><?= __('status_studying') ?></option><option value="leave"><?= __('status_leave') ?></option><option value="graduated"><?= __('status_graduated') ?></option><option value="withdrawn"><?= __('status_withdrawn') ?></option><option value="suspended"><?= __('status_suspended') ?></option></select></div></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px;"><div><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('cumulative_gpa') ?></label><input type="number" step="0.01" min="0" max="4" name="cumulative_gpa" value="0.00" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div><div><label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;"><?= __('credits_earned') ?></label><input type="number" min="0" name="total_credits_earned" value="0" style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"></div></div>
                    <button type="submit" class="btn"><?= __('save_student') ?></button>
                </form></div>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
