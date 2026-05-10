<?php
require_once 'db.php';
startSession();
requireLogin('dashboard.php');

$user    = currentUser();
$isHost  = in_array($user['role'], ['host','both','admin']);
$activeTab = isset($_GET['tab']) ? clean($_GET['tab']) : 'bookings';
// If user is not a host, redirect listings tab to bookings
if ($activeTab === 'listings' && !$isHost) $activeTab = 'bookings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rusty's Rest &amp; Lodging – Dashboard</title>
  <?= csrfMeta() ?>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Availability toggle switch in My Listings table */
    .toggle-wrap { display:flex; align-items:center; gap:.6rem; }
    .toggle-switch {
      position:relative; width:42px; height:22px; cursor:pointer;
    }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider {
      position:absolute; inset:0; background:#ccc;
      border-radius:22px; transition:.2s;
    }
    .toggle-slider::before {
      content:''; position:absolute; width:16px; height:16px;
      left:3px; bottom:3px; background:#fff;
      border-radius:50%; transition:.2s;
    }
    .toggle-switch input:checked + .toggle-slider { background:var(--accent); }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }
    .toggle-label { font-size:.8rem; font-weight:600; }

    /* Booking card (mobile-friendly list) */
    .booking-card {
      background:var(--white); border-radius:var(--radius);
      border:1px solid var(--border); padding:1.25rem;
      display:grid; grid-template-columns:auto 1fr auto;
      gap:1rem; align-items:center; margin-bottom:1rem;
      box-shadow:var(--shadow);
    }
    .booking-card-emoji { font-size:2.2rem; }
    .booking-card-title { font-family:'Playfair Display',serif; font-size:1rem; margin-bottom:.2rem; }
    .booking-card-meta  { font-size:.82rem; color:var(--text-muted); }
    .booking-card-right { text-align:right; }
    .booking-card-total { font-size:1.05rem; font-weight:700; color:var(--brown-dark); }

    .empty-dash { text-align:center; padding:3rem; color:var(--text-muted); }
    .empty-dash div { font-size:2.5rem; margin-bottom:.75rem; }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="dashboard-layout">

  <!-- ── SIDEBAR ──────────────────────────────────────────────── -->
  <aside class="dash-sidebar">
    <div class="user-card">
      <div class="user-avatar"><?= strtoupper(substr($user['name'],0,2)) ?></div>
      <div>
        <strong><?= htmlspecialchars($user['name']) ?></strong>
        <span><?= ucfirst($user['role']) ?></span>
      </div>
    </div>
    <nav class="dash-nav">
      <a href="?tab=bookings"  class="dash-link <?= $activeTab==='bookings' ?'active':'' ?>">📅 My Bookings</a>
      <?php if ($isHost): ?>
      <a href="?tab=listings"  class="dash-link <?= $activeTab==='listings' ?'active':'' ?>">🏠 My Listings</a>
      <?php endif; ?>
      <a href="?tab=profile"   class="dash-link <?= $activeTab==='profile'  ?'active':'' ?>">👤 Profile</a>
      <a href="logout.php"     class="dash-link danger">🚪 Log Out</a>
    </nav>
  </aside>

  <!-- ── MAIN ─────────────────────────────────────────────────── -->
  <main class="dash-main">

    <!-- Stats row: loaded via AJAX -->
    <div class="stats-row" id="statsRow">
      <div class="stat-card"><span class="stat-num" id="stat-bookings">—</span><span class="stat-label">My Bookings</span></div>
      <div class="stat-card"><span class="stat-num" id="stat-active">—</span><span class="stat-label">Active</span></div>
      <div class="stat-card"><span class="stat-num" id="stat-spent">—</span><span class="stat-label">Total Spent</span></div>
      <?php if ($isHost): ?>
      <div class="stat-card"><span class="stat-num" id="stat-listings">—</span><span class="stat-label">My Listings</span></div>
      <?php endif; ?>
    </div>

    <!-- ══ TAB: MY BOOKINGS ════════════════════════════════════ -->
    <div class="tab-panel <?= $activeTab!=='bookings'?'hidden':'' ?>" id="tab-bookings">
      <h2 class="dash-title">My Bookings</h2>
      <div id="bookingsList">
        <div class="loading-spinner">Loading your bookings…</div>
      </div>
    </div>

    <!-- ══ TAB: MY LISTINGS (host only) ═══════════════════════ -->
    <?php if ($isHost): ?>
    <div class="tab-panel <?= $activeTab!=='listings'?'hidden':'' ?>" id="tab-listings">
      <div class="dash-title-row">
        <h2 class="dash-title">My Listings</h2>
        <a href="host.php" class="btn-primary">+ Add New Room</a>
      </div>
      <div id="myListingsContainer">
        <div class="loading-spinner">Loading your listings…</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ TAB: PROFILE ═══════════════════════════════════════ -->
    <div class="tab-panel <?= $activeTab!=='profile'?'hidden':'' ?>" id="tab-profile">
      <h2 class="dash-title">My Profile</h2>
      <div class="profile-form">
        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input type="text" value="<?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>">
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" value="<?= htmlspecialchars(implode(' ', array_slice(explode(' ', $user['name']), 1))) ?>">
          </div>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" value="<?= htmlspecialchars($user['email']) ?>">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="tel" placeholder="+63 912 345 6789">
        </div>
        <div class="form-group">
          <label>Bio</label>
          <textarea rows="3" placeholder="Tell guests a little about yourself…"></textarea>
        </div>
        <button class="btn-primary" onclick="toast('Profile saved!','success')">Save Changes</button>
      </div>
    </div>

  </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="main.js"></script>
<script>
var CSRF    = $('meta[name="csrf-token"]').attr('content');
var isHost  = <?= $isHost ? 'true' : 'false' ?>;

// ── Status badge helper ─────────────────────────────────────────
function statusBadge(s) {
  var map = {
    confirmed: 'badge confirmed',
    pending:   'badge pending',
    cancelled: 'badge cancelled',
    completed: 'badge completed'
  };
  return '<span class="' + (map[s]||'badge') + '">' + s.charAt(0).toUpperCase()+s.slice(1) + '</span>';
}

// ── Load My Bookings ────────────────────────────────────────────
function loadBookings() {
  api({ data: { action: 'bookings' } }, function (bookings) {

    // Update stats
    var active = bookings.filter(function(b){ return b.status==='confirmed'; }).length;
    var spent  = bookings.reduce(function(s,b){ return s + parseFloat(b.total_price); }, 0);
    $('#stat-bookings').text(bookings.length);
    $('#stat-active').text(active);
    $('#stat-spent').text('₱' + spent.toLocaleString());

    var $list = $('#bookingsList').empty();

    if (!bookings.length) {
      $list.html('<div class="empty-dash"><div>📅</div><p>No bookings yet.</p>' +
                 '<a href="listings.php" class="btn-primary" style="margin-top:1rem">Browse Rooms</a></div>');
      return;
    }

    bookings.forEach(function (b) {
      // Dates
      var ci = new Date(b.check_in  + 'T00:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
      var co = new Date(b.check_out + 'T00:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});

      // Cancel button only for pending
      var cancelBtn = (b.status === 'pending')
        ? '<button class="btn-danger btn-sm js-cancel-booking" data-id="'+b.id+'" style="margin-top:.5rem">Cancel</button>'
        : '';

      // Room photo or emoji
      var img = b.cover_photo
        ? '<img src="'+b.cover_photo+'" style="width:56px;height:56px;object-fit:cover;border-radius:8px">'
        : '<div style="font-size:2.2rem">🏠</div>';

      var card = $('<div class="booking-card">').html(
        '<div>' + img + '</div>' +
        '<div>' +
          '<div class="booking-card-title">' + $('<div>').text(b.listing_title).html() + '</div>' +
          '<div class="booking-card-meta">📍 ' + $('<div>').text(b.city).html() + '</div>' +
          '<div class="booking-card-meta" style="margin-top:.25rem">📅 ' + ci + ' → ' + co +
            ' &nbsp;·&nbsp; ' + b.nights + ' night' + (b.nights>1?'s':'') +
            ' &nbsp;·&nbsp; ' + b.guests + ' guest' + (b.guests>1?'s':'') + '</div>' +
          '<div class="booking-card-meta" style="margin-top:.25rem">💳 ' + $('<div>').text(b.payment_method).html() + '</div>' +
        '</div>' +
        '<div class="booking-card-right">' +
          statusBadge(b.status) +
          '<div class="booking-card-total" style="margin-top:.4rem">₱' + Number(b.total_price).toLocaleString() + '</div>' +
          '<div class="booking-card-meta">Booked ' + new Date(b.created_at).toLocaleDateString('en-PH') + '</div>' +
          cancelBtn +
        '</div>'
      );
      $list.append(card);
    });
  });
}

// ── Cancel booking ──────────────────────────────────────────────
$(document).on('click', '.js-cancel-booking', function () {
  if (!confirm('Cancel this booking?')) return;
  var id   = $(this).data('id');
  var $btn = $(this).prop('disabled', true);
  api({
    method: 'POST',
    data:   { action: 'cancel_booking', id: id, csrf_token: CSRF }
  }, function (_, msg) {
    toast(msg, 'success');
    loadBookings(); // refresh list
  }, function () { $btn.prop('disabled', false); });
});

// ── Load My Listings (host only) ────────────────────────────────
function loadMyListings() {
  if (!isHost) return;
  api({ data: { action: 'host_listings' } }, function (listings) {

    $('#stat-listings').text(listings.length);
    var $c = $('#myListingsContainer').empty();

    if (!listings.length) {
      $c.html('<div class="empty-dash"><div>🏠</div>' +
              '<p>You haven\'t listed any rooms yet.</p>' +
              '<a href="host.php" class="btn-primary" style="margin-top:1rem">List a Room</a></div>');
      return;
    }

    // Build table
    var $wrap  = $('<div class="bookings-table-wrapper">');
    var $table = $('<table class="bookings-table">').html(
      '<thead><tr>' +
        '<th>Room</th><th>Type</th><th>Location</th>' +
        '<th>Price/Night</th><th>Availability</th><th>Action</th>' +
      '</tr></thead>'
    );
    var $tbody = $('<tbody>');

    listings.forEach(function (l) {
      var isAvail   = parseInt(l.available) === 1;
      var badgeCls  = isAvail ? 'avail-badge-inline avail-yes' : 'avail-badge-inline avail-no';
      var badgeTxt  = isAvail ? '✔ Available' : '✘ Not Available';
      var toggleTxt = isAvail ? 'Mark Unavailable' : 'Mark Available';

      var $row = $('<tr>').html(
        '<td><strong>' + $('<div>').text(l.title).html() + '</strong></td>' +
        '<td>' + $('<div>').text(l.type).html() + '</td>' +
        '<td>' + $('<div>').text(l.city + (l.province?', '+l.province:'')).html() + '</td>' +
        '<td>₱' + Number(l.price_per_night).toLocaleString() + '</td>' +
        '<td><span class="' + badgeCls + '" id="badge-' + l.id + '">' + badgeTxt + '</span></td>' +
        '<td>' +
          '<button class="action-btn js-toggle-avail" ' +
            'data-id="' + l.id + '" data-available="' + l.available + '" ' +
            'style="margin-right:.4rem">' + toggleTxt + '</button>' +
          '<button class="btn-danger btn-sm js-confirm-delete" ' +
            'data-id="' + l.id + '" data-action="delete_listing" ' +
            'data-confirm="Delete this listing? This cannot be undone.">' +
            'Delete</button>' +
        '</td>'
      );
      $tbody.append($row);
    });

    $table.append($tbody);
    $wrap.append($table);
    $c.append($wrap);
  });
}

// ── Availability toggle (inline in My Listings table) ───────────
$(document).on('click', '.js-toggle-avail', function () {
  var $btn   = $(this);
  var id     = $btn.data('id');
  var newVal = parseInt($btn.data('available')) === 1 ? 0 : 1;

  api({
    method: 'POST',
    data:   { action: 'toggle_availability', id: id, available: newVal, csrf_token: CSRF }
  }, function (_, msg) {
    toast(msg, 'success');
    $btn.data('available', newVal);

    var $badge = $('#badge-' + id);
    if (newVal === 1) {
      $badge.removeClass('avail-no').addClass('avail-yes').text('✔ Available');
      $btn.text('Mark Unavailable');
    } else {
      $badge.removeClass('avail-yes').addClass('avail-no').text('✘ Not Available');
      $btn.text('Mark Available');
    }
  });
});

// ── Delete listing (inline row removal) ─────────────────────────
$(document).on('click', '.js-confirm-delete', function (e) {
  e.preventDefault();
  if (!confirm($(this).data('confirm') || 'Are you sure?')) return;
  var $btn = $(this);
  api({
    method: 'POST',
    data:   { action: $btn.data('action'), id: $btn.data('id'), csrf_token: CSRF }
  }, function (_, msg) {
    toast(msg, 'success');
    $btn.closest('tr').fadeOut(350, function(){ $(this).remove(); });
  });
});

// ── Init ────────────────────────────────────────────────────────
$(function () {
  loadBookings();
  if (isHost) loadMyListings();

  // Tab switching
  $('.dash-link').on('click', function (e) {
    var href = $(this).attr('href');
    if (!href || href.indexOf('?tab=') === -1) return; // let logout link through
    // Handled by page reload via href
  });
});
</script>
</body>
</html>