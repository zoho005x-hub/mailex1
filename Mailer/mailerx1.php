<?php
/**
 * Modern Bulk Mailer – 2026 Dark Edition with TinyMCE
 * SMTP hidden, sender email editable, persistent fields, compact toolbar
 */

session_start();

// ────────────────────────────────────────────────
// CONFIG – SMTP hidden
// ────────────────────────────────────────────────
$smtp = [
    'host'     => 'smtp.zeptomail.com',
    'port'     => 587,
    'secure'   => 'tls',
    'username' => 'emailapikey',
    'password' => 'wSsVR613+0LyBqt0yTavdO4wyggHAVykHBh03Val6XP8Gv/E98c5khfMBwPyFaIYEjJuFTsW8Lp7n0oJhzJYjdh5z1AICSiF9mqRe1U4J3x17qnvhDzNWWhflxGPKY8Oww9rk2hjFMoq+g==',
    'from_name'=> 'Your App Name',
];

$default_sender_email = 'postmail@treworgy-baldacci.cc';

$admin_password = "B0TH"; // ← CHANGE THIS!
$delay_us = 150000;
$max_attach_size = 10 * 1024 * 1024;

// Restore saved data after sending
$saved = $_SESSION['saved_form'] ?? [];
$sender_name_val  = htmlspecialchars($saved['sender_name']  ?? $smtp['from_name']);
$subject_val      = htmlspecialchars($saved['subject']      ?? '');
$body_val         = $saved['body'] ?? ''; // raw HTML
unset($_SESSION['saved_form']);

// ────────────────────────────────────────────────
// AUTH
// ────────────────────────────────────────────────
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        echo '<!DOCTYPE html><html lang="en" data-bs-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#0d1117;display:flex;align-items:center;justify-content:center;min-height:100vh;}.card{max-width:380px;}</style></head><body><div class="card bg-dark border-secondary shadow-lg p-4"><h4 class="text-center mb-4">Password</h4><form method="post"><input type="password" name="pass" class="form-control mb-3" autofocus required><button type="submit" class="btn btn-primary w-100">Enter</button></form></div></body></html>';
        exit;
    }
}

// PHPMailer requires
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Disposable check function (unchanged)
function isDisposable($email) {
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    $list = ['mailinator.com','tempmail.com','10minutemail.com','guerrillamail.com','yopmail.com','trashmail.com','sharklasers.com','dispostable.com','temp-mail.org','throwawaymail.com','maildrop.cc','getairmail.com','fakeinbox.com','33mail.com','armyspy.com','cuvox.de','dayrep.com','einrot.com','fleckens.hu','gustr.com','jourrapide.com','rhyta.com','superrito.com','teleworm.us','webbox.us','mobimail.ga','temp-mail.io','moakt.com','mail.tm','tempmail.plus'];
    return in_array($domain, $list);
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
            <h5 class="modal-title">Preview</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p class="small text-muted">Sample: <?= htmlspecialchars($preview_sample_email) ?></p>
            <ul class="nav nav-tabs nav-fill border-secondary mb-2">
                <li class="nav-item"><button class="nav-link active bg-dark text-light" data-bs-toggle="tab" data-bs-target="#html">HTML</button></li>
                <li class="nav-item"><button class="nav-link bg-dark text-light" data-bs-toggle="tab" data-bs-target="#plain">Plain</button></li>
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
// SENDING LOGIC + SAVE FORM
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    $sender_email = trim($_POST['sender_email'] ?? $smtp['from_email']);
    $sender_name  = trim($_POST['sender_name'] ?? $smtp['from_name']);
    $reply_to     = trim($_POST['reply_to'] ?? '');
    $subject_raw  = trim($_POST['subject'] ?? '');
    $body_raw     = $_POST['body'] ?? '';
    $to_list      = trim($_POST['emails'] ?? '');

    // Save for restore
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
        <title>Sending...</title>
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
    echo "\nFinished.\nSent: $success / " . count($emails);
    ?></pre>
            <a href="?" class="btn btn-outline-light mt-3">← Back</a>
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
    <title>4RR0W Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- TinyMCE CDN -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body { background:#0d1117; color:#c9d1d9; padding:1rem; font-size:0.9rem; }
        .card { max-width:540px; margin:auto; border:1px solid #30363d; border-radius:8px; background:#161b22; box-shadow:0 1px 8px rgba(0,0,0,0.4); }
        .card-header { background:#1f6feb; color:white; padding:0.75rem; text-align:center; font-weight:600; }
        .form-label { font-size:0.85rem; margin-bottom:0.3rem; font-weight:600; }
        .form-control-sm { font-size:0.85rem; padding:0.4rem 0.6rem; background:#0d1117; color:#c9d1d9; border:1px solid #30363d; }
        .btn-sm { font-size:0.85rem; padding:0.4rem 0.9rem; }
        .form-text { font-size:0.75rem; color:#8b949e; }
        .tight-mb { margin-bottom:0.5rem !important; }
        .tox-tinymce { border:1px solid #30363d !important; }
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
                    <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= $sender_name_val ?>" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">From Email</label>
                    <input type="email" name="sender_email" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['from_email']) ?>" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control form-control-sm" value="<?= $subject_val ?>" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Message</label>
                    <textarea name="body" id="bodyEditor"><?= htmlspecialchars_decode($body_val) ?></textarea>
                    <div class="form-text mt-1 small">
                        [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
                    </div>
                </div>

                <!-- TinyMCE compact init -->
                <script>
                    let tinymceReady = false;

                    function initTiny() {
                        if (tinymceReady) return;
                        tinymce.init({
                            selector: '#bodyEditor',
                            height: 220,
                            menubar: false,
                            statusbar: false,
                            branding: false,
                            plugins: 'advlist lists link image code',
                            toolbar: 'undo redo | bold italic | bullist numlist | link image | code',
                            toolbar_mode: 'sliding',
                            toolbar_location: 'top',
                            toolbar_sticky: true,
                            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#c9d1d9; background:#0d1117; margin:8px; }',
                            skin: 'oxide-dark',
                            content_css: 'dark',
                            setup: (editor) => {
                                editor.on('init', () => {
                                    editor.focus();
                                    editor.getContainer().style.border = '1px solid #30363d';
                                    editor.getContainer().style.borderRadius = '6px';
                                });
                            }
                        });
                        tinymceReady = true;
                    }

                    document.getElementById('previewBtn').addEventListener('click', () => {
                        initTiny();
                        setTimeout(() => {
                            const content = tinymce.get('bodyEditor')?.getContent() || document.getElementById('bodyEditor').value;
                            const formData = new FormData(document.getElementById('mailerForm'));
                            formData.set('action', 'preview');
                            formData.set('body', content);
                            fetch('', { method: 'POST', body: formData })
                                .then(r => r.text())
                                .then(html => {
                                    document.querySelector('#previewModal .modal-content').innerHTML = html;
                                    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
                                    modal.show();
                                });
                        }, 400);
                    });
                </script>

                <div class="tight-mb">
                    <label class="form-label">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Recipients</label>
                    <textarea name="emails" class="form-control form-control-sm" rows="5" required></textarea>
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

            <div class="text-center mt-2 small text-muted">
                4RR0W H43D • Dark Compact
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Filled by JS -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
