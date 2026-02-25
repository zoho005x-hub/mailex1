<?php
/**
 * Modern Bulk Mailer – 2026 Edition (Bootstrap 5)
 * ZeptoMail SMTP + Reply-To + Attachments + Email Validation + HTML Preview
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
$max_attach_size = 10 * 1024 * 1024; // 10 MB

// Sample email for preview (personalization demo)
$preview_sample_email = 'test.user@example.com';

// ────────────────────────────────────────────────
// PASSWORD PROTECTION
// ────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        // ... (login page remains the same)
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
                    <form method="post" enctype="multipart/form-data">
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
// DISPOSABLE DOMAIN CHECK
// ────────────────────────────────────────────────
function isDisposable($email) {
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    $disposableList = [
        'mailinator.com','tempmail.com','10minutemail.com','guerrillamail.com',
        'yopmail.com','trashmail.com','sharklasers.com','dispostable.com',
        'temp-mail.org','throwawaymail.com','maildrop.cc','getairmail.com',
        'fakeinbox.com','33mail.com','armyspy.com','cuvox.de','dayrep.com',
        'einrot.com','fleckens.hu','gustr.com','jourrapide.com','rhyta.com',
        'superrito.com','teleworm.us','webbox.us','mobimail.ga','temp-mail.io',
        'moakt.com','mail.tm','tempmail.plus',
        // expand as needed
    ];
    return in_array($domain, $disposableList);
}

// ────────────────────────────────────────────────
// HANDLE PREVIEW REQUEST (AJAX-like via POST)
if (isset($_POST['action']) && $_POST['action'] === 'preview') {
    $body_raw = $_POST['body'] ?? '';
    $email_sample = $preview_sample_email;

    $body_preview = str_replace(
        ['[-email-]', '[-time-]', '[-randommd5-]'],
        [$email_sample, date('Y-m-d H:i:s'), md5(uniqid(rand(), true))],
        $body_raw
    );

    $plain_preview = strip_tags($body_preview);

    ?>
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Message Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <h6>Sample recipient:</h6>
            <p><code><?= htmlspecialchars($email_sample) ?></code></p>

            <ul class="nav nav-tabs" id="previewTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="html-tab" data-bs-toggle="tab" data-bs-target="#html" type="button" role="tab">HTML View</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="plain-tab" data-bs-toggle="tab" data-bs-target="#plain" type="button" role="tab">Plain Text</button>
                </li>
            </ul>

            <div class="tab-content border border-top-0 p-3 mt-0 rounded-bottom" style="min-height: 300px; background:#fff;">
                <div class="tab-pane fade show active" id="html" role="tabpanel">
                    <div style="border:1px solid #ddd; padding:20px; border-radius:8px; background:#f9f9f9;">
                        <?= $body_preview ?>
                    </div>
                </div>
                <div class="tab-pane fade" id="plain" role="tabpanel">
                    <pre style="white-space: pre-wrap; background:#f8f9fa; padding:15px; border-radius:6px;"><?= htmlspecialchars($plain_preview) ?></pre>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
    </div>
    <?php
    exit;
}

// ────────────────────────────────────────────────
// SENDING LOGIC (unchanged except attachments & validation)
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    // ... (the sending code remains almost identical to your previous version)

    $to_list      = trim($_POST['emails'] ?? '');
    $subject_raw  = trim($_POST['subject'] ?? '');
    $body_raw     = $_POST['body'] ?? '';
    $sender_name  = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $sender_email = trim($_POST['sender_email'] ?? $smtp['from_email']);
    $reply_to     = trim($_POST['reply_to'] ?? '');

    $emails = array_filter(array_map('trim', explode("\n", $to_list)));

    // Attachments handling (same as before)
    $attachments = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['attachments']['name'][$key];
                $file_size = $_FILES['attachments']['size'][$key];
                $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                $allowed = ['pdf','jpg','jpeg','png','txt','doc','docx','zip','rar'];
                if (in_array($file_type, $allowed) && $file_size <= $max_attach_size) {
                    $attachments[] = ['path' => $tmp_name, 'name' => $file_name];
                }
            }
        }
    }

    // Progress page (same structure)
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
                    <p class="lead">Do not close this tab. <?= count($attachments) ? 'Attachments: ' . count($attachments) : 'No attachments' ?></p>
                    <pre><?php
    $count   = 0;
    $success = 0;

    foreach ($emails as $email) {
        $count++;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "[$count] $email → <span class='status-fail'>Invalid format</span>\n";
            continue;
        }

        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            echo "[$count] $email → <span class='status-fail'>No valid MX/A records</span>\n";
            continue;
        }

        if (isDisposable($email)) {
            echo "[$count] $email → <span class='status-fail'>Disposable domain</span>\n";
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

            foreach ($attachments as $att) {
                $mail->addAttachment($att['path'], $att['name']);
            }

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

    foreach ($attachments as $att) {
        @unlink($att['path']);
    }

    echo "\nFinished.\nSuccessful: $success / " . count($emails) . "\n";
    ?></pre>
                </div>
                <div class="card-footer text-center">
                    <a href="?" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-2"></i>Back</a>
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
        #previewModal .modal-dialog { max-width: 800px; }
        #previewModal .modal-body { max-height: 70vh; overflow-y: auto; }
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
                        <form method="post" enctype="multipart/form-data" id="mailerForm">
                            <input type="hidden" name="action" value="send" id="formAction">

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
                                <textarea name="body" id="bodyEditor" class="form-control" rows="10" required placeholder="Hello [-email-],\n\nYour account was updated on [-time-].\nVerification code: [-randommd5-]\n\nBest regards,"></textarea>
                                <div class="form-text">Placeholders: [-email-], [-time-], [-randommd5-]</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Attachments <small class="text-muted">(pdf, jpg, png, txt, docx, zip – max 10MB total)</small></label>
                                <input type="file" name="attachments[]" class="form-control form-control-lg" multiple>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Recipients (one per line)</label>
                                <textarea name="emails" class="form-control" rows="7" required placeholder="user1@example.com\nuser2@example.com\n..."></textarea>
                                <div class="form-text">Validated: syntax + DNS + disposable check</div>
                            </div>

                            <div class="d-grid gap-3 d-md-flex justify-content-md-between">
                                <button type="button" class="btn btn-outline-info btn-lg flex-fill" id="previewBtn">
                                    <i class="bi bi-eye me-2"></i>Preview Message
                                </button>
                                <button type="submit" class="btn btn-primary btn-lg flex-fill">
                                    <i class="bi bi-send me-2"></i>Start Sending
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4 note">
                            <strong>Created by 4RR0W H43D</strong> • Use responsibly
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('previewBtn').addEventListener('click', function() {
            const formData = new FormData(document.getElementById('mailerForm'));
            formData.set('action', 'preview'); // override to preview mode

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                document.querySelector('#previewModal .modal-content').innerHTML = html;
                const modal = new bootstrap.Modal(document.getElementById('previewModal'));
                modal.show();
            })
            .catch(err => {
                alert('Preview failed: ' + err);
            });
        });
    </script>
</body>
</html>
