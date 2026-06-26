<?php
require 'includes/auth.php';
checkLogin();
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/sidebar.php'; ?>

<div class="main">

    <?php include 'includes/topbar.php'; ?>

    <div class="content">

        <div class="card">
            <h2>Welcome to DCI Academic Portal</h2>
            <p>ระบบบริหารการศึกษา (Student Information System)</p>
        </div>

        <div class="card">
            <h3>Dashboard (Mock)</h3>
            <p>ตรงนี้เดี๋ยวเราจะใส่ GPA / ตารางเรียน / เกรด</p>
        </div>

    </div>

</div>

<?php include 'includes/footer.php'; ?>