<?php
// ... (keep all your existing PHP logic: session/auth, config, PHPMailer requires, isDisposable, sending loop, etc.)
// Only add this new preview handler that supports editing

// ────────────────────────────────────────────────
// EDITABLE PREVIEW HANDLER
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
            <h5 class="modal-title">Editable Message Preview</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p class="small text-muted mb-2">Sample recipient: <?= htmlspecialchars($preview_sample_email) ?></p>

            <ul class="nav nav-tabs nav-fill border-secondary mb-2" id="previewTab">
                <li class="nav-item">
                    <button class="nav-link active bg-dark text-light" data-bs-toggle="tab" data-bs-target="#html">HTML (Editable)</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link bg-dark text-light" data-bs-toggle="tab" data-bs-target="#plain">Plain Text</button>
                </li>
            </ul>

            <div class="tab-content border border-top-0 border-secondary p-3 bg-dark rounded-bottom">
                <div class="tab-pane fade show active" id="html">
                    <div contenteditable="true" id="editablePreview" class="border border-secondary p-3 bg-black text-light rounded" style="min-height:300px; outline:none;">
                        <?= $body_preview ?>
                    </div>
                    <div class="mt-2 text-end">
                        <button type="button" class="btn btn-sm btn-success" id="applyEdit">Apply & Close</button>
                    </div>
                </div>
                <div class="tab-pane fade" id="plain">
                    <pre class="bg-secondary p-3 rounded m-0 text-light" style="white-space:pre-wrap;"><?= htmlspecialchars($plain_preview) ?></pre>
                </div>
            </div>
        </div>
    </div>

    <script>
        const editable = document.getElementById('editablePreview');
        const applyBtn = document.getElementById('applyEdit');

        applyBtn.addEventListener('click', () => {
            const editedContent = editable.innerHTML;
            document.querySelector('#bodyEditor').value = editedContent;
            bootstrap.Modal.getInstance(document.getElementById('previewModal')).hide();
        });
    </script>
    <?php
    exit;
}
?>

<!-- In your main HTML form (replace your existing <form> part) -->
<form method="post" enctype="multipart/form-data" id="mailerForm">
    <input type="hidden" name="action" value="send">

    <!-- ... your other fields: From Name, From Email, Reply-To, Subject ... -->

    <div class="tight-mb">
        <label class="form-label">Message (HTML supported - editable in preview)</label>
        <textarea name="body" id="bodyEditor" class="form-control" rows="10" required placeholder="Hello [-email-], ..."><?= htmlspecialchars($body_val) ?></textarea>
        <div class="form-text mt-2">
            Placeholders: <code>[-email-]</code> <code>[-emailuser-]</code> <code>[-emaildomain-]</code> <code>[-time-]</code> <code>[-randommd5-]</code><br>
            Click Preview → edit directly → Apply to save changes back here
        </div>
    </div>

    <!-- ... rest of your form: attachments, recipients, buttons ... -->

    <div class="d-grid gap-2 d-md-flex justify-content-md-between">
        <button type="button" class="btn btn-outline-info btn-lg flex-fill" id="previewBtn">
            <i class="bi bi-eye me-2"></i>Open Editable Preview
        </button>
        <button type="submit" class="btn btn-primary btn-lg flex-fill">
            <i class="bi bi-send me-2"></i>Start Sending
        </button>
    </div>
</form>

<!-- Your Preview Modal remains the same, but now includes editable content -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <!-- Filled dynamically by the preview handler above -->
        </div>
    </div>
</div>

<!-- Keep your existing live preview JavaScript -->
<script>
    // ... your previous script for opening modal, debounce input, etc. ...
    // No changes needed – it already works with the new editable preview
</script>
