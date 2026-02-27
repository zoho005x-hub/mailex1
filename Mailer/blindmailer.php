<?php
/**
 * Full-Page Dark Bulk Mailer with TinyMCE
 * Fully editable sender email + persisted after send → Back
 * Server-side temporary attachment storage → files remain after send → Back
 * Detailed vertical report: sent / not sent with exact reasons
 */

session_start();

// Debugging – show errors only when ?debug=1
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
ini_set('display_errors', $debug ? 1 : 0);
ini_set('display_startup_errors', $debug ? 1 : 0);
error_reporting($debug ? E_ALL : E_ALL & ~E_NOTICE & ~E_WARNING);

// ────────────────────────────────────────────────
// CONFIG
// ────────────────────────────────────────────────
$smtp = [
    'host'     => 'smtp.zeptomail.com',
    'port'     => 587,
    'secure'   => 'tls',
    'username' => 'khaddock@jmmiles.com',
    'password' => 'Colinh22!',
    'from_name'=> 'Name or Company Name',
];

$preview_sample_email = 'test.user@example.com';

$admin_password = "US3R"; // ← CHANGE THIS!
$delay_us = 150000;
$max_attach_size = 10 * 1024 * 1024; // 10 MB

// Temporary attachment storage
$attachment_dir = __DIR__ . '/attachments/';
$attachment_lifetime_seconds = 3600; // 1 hour

if (!is_dir($attachment_dir)) {
    mkdir($attachment_dir, 0755, true);
}

$session_attach_dir = $attachment_dir . session_id() . '/';
if (!is_dir($session_attach_dir)) {
    mkdir($session_attach_dir, 0755, true);
}

// Cleanup old files
$now = time();
foreach (glob($session_attach_dir . '*') as $file) {
    if (is_file($file) && ($now - filemtime($file)) > $attachment_lifetime_seconds) {
        @unlink($file);
    }
}

// Restore saved data
$saved = $_SESSION['saved_form'] ?? [];
$sender_name_val     = htmlspecialchars($saved['sender_name'] ?? $smtp['from_name']);
$sender_email_val    = htmlspecialchars($saved['sender_email'] ?? 'info@yourdmain.com');
$reply_to_val        = htmlspecialchars($saved['reply_to'] ?? '');
$subject_val         = htmlspecialchars($saved['subject'] ?? '');
$body_val            = $saved['body'] ?? '';

// Previous attachments (files still on disk)
$previous_attachments = [];
if (!empty($saved['attachments'])) {
    foreach ($saved['attachments'] as $filename => $server_path) {
        if (file_exists($server_path)) {
            $previous_attachments[$filename] = $server_path;
        }
    }
}
unset($_SESSION['saved_form']);

// ────────────────────────────────────────────────
// AUTH
// ────────────────────────────────────────────────
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="en" data-bs-theme="dark">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Login</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>body{background:#0d1117;display:flex;align-items:center;justify-content:center;min-height:100vh;}.card{max-width:380px;}</style>
        </head>
        <body>
            <div class="card bg-dark border-secondary shadow-lg p-4">
                <h4 class="text-center mb-4">Password</h4>
                <form method="post">
                    <input type="password" name="pass" class="form-control mb-3" autofocus required>
                    <button type="submit" class="btn btn-primary w-100">Enter</button>
                </form>
            </div>
        </body>
        </html>
        <?php exit;
    }
}

// PHPMailer
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Disposable check
function isDisposable($email) {
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    $list = ['mailinator.com','tempmail.com','10minutemail.com','guerrillamail.com','yopmail.com','trashmail.com','sharklasers.com','dispostable.com','temp-mail.org','throwawaymail.com','maildrop.cc','getairmail.com','fakeinbox.com','33mail.com','armyspy.com','cuvox.de','dayrep.com','einrot.com','fleckens.hu','gustr.com','jourrapide.com','rhyta.com','superrito.com','teleworm.us','webbox.us','mobimail.ga','temp-mail.io','moakt.com','mail.tm','tempmail.plus'];
    return in_array($domain, $list);
}

// Validation helpers
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && !isDisposable($email);
}

// ────────────────────────────────────────────────
// PREVIEW HANDLER
// ────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'preview') {
    $body_raw = $_POST['body'] ?? '';
    $body_preview = str_replace(
        ['[-email-]', '[-time-]', '[-randommd5-]'],
        [$preview_sample_email, date('Y-m-d H:i:s'), md5(uniqid(rand(), true))],
        $body_raw
    );
    $plain_preview = strip_tags($body_preview);
    ?>
    <div class="modal-content bg-dark text-light border-secondary">
        <div class="modal-header border-secondary">
            <h5 class="modal-title">Message Preview</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p class="small text-muted">Sample recipient: <?= htmlspecialchars($preview_sample_email) ?></p>
            <ul class="nav nav-tabs nav-fill border-secondary mb-2">
                <li class="nav-item"><button class="nav-link active bg-dark text-light" data-bs-toggle="tab" data-bs-target="#html">HTML</button></li>
                <li class="nav-item"><button class="nav-link bg-dark text-light" data-bs-toggle="tab" data-bs-target="#plain">Plain Text</button></li>
            </ul>
            <div class="tab-content border border-top-0 border-secondary p-3 bg-dark rounded-bottom" style="min-height:300px;">
                <div class="tab-pane fade show active" id="html">
                    <div class="p-3 border border-secondary rounded bg-black"><?= $body_preview ?></div>
                </div>
                <div class="tab-pane fade" id="plain">
                    <pre class="bg-secondary p-3 rounded m-0 text-light" style="white-space:pre-wrap;"><?= htmlspecialchars($plain_preview) ?></pre>
                </div>
            </div>
        </div>
    </div>
    <?php exit;
}

// ────────────────────────────────────────────────
// SENDING LOGIC + VALIDATION + DETAILED REPORT
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $sender_name     = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $sender_email    = trim($_POST['sender_email'] ?? '');
    $reply_to        = trim($_POST['reply_to'] ?? '');
    $subject_raw     = trim($_POST['subject'] ?? '');
    $body_raw        = $_POST['body'] ?? '';
    $to_list         = trim($_POST['emails'] ?? '');

    // ─── Server-side validation ───
    $errors = [];

    if (empty($sender_name)) $errors[] = "From Name is required.";
    if (!filter_var($sender_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid From Email address.";
    if ($reply_to !== '' && !filter_var($reply_to, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid Reply-To email address.";

    if (!empty($errors)) {
        echo '<!DOCTYPE html><html lang="en" data-bs-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Error</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-dark text-light p-5"><div class="container"><div class="alert alert-danger"><h4>Validation Errors</h4><ul>';
        foreach ($errors as $err) echo '<li>' . htmlspecialchars($err) . '</li>';
        echo '</ul><a href="?" class="btn btn-outline-light mt-3">Back to Form</a></div></div></body></html>';
        exit;
    }

    // ─── Filter valid vs skipped recipients ───
    $all_emails = array_filter(array_map('trim', explode("\n", $to_list)));
    $valid_emails = [];
    $skipped_reasons = [];

    foreach ($all_emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped_reasons[$email] = "invalid format";
            continue;
        }
        if (isDisposable($email)) {
            $skipped_reasons[$email] = "disposable domain";
            continue;
        }
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            $skipped_reasons[$email] = "no MX or A record";
            continue;
        }
        $valid_emails[] = $email;
    }

    // ─── Handle attachments (move to server storage) ───
    $saved_attachments = []; // filename => full server path

    if (!empty($_FILES['attachments']['name'][0])) {
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['attachments']['name'][$key];
                $file_size = $_FILES['attachments']['size'][$key];
                $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = ['pdf','jpg','jpeg','png','txt','doc','docx','zip','rar'];

                if (in_array($file_type, $allowed) && $file_size <= $max_attach_size) {
                    $safe_name = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file_name);
                    $dest_path = $session_attach_dir . time() . '_' . $safe_name;

                    if (move_uploaded_file($tmp_name, $dest_path)) {
                        $saved_attachments[$file_name] = $dest_path;
                    }
                }
            }
        }
    } else if (!empty($previous_attachments)) {
        $saved_attachments = $previous_attachments;
    }

    // ─── Save form data for restore ───
    $_SESSION['saved_form'] = [
        'sender_name'     => $sender_name,
        'sender_email'    => $sender_email,
        'reply_to'        => $reply_to,
        'subject'         => $subject_raw,
        'body'            => $body_raw,
        'attachments'     => $saved_attachments,
    ];

    // ─── Send valid emails ───
    $send_results = [];
    $success_count = 0;

    foreach ($valid_emails as $email) {
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

            foreach ($saved_attachments as $orig_name => $path) {
                if (file_exists($path)) {
                    $mail->addAttachment($path, $orig_name);
                }
            }

            $mail->send();
            $success_count++;
            $send_results[$email] = "OK";
        } catch (Exception $e) {
            $send_results[$email] = htmlspecialchars($mail->ErrorInfo);
        }

        // Safe flush
        echo str_repeat(' ', 4096);
        if (ob_get_length()) ob_flush();
        flush();

        usleep($delay_us);
    }

    // Optional: cleanup after send
    // foreach ($saved_attachments as $path) @unlink($path);

    // ─── Detailed vertical report ───
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sending Report</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding:2rem; background:#0d1117; color:#c9d1d9; font-family:monospace; line-height:1.5; }
            .report-card { background:#161b22; border:1px solid #30363d; border-radius:6px; padding:2rem; max-width:900px; margin:0 auto; }
            pre { background:#0d1117; border:1px solid #30363d; padding:1.2rem; border-radius:6px; white-space:pre-wrap; word-wrap:break-word; }
            .summary { font-size:1.25rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid #30363d; }
            .ok { color:#3fb950; font-weight:bold; }
            .fail { color:#f85149; font-weight:bold; }
            .reason { color:#f39c12; }
            h5 { margin-top:1.5rem; margin-bottom:0.75rem; }
        </style>
    </head>
    <body>
        <div class="report-card">
            <h3>Sending Report</h3>

            <div class="summary">
                <div>Total emails submitted: <strong><?= count($all_emails) ?></strong></div>
                <div>Successfully sent: <strong class="ok"><?= $success_count ?></strong></div>
                <div>Not sent / skipped: <strong class="fail"><?= count($all_emails) - $success_count ?></strong></div>
            </div>

            <?php if (!empty($skipped_reasons)): ?>
                <h5>Skipped / invalid recipients (<?= count($skipped_reasons) ?>):</h5>
                <pre><?php
                    foreach ($skipped_reasons as $email => $reason) {
                        echo htmlspecialchars($email) . " → <span class='reason'>" . htmlspecialchars($reason) . "</span>\n";
                    }
                ?></pre>
            <?php endif; ?>

            <h5>Sent emails results (<?= $success_count ?>):</h5>
            <pre><?php
                if (empty($send_results)) {
                    echo "No emails were sent (all skipped or no valid recipients).";
                } else {
                    foreach ($send_results as $email => $result) {
                        if ($result === "OK") {
                            echo htmlspecialchars($email) . " → <span class='ok'>OK</span>\n";
                        } else {
                            echo htmlspecialchars($email) . " → <span class='fail'>" . $result . "</span>\n";
                        }
                    }
                }
            ?></pre>

            <a href="?" class="btn btn-outline-light mt-4">← Back to Mailer</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>4RR0W H43D Bulk Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- TinyMCE with your API key -->
    <script src="https://cdn.tiny.cloud/1/zza75uc5aisnrmt8km3mj0hwei4yoqccp134hst3arcbe65j/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

    <style>
        body { background:#0d1117; color:#c9d1d9; padding:1rem; margin:0; font-size:0.95rem; }
        .container { max-width:100%; padding:0; }
        .card { border:1px solid #30363d; border-radius:0; background:#161b22; box-shadow:none; min-height:100vh; margin:0; }
        .card-header { background:#1f6feb; color:white; padding:1rem; text-align:center; font-weight:600; border-radius:0; }
        .card-body { padding:1.5rem 1.5rem 3rem; }
        .form-label { font-size:0.9rem; margin-bottom:0.4rem; font-weight:600; }
        .form-control-sm { font-size:0.9rem; padding:0.5rem 0.75rem; background:#0d1117; color:#c9d1d9; border:1px solid #30363d; }
        .btn { font-size:0.95rem; padding:0.5rem 1rem; }
        .form-text { font-size:0.8rem; color:#8b949e; }
        .tight-mb { margin-bottom:0.75rem !important; }
        .tox-tinymce { border:1px solid #30363d !important; background:#0d1117 !important; }
        .tox-toolbar { background:#161b22 !important; border-bottom:1px solid #30363d !important; min-height:32px !important; padding:2px 4px !important; }
        .tox-tbtn { min-width:26px !important; padding:2px !important; margin:0 1px !important; }
        .error-message { color:#f85149; font-size:0.85rem; margin-top:0.25rem; }
        .prev-files { font-size:0.85rem; color:#8b949e; margin-top:0.3rem; background:#21262d; padding:0.5rem; border-radius:4px; }
        .is-valid { border-color:#3fb950 !important; background:#1e3a2f !important; }
        .is-invalid { border-color:#f85149 !important; background:#3d1f1f !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-envelope-at me-1"></i>4RR0W H43D Bulk Mailer
            </div>
            <div class="card-body p-3">
                <form method="post" enctype="multipart/form-data" id="mailerForm" onsubmit="return validateForm()">
                    <input type="hidden" name="action" value="send">

                    <div class="tight-mb">
                        <label class="form-label">From Name</label>
                        <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= $sender_name_val ?>" required>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">From Email (fully editable)</label>
                        <input type="email" name="sender_email" id="sender_email" class="form-control form-control-sm" value="<?= $sender_email_val ?>" required placeholder="yourname@yourdomain.com">
                        <div class="form-text small mt-1 text-muted">
                            Enter the full sender email address you want to use.
                        </div>
                        <div id="senderEmailError" class="error-message"></div>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">Reply-To (optional)</label>
                        <input type="email" name="reply_to" id="reply_to" class="form-control form-control-sm" value="<?= $reply_to_val ?>" placeholder="replies@yourdomain.com">
                        <div id="replyToError" class="error-message"></div>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control form-control-sm" value="<?= $subject_val ?>" required>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">Message (TinyMCE Editor)</label>
                        <textarea name="body" id="bodyEditor"><?= htmlspecialchars($body_val) ?></textarea>
                        <div class="form-text mt-1 small">
                            Placeholders: [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
                        </div>
                    </div>

                    <!-- TinyMCE -->
                    <script>
                        tinymce.init({
                            selector: '#bodyEditor',
                            height: 260,
                            menubar: false,
                            statusbar: false,
                            branding: false,
                            apiKey: 'zza75uc5aisnrmt8km3mj0hwei4yoqccp134hst3arcbe65j',
                            plugins: 'advlist lists link code',
                            toolbar: 'undo redo bold italic bullist numlist link code',
                            toolbar_mode: 'sliding',
                            toolbar_location: 'top',
                            toolbar_sticky: true,
                            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#c9d1d9; background:#0d1117; margin:8px; }',
                            skin: 'oxide-dark',
                            content_css: 'dark',
                            setup: (editor) => {
                                editor.on('init', () => {
                                    editor.focus();
                                    console.log('TinyMCE ready');
                                });
                            }
                        });
                    </script>

                    <div class="tight-mb">
                        <label class="form-label">Attachments</label>
                        <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                        <?php if (!empty($previous_attachments)): ?>
                            <div class="prev-files mt-2 p-2 bg-dark border border-secondary rounded">
                                <strong>Previously attached (auto-re-attached):</strong><br>
                                <?php foreach ($previous_attachments as $name => $path): ?>
                                    <?= htmlspecialchars($name) ?> (<?= round(filesize($path) / 1024, 1) ?> KB)<br>
                                <?php endforeach; ?>
                                <small class="text-muted">Files are kept on server temporarily. Clear browser cache if you want to remove them.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tight-mb">
                        <label class="form-label">Recipients (one per line)</label>
                        <textarea name="emails" id="emails" class="form-control form-control-sm" rows="6" required placeholder="email1@example.com\nemail2@example.com"></textarea>
                        <div id="recipientsError" class="error-message"></div>
                        <div class="form-text small mt-1">Invalid emails will be skipped automatically (format, disposable, no MX/A).</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4">
                        <button type="button" class="btn btn-outline-info btn-lg flex-fill" id="previewBtn">
                            <i class="bi bi-eye me-2"></i>Open Preview
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg flex-fill">
                            <i class="bi bi-send me-2"></i>Start Sending
                        </button>
                    </div>
                </form>

                <div class="text-center mt-4 small text-muted">
                    <strong>Created by 4RR0W H43D</strong> • Dark mode • TinyMCE
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <!-- Filled dynamically -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateForm() {
            let valid = true;

            // From Email validation
            const senderEmail = document.getElementById('sender_email').value.trim();
            const emailErrorEl = document.getElementById('senderEmailError');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(senderEmail)) {
                emailErrorEl.textContent = 'Please enter a valid full email address';
                document.getElementById('sender_email').classList.add('is-invalid');
                document.getElementById('sender_email').classList.remove('is-valid');
                valid = false;
            } else {
                emailErrorEl.textContent = '';
                document.getElementById('sender_email').classList.remove('is-invalid');
                document.getElementById('sender_email').classList.add('is-valid');
            }

            // Reply-To validation
            const replyTo = document.getElementById('reply_to').value.trim();
            if (replyTo !== '') {
                if (!emailRegex.test(replyTo)) {
                    document.getElementById('replyToError').textContent = 'Invalid email format';
                    valid = false;
                } else {
                    document.getElementById('replyToError').textContent = '';
                }
            }

            // Recipients basic format check
            const recipientsText = document.getElementById('emails').value.trim();
            if (!recipientsText) {
                document.getElementById('recipientsError').textContent = 'At least one recipient required';
                valid = false;
            } else {
                const lines = recipientsText.split('\n').map(l => l.trim()).filter(l => l);
                let invalid = false;
                for (let line of lines) {
                    if (!emailRegex.test(line)) {
                        invalid = true;
                        break;
                    }
                }
                document.getElementById('recipientsError').textContent = invalid ? 'One or more recipient emails have invalid format' : '';
                if (invalid) valid = false;
            }

            return valid;
        }

        // Preview modal logic
        const previewModalEl = document.getElementById('previewModal');
        const previewBtn = document.getElementById('previewBtn');
        let previewModal = null;

        function updatePreview() {
            const content = tinymce.get('bodyEditor')?.getContent() || document.getElementById('bodyEditor').value;
            const formData = new FormData(document.getElementById('mailerForm'));
            formData.set('action', 'preview');
            formData.set('body', content);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(html => {
                    document.querySelector('#previewModal .modal-content').innerHTML = html;
                    const closeBtn = document.querySelector('#previewModal .btn-close');
                    if (closeBtn) {
                        closeBtn.onclick = () => previewModal?.hide();
                    }
                })
                .catch(e => console.error('Preview failed', e));
        }

        previewBtn.addEventListener('click', function () {
            if (!previewModal) {
                previewModal = new bootstrap.Modal(previewModalEl, {
                    backdrop: true,
                    keyboard: true
                });
            }
            updatePreview();
            previewModal.show();
        });
    </script>
</body>
</html>
