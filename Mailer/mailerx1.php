<?php
// ... (keep your existing PHP logic: session/auth, PHPMailer requires, isDisposable, preview handler)

// ────────────────────────────────────────────────
// HANDLE SMTP TEST
// ────────────────────────────────────────────────
$test_result = '';
$test_alert_class = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_smtp') {
    $test_email = trim($_POST['test_email'] ?? '');

    if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $test_result = "Invalid test email address.";
        $test_alert_class = 'alert-danger';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['secure'];
            $mail->Port       = $smtp['port'];

            $mail->setFrom($smtp['from_email'], $smtp['from_name']);
            $mail->addAddress($test_email);

            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test from 4RR0W Mailer';
            $mail->Body    = "<h2>Test Email</h2><p>This is a test message sent from your SMTP settings.<br>Time: " . date('Y-m-d H:i:s') . "</p>";
            $mail->AltBody = "Test Email\nThis is a test message sent from your SMTP settings.\nTime: " . date('Y-m-d H:i:s');

            $mail->send();
            $test_result = "Test email sent successfully to <strong>$test_email</strong>!";
            $test_alert_class = 'alert-success';
        } catch (Exception $e) {
            $test_result = "SMTP Test Failed: " . htmlspecialchars($mail->ErrorInfo);
            $test_alert_class = 'alert-danger';
        }
    }
}

// ... (keep sending logic unchanged)

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background:#f8f9fa; padding:1rem 0.75rem; font-size:0.9rem; line-height:1.3; }
        .card { border:none; box-shadow:0 1px 8px rgba(0,0,0,0.08); border-radius:8px; max-width:520px; margin:auto; }
        .card-header { background:#0d6efd; color:white; padding:0.75rem; text-align:center; font-size:1.1rem; font-weight:600; }
        .form-label { margin-bottom:0.25rem; font-size:0.85rem; font-weight:600; }
        .form-control-sm { font-size:0.85rem; padding:0.4rem 0.6rem; }
        .btn-sm { font-size:0.85rem; padding:0.4rem 0.9rem; }
        .form-text { font-size:0.75rem; }
        .tight-mb { margin-bottom:0.5rem !important; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <i class="bi bi-envelope-at me-1"></i>4RR0W Mailer
        </div>
        <div class="card-body p-3">

            <?php if ($test_result): ?>
            <div class="alert <?= $test_alert_class ?> alert-dismissible fade show mb-3" role="alert">
                <?= $test_result ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="mailerForm">
                <input type="hidden" name="action" value="send">

                <!-- SMTP Settings -->
                <div class="tight-mb">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['host']) ?>" required>
                </div>

                <div class="row g-2 tight-mb">
                    <div class="col-6">
                        <label class="form-label">Port</label>
                        <input type="number" name="smtp_port" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['port']) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Secure</label>
                        <select name="smtp_secure" class="form-select form-select-sm">
                            <option value="" <?= $smtp['secure'] === '' ? 'selected' : '' ?>>None</option>
                            <option value="tls" <?= $smtp['secure'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= $smtp['secure'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>
                </div>

                <div class="tight-mb">
                    <label class="form-label">SMTP Username</label>
                    <input type="text" name="smtp_username" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['username']) ?>" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">SMTP Password</label>
                    <input type="password" name="smtp_password" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['password']) ?>" required>
                </div>

                <!-- Sender & other fields -->
                <div class="tight-mb">
                    <label class="form-label">From Name</label>
                    <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['from_name']) ?>" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">From Email</label>
                    <input type="email" name="sender_email" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['from_email']) ?>" required>
                    <div class="form-text text-danger small">Must be verified in ZeptoMail</div>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Reply-To</label>
                    <input type="email" name="reply_to" class="form-control form-control-sm" placeholder="optional">
                </div>

                <div class="tight-mb">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control form-control-sm" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Message</label>
                    <textarea name="body" id="bodyEditor" class="form-control form-control-sm" rows="5" required></textarea>
                    <div class="form-text mt-1 small">
                        [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
                    </div>
                </div>

                <div class="form-check tight-mb">
                    <input class="form-check-input" type="checkbox" name="attach_receiver_email" value="1" id="attachRec" checked>
                    <label class="form-check-label small" for="attachRec">Add "To: [-email-]" header</label>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Test Email (for SMTP check)</label>
                    <div class="input-group input-group-sm">
                        <input type="email" name="test_email" class="form-control" placeholder="your@email.com">
                        <button type="submit" name="action" value="test_smtp" class="btn btn-outline-warning">
                            <i class="bi bi-lightning"></i> Test SMTP
                        </button>
                    </div>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Recipients</label>
                    <textarea name="emails" class="form-control form-control-sm" rows="4" required placeholder="email1@example.com&#10;email2@example.com"></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="previewBtn">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                    <button type="submit" name="action" value="send" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-send"></i> Send
                    </button>
                </div>
            </form>

            <div class="text-center mt-2 small text-muted">
                4RR0W H43D • Test SMTP first
            </div>
        </div>
    </div>

    <!-- Your Preview Modal HTML here -->
    <!-- ... -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Your live preview JavaScript here -->
    <!-- ... -->
</body>
</html>
