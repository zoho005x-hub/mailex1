<?php
/**
 * Modern Bulk Mailer – 2026 Edition (Bootstrap 5)
 * ZeptoMail SMTP + Reply-To + Progress Feedback
 */

// Show errors during testing (disable in production!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ────────────────────────────────────────────────
// CONFIG
// ────────────────────────────────────────────────
$smtp = [
    'host'       => 'smtp.zeptomail.com',
    'port'       => 587,
    'secure'     => 'tls',
    'username'   => 'emailapikey',
    'password'   => 'wSsVR613+0LyBqt0yTavdO4wyggHAVykHBh03Val6XP8Gv/E98c5khfMBwPyFaIYEjJuFTsW8Lp7n0oJhzJYjdh5z1AICSiF9mqRe1U4J3x17qnvhDzNWWhflxGPKY8Oww9rk2hjFMoq+g==',
    'from_email' => 'postmail@treworgy-baldacci.cc',
    'from_name'  => 'Your App Name',
];

$admin_password = "B0TH";          // ← CHANGE THIS!
$delay_us       = 150000;           // 0.15 sec delay

// ────────────────────────────────────────────────
// PASSWORD PROTECTION
// ────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="en" data-bs-theme="light">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Login - Bulk Mailer</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
            <style>
                body { background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .login-card { max-width: 420px; width: 100%; }
            </style>
        </head>
        <body>
            <div class="card login-card shadow-lg border-0">
                <div class="card-body p-5 text-center">
                    <h3 class="mb-4"><i class="bi bi-shield-lock me-2"></i>Secure Access</h3>
                    <form method="post">
                        <div class="mb-3">
                            <input type="password" name="pass" class="form-control form-control-lg" placeholder="Enter password" autofocus required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
                    </form>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// ────────────────────────────────────────────────
// LOAD PHPMailer
// ────────────────────────────────────────────────
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ────────────────────────────────────────────────
// SENDING LOGIC
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $to_list     = trim($_POST['emails'] ?? '');
    $subject_raw = trim($_POST['subject'] ?? '');
    $body_raw    = $_POST['body'] ?? '';
    $sender_name = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $sender_email= trim($_POST['sender_email'] ?? $smtp['from_email']);
    $reply_to    = trim($_POST['reply_to'] ?? '');

    $emails = array_filter(array_map('trim', explode("\n", $to_list)));

    // Progress page
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sending Progress</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            body { padding: 2rem; background-color: #f8f9fa; }
            pre { background: #fff; border: 1px solid #dee2e6; padding: 1.5rem; border-radius: 0.5rem; max-height: 70vh; overflow-y: auto; font-size: 0.95rem; }
            .status-ok    { color: #198754; font-weight: bold; }
            .status-fail  { color: #dc3545; font-weight: bold; }
            .status-warn  { color: #fd7e14; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-send me-2"></i>Sending in Progress</h4>
                </div>
                <div class="card-body">
                    <p class="lead">Do not close this tab until finished.</p>
                    <pre>
    <?php
    $count   = 0;
    $success = 0;

    foreach ($emails as $email) {
        $count++;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "[$count] $email → <span class='status-fail'>Invalid email</span>\n";
            continue;
        }

        $body = str_replace(
            ['[-email-]', '[-time-]', '[-randommd5-]'],
            [$email, date('Y-m-d H:i:s'), md5(uniqid(rand(), true))],
            $body_raw
        );

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];
            $mail->Port       = $smtp['port'];

            $mail->setFrom($sender_email, $sender_name);
            if (!empty($reply_to) && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($reply_to);
            }
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = $subject_raw;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            $success++;
            echo "[$count] $email → <span class='status-ok'>OK</span>\n";
        } catch (Exception $e) {
            $err = htmlspecialchars($mail->ErrorInfo);
            echo "[$count] $email → <span class='status-fail'>Failed</span> – $err\n";
        }

        flush();
        ob_flush();
        usleep($delay_us);
    }

    echo "\nFinished.\nSuccessful: $success / " . count($emails) . "\n";
    ?>
                    </pre>
                </div>
                <div class="card-footer text-center">
                    <a href="?" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-2"></i>Back to Form</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>4RR0W H43D Bulk Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 2rem 1rem; }
        .card { border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border-radius: 12px; overflow: hidden; }
        .card-header { background: linear-gradient(90deg, #0d6efd, #6610f2); color: white; }
        .form-label { font-weight: 600; }
        .btn-primary { background: #0d6efd; border: none; }
        .btn-primary:hover { background: #0b5ed7; }
        .note { font-size: 0.9rem; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h3 class="mb-0"><i class="bi bi-envelope-at me-2"></i>4RR0W H43D Bulk Mailer</h3>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <form method="post">
                            <input type="hidden" name="action" value="send">

                            <div class="mb-4">
                                <label class="form-label">Sender Name</label>
                                <input type="text" name="sender_name" class="form-control form-control-lg" value="<?= htmlspecialchars($smtp['from_name']) ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Sender Email</label>
                                <input type="email" name="sender_email" class="form-control form-control-lg" value="<?= htmlspecialchars($smtp['from_email']) ?>" required readonly>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Reply-To Email <small class="text-muted">(optional)</small></label>
                                <input type="email" name="reply_to" class="form-control form-control-lg" placeholder="replies@yourdomain.com" value="<?= htmlspecialchars($smtp['from_email']) ?>">
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control form-control-lg" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Message (HTML supported)</label>
                                <textarea name="body" class="form-control" rows="8" required placeholder="Hello [-email-],\n\nYour account was updated on [-time-].\nVerification code: [-randommd5-]\n\nBest regards,"></textarea>
                                <div class="form-text">Placeholders: [-email-], [-time-], [-randommd5-]</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Recipients (one per line)</label>
                                <textarea name="emails" class="form-control" rows="7" required placeholder="user1@example.com
user2@example.com
..."></textarea>
                                <div class="form-text">Test with 1–5 emails first!</div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-send me-2"></i>Start Sending
                            </button>
                        </form>

                        <div class="text-center mt-4 note">
                            <strong>Created by 4RR0W H43D</strong> • Use responsibly • Test small batches
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
