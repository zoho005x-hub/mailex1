<?php
/**
 * Compact Modern Bulk Mailer – 2026 Edition
 * Smaller layout + Attach receiver email option
 */

// ────────────────────────────────────────────────
// CONFIG (unchanged)
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

$admin_password = "B0TH";
$delay_us       = 150000;
$max_attach_size = 10 * 1024 * 1024;

// ────────────────────────────────────────────────
// PASSWORD PROTECTION (unchanged)
// ────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    if (isset($_POST['pass']) && $_POST['pass'] === $admin_password) {
        $_SESSION['auth'] = true;
    } else {
        // Compact login page
        ?>
        <!DOCTYPE html>
        <html lang="en" data-bs-theme="light">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Login</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>body{background:#0d1117;color:#c9d1d9;min-height:100vh;display:flex;align-items:center;justify-content:center;} .card{max-width:380px;}</style>
        </head>
        <body>
            <div class="card bg-dark border-secondary shadow">
                <div class="card-body p-4 text-center">
                    <h4 class="mb-4">Enter Password</h4>
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

// PHPMailer & disposable function (unchanged - omitted for brevity)

// ────────────────────────────────────────────────
// PREVIEW & SENDING LOGIC (only relevant changes shown)
// ────────────────────────────────────────────────

// ... (keep your existing preview and sending logic)

// In the sending loop, add this before str_replace:
$attach_receiver_email = isset($_POST['attach_receiver_email']) && $_POST['attach_receiver_email'] === '1';

$body_final = $body_raw;
if ($attach_receiver_email) {
    $body_final = "<p><strong>To:</strong> [-email-]</p>\n" . $body_final;
}

// Then use $body_final in str_replace and $mail->Body

// ────────────────────────────────────────────────
// COMPACT HOMEPAGE TEMPLATE
// ────────────────────────────────────────────────
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
        body { background:#f5f5f5; padding:1.5rem 1rem; font-size:0.95rem; }
        .card { border:none; box-shadow:0 2px 12px rgba(0,0,0,0.1); border-radius:10px; max-width:580px; margin:auto; }
        .card-header { background:linear-gradient(90deg,#0d6efd,#0056b3); color:white; padding:1rem; text-align:center; }
        .form-label { font-weight:600; margin-bottom:0.35rem; font-size:0.9rem; }
        .form-control, .form-control-lg { font-size:0.95rem; padding:0.55rem 0.75rem; }
        .btn { font-size:0.95rem; padding:0.6rem 1.2rem; }
        .form-text { font-size:0.8rem; }
        .compact-mb { margin-bottom:1rem !important; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="bi bi-envelope-at me-2"></i>4RR0W H43D Mailer</h4>
        </div>
        <div class="card-body p-4">
            <form method="post" enctype="multipart/form-data" id="mailerForm">
                <input type="hidden" name="action" value="send">

                <div class="compact-mb">
                    <label class="form-label">Sender Name</label>
                    <input type="text" name="sender_name" class="form-control" value="<?= htmlspecialchars($smtp['from_name']) ?>" required>
                </div>

                <div class="compact-mb">
                    <label class="form-label">Sender Email</label>
                    <input type="email" name="sender_email" class="form-control" value="<?= htmlspecialchars($smtp['from_email']) ?>" readonly>
                </div>

                <div class="compact-mb">
                    <label class="form-label">Reply-To (optional)</label>
                    <input type="email" name="reply_to" class="form-control" placeholder="replies@domain.com" value="<?= htmlspecialchars($smtp['from_email']) ?>">
                </div>

                <div class="compact-mb">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>

                <div class="compact-mb">
                    <label class="form-label">Message (HTML ok)</label>
                    <textarea name="body" id="bodyEditor" class="form-control" rows="8" required placeholder="Hello [-emailuser-],\nDomain: [-emaildomain-]\n[-time-] [-randommd5-]\n..."></textarea>
                    <div class="form-text mt-1">
                        Placeholders: [-email-], [-emailuser-], [-emaildomain-], [-time-], [-randommd5-]
                    </div>
                </div>

                <div class="compact-mb form-check">
                    <input type="checkbox" name="attach_receiver_email" value="1" class="form-check-input" id="attachReceiver">
                    <label class="form-check-label" for="attachReceiver">
                        Add "To: [-email-]" at the top of every message
                    </label>
                </div>

                <div class="compact-mb">
                    <label class="form-label">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control" multiple>
                    <div class="form-text">pdf, jpg, png, txt, docx, zip • max 10MB</div>
                </div>

                <div class="compact-mb">
                    <label class="form-label">Recipients (one per line)</label>
                    <textarea name="emails" class="form-control" rows="5" required placeholder="user1@gmail.com\njohn@yahoo.com\n..."></textarea>
                </div>

                <div class="d-flex gap-2 flex-column flex-md-row">
                    <button type="button" class="btn btn-outline-info flex-fill" id="previewBtn">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="bi bi-send"></i> Send
                    </button>
                </div>
            </form>

            <div class="text-center mt-3 small text-muted">
                Created by 4RR0W H43D • Test small first
            </div>
        </div>
    </div>

    <!-- Keep your existing Preview Modal and JavaScript from previous version -->
    <!-- ... paste your modal HTML and live preview JS here ... -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Your live preview script remains the same -->
</body>
</html>
