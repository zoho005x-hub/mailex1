<?php
// ... (keep all your existing PHP logic: config, session/auth, PHPMailer requires, disposable function, preview handler, sending loop with placeholders, attachments, etc.)

// Only the form part changes – replace your <body> content with this compact version:

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
        .form-control-sm, .form-control { font-size:0.85rem; padding:0.4rem 0.6rem; }
        .form-check-label { font-size:0.85rem; }
        .btn-sm { font-size:0.85rem; padding:0.4rem 0.9rem; }
        .form-text { font-size:0.75rem; }
        .tight-mb { margin-bottom:0.6rem !important; }
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
                    <label class="form-label">Sender Email</label>
                    <input type="email" name="sender_email" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['from_email']) ?>" required>
                    <div class="form-text text-danger">Must be verified in ZeptoMail → otherwise emails will bounce</div>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Reply-To</label>
                    <input type="email" name="reply_to" class="form-control form-control-sm" placeholder="optional" value="<?= htmlspecialchars($smtp['from_email']) ?>">
                </div>

                <div class="tight-mb">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control form-control-sm" required>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Message</label>
                    <textarea name="body" id="bodyEditor" class="form-control form-control-sm" rows="6" required placeholder="Hello [-emailuser-], [-emaildomain-]..."></textarea>
                    <div class="form-text mt-1">
                        [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
                    </div>
                </div>

                <div class="form-check tight-mb">
                    <input class="form-check-input" type="checkbox" name="attach_receiver_email" value="1" id="attachRec" checked>
                    <label class="form-check-label" for="attachRec">
                        Add "To: [-email-]" header
                    </label>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Attachments</label>
                    <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
                </div>

                <div class="tight-mb">
                    <label class="form-label">Recipients</label>
                    <textarea name="emails" class="form-control form-control-sm" rows="5" required placeholder="one@email.com&#10;another@email.com"></textarea>
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
                4RR0W H43D • Test small
            </div>
        </div>
    </div>

    <!-- Your existing Preview Modal goes here -->
    <!-- ... paste the modal HTML ... -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Your live preview JavaScript (unchanged) -->
    <!-- ... paste your <script> block with updatePreview(), debounce, etc. ... -->
</body>
</html>
