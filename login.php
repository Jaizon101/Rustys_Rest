<?php
// ============================================================
//  login.php — Rusty's Rest and Lodging
//  Authenticates against rustys_rest_db.users
// ============================================================
require_once 'db.php';
startSession();

// Already logged in → go to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Where to send user after successful login
$redirect = trim($_GET['redirect'] ?? 'dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF check ──────────────────────────────────────────────
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password =      $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error = 'Please enter your email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $db   = getDB();
                // Prepared statement — no SQL injection possible
                $stmt = $db->prepare(
                    'SELECT id, first_name, last_name, email, password, role
                     FROM users WHERE email = ? LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // ✅ Credentials valid — regenerate session ID (session fixation prevention)
                    session_regenerate_id(true);

                    $_SESSION['user_id']    = $user['id'];
                    $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role'];

                    // Safe redirect — only allow .php files in same directory
                    $safe = preg_match('/^[a-zA-Z0-9_\-]+\.php(\?.*)?$/', $redirect)
                        ? $redirect : 'dashboard.php';

                    header('Location: ' . $safe);
                    exit;
                } else {
                    // Intentionally vague — don't reveal if email exists
                    $error = 'Incorrect email or password. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'A database error occurred. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rusty's Rest &amp; Lodging – Log In</title>
  <?= csrfMeta() ?>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .alert-error   { background:#fdecea;border:1px solid #f5c6c6;color:#a61c00;border-radius:8px;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.9rem; }
    .alert-info    { background:#eaf3ff;border:1px solid #b3d4f5;color:#1a5fa8;border-radius:8px;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.9rem; }
    .alert-success { background:#eaf7ee;border:1px solid #a8ddb5;color:#1e7e34;border-radius:8px;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.9rem; }
  </style>
</head>
<body class="auth-page">

<div class="auth-split">

  <!-- ── Left visual panel ─────────────────────────────────── -->
  <div class="auth-visual">
    <a href="index.php" class="logo white">🏠 Rusty's Rest &amp; Lodging</a>
    <div class="auth-visual-text">
      <h2>Welcome back,<br>valued guest.</h2>
      <p>Log in to manage your bookings, explore rooms, and host with confidence.</p>
    </div>
  </div>

  <!-- ── Right form panel ───────────────────────────────────── -->
  <div class="auth-form-panel">
    <div class="auth-form-inner">
      <h1>Log In</h1>
      <p class="auth-sub">
        Don't have an account?
        <a href="register.php?redirect=<?= urlencode($redirect) ?>">Sign up</a>
      </p>

      <?php if ($redirect === 'host.php'): ?>
        <div class="alert-info">🏠 Please log in first to list your room.</div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- POST to login.php — CSRF token in hidden field -->
      <form method="POST" action="login.php?redirect=<?= urlencode($redirect) ?>">
        <?= csrfField() ?>

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email"
                 placeholder="you@example.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 autocomplete="email" required/>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="••••••••"
                 autocomplete="current-password" required/>
        </div>

        <div class="form-row-between">
          <label class="check-label">
            <input type="checkbox" name="remember"> Remember me
          </label>
          <a href="#" class="link-muted">Forgot password?</a>
        </div>

        <button type="submit" class="btn-primary w-full">Log In</button>
      </form>

      <div class="divider">or</div>
      <div style="text-align:center;font-size:.88rem;color:var(--text-muted)">
        New here? <a href="register.php">Create a free account</a>
      </div>

      <!-- Demo credentials hint -->
      <div style="margin-top:1.5rem;background:var(--beige);border-radius:8px;padding:.85rem 1rem;font-size:.82rem;color:var(--brown)">
        <strong>Demo accounts</strong><br>
        Admin: admin@rustysrest.com<br>
        Host:&nbsp; maria@example.com<br>
        Guest: pedro@example.com<br>
        Password for all: <code>password123</code>
      </div>
    </div>
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function () {
  // Client-side: disable submit while processing to prevent double-submit
  $('form').on('submit', function () {
    $(this).find('[type=submit]').prop('disabled', true).text('Logging in…');
  });
});
</script>
</body>
</html>