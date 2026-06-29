<?php
require __DIR__ . '/config/session.php';
$_SESSION['lang'] = 'en';
require __DIR__ . '/includes/lang.php';
require __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'] ?? '';

    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    }
    if ($role === 'registrar') {
        header('Location: registrar/dashboard.php');
        exit;
    }
    if ($role === 'student') {
        header('Location: student/dashboard.php');
        exit;
    }
    if ($role === 'professor') {
        header('Location: professor/dashboard.php');
        exit;
    }
    if ($role === 'alumni') {
        header('Location: alumni/dashboard.php');
        exit;
    }
}

$hasError = isset($_GET['error']);
?>
<!doctype html>
<html lang="<?= currentLang() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= __('login_title') ?> | DCI Academic Portal</title>
    <style>
        :root {
            --crimson: #A51C30;
            --crimson-dark: #7A0F1F;
            --ink: #1E1E1E;
            --soft-ink: #4B4B4B;
            --muted: #747474;
            --paper: #F7F3EA;
            --panel: #FFFFFF;
            --line: #D8D0C2;
            --gold: #B08A38;
            --gold-soft: #E8D9B7;
            --danger: #A51C30;
            --danger-bg: #FFF1F1;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Georgia, 'Times New Roman', serif;
            color: var(--ink);
            background:
                linear-gradient(90deg, rgba(165, 28, 48, 0.94), rgba(122, 15, 31, 0.92)),
                radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.12), transparent 30%);
            display: grid;
            place-items: center;
            padding: 30px;
        }

        .page {
            width: min(1160px, 100%);
            min-height: 650px;
            display: grid;
            grid-template-columns: 0.92fr 1.08fr;
            background: var(--paper);
            border: 1px solid rgba(255,255,255,0.24);
            box-shadow: 0 28px 90px rgba(0,0,0,0.32);
        }

        .identity {
            position: relative;
            padding: 46px 44px;
            background: var(--crimson);
            color: #fff;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .identity::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(rgba(255,255,255,0.055) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.055) 1px, transparent 1px);
            background-size: 42px 42px;
            opacity: 0.65;
        }

        .identity::after {
            content: "DCI";
            position: absolute;
            right: -22px;
            bottom: -44px;
            font-size: 170px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: -0.08em;
            color: rgba(255,255,255,0.08);
        }

        .identity-inner {
            position: relative;
            z-index: 1;
        }

        .crest-row {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 62px;
        }

        .crest {
            width: 92px;
            height: 92px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            padding: 0;
            overflow: visible;
            background: transparent;
            border: 0;
            box-shadow: none;
            flex: 0 0 92px;
        }

        .crest img {
            width: 92px;
            height: 92px;
            object-fit: contain;
            border-radius: 999px;
            display: block;
        }

        .school-name {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: 0.01em;
        }

        .school-sub {
            margin-top: 4px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
            opacity: 0.78;
        }

        .label {
            display: inline-block;
            margin-bottom: 22px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.5);
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--gold-soft);
        }

        .headline {
            margin: 0;
            font-size: clamp(38px, 4.9vw, 68px);
            line-height: 0.98;
            letter-spacing: -0.055em;
            font-weight: 700;
        }

        .copy {
            max-width: 430px;
            margin: 24px 0 0;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.85;
            color: rgba(255,255,255,0.82);
        }

        .identity-footer {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-top: 1px solid rgba(255,255,255,0.28);
            margin-top: 44px;
        }

        .stat {
            padding: 18px 14px 0 0;
        }

        .stat strong {
            display: block;
            color: var(--gold-soft);
            font-size: 22px;
            line-height: 1;
        }

        .stat span {
            display: block;
            margin-top: 7px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: rgba(255,255,255,0.72);
        }

        .login-side {
            padding: 54px 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.80), rgba(247,243,234,0.95)),
                var(--paper);
        }

        .login-card {
            width: min(430px, 100%);
        }

        .portal-label {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: var(--crimson);
            font-weight: 700;
            margin-bottom: 14px;
        }

        .login-title {
            margin: 0 0 10px;
            font-size: 38px;
            line-height: 1.05;
            letter-spacing: -0.035em;
            color: var(--ink);
        }

        .login-desc {
            margin: 0 0 30px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.75;
            color: var(--soft-ink);
        }

        .alert {
            padding: 13px 14px;
            background: var(--danger-bg);
            border-left: 4px solid var(--danger);
            color: var(--danger);
            font-family: Tahoma, Arial, sans-serif;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .field {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #3E352B;
        }

        input {
            width: 100%;
            min-height: 50px;
            border: 1px solid var(--line);
            background: #fff;
            padding: 12px 14px;
            color: var(--ink);
            font-family: Tahoma, Arial, sans-serif;
            font-size: 14px;
            outline: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        input:focus {
            border-color: var(--crimson);
            box-shadow: 0 0 0 4px rgba(165, 28, 48, 0.10);
        }

        .button {
            width: 100%;
            min-height: 52px;
            border: 0;
            background: var(--ink);
            color: #fff;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.02em;
            cursor: pointer;
            margin-top: 8px;
        }

        .button:hover {
            background: var(--crimson-dark);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 30px 0 16px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        .demo-box {
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.56);
            padding: 16px;
        }

        .demo-title {
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: var(--crimson);
            margin-bottom: 10px;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
        }

        .demo-item {
            padding: 8px 10px;
            background: #fff;
            border: 1px solid #E4DCCA;
        }

        .demo-item strong {
            color: var(--ink);
        }

        .small-note {
            margin-top: 22px;
            text-align: center;
            font-family: Tahoma, Arial, sans-serif;
            color: var(--muted);
            font-size: 11px;
        }

        @media (max-width: 920px) {
            body {
                padding: 16px;
                display: block;
            }

            .page {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .identity,
            .login-side {
                padding: 34px;
            }

            .headline {
                font-size: 44px;
            }

            .identity-footer {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .identity,
            .login-side {
                padding: 24px;
            }

            .crest-row {
                margin-bottom: 34px;
            }

            .crest {
                width: 82px;
                height: 82px;
                flex-basis: 82px;
            }

            .crest img {
                width: 82px;
                height: 82px;
            }

            .demo-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="identity">
            <div class="identity-inner">
                <div class="crest-row">
                    <div class="crest">
                        <img src="/dci-sis/assets/images/logo.png" alt="DCI Logo">
                    </div>
                    <div>
                        <div class="school-name"><?= __('dci_name_en') ?></div>
                    </div>
                </div>

                <div class="label"><?= __('academic_info_system') ?></div>
                <h1 class="headline"><?= __('login_headline') ?></h1>
                <p class="copy"><?= __('login_copy') ?></p>
            </div>

            <div class="identity-footer">
                <div class="stat"><strong><?= __('sis_label') ?></strong><span><?= __('sis_text') ?></span></div>
                <div class="stat"><strong><?= __('gpa_label') ?></strong><span><?= __('gpa_text') ?></span></div>
                <div class="stat"><strong><?= __('doc_label') ?></strong><span><?= __('doc_text') ?></span></div>
            </div>
        </section>

        <section class="login-side">
            <div class="login-card">
                <div class="portal-label"><?= __('secure_sign_in') ?></div>
                <h2 class="login-title"><?= __('login_title') ?></h2>
                <p class="login-desc"><?= __('login_desc') ?></p>

                <?php if ($hasError): ?>
                    <div class="alert"><?= __('login_error') ?></div>
                <?php endif; ?>

                <form method="POST" action="actions/login-action.php" autocomplete="on">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="username"><?= __('username') ?></label>
                        <input id="username" type="text" name="username" required autofocus placeholder="admin">
                    </div>

                    <div class="field">
                        <label for="password"><?= __('password') ?></label>
                        <input id="password" type="password" name="password" required placeholder="<?= __('password_placeholder') ?>">
                    </div>

                    <button type="submit" class="button"><?= __('login_button') ?></button>
                </form>

                <?php if (APP_DEBUG): ?>
                <div class="divider"><?= __('demo_access') ?></div>

                <div class="demo-box">
                    <div class="demo-title"><?= __('demo_accounts') ?></div>
                    <div class="demo-grid">
                        <div class="demo-item"><strong>admin</strong> / 1234</div>
                        <div class="demo-item"><strong>registrar1</strong> / 1234</div>
                        <div class="demo-item"><strong>prof1</strong> / 1234</div>
                        <div class="demo-item"><strong>student1</strong> / 1234</div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="small-note"><?= __('dci_name_en') ?></div>
            </div>
        </section>
    </main>
</body>
</html>
