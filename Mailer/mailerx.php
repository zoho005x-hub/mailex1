let use this code:
 
code: <div class="card-body p-3">
    <form method="post" enctype="multipart/form-data" id="mailerForm">
        <input type="hidden" name="action" value="send">
        <div class="tight-mb">
            <label class="form-label">From Name</label>
            <input type="text" name="sender_name" class="form-control form-control-sm" value="<?= $sender_name_val ?>" required>
        </div>
        <div class="tight-mb">
            <label class="form-label">From Email</label>
            <input type="email" name="sender_email" class="form-control form-control-sm" value="<?= htmlspecialchars($smtp['from_email']) ?>" required>
            <div class="form-text text-danger small">Verified in ZeptoMail required</div>
        </div>
        <div class="tight-mb">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-control form-control-sm" value="<?= $subject_val ?>" required>
        </div>
        <div class="tight-mb">
            <label class="form-label">Message (WYSIWYG)</label>
            <textarea name="body" id="bodyEditor"><?= htmlspecialchars_decode($body_val) ?></textarea>
            <div class="form-text mt-1 small">
                Placeholders: [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
            </div>
        </div>
        <!-- TinyMCE init script goes here (the compact one above) -->
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
    <div class="text-center mt-2 small text-muted">
        4RR0W H43D • Compact Dark Mode
    </div>
</div>
