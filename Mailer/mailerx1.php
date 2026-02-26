<?php
/**
 * Advanced Bulk Mailer – 2026 Dark Edition with TinyMCE WYSIWYG
 * Persistent fields + full rich text editor + CSV import
 */

session_start();

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
$max_attach_size = 10 * 1024 * 1024;

// Restore saved form data after sending
$saved = $_SESSION['saved_form'] ?? [];
$sender_name_val  = htmlspecialchars($saved['sender_name']  ?? $smtp['from_name']);
$subject_val      = htmlspecialchars($saved['subject']      ?? '');
$body_val         = $saved['body'] ?? ''; // raw HTML, no htmlspecialchars here
unset($_SESSION['saved_form']);

// ────────────────────────────────────────────────
// PASSWORD PROTECTION
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
            <style>body{background:#0d1117;display:flex;align-items:center;justify-content:center;min-height:100vh;}.card{max-width:400px;}</style>
        </head>
        <body>
            <div class="card bg-dark border-secondary shadow-lg p-4">
                <h4 class="text-center mb-4">Enter Password</h4>
                <form method="post">
                    <input type="password" name="pass" class="form-control mb-3" autofocus required>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
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
// PREVIEW HANDLER – now uses TinyMCE output
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
            <p class="small text-muted mb-2">Sample: <?= htmlspecialchars($preview_sample_email) ?></p>
            <ul class="nav nav-tabs nav-fill border-secondary mb-2">
                <li class="nav-item"><button class="nav-link active bg-dark text-light" data-bs-toggle="tab" data-bs-target="#html">HTML</button></li>
                <li class="nav-item"><button class="nav-link bg-dark text-light" data-bs-toggle="tab" data-bs-target="#plain">Plain</button></li>
            </ul>
            <div class="tab-content border border-top-0 border-secondary p-3 bg-dark rounded-bottom" style="min-height:350px;">
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
// SENDING LOGIC + SAVE FORM DATA
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $sender_email = trim($_POST['sender_email'] ?? $smtp['from_email']);
    $sender_name  = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $reply_to     = trim($_POST['reply_to'] ?? '');
    $subject_raw  = trim($_POST['subject'] ?? '');
    $body_raw     = $_POST['body'] ?? '';
    $to_list      = trim($_POST['emails'] ?? '');

    // SAVE form data for restore
    $_SESSION['saved_form'] = [
        'sender_name' => $sender_name,
        'subject'     => $subject_raw,
        'body'        => $body_raw,
    ];

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
    <html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sending Progress</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { padding:1.5rem; background:#0d1117; color:#c9d1d9; font-family:monospace; }
            pre { background:#161b22; border:1px solid #30363d; padding:1rem; border-radius:6px; max-height:60vh; overflow-y:auto; }
            .ok { color:#3fb950; font-weight:bold; }
            .fail { color:#f85149; font-weight:bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card bg-dark border-secondary">
                <div class="card-header bg-primary text-white">
                    <h4>Sending Progress</h4>
                </div>
                <div class="card-body">
                    <p>Do not close this tab. <?= count($attachments) ? 'Attachments: ' . count($attachments) : 'No attachments' ?></p>
                    <pre><?php
    $count = 0;
    $success = 0;
    foreach ($emails as $email) {
        $count++;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "[$count] $email → <span class='fail'>invalid format</span>\n";
            continue;
        }
        $domain = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            echo "[$count] $email → <span class='fail'>no MX/A</span>\n";
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
    echo "\nFinished.\nSent: $success / " . count($emails);
    ?></pre>
                    <a href="?" class="btn btn-outline-light mt-3">← Back</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php exit;
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
    <!-- TinyMCE CDN -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body { background:#0d1117; color:#c9d1d9; padding:1.5rem 1rem; font-size:0.95rem; }
        .card { max-width:720px; margin:auto; border:1px solid #30363d; border-radius:10px; background:#161b22; box-shadow:0 2px 12px rgba(0,0,0,0.4); }
        .card-header { background:#1f6feb; color:white; padding:0.9rem; text-align:center; font-weight:600; }
        .form-label { font-size:0.9rem; margin-bottom:0.35rem; font-weight:600; }
        .form-control, .form-control-sm { background:#0d1117; color:#c9d1d9; border:1px solid #30363d; font-size:0.9rem; padding:0.45rem 0.65rem; }
        .form-control:focus { border-color:#1f6feb; box-shadow:0 0 0 0.2rem rgba(31,111,235,0.25); }
        .btn-primary { background:#1f6feb; border:none; }
        .btn-outline-info { border-color:#388bfd; color:#58a6ff; }
        .btn-outline-info:hover { background:#388bfd; color:white; }
        .form-text { font-size:0.8rem; color:#8b949e; }
        .tight-mb { margin-bottom:0.75rem !important; }
        .tox-tinymce { border:1px solid #30363d !important; background:#0d1117 !important; }
        .tox-editor-container { background:#0d1117 !important; color:#c9d1d9 !important; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <i class="bi bi-envelope-at me-1"></i>4RR0W H43D Bulk Mailer
        </div>
        <div class="card-body p-4">

            <form method="post" enctype="multipart/form-data" id="mailerForm">
                <input type="hidden" name="action" value="send">

                <div class="tight-mb">
                    <label class="form-label">From Name</label>
                    <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= $sender_name_val ?>" required>
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
                    <input type="text" name="subject" class="form-control form-control-sm" value="<?= $subject_val ?>" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Message (WYSIWYG Editor)</label>
                    <textarea name="body" id="bodyEditor"><?= htmlspecialchars_decode($body_val) ?></textarea>
                    <div class="form-text mt-2">
                        Placeholders: <code>[-email-]</code> <code>[-emailuser-]</code> <code>[-emaildomain-]</code> <code>[-time-]</code> <code>[-randommd5-]</code><br>
                        Use toolbar for formatting • Click Preview to review/edit live
                    </div>
                </div>

                <!-- TinyMCE initialization -->
                <script>
                    tinymce.init({
                        selector: '#bodyEditor',
                        height: 380,
                        menubar: false,
                        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
                        toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#c9d1d9; background:#0d1117; }',
                        skin: 'oxide-dark',
                        content_css: 'dark',
                    });
                </script>

                <div class="tight-mb">
                    <label class="form-label">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Recipients (one per line)</label>
                    <textarea name="emails" class="form-control form-control-sm" rows="6" required placeholder="user1@example.com\nuser2@example.com\n..."></textarea>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4">
                    <button type="button" class="btn btn-outline-info btn-lg flex-fill" id="previewBtn">
                        <i class="bi bi-eye me-2"></i>Preview & Edit
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg flex-fill">
                        <i class="bi bi-send me-2"></i>Start Sending
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 small text-muted">
                Created by 4RR0W H43D • Dark mode • WYSIWYG Editor
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-dark text-light border-secondary">
                <!-- Filled dynamically -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const previewModalEl = document.getElementById('previewModal');
        const previewBtn = document.getElementById('previewBtn');
        let previewModal = null;

        function updatePreview() {
            // Get content directly from TinyMCE
            const bodyContent = tinymce.get('bodyEditor').getContent();
            const formData = new FormData();
            formData.append('action', 'preview');
            formData.append('body', bodyContent);

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

        // Optional: update preview when modal is shown
        previewModalEl.addEventListener('shown.bs.modal', updatePreview);

        // Auto-update preview every few seconds while modal open (optional)
        setInterval(() => {
            if (previewModal && previewModalEl.classList.contains('show')) {
                updatePreview();
            }
        }, 8000);
    </script>
</body>
</html>
