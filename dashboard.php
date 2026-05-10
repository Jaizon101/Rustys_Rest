<?php
require_once 'db.php';
startSession();
requireLogin('dashboard.php');

$user      = currentUser();
$isHost    = in_array($user['role'], ['host','both','admin']);
$activeTab = isset($_GET['tab']) ? clean($_GET['tab']) : 'bookings';
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
    .booking-card {
      background:var(--white); border-radius:var(--radius);
      border:1px solid var(--border); padding:1.25rem;
      display:grid; grid-template-columns:56px 1fr auto;
      gap:1rem; align-items:start; margin-bottom:1rem;
      box-shadow:var(--shadow);
    }
    .booking-card-title { font-family:'Playfair Display',serif; font-size:1rem; margin-bottom:.2rem; font-weight:600; }
    .booking-card-meta  { font-size:.82rem; color:var(--text-muted); margin-top:.2rem; }
    .booking-card-right { text-align:right; min-width:120px; }
    .booking-card-total { font-size:1.05rem; font-weight:700; color:var(--brown-dark); margin-top:.3rem; }
    .empty-dash { text-align:center; padding:3rem; color:var(--text-muted); }
    .empty-dash div { font-size:2.5rem; margin-bottom:.75rem; }

    /* ── Booking status badges ── */
    .status-pending   { background:#fff8ea; color:#9c6400; border:1px solid #f5d88e; }
    .status-approved  { background:#eaf7ee; color:#1e7e34; border:1px solid #a8ddb5; }
    .status-rejected  { background:#fdecea; color:#a61c00; border:1px solid #f5c6c6; }
    .status-cancelled { background:#f5f5f5; color:#555;    border:1px solid #ddd; }
    .status-completed { background:#eaf0ff; color:#1a56a8; border:1px solid #b3d4f5; }
    .status-badge {
      display:inline-block; padding:.28rem .8rem; border-radius:50px;
      font-size:.75rem; font-weight:700; white-space:nowrap;
    }

    /* Extend day section inside booking card */
    .extend-wrap {
      margin-top:.6rem; padding:.6rem .75rem;
      background:var(--beige); border-radius:var(--radius-sm);
      font-size:.82rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;
    }
    .extend-wrap label { margin:0; color:var(--brown-dark); font-size:.82rem; }
    .extend-wrap input[type=date] { width:auto; padding:.3rem .5rem; font-size:.82rem; }
    .extend-wrap button { padding:.3rem .8rem; font-size:.8rem; }

    /* action buttons in listings table */
    .action-btn-group { display:flex; gap:.35rem; flex-wrap:wrap; }
    .btn-edit {
      background:var(--beige-dark); border:1px solid var(--border);
      border-radius:var(--radius-sm); padding:.28rem .65rem;
      font-size:.8rem; cursor:pointer; color:var(--brown-dark);
      font-family:'DM Sans',sans-serif; transition:all var(--t);
      text-decoration:none; display:inline-block;
    }
    .btn-edit:hover { background:var(--accent); color:#fff; border-color:var(--accent); }
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
      <a href="?tab=bookings" class="dash-link <?= $activeTab==='bookings'?'active':'' ?>">📅 My Bookings</a>
      <?php if ($isHost): ?>
      <a href="?tab=listings" class="dash-link <?= $activeTab==='listings'?'active':'' ?>">🏠 My Listings</a>
      <?php endif; ?>
      <a href="?tab=profile"  class="dash-link <?= $activeTab==='profile'?'active':'' ?>">👤 Profile</a>
      <a href="logout.php"    class="dash-link danger">🚪 Log Out</a>
    </nav>
  </aside>

  <!-- ── MAIN ─────────────────────────────────────────────────── -->
  <main class="dash-main">

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card"><span class="stat-num" id="stat-bookings">—</span><span class="stat-label">My Bookings</span></div>
      <div class="stat-card"><span class="stat-num" id="stat-active">—</span><span class="stat-label">Approved</span></div>
      <div class="stat-card"><span class="stat-num" id="stat-spent">—</span><span class="stat-label">Total Spent</span></div>
      <?php if ($isHost): ?>
      <div class="stat-card"><span class="stat-num" id="stat-listings">—</span><span class="stat-label">My Listings</span></div>
      <?php endif; ?>
    </div>

    <!-- ══ TAB: MY BOOKINGS ════════════════════════════════════ -->
    <div class="tab-panel <?= $activeTab!=='bookings'?'hidden':'' ?>" id="tab-bookings">
      <h2 class="dash-title">My Bookings</h2>
      <div id="bookingsList"><div class="loading-spinner">Loading your bookings…</div></div>
    </div>

    <!-- ══ TAB: MY LISTINGS (host only) ═══════════════════════ -->
    <?php if ($isHost): ?>
    <div class="tab-panel <?= $activeTab!=='listings'?'hidden':'' ?>" id="tab-listings">
      <div class="dash-title-row">
        <h2 class="dash-title">My Listings</h2>
        <a href="host.php" class="btn-primary">+ Add New Room</a>
      </div>
      <div id="myListingsContainer"><div class="loading-spinner">Loading your listings…</div></div>
    </div>
    <?php endif; ?>

    <!-- ══ TAB: PROFILE ═══════════════════════════════════════ -->
    <div class="tab-panel <?= $activeTab!=='profile'?'hidden':'' ?>" id="tab-profile">
      <h2 class="dash-title">My Profile</h2>
      <div class="profile-form">
        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input type="text" value="<?= htmlspecialchars(explode(' ',$user['name'])[0]) ?>">
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" value="<?= htmlspecialchars(implode(' ',array_slice(explode(' ',$user['name']),1))) ?>">
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
var CSRF   = $('meta[name="csrf-token"]').attr('content');
var isHost = <?= $isHost ? 'true' : 'false' ?>;
var TODAY  = '<?= date('Y-m-d') ?>';

// ── Booking status badge ────────────────────────────────────────
function statusBadge(s) {
  var labels = {
    pending:   '⏳ Pending Approval',
    approved:  '✔ Approved',
    rejected:  '✘ Rejected',
    cancelled: '— Cancelled',
    completed: '★ Completed'
  };
  return '<span class="status-badge status-' + s + '">' + (labels[s] || s) + '</span>';
}

// ── Load My Bookings ────────────────────────────────────────────
function loadBookings() {
  api({ data: { action: 'bookings' } }, function (bookings) {

    // Stats — use booking_status field
    var approved = bookings.filter(function(b){ return b.booking_status === 'approved'; }).length;
    var spent    = bookings.reduce(function(s,b){ return s + parseFloat(b.total_price||0); }, 0);
    $('#stat-bookings').text(bookings.length);
    $('#stat-active').text(approved);
    $('#stat-spent').text('₱' + spent.toLocaleString());

    var $list = $('#bookingsList').empty();

    if (!bookings.length) {
      $list.html('<div class="empty-dash"><div>📅</div><p>No bookings yet.</p>' +
        '<a href="listings.php" class="btn-primary" style="margin-top:1rem">Browse Rooms</a></div>');
      return;
    }

    bookings.forEach(function (b) {
      var ci     = new Date(b.check_in  + 'T00:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
      var co     = new Date(b.check_out + 'T00:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
      var status = b.booking_status || 'pending';

      // Photo
      var img = b.cover_photo
        ? '<img src="'+b.cover_photo+'" style="width:56px;height:56px;object-fit:cover;border-radius:8px;display:block">'
        : '<div style="font-size:2.2rem;line-height:1">🏠</div>';

      // Cancel button — only for pending bookings
      var cancelBtn = (status === 'pending')
        ? '<br><button class="btn-danger btn-sm js-cancel-booking" data-id="'+b.id+'" style="margin-top:.45rem">Cancel Request</button>'
        : '';

      // Host note (shown if rejected)
      var hostNote = '';
      if (status === 'rejected' && b.host_note) {
        hostNote = '<div class="booking-card-meta" style="margin-top:.3rem;color:#a61c00">Host note: ' +
                   $('<div>').text(b.host_note).html() + '</div>';
      }

      // ── EXTEND DAY section (only for approved bookings with future checkout) ──
      var extendHtml = '';
      if (status === 'approved') {
        extendHtml =
          '<div class="extend-wrap" id="extend-wrap-' + b.id + '">' +
            '<label>Extend stay:</label>' +
            '<input type="date" id="extend-date-' + b.id + '"' +
              ' min="' + b.check_out + '"' +
              ' style="border-radius:6px;border:1px solid var(--border);padding:.28rem .5rem;font-size:.8rem">' +
            '<button class="btn-edit js-extend-booking"' +
              ' data-id="' + b.id + '"' +
              ' data-listing="' + b.listing_id + '"' +
              ' data-checkout="' + b.check_out + '">' +
              'Check &amp; Extend' +
            '</button>' +
            '<span class="extend-msg" id="extend-msg-' + b.id + '" style="font-size:.78rem"></span>' +
          '</div>';
      }

      var card = $('<div class="booking-card" data-id="'+b.id+'">').html(
        '<div>' + img + '</div>' +
        '<div>' +
          '<div class="booking-card-title">' + $('<div>').text(b.listing_title).html() + '</div>' +
          '<div class="booking-card-meta">📍 ' + $('<div>').text(b.city||'').html() + '</div>' +
          '<div class="booking-card-meta">📅 ' + ci + ' → ' + co +
            ' · ' + b.nights + ' night'+(b.nights>1?'s':'') +
            ' · ' + b.guests + ' guest'+(b.guests>1?'s':'')+'</div>' +
          '<div class="booking-card-meta">💳 ' + $('<div>').text(b.payment_method||'').html() + '</div>' +
          hostNote +
          extendHtml +
        '</div>' +
        '<div class="booking-card-right">' +
          statusBadge(status) +
          '<div class="booking-card-total">₱' + Number(b.total_price).toLocaleString() + '</div>' +
          '<div class="booking-card-meta">Requested ' + new Date(b.created_at).toLocaleDateString('en-PH') + '</div>' +
          cancelBtn +
        '</div>'
      );
      $list.append(card);
    });
  });
}

// ── Cancel booking ──────────────────────────────────────────────
$(document).on('click', '.js-cancel-booking', function () {
  if (!confirm('Cancel this booking request?')) return;
  var $btn = $(this).prop('disabled', true);
  api({
    method: 'POST',
    data:   { action: 'cancel_booking', id: $btn.data('id'), csrf_token: CSRF }
  }, function (_, msg) {
    toast(msg, 'success');
    loadBookings();
  }, function () { $btn.prop('disabled', false); });
});

// ── Extend stay ─────────────────────────────────────────────────
$(document).on('click', '.js-extend-booking', function () {
  var $btn       = $(this);
  var bookingId  = $btn.data('id');
  var listingId  = $btn.data('listing');
  var curOut     = $btn.data('checkout');
  var newOut     = $('#extend-date-' + bookingId).val();
  var $msg       = $('#extend-msg-' + bookingId);

  $msg.text('').css('color','var(--text-muted)');

  if (!newOut) {
    $msg.text('Please pick a new check-out date.').css('color','#a61c00'); return;
  }
  if (newOut <= curOut) {
    $msg.text('New date must be after current check-out (' + curOut + ').').css('color','#a61c00'); return;
  }

  $btn.prop('disabled', true).text('Checking…');
  $msg.text('Checking availability…').css('color','var(--text-muted)');

  // First check if the extended dates are free
  $.getJSON('api.php', {
    action:    'check_availability',
    id:        listingId,
    check_in:  curOut,    // from current checkout
    check_out: newOut     // to new checkout
  }, function (res) {
    if (!res.success) {
      $msg.text('Could not check availability.').css('color','#a61c00');
      $btn.prop('disabled', false).text('Check & Extend');
      return;
    }

    if (!res.data.date_available || !res.data.listing_available) {
      // Show NOT available message
      $msg.html('✘ <strong>Not available</strong> for those extra dates. Dates are already booked.')
          .css('color','#a61c00');
      $btn.prop('disabled', false).text('Check & Extend');
      return;
    }

    // Available — confirm then extend
    if (!confirm('Extend your stay to ' + newOut + '? This will send a new extension request to the host.')) {
      $btn.prop('disabled', false).text('Check & Extend');
      return;
    }

    api({
      method: 'POST',
      data: {
        action:     'extend_booking',
        id:         bookingId,
        new_checkout: newOut,
        csrf_token: CSRF
      }
    }, function (_, msg) {
      toast('Stay extended! ✅', 'success');
      $msg.text('✔ Extended to ' + newOut).css('color','#1e7e34');
      $btn.prop('disabled', false).text('Check & Extend');
      loadBookings(); // refresh to show new date
    }, function () {
      $btn.prop('disabled', false).text('Check & Extend');
    });
  }).fail(function () {
    $msg.text('Server error.').css('color','#a61c00');
    $btn.prop('disabled', false).text('Check & Extend');
  });
});

// ── Load My Listings (host only) — WITH EDIT BUTTON ─────────────
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

    var $wrap  = $('<div class="bookings-table-wrapper">');
    var $table = $('<table class="bookings-table">').html(
      '<thead><tr>' +
        '<th>Room</th><th>Type</th><th>Location</th>' +
        '<th>Price/Night</th><th>Status</th><th>Actions</th>' +
      '</tr></thead>'
    );
    var $tbody = $('<tbody>');

    listings.forEach(function (l) {
      var isAvail  = parseInt(l.available) === 1;
      var badgeCls = isAvail ? 'avail-badge-inline avail-yes' : 'avail-badge-inline avail-no';
      var badgeTxt = isAvail ? '✔ Available' : '✘ Not Available';
      var togTxt   = isAvail ? 'Mark Unavailable' : 'Mark Available';

      var $row = $('<tr data-id="'+l.id+'">').html(
        '<td><strong>' + $('<div>').text(l.title).html() + '</strong></td>' +
        '<td>' + $('<div>').text(l.type).html()  + '</td>' +
        '<td>' + $('<div>').text(l.city + (l.province?', '+l.province:'')).html() + '</td>' +
        '<td>₱' + Number(l.price_per_night).toLocaleString() + '</td>' +
        '<td><span class="' + badgeCls + '" id="badge-' + l.id + '">' + badgeTxt + '</span></td>' +
        '<td>' +
          '<div class="action-btn-group">' +
            // ── EDIT BUTTON — links to edit-listing.php ──
            '<a href="edit-listing.php?id=' + l.id + '" class="btn-edit">✏ Edit</a>' +
            '<button class="action-btn js-toggle-avail"' +
              ' data-id="' + l.id + '" data-available="' + l.available + '">' +
              togTxt +
            '</button>' +
            '<button class="btn-danger btn-sm js-confirm-delete"' +
              ' data-id="' + l.id + '" data-action="delete_listing"' +
              ' data-confirm="Delete \'' + $('<div>').text(l.title).html() + '\'? This cannot be undone.">' +
              'Delete' +
            '</button>' +
          '</div>' +
        '</td>'
      );
      $tbody.append($row);
    });

    $table.append($tbody);
    $wrap.append($table);
    $c.append($wrap);
  });
}

// ── Availability toggle ─────────────────────────────────────────
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

// ── Delete listing ──────────────────────────────────────────────
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
});
</script>
</body>
</html>
