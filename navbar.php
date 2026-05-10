<?php // navbar.php
if (!isset($user)) $user = currentUser();
$cur = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
  <div class="nav-inner">
    <a href="index.php" class="logo">🏠 Rusty's Rest &amp; Lodging</a>
    <ul class="nav-links">
      <li><a href="listings.php" <?= $cur==='listings.php'?'class="active"':'' ?>>Explore Rooms</a></li>
      <?php if ($user): ?>
        <li><a href="dashboard.php" <?= $cur==='dashboard.php'?'class="active"':'' ?>>Dashboard</a></li>
        <li><a href="host.php"      <?= $cur==='host.php'?'class="active"':'' ?>>List a Room</a></li>
        <?php if ($user['role']==='admin'): ?>
          <li><a href="admin.php"   <?= $cur==='admin.php'?'class="active"':'' ?>>⚙ Admin</a></li>
        <?php endif; ?>
      <?php endif; ?>
    </ul>
    <div class="nav-auth">
      <?php if ($user): ?>
        <span class="user-pill">👤 <?= htmlspecialchars($user['name']) ?></span>
        <a href="logout.php" class="btn-ghost">Log Out</a>
      <?php else: ?>
        <a href="login.php"    class="btn-ghost">Log In</a>
        <a href="register.php" class="btn-primary">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>
</nav>