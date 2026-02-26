<div class="card-body p-3">
    <form method="post" enctype="multipart/form-data" id="mailerForm">
        <input type="hidden" name="action" value="send">

        <div class="tight-mb">
            <label class="form-label">From Name</label>
            <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= $sender_name_val ?>" required>
        </div>

        <div class="tight-mb">
            <label class="form-label">From Email Username</label>
            <div class="input-group input-group-sm">
                <input type="text" name="sender_username" class="form-control form-control-sm" value="<?= $sender_username_val ?>" required placeholder="notification-docusign">
                <span class="input-group-text">@<?= htmlspecialchars($smtp_domain) ?></span>
            </div>
            <div class="form-text text-danger small">Username editable • Domain fixed</div>
        </div>

        <div class="tight-mb">
            <label class="form-label">Reply-To (optional)</label>
            <input type="email" name="reply_to" class="form-control form-control-sm" value="<?= $reply_to_val ?>" placeholder="replies@domain.com">
        </div>

        <div class="tight-mb">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control form-control-sm" value="<?= $subject_val ?>" required>
        </div>

        <div class="tight-mb">
            <label class="form-label">Message</label>
            <textarea name="body" id="bodyEditor"><?= htmlspecialchars($body_val) ?></textarea>
            <div class="form-text mt-1 small">
                Placeholders: [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
            </div>
        </div>

        <!-- CKEditor 5 – ultra-compact toolbar (paste the script above here) -->

        <div class="tight-mb">
            <label class="form-label">Attachments</label>
            <input type="file" name="attachments[]" class="form-control form-control-sm" multiple>
        </div>

        <div class="tight-mb">
            <label class="form-label">Recipients</label>
            <textarea name="emails" class="form-control form-control-sm" rows="6" required placeholder="email1@example.com&#10;email2@example.com"></textarea>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4">
            <button type="button" class="btn btn-outline-info btn-lg flex-fill" id="previewBtn">
                <i class="bi bi-eye me-2"></i>Preview
            </button>
            <button type="submit" class="btn btn-primary btn-lg flex-fill">
                <i class="bi bi-send me-2"></i>Send
            </button>
        </div>
    </form>

    <div class="text-center mt-4 small text-muted">
        4RR0W H43D • Dark • Ultra-compact CKEditor
    </div>
</div>
