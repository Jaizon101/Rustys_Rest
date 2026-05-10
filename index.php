<?php
require_once 'db.php';
startSession();
$user       = currentUser();
$jsonData   = json_decode(file_get_contents(__DIR__ . '/data.json'), true);
$categories = $jsonData['categories'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rusty's Rest &amp; Lodging – Find Your Perfect Room</title>
  <?= csrfMeta() ?>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<!-- ── HERO ──────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-content">
    <span class="hero-tag">Comfortable Lodging, Honest Prices</span>
    <h1>Welcome to<br><em>Rusty's Rest &amp; Lodging</em></h1>
    <p>Find clean, comfortable rooms hosted by real people — rated and reviewed by guests like you.</p>
    <form class="search-box" method="GET" action="listings.php">
      <div class="search-field">
        <label>Where</label>
        <input type="text" id="hero-where" name="location" placeholder="City or province…" autocomplete="off"/>
      </div>
      <div class="search-divider"></div>
      <div class="search-field">
        <label>Check-in</label>
        <input type="date" name="check_in"/>
      </div>
      <div class="search-divider"></div>
      <div class="search-field">
        <label>Check-out</label>
        <input type="date" name="check_out"/>
      </div>
      <div class="search-divider"></div>
      <div class="search-field">
        <label>Guests</label>
        <input type="number" name="guests" min="1" max="20" placeholder="1"/>
      </div>
      <button type="submit" class="search-btn">Search</button>
    </form>


<!-- ── CATEGORIES ────────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <h2 class="section-title">Browse by room type</h2>
    <div class="categories">
      <?php foreach ($categories as $c): ?>
        <a class="cat-card" href="listings.php?type=<?= urlencode($c['label']) ?>">
          <?= $c['emoji'] ?><span><?= htmlspecialchars($c['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── FEATURED LISTINGS — jQuery AJAX ───────────────────────── -->
<section class="section bg-cream">
  <div class="container">
    <h2 class="section-title">Featured Rooms</h2>
    <div class="listings-grid" id="featuredGrid">
      <div class="loading-spinner">Loading rooms…</div>
    </div>
    <div class="text-center" style="margin-top:2rem">
      <a href="listings.php" class="btn-primary">View All Rooms</a>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ──────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <h2 class="section-title">How It Works</h2>
    <div class="steps">
      <div class="step">
        <div class="step-num">01</div>
        <h3>Search</h3>
        <p>Enter your destination, dates, and number of guests. Browse rooms with real photos and availability badges.</p>
      </div>
      <div class="step">
        <div class="step-num">02</div>
        <h3>Book</h3>
        <p>Review room details, confirm pricing, and complete your booking in seconds.</p>
      </div>
      <div class="step">
        <div class="step-num">03</div>
        <h3>Stay</h3>
        <p>Check in with ease and enjoy your stay. Leave a review when you're done!</p>
      </div>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>
<?php include 'booking-modal.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="main.js"></script>
<script>
$(function () {
  // Load top-rated rooms via jQuery AJAX → api.php
  api({ data: { action: 'listings', sort: 'rating' } }, function (listings) {
    renderListings(listings.slice(0, 3), $('#featuredGrid'), <?= $user ? 'true' : 'false' ?>);
  });

  // ── Live "Where" map: update iframe as user types in the location ──
  var mapTimer = null;
  $('#hero-where').on('input', function () {
    var q = $(this).val().trim();
    clearTimeout(mapTimer);
    mapTimer = setTimeout(function () {
      if (!q) {
        $('#whereMap').attr('src', 'https://maps.google.com/maps?q=Philippines&output=embed&z=6');
        $('#whereMapLabel').text('Philippines — type a city or province above to zoom in');
      } else {
        var query = encodeURIComponent(q + ', Philippines');
        $('#whereMap').attr('src', 'https://maps.google.com/maps?q=' + query + '&output=embed&z=11');
        $('#whereMapLabel').text('Showing: ' + q);
      }
    }, 600); // wait until typing pauses
  });
});
</script>
</body>
</html>
