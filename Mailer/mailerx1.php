<?php
/**
 * Modern Bulk Mailer – 2026 Edition (Bootstrap 5)
 * ZeptoMail SMTP + Reply-To + Attachments + Validation + Live Preview + CSV Import
 */

// Show errors during testing (disable in production!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ────────────────────────────────────────────────
// CONFIG
// ────────────────────────────────────────────────
$smtp = [
    'host'     => 'smtp.zeptomail.com',
    'port'     => 587,
    'secure'   => 'tls',
    'username' => 'emailapikey',
    'password' => 'wSsVR613+0LyBqt0yTavdO4wyggHAVykHBh03Val6XP8Gv/E98c5khfMBwPyFaIYEjJuFTsW8Lp7n0oJhzJYjdh5z1AICSiF9mqRe1U4J3x17qnvhDzNWWhflxGPKY8Oww9rk2hjFMoq+g==',
    'from_email' => 'postmail@treworgy-baldacci.cc',
    'from_name'  => 'Your App Name',
];

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
        <?php exit;
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
    ];
    return in_array($domain, $disposableList);
}

// ────────────────────────────────────────────────
// HANDLE CSV IMPORT
// ────────────────────────────────────────────────
$csv_emails = '';
$csv_message = '';
if (isset($_POST['action']) && $_POST['action'] === 'import_csv' && !empty($_FILES['csv_file']['tmp_name'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');
    if ($handle) {
        $lines = [];
        $header = fgetcsv($handle); // first row as header
        $has_name = is_array($header) && in_array('name', array_map('strtolower', $header));

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0])) continue;
            $email = trim($row[0]);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $name = $has_name ? trim($row[1] ?? '') : '';
                $lines[] = $email . ($name ? " ($name)" : '');
            }
        }
        fclose($handle);
        $csv_emails = implode("\n", $lines);
        $csv_message = count($lines) . " valid email(s) imported from CSV.";
    } else {
        $csv_message = "Failed to read CSV file.";
    }
}

// ────────────────────────────────────────────────
// PREVIEW HANDLER (updated with [-name-] support)
// ────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'preview') {
    $body_raw = $_POST['body'] ?? '';
    $body_preview = str_replace(
        ['[-email-]', '[-time-]', '[-randommd5-]', '[-name-]'],
        [$preview_sample_email, date('Y-m-d H:i:s'), md5(uniqid(rand(), true)), 'Test User'],
        $body_raw
    );
    $plain_preview = strip_tags($body_preview);
    ?>
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Live Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body small">
            <p><strong>Sample:</strong> <?= htmlspecialchars($preview_sample_email) ?> (Test User)</p>
            <ul class="nav nav-tabs nav-fill" id="previewTab">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#html">HTML</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#plain">Plain</button></li>
            </ul>
            <div class="tab-content border border-top-0 p-3 bg-white">
                <div class="tab-pane fade show active" id="html"><?= $body_preview ?></div>
                <div class="tab-pane fade" id="plain"><pre><?= htmlspecialchars($plain_preview) ?></pre></div>
            </div>
        </div>
    </div>
    <?php exit;
}

// ────────────────────────────────────────────────
// SENDING LOGIC (updated with [-name-] placeholder)
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $sender_email = trim($_POST['sender_email'] ?? $smtp['from_email']);
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
        <title>Sending Progress</title>
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
    foreach ($emails as $line) {
        $count++;
        $line = trim($line);
        if (empty($line)) continue;

        // Support CSV-style "email (name)" format from import
        if (preg_match('/(.+?)\s*\((.+?)\)/', $line, $m)) {
            $email = trim($m[1]);
            $name  = trim($m[2]);
        } else {
            $email = $line;
            $name  = '';
        }

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
            ['[-email-]', '[-emailuser-]', '[-emaildomain-]', '[-time-]', '[-randommd5-]', '[-name-]'],
            [
                $email,
                explode('@', $email)[0] ?? '',
                $domain,
                date('Y-m-d H:i:s'),
                md5(uniqid(rand(), true)),
                $name ?: 'Recipient'
            ],
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
    <?php exit;
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
        body { background:#f0f2f5; padding:1.5rem 1rem; font-size:0.95rem; }
        .card { max-width:620px; margin:auto; border:none; box-shadow:0 2px 12px rgba(0,0,0,0.08); border-radius:10px; }
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
            <i class="bi bi-envelope-at me-1"></i>4RR0W H43D Bulk Mailer
        </div>
        <div class="card-body p-4">

            <?php if ($csv_message): ?>
            <div class="alert alert-info alert-dismissible fade show mb-3">
                <?= htmlspecialchars($csv_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="mailerForm">
                <input type="hidden" name="action" value="send">

                <div class="tight-mb">
                    <label class="form-label">From Name</label>
                    <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['from_name']) ?>" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">From Email</label>
                    <input type="email" name="sender_email" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['from_email']) ?>" required>
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
                    <label class="form-label">Message (HTML supported)</label>
                    <textarea name="body" id="bodyEditor" class="form-control" rows="10" required placeholder="Dear [-name-],\n\nYour account [-email-] was updated on [-time-].\nCode: [-randommd5-]\n\nBest regards,"></textarea>
                    <div class="form-text mt-2">
                        Placeholders: <code>[-email-]</code> <code>[-emailuser-]</code> <code>[-emaildomain-]</code> <code>[-name-]</code> <code>[-time-]</code> <code>[-randommd5-]</code>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Import Recipients from CSV</label>
                        <div class="input-group input-group-sm">
                            <input type="file" name="csv_file" accept=".csv" class="form-control">
                            <button type="submit" name="action" value="import_csv" class="btn btn-outline-info">
                                <i class="bi bi-upload"></i> Import
                            </button>
                        </div>
                        <div class="form-text">CSV format: email (optional name)</div>
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="replace_emails" value="1" id="replaceOnImport">
                            <label class="form-check-label" for="replaceOnImport">Replace existing list</label>
                        </div>
                    </div>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Recipients (one per line)</label>
                    <textarea name="emails" class="form-control" rows="8" required placeholder="user1@example.com\njohn.doe@yahoo.com (John Doe)\n..."><?= htmlspecialchars($csv_emails) ?></textarea>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="attach_receiver_email" value="1" id="attachRec" checked>
                    <label class="form-check-label" for="attachRec">Add "To: [-email-]" line at top</label>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="button" class="btn btn-outline-info" id="previewBtn">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Start Sending
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 small text-muted">
                Created by 4RR0W H43D • Use responsibly
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <!-- Filled dynamically -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live preview script (unchanged from previous version)
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
                if (previewModal && previewModalEl.classList.contains('show')) updatePreview();
            }, 500);
        });

        previewModalEl.addEventListener('shown.bs.modal', updatePreview);
    </script>
</body>
</html>
