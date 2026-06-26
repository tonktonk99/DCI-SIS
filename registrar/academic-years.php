<?php
require '../includes/auth.php';
require '../config/database.php';

requireRole('registrar', 'admin');
$user = getUser();

$pageTitle = __('manage_academic_years');
$crumb = __('office_of_registrar') . ' / ' . __('academic_setup');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year_label = trim($_POST['year_label'] ?? '');
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $is_current = isset($_POST['is_current']) ? 1 : 0;

    if ($year_label === '') {
        $message = __('fill_academic_year');
    } else {
        if ($is_current === 1) {
            $pdo->query("UPDATE academic_years SET is_current = 0");
        }

        $stmt = $pdo->prepare("
            INSERT INTO academic_years (
                year_label,
                start_date,
                end_date,
                is_current
            ) VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $year_label,
            $start_date ?: null,
            $end_date ?: null,
            $is_current
        ]);

        header('Location: academic-years.php');
        exit;
    }
}

if (isset($_GET['set_current'])) {
    $id = (int)$_GET['set_current'];

    $pdo->query("UPDATE academic_years SET is_current = 0");

    $stmt = $pdo->prepare("UPDATE academic_years SET is_current = 1 WHERE id = ?");
    $stmt->execute([$id]);

    header('Location: academic-years.php');
    exit;
}

$stmt = $pdo->query("
    SELECT *
    FROM academic_years
    ORDER BY id DESC
");

$years = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="content">

        <div class="hero-card">
            <div class="hero-kicker"><?= __('academic_setup') ?></div>
            <div class="hero-title"><?= __('manage_academic_years') ?></div>
            <div class="hero-desc"><?= __('manage_academic_years_desc') ?></div>
        </div>

        <?php if ($message): ?>
            <div class="card" style="border-left:4px solid #c89028;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid-2">

            <div>
                <h3 class="section-title"><?= __('academic_year_list') ?></h3>

                <div class="card">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('academic_year_label') ?></th>
                                <th><?= __('start_date_label') ?></th>
                                <th><?= __('end_date_label') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('manage') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($years) === 0): ?>
                                <tr>
                                    <td colspan="5"><?= __('no_academic_year_records') ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($years as $year): ?>
                                <tr>
                                    <td class="mono">
                                        <?= htmlspecialchars($year['year_label']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($year['start_date'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($year['end_date'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?php if ((int)$year['is_current'] === 1): ?>
                                            <span class="badge badge-green"><?= __('current_label') ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-blue"><?= __('inactive') ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ((int)$year['is_current'] !== 1): ?>
                                            <a class="btn btn-light" href="academic-years.php?set_current=<?= (int)$year['id'] ?>">
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
                <h3 class="section-title"><?= __('add_academic_year') ?></h3>

                <div class="card">
                    <form method="post" action="academic-years.php">

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                <?= __('academic_year_label') ?>
                            </label>
                            <input
                                type="text"
                                name="year_label"
                                placeholder="<?= __('year_label_placeholder') ?>"
                                required
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                <?= __('academic_year_start') ?>
                            </label>
                            <input
                                type="date"
                                name="start_date"
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                        </div>

                        <div style="margin-bottom:14px;">
                            <label style="display:block;font-size:12px;color:#8a7c5e;margin-bottom:6px;">
                                <?= __('academic_year_end') ?>
                            </label>
                            <input
                                type="date"
                                name="end_date"
                                style="width:100%;padding:10px;border:1px solid #d9cfb8;background:#fff;font-family:inherit;"
                            >
                        </div>

                        <div style="margin-bottom:18px;">
                            <label style="font-size:13px;">
                                <input type="checkbox" name="is_current" value="1">
                                <?= __('set_as_current_year') ?>
                            </label>
                        </div>

                        <button type="submit" class="btn">
                            <?= __('save_academic_year') ?>
                        </button>

                    </form>
                </div>
            </div>

        </div>

    </div>
</main>

<?php include '../includes/footer.php'; ?>
