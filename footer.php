<?php // footer.php ?>
<footer class="footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <span class="logo">🏠 Rusty's Rest &amp; Lodging</span>
      <p>A trusted lodging management platform — PHP · MySQL · jQuery · AJAX</p>
    </div>
    <div class="footer-links">
      <h4>Platform</h4>
      <a href="listings.php">Browse Rooms</a>
      <a href="<?= (isset($user) && $user) ? 'host.php' : 'login.php?redirect=host.php' ?>">List Your Room</a>
      <?php if (isset($user) && $user): ?><a href="dashboard.php">Dashboard</a><?php endif; ?>
    </div>
    <div class="footer-links">
      <h4>Account</h4>
      <?php if (isset($user) && $user): ?>
        <a href="dashboard.php">My Dashboard</a>
        <a href="logout.php">Log Out</a>
      <?php else: ?>
        <a href="login.php">Log In</a>
        <a href="register.php">Sign Up</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="footer-copy">© 2026 Rusty's Rest &amp; Lodging. All rights reserved.</div>
</footer>