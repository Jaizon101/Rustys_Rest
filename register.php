<?php
// ============================================================
//  register.php — Rusty's Rest and Lodging
//  Creates a new user in rustys_rest_db.users
// ============================================================
require_once 'db.php';
startSession();

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$redirect = trim($_GET['redirect'] ?? 'dashboard.php');
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $first    = trim($_POST['first_name'] ?? '');
        $last     = trim($_POST['last_name']  ?? '');
        $email    = trim($_POST['email']      ?? '');
        $password =      $_POST['password']   ?? '';
        $confirm  =      $_POST['confirm']    ?? '';
        $role     = trim($_POST['role']       ?? 'guest');

        if (!$first || !$last || !$email || !$password) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!in_array($role, ['guest','host','both'])) {
            $error = 'Invalid role selected.';
        } else {
            try {
                $db  = getDB();
                $chk = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    $error = 'An account with that email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins  = $db->prepare(
                        'INSERT INTO users (first_name,last_name,email,password,role)
                         VALUES (?,?,?,?,?)'
                    );
                    $ins->execute([$first, $last, $email, $hash, $role]);
                    $newId = $db->lastInsertId();

                    // Auto-login after registration
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $newId;
                    $_SESSION['user_name']  = "$first $last";
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role']  = $role;

                    $safe = preg_match('/^[a-zA-Z0-9_\-]+\.php(\?.*)?$/', $redirect)
                        ? $redirect : 'dashboard.php';
                    header('Location: ' . $safe);
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Registration failed. Please try again later.';
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
  <title>Rusty's Rest &amp; Lodging – Sign Up</title>
  <?= csrfMeta() ?>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .alert-error{background:#fdecea;border:1px solid #f5c6c6;color:#a61c00;border-radius:8px;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.9rem}
    #pwd-strength{font-size:.8rem;font-weight:600;margin-top:.3rem;height:1rem;display:block}
  </style>
</head>
<body class="auth-page">

<div class="auth-split">
  <div class="auth-visual">
    <a href="index.php" class="logo white">🏠 Rusty's Rest &amp; Lodging</a>
    <div class="auth-visual-text">
      <h2>Join our growing<br>community.</h2>
      <p>Sign up and start exploring comfortable rooms hosted by real people.</p>
    </div>
  </div>

  <div class="auth-form-panel">
    <div class="auth-form-inner">
      <h1>Create Account</h1>
      <p class="auth-sub">
        Already have an account?
        <a href="login.php?redirect=<?= urlencode($redirect) ?>">Log in</a>
      </p>

      <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="register.php?redirect=<?= urlencode($redirect) ?>">
        <?= csrfField() ?>

        <div class="form-row">
          <div class="form-group">
            <label>First Name *</label>
            <input type="text" name="first_name" placeholder="Juan"
                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required/>
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input type="text" name="last_name" placeholder="dela Cruz"
                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required/>
          </div>
        </div>

        <div class="form-group">
          <label>Email Address *</label>
          <input type="email" name="email" placeholder="you@example.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 autocomplete="email" required/>
        </div>

        <div class="form-group">
          <label>Password * <small style="font-weight:400;color:var(--text-muted)">(min. 8 characters)</small></label>
          <input type="password" id="reg-password" name="password"
                 placeholder="Create a strong password"
                 autocomplete="new-password" required minlength="8"/>
          <span id="pwd-strength"></span>
        </div>

        <div class="form-group">
          <label>Confirm Password *</label>
          <input type="password" name="confirm"
                 placeholder="Repeat your password"
                 autocomplete="new-password" required/>
        </div>

        <div class="form-group">
          <label>I am signing up as a:</label>
          <select name="role">
            <option value="guest" <?= (($_POST['role']??'')==='guest')?'selected':'' ?>>
              Guest — I want to book rooms
            </option>
            <option value="host"  <?= (($_POST['role']??'')==='host') ?'selected':'' ?>>
              Host — I want to list my room
            </option>
            <option value="both"  <?= (($_POST['role']??'')==='both') ?'selected':'' ?>>
              Both — I want to do both
            </option>
          </select>
        </div>

        <label class="check-label" style="margin-bottom:1rem">
          <input type="checkbox" required>
          I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
        </label>

        <button type="submit" class="btn-primary w-full">Create Account</button>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function () {
  // Password strength meter
  $('#reg-password').on('input', function () {
    var v = $(this).val();
    var score = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/, /.{8,}/]
                  .filter(function (r) { return r.test(v); }).length;
    var labels = ['', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    var colors = ['', '#c0392b', '#e67e22', '#f1c40f', '#27ae60', '#1e8449'];
    $('#pwd-strength').text(labels[score] || '').css('color', colors[score] || '');
  });

  // Disable submit on send to prevent double-click
  $('form').on('submit', function () {
    $(this).find('[type=submit]').prop('disabled', true).text('Creating account…');
  });
});
</script>
</body>
</html>