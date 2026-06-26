<?php
require '../includes/auth.php';
checkLogin();
$user = getUser();

if (($user['role'] ?? '') !== 'admin') {
    die('Access denied');
}

$pageTitle = __('roles_permissions');
$crumb = __('administration') . ' / ' . __('roles_permissions');
$roles = [
    'admin' => [__('role_scope_admin')],
    'registrar' => [__('role_scope_registrar')],
    'professor' => [__('role_scope_professor')],
    'student' => [__('role_scope_student')],
    'alumni' => [__('role_scope_alumni')]
];
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main">
    <?php include '../includes/topbar.php'; ?>
    <div class="content">
        <div class="hero-card">
            <div class="hero-kicker"><?= __('system_administration') ?></div>
            <div class="hero-title"><?= __('roles_permissions') ?></div>
            <div class="hero-desc"><?= __('roles_desc') ?></div>
        </div>

        <div class="card">
            <table class="table">
                <thead>
                    <tr><th><?= __('role') ?></th><th><?= __('permission_scope') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role => $scopes): ?>
                        <tr>
                            <td><span class="badge badge-blue"><?= htmlspecialchars($role) ?></span></td>
                            <td>
                                <?php foreach ($scopes as $scope): ?>
                                    <div><?= htmlspecialchars($scope) ?></div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
