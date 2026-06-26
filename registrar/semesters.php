<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('manage_semesters');
$crumb = __('office_of_registrar') . ' / ' . __('academic_setup');

$message = '';

$yearStmt = $pdo->query("
    SELECT *
    FROM academic_years
    ORDER BY id DESC
");
$academicYears = $yearStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $academic_year_id = (int)($_POST['academic_year_id'] ?? 0);
    $semester_name = trim($_POST['semester_name'] ?? '');
    $term = trim($_POST['term'] ?? '');
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $registration_start = $_POST['registration_start'] ?? null;
    $registration_end = $_POST['registration_end'] ?? null;
    $grade_release_date = $_POST['grade_release_date'] ?? null;
    $status = $_POST['status'] ?? 'upcoming';
    $is_current = isset($_POST['is_current']) ? 1 : 0;

    if ($academic_year_id <= 0 || $semester_name === '' || $term === '') {
        $message = __('fill_semester_fields');
    } else {
        if ($is_current === 1) {
            $pdo->query("UPDATE semesters SET is_current = 0");
        }

        $stmt = $pdo->prepare("
            INSERT INTO semesters (
                academic_year_id,
                semester_name,
                term,
                start_date,
                end_date,
                registration_start,
                registration_end,
                grade_release_date,
                status,
                is_current
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $academic_year_id,
            $semester_name,
            $term,
            $start_date ?: null,
            $end_date ?: null,
            $registration_start ?: null,
            $registration_end ?: null,
            $grade_release_date ?: null,
            $status,
            $is_current
        ]);

        header('Location: semesters.php');
        exit;
    }
}

if (isset($_GET['set_current'])) {
    $id = (int)$_GET['set_current'];

    $pdo->query("UPDATE semesters SET is_current = 0");

    $stmt = $pdo->prepare("UPDATE semesters SET is_current = 1 WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: semesters.php');
    exit;
}

$stmt = $pdo->query("
    SELECT
        semesters.*,
        academic_years.year_label
    FROM semesters
    JOIN academic_years ON academic_years.id = semesters.academic_year_id
    ORDER BY semesters.id DESC
");

$semesters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">

        <div class="hero-card">
            <div class="hero-kicker"><?= __('academic_setup') ?></div>
            <div class="hero-title"><?= __('manage_semesters') ?></div>
            <div class="hero-desc"><?= __('manage_semesters_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid-2">

            <div>
                <h3 class="section-title"><?= __('semester_list') ?></h3>

                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('academic_year_label') ?></th>
                                <th><?= __('semester_name_label') ?></th>
                                <th><?= __('term_label') ?></th>
                                <th><?= __('registration_label') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('current_label') ?></th>
                                <th><?= __('manage') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($semesters) === 0): ?>
                                <tr>
                                    <td colspan="7"><?= __('no_semester_records') ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($semesters as $semester): ?>
                                <tr>
                                    <td class="mono">
                                        <?= htmlspecialchars($semester['year_label']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($semester['semester_name']) ?>
                                    </td>

                                    <td class="mono">
                                        <?= htmlspecialchars($semester['term']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($semester['registration_start'] ?? '-') ?>
                                        <?= __('to_label') ?>
                                        <?= htmlspecialchars($semester['registration_end'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?php if ($semester['status'] === 'active'): ?>
                                            <span class="badge badge-green"><?= __('active') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-blue">
                                                <?= htmlspecialchars($semester['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ((int)$semester['is_current'] === 1): ?>
                                            <span class="badge badge-green"><?= __('current_label') ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ((int)$semester['is_current'] !== 1): ?>
                                            <a class="btn btn-light" href="semesters.php?set_current=<?= (int)$semester['id'] ?>">
                                                <?= __('set_current') ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="section-title"><?= __('add_semester') ?></h3>

                <div class="card">
                    <form method="POST" action="semesters.php">

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                <?= __('academic_year_label') ?>
                            </label>
                            <select
                                name="academic_year_id"
                                required
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                                <option value=""><?= __('select_academic_year') ?></option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= (int)$year['id'] ?>">
                                        <?= htmlspecialchars($year['year_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                <?= __('semester_name_field') ?>
                            </label>
                            <input
                                type="text"
                                name="semester_name"
                                placeholder="<?= __('semester_name_placeholder') ?>"
                                required
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                <?= __('term_label') ?>
                            </label>
                            <select
                                name="term"
                                required
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                                <option value=""><?= __('select_term') ?></option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="summer"><?= __('term_summer') ?></option>
                            </select>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                    <?= __('class_start_date') ?>
                                </label>
                                <input
                                    type="date"
                                    name="start_date"
                                    style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                                >
                            </div>

                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                    <?= __('end_date_label') ?>
                                </label>
                                <input
                                    type="date"
                                    name="end_date"
                                    style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                                >
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                    <?= __('registration_open') ?>
                                </label>
                                <input
                                    type="date"
                                    name="registration_start"
                                    style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                                >
                            </div>

                            <div>
                                <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                    <?= __('registration_close') ?>
                                </label>
                                <input
                                    type="date"
                                    name="registration_end"
                                    style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                                >
                            </div>
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                <?= __('grade_release_date') ?>
                            </label>
                            <input
                                type="date"
                                name="grade_release_date"
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                <?= __('status') ?>
                            </label>
                            <select
                                name="status"
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                                <option value="upcoming"><?= __('status_upcoming') ?></option>
                                <option value="registration"><?= __('status_registration') ?></option>
                                <option value="active"><?= __('active') ?></option>
                                <option value="grading"><?= __('status_grading') ?></option>
                                <option value="closed"><?= __('status_closed') ?></option>
                            </select>
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="font-size:13px;">
                                <input type="checkbox" name="is_current" value="1">
                                <?= __('set_as_current_semester') ?>
                            </label>
                        </div>

                        <button type="submit" class="btn">
                            <?= __('save_semester') ?>
                        </button>

                    </form>
                </div>
            </div>

        </div>

    </div>
</main>

<?php include '../includes/footer.php'; ?>
