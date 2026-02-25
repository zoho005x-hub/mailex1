<?php
session_start();

// Password protection
$admin_password = "B0TH";
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-dark text-light d-flex align-items-center justify-content-center min-vh-100"><div class="card bg-secondary p-4"><h4>Password</h4><form method="post"><input type="password" name="pass" class="form-control mb-3" autofocus required><button type="submit" class="btn btn-primary w-100">Enter</button></form></div></body></html>';
        exit;
    }
}

// PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Default / submitted SMTP values
$smtp = [
    'host'     => $_POST['smtp_host']     ?? 'smtp.zeptomail.com',
    'port'     => $_POST['smtp_port']     ?? '587',
    'secure'   => $_POST['smtp_secure']   ?? 'tls',
    'username' => $_POST['smtp_username'] ?? 'emailapikey',
    'password' => $_POST['smtp_password'] ?? 'YOUR_LONG_PASSWORD_HERE',
    'from_email' => $_POST['sender_email'] ?? 'postmail@treworgy-baldacci.cc',
    'from_name'  => $_POST['sender_name']  ?? 'Your App Name',
];

// Result messages
$alert = '';
$alert_type = '';

// Handle Test SMTP
if (isset($_POST['action']) && $_POST['action'] === 'test_smtp') {
    $test_email = trim($_POST['test_email'] ?? '');
    if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $alert = "Invalid test email.";
        $alert_type = 'danger';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->Port       = (int)$smtp['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];

            $mail->setFrom($smtp['from_email'], $smtp['from_name']);
            $mail->addAddress($test_email);

            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test - 4RR0W Mailer';
            $mail->Body    = '<h3>Test Successful</h3><p>SMTP settings are working.<br>Time: ' . date('Y-m-d H:i:s') . '</p>';
            $mail->AltBody = "Test Successful\nTime: " . date('Y-m-d H:i:s');

            $mail->send();
            $alert = "Test email sent to <b>$test_email</b>!";
            $alert_type = 'success';
        } catch (Exception $e) {
            $alert = "SMTP Error: " . htmlspecialchars($mail->ErrorInfo);
            $alert_type = 'danger';
        }
    }
}

// ... (keep your full sending logic here for action === 'send')
// You can paste your existing sending code (with placeholders, attachments, validation, etc.)

// If no alert and not sending, show form
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>4RR0W Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body{font-size:0.9rem;padding:1rem;background:#f8f9fa;}
        .card{max-width:540px;margin:auto;border:none;box-shadow:0 1px 10px rgba(0,0,0,0.1);border-radius:8px;}
        .card-header{background:#0d6efd;color:white;padding:0.75rem;text-align:center;font-weight:600;}
        .form-label{font-size:0.85rem;margin-bottom:0.3rem;}
        .form-control-sm{font-size:0.85rem;padding:0.4rem 0.6rem;}
        .btn-sm{padding:0.4rem 0.9rem;}
        .tight-mb{margin-bottom:0.5rem!important;}
        .alert-dismissible .btn-close{position:absolute;top:0;right:0;padding:0.75rem;}
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">4RR0W SMTP Mailer</div>
    <div class="card-body p-3">

        <?php if ($alert): ?>
        <div class="alert alert-<?=$alert_type?> alert-dismissible fade show mb-3" role="alert">
            <?=$alert?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" id="mailerForm">
            <input type="hidden" name="action" id="formAction" value="send">

            <!-- SMTP -->
            <div class="row g-2 tight-mb">
                <div class="col-7">
                    <label class="form-label">Host</label>
                    <input type="text" name="smtp_host" class="form-control form-control-sm" value="<?=htmlspecialchars($smtp['host'])?>" required>
                </div>
                <div class="col-5">
                    <label class="form-label">Port</label>
                    <input type="number" name="smtp_port" class="form-control form-control-sm" value="<?=htmlspecialchars($smtp['port'])?>" required>
                </div>
            </div>

            <div class="tight-mb">
                <label class="form-label">Secure</label>
                <select name="smtp_secure" class="form-select form-select-sm">
                    <option value="" <?= $smtp['secure']===''?'selected':'' ?>>None</option>
                    <option value="tls" <?= $smtp['secure']==='tls'?'selected':'' ?>>TLS</option>
                    <option value="ssl" <?= $smtp['secure']==='ssl'?'selected':'' ?>>SSL</option>
                </select>
            </div>

            <div class="tight-mb">
                <label class="form-label">Username</label>
                <input type="text" name="smtp_username" class="form-control form-control-sm" value="<?=htmlspecialchars($smtp['username'])?>" required>
            </div>

            <div class="tight-mb">
                <label class="form-label">Password</label>
                <input type="password" name="smtp_password" class="form-control form-control-sm" value="<?=htmlspecialchars($smtp['password'])?>" required>
            </div>

            <!-- Sender -->
            <div class="tight-mb">
                <label class="form-label">From Name</label>
                <input type="text" name="sender_name" class="form-control form-control-sm" value="<?=htmlspecialchars($smtp['from_name'])?>" required>
            </div>

            <div class="tight-mb">
                <label class="form-label">From Email</label>
                <input type="email" name="sender_email" class="form-control form-control-sm" value="<?=htmlspecialchars($smtp['from_email'])?>" required>
            </div>

            <!-- Rest of form (subject, message, recipients, etc.) -->
            <!-- ... paste your existing fields here (subject, body, attach_receiver_email, attachments, recipients) ... -->

            <!-- Action buttons -->
            <div class="row g-2 tight-mb">
                <div class="col-5">
                    <label class="form-label">Test To</label>
                    <input type="email" name="test_email" class="form-control form-control-sm" placeholder="your@email.com">
                </div>
                <div class="col-7 d-flex gap-2 align-items-end">
                    <button type="submit" name="action" value="test_smtp" class="btn btn-outline-warning btn-sm flex-fill">
                        <i class="bi bi-lightning"></i> Test
                    </button>
                    <button type="submit" name="action" value="send" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-send"></i> Send
                    </button>
                </div>
            </div>

            <div class="text-center mt-2 small text-muted">
                Test SMTP first • 4RR0W H43D
            </div>
        </form>
    </div>
</div>

<!-- Your Preview Modal + JS here -->
<!-- ... -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Your live preview script -->
</body>
</html>
