<?php
/**
 * Modern Bulk Mailer – 2026 Edition (Bootstrap 5)
 * SMTP settings hidden – only sender email is editable
 * ZeptoMail SMTP + Reply-To + Attachments + Validation + Live HTML Preview
 */

// Show errors during testing (disable in production!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ────────────────────────────────────────────────
// CONFIG – SMTP settings are now hidden / hardcoded
// ────────────────────────────────────────────────
$smtp = [
    'host'     => 'smtp.zeptomail.com',
    'port'     => 587,
    'secure'   => 'tls',
    'username' => 'emailapikey',
    'password' => 'wSsVR613+0LyBqt0yTavdO4wyggHAVykHBh03Val6XP8Gv/E98c5khfMBwPyFaIYEjJuFTsW8Lp7n0oJhzJYjdh5z1AICSiF9mqRe1U4J3x17qnvhDzNWWhflxGPKY8Oww9rk2hjFMoq+g==',
    'from_name'=> 'Your App Name',
];

// Only this part is editable on the form
$default_sender_email = 'postmail@treworgy-baldacci.cc';

$admin_password = "B0TH"; // ← CHANGE THIS!
$delay_us = 150000;
$max_attach_size = 10 * 1024 * 1024; // 10 MB

$preview_sample_email = 'test.user@example.com';

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
            <title>Login</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .login-card { max-width: 380px; width: 100%; }
            </style>
        </head>
        <body>
            <div class="card login-card shadow-lg border-0">
                <div class="card-body p-4 text-center">
                    <h4 class="mb-3">Enter Password</h4>
                    <form method="post">
                        <input type="password" name="pass" class="form-control mb-3" autofocus required>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
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
// DISPOSABLE DOMAIN CHECK (unchanged)
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
    ];
    return in_array($domain, $disposableList);
}

// ────────────────────────────────────────────────
// PREVIEW HANDLER (updated to show sender email)
// ────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'preview') {
    $body_raw = $_POST['body'] ?? '';
    $sender_email_preview = $_POST['sender_email'] ?? $default_sender_email;

    $body_preview = str_replace(
        ['[-email-]', '[-time-]', '[-randommd5-]'],
        [$preview_sample_email, date('Y-m-d H:i:s'), md5(uniqid(rand(), true))],
        $body_raw
    );
    $plain_preview = strip_tags($body_preview);
    ?>
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Live Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <small class="text-muted">From: <?= htmlspecialchars($sender_email_preview) ?></small><br>
            <small class="text-muted">To (sample): <?= htmlspecialchars($preview_sample_email) ?></small>
            <hr class="my-2">
            <ul class="nav nav-tabs nav-fill small" id="previewTab">
                <li class="nav-item"><button class="nav-link active" id="html-tab" data-bs-toggle="tab" data-bs-target="#html">HTML</button></li>
                <li class="nav-item"><button class="nav-link" id="plain-tab" data-bs-toggle="tab" data-bs-target="#plain">Plain</button></li>
            </ul>
            <div class="tab-content border border-top-0 p-3 bg-white rounded-bottom" style="min-height:300px;">
                <div class="tab-pane fade show active" id="html"><?= $body_preview ?></div>
                <div class="tab-pane fade" id="plain"><pre class="m-0"><?= htmlspecialchars($plain_preview) ?></pre></div>
            </div>
        </div>
    </div>
    <?php exit;
}

// ────────────────────────────────────────────────
// SENDING LOGIC
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $sender_email = trim($_POST['sender_email'] ?? $default_sender_email);
    $to_list      = trim($_POST['emails'] ?? '');
    $subject_raw  = trim($_POST['subject'] ?? '');
    $body_raw     = $_POST['body'] ?? '';
    $sender_name  = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $reply_to     = trim($_POST['reply_to'] ?? '');

    $emails = array_filter(array_map('trim', explode("\n", $to_list)));

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

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sending...</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding:1.5rem; background:#f8f9fa; font-family:monospace; }
            pre { background:#fff; border:1px solid #dee2e6; padding:1rem; border-radius:6px; max-height:60vh; overflow-y:auto; }
            .ok { color:#198754; font-weight:bold; }
            .fail { color:#dc3545; font-weight:bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <h4>Sending Progress</h4>
            <pre><?php
    $count = 0;
    $success = 0;
    foreach ($emails as $email) {
        $count++;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "[$count] $email → <span class='fail'>invalid</span>\n";
            continue;
        }
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            echo "[$count] $email → <span class='fail'>no MX</span>\n";
            continue;
        }
        if (isDisposable($email)) {
            echo "[$count] $email → <span class='fail'>disposable</span>\n";
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
            $mail->Port       = $smtp['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];

            $mail->setFrom($sender_email, $sender_name);
            if ($reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
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
            echo "[$count] $email → <span class='ok'>OK</span>\n";
        } catch (Exception $e) {
            echo "[$count] $email → <span class='fail'>" . htmlspecialchars($mail->ErrorInfo) . "</span>\n";
        }
        flush();
        ob_flush();
        usleep($delay_us);
    }
    foreach ($attachments as $att) @unlink($att['path']);
    echo "\nDone. Sent: $success / " . count($emails);
    ?></pre>
            <a href="?" class="btn btn-outline-primary mt-3">Back</a>
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
    <title>4RR0W H43D Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; padding:1.5rem 1rem; font-size:0.95rem; }
        .card { max-width:580px; margin:auto; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.08); border-radius:10px; }
        .card-header { background:#0d6efd; color:white; padding:0.9rem; text-align:center; font-weight:600; }
        .form-label { font-size:0.9rem; margin-bottom:0.35rem; font-weight:600; }
        .form-control-sm { font-size:0.9rem; padding:0.45rem 0.65rem; }
        .btn { font-size:0.95rem; }
        .form-text { font-size:0.8rem; }
        .tight-mb { margin-bottom:0.75rem !important; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <i class="bi bi-envelope-at me-1"></i>4RR0W Mailer
        </div>
        <div class="card-body p-3">

            <form method="post" enctype="multipart/form-data" id="mailerForm">
                <input type="hidden" name="action" value="send">

                <div class="tight-mb">
                    <label class="form-label">From Name</label>
                    <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['from_name']) ?>" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">From Email <small>(editable)</small></label>
                    <input type="email" name="sender_email" class="form-control form-control-sm" value="<?= htmlspecialchars($default_sender_email) ?>" required>
                    <div class="form-text text-danger">Must be verified in ZeptoMail</div>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Reply-To (optional)</label>
                    <input type="email" name="reply_to" class="form-control form-control-sm" placeholder="optional">
                </div>

                <div class="tight-mb">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control form-control-sm" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Message</label>
                    <textarea name="body" id="bodyEditor" class="form-control form-control-sm" rows="7" required placeholder="Hello [-emailuser-], domain: [-emaildomain-] ..."></textarea>
                    <div class="form-text mt-1">
                        [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
                    </div>
                </div>

                <div class="form-check tight-mb">
                    <input class="form-check-input" type="checkbox" name="attach_receiver_email" value="1" id="attachRec" checked>
                    <label class="form-check-label small" for="attachRec">Add "To: [-email-]" line</label>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Recipients</label>
                    <textarea name="emails" class="form-control form-control-sm" rows="5" required placeholder="email1@example.com&#10;email2@example.com"></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="previewBtn">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-send"></i> Send
                    </button>
                </div>
            </form>

            <div class="text-center mt-3 small text-muted">
                4RR0W H43D • SMTP hidden • Test small
            </div>
        </div>
    </div>

    <!-- Preview Modal (keep your existing modal code here) -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- filled by JS -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Your existing live preview script here
        const previewModalEl = document.getElementById('previewModal');
        const previewBtn = document.getElementById('previewBtn');
        const bodyEditor = document.getElementById('bodyEditor');
        let previewModal = null;
        let debounceTimer = null;

        function updatePreview() {
            const formData = new FormData(document.getElementById('mailerForm'));
            formData.set('action', 'preview');

            fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    document.querySelector('#previewModal .modal-content').innerHTML = html;
                })
                .catch(e => console.error('Preview failed', e));
        }

        previewBtn.addEventListener('click', () => {
            if (!previewModal) previewModal = new bootstrap.Modal(previewModalEl);
            updatePreview();
            previewModal.show();
        });

        bodyEditor.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (previewModal && previewModalEl.classList.contains('show')) {
                    updatePreview();
                }
            }, 500);
        });

        previewModalEl.addEventListener('shown.bs.modal', updatePreview);
    </script>
</body>
</html>
