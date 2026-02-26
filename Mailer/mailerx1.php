<?php
/**
 * Advanced PHPMailer Bulk Sender - Complex Edition 2026
 * Single file - Bootstrap 5 - Queue + Progress + CSV personalization
 * Author: Grok (xAI) - Use at your own risk - Only for legitimate consented sending
 */

session_start();

// ────────────────────────────────────────────────
// SECURITY & CSRF
// ────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ────────────────────────────────────────────────
// CONFIG DEFAULTS
// ────────────────────────────────────────────────
$defaults = [
    'smtp_host'     => 'smtp.zeptomail.com',
    'smtp_port'     => 587,
    'smtp_secure'   => 'tls',
    'smtp_user'     => 'emailapikey',
    'smtp_pass'     => 'YOUR_SMTP_TOKEN_HERE',
    'from_email'    => 'postmail@yourdomain.cc',
    'from_name'     => 'Company Name',
    'reply_to'      => '',
    'admin_pass'    => 'B0TH',           // ← CHANGE THIS!
    'delay_us'      => 250000,            // 0.25 sec
    'max_per_run'   => 50,                // prevent timeout
];

// Load from session or defaults
$smtp = [];
foreach ($defaults as $k => $v) {
    $smtp[$k] = $_SESSION['smtp'][$k] ?? $v;
}

// ────────────────────────────────────────────────
// AUTHENTICATION
// ────────────────────────────────────────────────
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    if (isset($_POST['admin_pass']) && $_POST['admin_pass'] === $defaults['admin_pass']) {
        $_SESSION['authenticated'] = true;
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
                <h4 class="text-center mb-4">Admin Login</h4>
                <form method="post">
                    <input type="password" name="admin_pass" class="form-control mb-3" placeholder="Password" autofocus required>
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
// QUEUE & LOG (session-based for simplicity)
// ────────────────────────────────────────────────
if (!isset($_SESSION['mail_queue'])) {
    $_SESSION['mail_queue'] = [];
    $_SESSION['mail_log']   = [];
}

// ────────────────────────────────────────────────
// HANDLE ACTIONS
// ────────────────────────────────────────────────
$alert = '';
$alert_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrf_token) {
        $alert = "Invalid CSRF token.";
        $alert_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_smtp') {
            // Save SMTP settings to session
            $smtp_keys = ['host','port','secure','user','pass','from_email','from_name','reply_to'];
            foreach ($smtp_keys as $k) {
                $key = 'smtp_' . $k;
                $_SESSION['smtp'][$k] = $_POST[$key] ?? $smtp[$k];
            }
            $alert = "SMTP settings saved.";
            $alert_type = 'success';
        }

        elseif ($action === 'add_to_queue') {
            $recipients = array_filter(array_map('trim', explode("\n", $_POST['emails'] ?? '')));
            $subject    = trim($_POST['subject'] ?? '');
            $body       = $_POST['body'] ?? '';
            $attach_receiver = isset($_POST['attach_receiver_email']);

            if (empty($recipients) || empty($subject) || empty($body)) {
                $alert = "Missing required fields.";
                $alert_type = 'danger';
            } else {
                foreach ($recipients as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $_SESSION['mail_queue'][] = [
                            'email'   => $email,
                            'subject' => $subject,
                            'body'    => $body,
                            'attach_receiver' => $attach_receiver,
                            'status'  => 'pending'
                        ];
                    }
                }
                $alert = count($recipients) . " email(s) added to queue.";
                $alert_type = 'success';
            }
        }

        elseif ($action === 'process_queue') {
            $processed = 0;
            $max_run   = $defaults['max_per_run'];

            foreach ($_SESSION['mail_queue'] as &$item) {
                if ($item['status'] === 'pending' && $processed < $max_run) {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = $smtp['host'];
                        $mail->Port       = (int)$smtp['port'];
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $smtp['username'];
                        $mail->Password   = $smtp['password'];
                        $mail->SMTPSecure = $smtp['secure'];

                        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
                        if (!empty($smtp['reply_to'])) {
                            $mail->addReplyTo($smtp['reply_to']);
                        }
                        $mail->addAddress($item['email']);

                        $body = $item['body'];
                        if ($item['attach_receiver']) {
                            $body = "<p><strong>To:</strong> {$item['email']}</p>\n" . $body;
                        }

                        $body = str_replace(
                            ['[-email-]', '[-emailuser-]', '[-emaildomain-]', '[-time-]', '[-randommd5-]'],
                            [
                                $item['email'],
                                explode('@', $item['email'])[0] ?? '',
                                explode('@', $item['email'])[1] ?? '',
                                date('Y-m-d H:i:s'),
                                md5(uniqid(rand(), true))
                            ],
                            $body
                        );

                        $mail->isHTML(true);
                        $mail->Subject = $item['subject'];
                        $mail->Body    = $body;
                        $mail->AltBody = strip_tags($body);

                        $mail->send();
                        $item['status'] = 'sent';
                        $_SESSION['mail_log'][] = ['time' => date('H:i:s'), 'email' => $item['email'], 'status' => 'OK'];
                        $processed++;
                    } catch (Exception $e) {
                        $item['status'] = 'failed';
                        $_SESSION['mail_log'][] = ['time' => date('H:i:s'), 'email' => $item['email'], 'status' => $mail->ErrorInfo];
                    }
                }
            }
            $alert = $processed ? "$processed email(s) processed." : "Queue empty or all processed.";
            $alert_type = $processed ? 'success' : 'info';
        }

        elseif ($action === 'clear_queue') {
            $_SESSION['mail_queue'] = [];
            $alert = "Queue cleared.";
            $alert_type = 'warning';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advanced PHPMailer Bulk Sender</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { padding: 1.5rem; background:#0d1117; color:#c9d1d9; font-size:0.95rem; }
        .card { background:#161b22; border:1px solid #30363d; border-radius:8px; }
        .form-control, .form-select { background:#0d1117; color:#c9d1d9; border:1px solid #30363d; }
        .form-control:focus { border-color:#1f6feb; box-shadow:0 0 0 0.2rem rgba(31,111,235,0.25); }
        .btn-primary { background:#1f6feb; border:none; }
        .btn-outline-secondary { border-color:#30363d; }
        .alert { font-size:0.9rem; }
        .progress { height:0.6rem; }
    </style>
</head>
<body>
<div class="container">
    <div class="card shadow">
        <div class="card-header text-center py-3">
            <h4 class="mb-0"><i class="bi bi-envelope-at me-2"></i>Advanced PHPMailer Bulk Sender</h4>
        </div>

        <div class="card-body p-4">

            <?php if ($alert): ?>
            <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
                <?= $alert ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $csrf_token ?>">

                <!-- SMTP Settings -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($smtp['host']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($smtp['port']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Secure</label>
                        <select name="smtp_secure" class="form-select">
                            <option value="">None</option>
                            <option value="tls" <?= $smtp['secure']==='tls'?'selected':'' ?>>STARTTLS</option>
                            <option value="ssl" <?= $smtp['secure']==='ssl'?'selected':'' ?>>SSL/TLS</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= htmlspecialchars($smtp['username']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="smtp_password" class="form-control" value="<?= htmlspecialchars($smtp['password']) ?>" required>
                    </div>
                </div>

                <!-- Sender -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">From Name</label>
                        <input type="text" name="sender_name" class="form-control" value="<?= htmlspecialchars($smtp['from_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Email</label>
                        <input type="email" name="sender_email" class="form-control" value="<?= htmlspecialchars($smtp['from_email']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Reply-To (optional)</label>
                        <input type="email" name="reply_to" class="form-control" value="<?= htmlspecialchars($smtp['reply_to'] ?? '') ?>">
                    </div>
                </div>

                <!-- Message -->
                <div class="mb-4">
                    <label class="form-label">Subject</label>
                    <input type="text" name="subject" class="form-control mb-2" required>
                    <label class="form-label">HTML Message</label>
                    <textarea name="body" class="form-control" rows="8" required placeholder="Hello [-emailuser-], your domain is [-emaildomain-] ..."></textarea>
                    <div class="form-text mt-1">
                        Placeholders: [-email-] [-emailuser-] [-emaildomain-] [-time-] [-randommd5-]
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="attach_receiver_email" value="1" id="attachReceiver" checked>
                    <label class="form-check-label" for="attachReceiver">Add "To: [-email-]" line at top</label>
                </div>

                <!-- Attachments -->
                <div class="mb-4">
                    <label class="form-label">Attachments (all recipients)</label>
                    <input type="file" name="attachments[]" class="form-control" multiple>
                </div>

                <!-- Recipients -->
                <div class="mb-4">
                    <label class="form-label">Recipients (one per line)</label>
                    <textarea name="emails" class="form-control" rows="6" required placeholder="user1@domain.com\nuser2@domain.com"></textarea>
                </div>

                <!-- Actions -->
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" name="action" value="save_smtp" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-save"></i> Save SMTP
                    </button>
                    <button type="submit" name="action" value="add_to_queue" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-plus-circle"></i> Add to Queue
                    </button>
                    <button type="submit" name="action" value="process_queue" class="btn btn-primary btn-sm">
                        <i class="bi bi-play-circle"></i> Process Queue
                    </button>
                    <button type="submit" name="action" value="clear_queue" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i> Clear Queue
                    </button>
                </div>
            </form>

            <!-- Queue Status -->
            <div class="mt-4">
                <h6>Queue Status (<?= count($_SESSION['mail_queue']) ?> pending)</h6>
                <?php if (!empty($_SESSION['mail_queue'])): ?>
                <div class="progress mb-2">
                    <?php
                    $total = count($_SESSION['mail_queue']);
                    $sent  = count(array_filter($_SESSION['mail_queue'], fn($i) => $i['status'] === 'sent'));
                    $pct   = $total ? round(($sent / $total) * 100) : 0;
                    ?>
                    <div class="progress-bar bg-success" style="width:<?= $pct ?>%"><?= $pct ?>%</div>
                </div>
                <?php endif; ?>

                <?php if (!empty($_SESSION['mail_log'])): ?>
                <div class="small bg-dark p-2 rounded">
                    <strong>Recent Log:</strong><br>
                    <?php foreach (array_slice(array_reverse($_SESSION['mail_log']), 0, 10) as $log): ?>
                        [<?= $log['time'] ?>] <?= htmlspecialchars($log['email']) ?> → <?= htmlspecialchars($log['status']) ?><br>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
