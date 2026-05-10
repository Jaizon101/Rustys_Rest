<?php
// ============================================================
//  room.php — Rusty's Rest and Lodging
//  Room detail page with interactive availability calendar.
//  Visible to everyone; booking form shown to logged-in guests.
// ============================================================
require_once 'db.php';
startSession();

$user      = currentUser();
$listingId = cleanInt($_GET['id'] ?? 0);

if (!$listingId) {
    header('Location: listings.php');
    exit;
}

// Fetch room details from DB
try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT l.*,
               CONCAT(u.first_name,' ',u.last_name) AS host_name,
               u.email AS host_email
        FROM listings l
        JOIN users u ON u.id = l.host_id
        WHERE l.id = ? LIMIT 1
    ");
    $stmt->execute([$listingId]);
    $room = $stmt->fetch();

    if (!$room) {
        header('Location: listings.php');
        exit;
    }

    // Fetch all booked date ranges (pending + approved = blocks the calendar)
    $bkStmt = $db->prepare("
        SELECT check_in, check_out, booking_status
        FROM bookings
        WHERE listing_id = ? AND booking_status IN ('pending','approved')
        ORDER BY check_in
    ");
    $bkStmt->execute([$listingId]);
    $bookedRanges = $bkStmt->fetchAll();

    // Amenities
    $amenities = json_decode($room['amenities'] ?? '[]', true) ?: [];

    // Reviews
    $revStmt = $db->prepare("
        SELECT r.rating, r.comment, r.created_at,
               CONCAT(u.first_name,' ',u.last_name) AS reviewer
        FROM reviews r JOIN users u ON u.id = r.user_id
        WHERE r.listing_id = ?
        ORDER BY r.created_at DESC LIMIT 10
    ");
    $revStmt->execute([$listingId]);
    $reviews = $revStmt->fetchAll();

} catch (PDOException $e) {
    header('Location: listings.php');
    exit;
}

$isHost  = $user && $user['id'] == $room['host_id'];
$isGuest = $user && !$isHost;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($room['title']) ?> – Rusty's Rest &amp; Lodging</title>
  <?= csrfMeta() ?>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
  /* ── Room detail layout ──────────────────────────────────── */
  .room-page { max-width:1100px; margin:2rem auto; padding:0 1.5rem; }
  .room-hero {
    width:100%; height:360px; border-radius:var(--radius);
    background:var(--beige-dark); display:flex; align-items:center;
    justify-content:center; font-size:6rem; margin-bottom:2rem;
    overflow:hidden; position:relative;
  }
  .room-hero img { width:100%; height:100%; object-fit:cover; }
  .room-layout { display:grid; grid-template-columns:1fr 380px; gap:2rem; align-items:start; }

  /* ── Room info ───────────────────────────────────────────── */
  .room-info .room-title { font-size:2rem; margin-bottom:.5rem; }
  .room-info .room-location { color:var(--text-muted); margin-bottom:1rem; }
  .room-specs {
    display:flex; gap:1.5rem; flex-wrap:wrap;
    padding:1rem 0; border-top:1px solid var(--border); border-bottom:1px solid var(--border);
    margin-bottom:1.25rem;
  }
  .room-spec { display:flex; flex-direction:column; font-size:.88rem; }
  .room-spec strong { font-size:1rem; color:var(--brown-dark); }
  .room-spec span { color:var(--text-muted); }
  .room-desc { line-height:1.8; color:var(--text); margin-bottom:1.5rem; }
  .amenity-list { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:2rem; }
  .amenity-pill {
    background:var(--beige); border:1px solid var(--border);
    border-radius:50px; padding:.35rem .85rem; font-size:.85rem; color:var(--brown);
  }

  /* ── Reviews ─────────────────────────────────────────────── */
  .reviews-section h3 { font-size:1.3rem; margin-bottom:1rem; }
  .review-card {
    background:var(--white); border:1px solid var(--border);
    border-radius:var(--radius-sm); padding:1rem; margin-bottom:.85rem;
  }
  .review-header { display:flex; justify-content:space-between; margin-bottom:.4rem; }
  .review-name { font-weight:600; font-size:.9rem; }
  .review-stars { color:var(--accent); font-size:.9rem; }
  .review-date { font-size:.78rem; color:var(--text-muted); }
  .review-text { font-size:.88rem; color:var(--text); line-height:1.6; }

  /* ── Booking sidebar ─────────────────────────────────────── */
  .booking-sidebar {
    background:var(--white); border:1.5px solid var(--border);
    border-radius:var(--radius); padding:1.5rem;
    box-shadow:var(--shadow); position:sticky; top:88px;
  }
  .sidebar-price { font-size:1.5rem; font-weight:700; color:var(--brown-dark); margin-bottom:.25rem; }
  .sidebar-price span { font-size:.9rem; font-weight:400; color:var(--text-muted); }
  .sidebar-avail { margin-bottom:1rem; }

  /* ── CALENDAR ────────────────────────────────────────────── */
  .cal-wrap { margin:1.25rem 0; user-select:none; }
  .cal-header {
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:.75rem;
  }
  .cal-header h4 { font-size:1rem; font-family:'Playfair Display',serif; }
  .cal-nav {
    background:var(--beige-dark); border:none; border-radius:6px;
    padding:.3rem .7rem; cursor:pointer; font-size:.9rem; font-weight:700;
    transition:background var(--t); color:var(--brown-dark);
  }
  .cal-nav:hover { background:var(--border); }
  .cal-grid {
    display:grid; grid-template-columns:repeat(7,1fr); gap:3px;
  }
  .cal-day-name {
    text-align:center; font-size:.7rem; font-weight:700;
    color:var(--text-muted); text-transform:uppercase; padding:.3rem 0;
  }
  .cal-day {
    text-align:center; padding:.45rem .25rem; border-radius:6px;
    font-size:.82rem; cursor:pointer; transition:background .15s,color .15s;
    border:1.5px solid transparent; position:relative;
  }
  .cal-day.empty          { cursor:default; }
  .cal-day.past           { color:#ccc; cursor:not-allowed; }
  .cal-day.today          { border-color:var(--accent); font-weight:700; }
  .cal-day.avail:hover    { background:var(--beige-dark); }

  /* Taken = pending (orange) or approved (red) booking exists */
  .cal-day.taken-approved {
    background:#fdecea; color:#a61c00;
    cursor:not-allowed; font-weight:600;
  }
  .cal-day.taken-pending  {
    background:#fff8ea; color:#9c6400;
    cursor:not-allowed; font-weight:600;
  }
  /* Guest's own selection */
  .cal-day.selected-start,
  .cal-day.selected-end   {
    background:var(--accent); color:#fff; font-weight:700;
  }
  .cal-day.selected-range {
    background:rgba(196,113,58,.18); color:var(--brown-dark);
  }

  /* Legend */
  .cal-legend { display:flex; flex-wrap:wrap; gap:.5rem; margin:.75rem 0; }
  .leg-item { display:flex; align-items:center; gap:.35rem; font-size:.75rem; color:var(--text-muted); }
  .leg-dot {
    width:12px; height:12px; border-radius:3px; flex-shrink:0;
  }
  .leg-avail   { background:var(--beige-dark); border:1px solid var(--border); }
  .leg-approved{ background:#fdecea; border:1px solid #f5c6c6; }
  .leg-pending { background:#fff8ea; border:1px solid #f5d88e; }
  .leg-sel     { background:var(--accent); }

  /* Price breakdown */
  .price-breakdown {
    background:var(--beige-light); border-radius:var(--radius-sm);
    padding:1rem; margin:.75rem 0; font-size:.88rem;
    border:1px solid var(--border);
  }
  .price-line { display:flex; justify-content:space-between; padding:.25rem 0; }
  .price-line.total {
    font-weight:700; border-top:1px solid var(--border);
    margin-top:.4rem; padding-top:.5rem;
  }

  /* Avail banner */
  .avail-banner {
    padding:.65rem .9rem; border-radius:8px; font-size:.85rem;
    font-weight:600; margin:.75rem 0; border:1.5px solid transparent; display:none;
  }
  .avail-banner.ok  { background:#eaf7ee; color:#1e7e34; border-color:#a8ddb5; }
  .avail-banner.bad { background:#fdecea; color:#a61c00; border-color:#f5c6c6; }
  .avail-banner.warn{ background:#fff8ea; color:#9c6400; border-color:#f5d88e; }

  /* Host view notice */
  .host-notice {
    background:var(--beige); border:1.5px solid var(--border);
    border-radius:var(--radius-sm); padding:1rem; font-size:.88rem;
    color:var(--brown); margin-top:1rem; line-height:1.6;
  }

  @media(max-width:768px){
    .room-layout { grid-template-columns:1fr; }
    .booking-sidebar { position:static; }
    .room-hero { height:220px; }
  }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="room-page">

  <!-- ── HERO IMAGE / PLACEHOLDER ─────────────────────────── -->
  <div class="room-hero">
    <?php if (!empty($room['cover_photo'])): ?>
      <img src="<?= htmlspecialchars($room['cover_photo']) ?>"
           alt="<?= htmlspecialchars($room['title']) ?>">
    <?php else: ?>
      🏠
    <?php endif; ?>
    <!-- Availability badge on hero -->
    <div style="position:absolute;top:1rem;left:1rem">
      <?php if ($room['available']): ?>
        <span class="avail-badge avail-yes">✔ Available</span>
      <?php else: ?>
        <span class="avail-badge avail-no">✘ Not Available</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="room-layout">

    <!-- ── LEFT: Room info ───────────────────────────────── -->
    <div class="room-info">
      <h1 class="room-title"><?= htmlspecialchars($room['title']) ?></h1>
      <div class="room-location">
        📍 <?= htmlspecialchars($room['city']) ?>
        <?php if ($room['province']): ?>, <?= htmlspecialchars($room['province']) ?><?php endif; ?>
        &nbsp;·&nbsp; 🏷 <?= htmlspecialchars($room['type']) ?>
        &nbsp;·&nbsp; ★ <?= number_format($room['rating'], 1) ?>
        (<?= $room['review_count'] ?> reviews)
      </div>

      <div class="room-specs">
        <div class="room-spec"><strong><?= $room['bedrooms'] ?></strong><span>Bedroom<?= $room['bedrooms']>1?'s':'' ?></span></div>
        <div class="room-spec"><strong><?= $room['bathrooms'] ?></strong><span>Bathroom<?= $room['bathrooms']>1?'s':'' ?></span></div>
        <div class="room-spec"><strong><?= $room['max_guests'] ?></strong><span>Max Guests</span></div>
        <div class="room-spec"><strong>₱<?= number_format($room['price_per_night']) ?></strong><span>/ night</span></div>
        <?php if ($room['cleaning_fee'] > 0): ?>
          <div class="room-spec"><strong>₱<?= number_format($room['cleaning_fee']) ?></strong><span>Cleaning Fee</span></div>
        <?php endif; ?>
      </div>

      <?php if ($room['description']): ?>
        <p class="room-desc"><?= nl2br(htmlspecialchars($room['description'])) ?></p>
      <?php endif; ?>

      <?php if ($amenities): ?>
        <h3 style="font-size:1.1rem;margin-bottom:.75rem">Amenities</h3>
        <div class="amenity-list">
          <?php foreach ($amenities as $a): ?>
            <span class="amenity-pill"><?= htmlspecialchars($a) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p style="color:var(--text-muted);font-size:.88rem">
        Hosted by <strong><?= htmlspecialchars($room['host_name']) ?></strong>
      </p>

      <!-- ── REVIEWS ─────────────────────────────────────── -->
      <?php if ($reviews): ?>
      <div class="reviews-section" style="margin-top:2rem">
        <h3>Guest Reviews</h3>
        <?php foreach ($reviews as $r): ?>
          <div class="review-card">
            <div class="review-header">
              <span class="review-name"><?= htmlspecialchars($r['reviewer']) ?></span>
              <span class="review-stars"><?= str_repeat('★', $r['rating']) ?><?= str_repeat('☆', 5-$r['rating']) ?></span>
            </div>
            <div class="review-date"><?= date('M j, Y', strtotime($r['created_at'])) ?></div>
            <?php if ($r['comment']): ?>
              <p class="review-text" style="margin-top:.4rem"><?= htmlspecialchars($r['comment']) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── RIGHT: Booking sidebar + Calendar ─────────────── -->
    <div class="booking-sidebar">

      <div class="sidebar-price">
        ₱<?= number_format($room['price_per_night']) ?>
        <span>/ night</span>
      </div>

      <div class="sidebar-avail">
        <?php if ($room['available']): ?>
          <span class="avail-badge-inline avail-yes">✔ Available for requests</span>
        <?php else: ?>
          <span class="avail-badge-inline avail-no">✘ Not accepting bookings</span>
        <?php endif; ?>
      </div>

      <!-- ── INTERACTIVE CALENDAR ──────────────────────── -->
      <div class="cal-wrap">
        <div class="cal-header">
          <button class="cal-nav" id="calPrev">◀</button>
          <h4 id="calMonthLabel"></h4>
          <button class="cal-nav" id="calNext">▶</button>
        </div>
        <div class="cal-grid" id="calGrid">
          <!-- Days rendered by JS -->
        </div>

        <!-- Legend -->
        <div class="cal-legend">
          <div class="leg-item"><div class="leg-dot leg-avail"></div> Available</div>
          <div class="leg-item"><div class="leg-dot leg-approved"></div> Booked</div>
          <div class="leg-item"><div class="leg-dot leg-pending"></div> Pending</div>
          <?php if ($isGuest): ?>
          <div class="leg-item"><div class="leg-dot leg-sel"></div> Your selection</div>
          <?php endif; ?>
        </div>
      </div>
      <!-- END CALENDAR -->

      <?php if ($isGuest && $room['available']): ?>
      <!-- ── BOOKING FORM (guests only) ────────────────── -->
      <div id="avail-banner" class="avail-banner"></div>

      <form id="bookingForm">
        <?= csrfField() ?>
        <input type="hidden" name="action"     value="create_booking">
        <input type="hidden" name="listing_id" value="<?= $room['id'] ?>">
        <input type="hidden" name="check_in"   id="f-checkin">
        <input type="hidden" name="check_out"  id="f-checkout">

        <div class="form-group" style="margin-bottom:.75rem">
          <label>Selected Dates</label>
          <div id="selected-dates-display" style="padding:.65rem 1rem;background:var(--beige);
               border-radius:var(--radius-sm);font-size:.88rem;color:var(--text-muted)">
            Click the calendar to pick check-in → check-out
          </div>
        </div>

        <div class="form-group">
          <label>Guests</label>
          <select name="guests" id="f-guests">
            <?php for ($i=1;$i<=$room['max_guests'];$i++): ?>
              <option value="<?= $i ?>"><?= $i ?> Guest<?= $i>1?'s':'' ?></option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Payment Method</label>
          <select name="payment_method">
            <option value="GCash">GCash</option>
            <option value="Card">Credit / Debit Card</option>
            <option value="Cash">Cash on Arrival</option>
          </select>
        </div>

        <div class="form-group">
          <label>Special Requests</label>
          <textarea name="special_requests" rows="2"
                    placeholder="Early check-in, extra pillows…"></textarea>
        </div>

        <!-- Live price breakdown -->
        <div class="price-breakdown" id="price-breakdown" style="display:none">
          <div class="price-line">
            <span id="pb-rate">Rate</span><span id="pb-rate-val"></span>
          </div>
          <div class="price-line">
            <span>Cleaning Fee</span>
            <span>₱<?= number_format($room['cleaning_fee']) ?></span>
          </div>
          <div class="price-line total">
            <strong>Total</strong><strong id="pb-total"></strong>
          </div>
        </div>

        <button type="submit" class="btn-primary w-full" id="bookBtn"
                style="margin-top:.75rem" disabled>
          Select Dates First
        </button>
      </form>

      <?php elseif (!$user): ?>
      <!-- Not logged in -->
      <div style="text-align:center;padding:.75rem 0">
        <p style="color:var(--text-muted);margin-bottom:.75rem;font-size:.9rem">
          Log in to request a booking for this room.
        </p>
        <a href="login.php?redirect=room.php%3Fid=<?= $room['id'] ?>" class="btn-primary">
          Log In to Book
        </a>
      </div>

      <?php elseif ($isHost): ?>
      <!-- Host viewing their own room -->
      <div class="host-notice">
        📋 <strong>You are viewing your own listing.</strong><br>
        The calendar shows all pending and confirmed bookings from guests.
        Manage requests in your
        <a href="dashboard.php?tab=requests">Dashboard → Booking Requests</a>.
      </div>

      <?php else: ?>
      <!-- Room not available -->
      <div style="text-align:center;padding:.75rem 0;color:#a61c00;font-weight:600">
        ✘ This room is not currently accepting booking requests.
      </div>
      <?php endif; ?>

    </div><!-- /.booking-sidebar -->
  </div><!-- /.room-layout -->
</div><!-- /.room-page -->

<!-- ── SUCCESS MODAL ────────────────────────────────────────── -->
<div class="modal-overlay" id="successOverlay"></div>
<div class="modal" id="successModal" style="max-width:420px;padding:2.5rem;text-align:center">
  <div style="font-size:3rem;margin-bottom:1rem">🎉</div>
  <h2 style="margin-bottom:.5rem">Request Sent!</h2>
  <p style="color:var(--text-muted);margin-bottom:1.5rem">
    Your booking request has been sent to the host.<br>
    Check <strong>My Bookings</strong> for status updates.
  </p>
  <a href="dashboard.php?tab=bookings" class="btn-primary">View My Bookings</a>
  <br><br>
  <a href="listings.php" class="link-muted">Browse more rooms</a>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// ================================================================
//  Booked ranges from PHP (JSON-encoded for JS use)
//  Each range: { check_in:"YYYY-MM-DD", check_out:"YYYY-MM-DD", booking_status:"..." }
// ================================================================
var bookedRanges = <?= json_encode($bookedRanges) ?>;
var pricePerNight = <?= (float)$room['price_per_night'] ?>;
var cleaningFee   = <?= (float)$room['cleaning_fee'] ?>;
var isGuest       = <?= $isGuest ? 'true' : 'false' ?>;
var CSRF          = $('meta[name="csrf-token"]').attr('content');

// ── Calendar state ──────────────────────────────────────────────
var today    = new Date(); today.setHours(0,0,0,0);
var calYear  = today.getFullYear();
var calMonth = today.getMonth(); // 0-based

var selStart = null;  // Date object
var selEnd   = null;  // Date object
var picking  = 'start'; // 'start' or 'end'

var MONTHS = ['January','February','March','April','May','June',
              'July','August','September','October','November','December'];
var DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

// ── Convert "YYYY-MM-DD" to local Date (midnight) ───────────────
function parseDate(s) {
  var p = s.split('-');
  return new Date(+p[0], +p[1]-1, +p[2]);
}

// ── Format Date → "YYYY-MM-DD" ──────────────────────────────────
function fmtISO(d) {
  return d.getFullYear() + '-' +
    String(d.getMonth()+1).padStart(2,'0') + '-' +
    String(d.getDate()).padStart(2,'0');
}

// ── Format Date → "Mon D, YYYY" ─────────────────────────────────
function fmtPretty(d) {
  return MONTHS[d.getMonth()].slice(0,3) + ' ' + d.getDate() + ', ' + d.getFullYear();
}

// ── Check if a date falls inside any booked range ───────────────
function dayStatus(date) {
  var ds = fmtISO(date);
  for (var i = 0; i < bookedRanges.length; i++) {
    var r = bookedRanges[i];
    if (ds >= r.check_in && ds < r.check_out) {
      return r.booking_status; // 'approved' or 'pending'
    }
  }
  return 'free';
}

// ── Is any day in range [a,b) booked? ───────────────────────────
function rangeHasConflict(a, b) {
  var cur = new Date(a);
  while (cur < b) {
    if (dayStatus(cur) !== 'free') return true;
    cur.setDate(cur.getDate() + 1);
  }
  return false;
}

// ── Render calendar ──────────────────────────────────────────────
function renderCalendar() {
  $('#calMonthLabel').text(MONTHS[calMonth] + ' ' + calYear);
  var $grid = $('#calGrid').empty();

  // Day name headers
  $.each(DAYS, function (_, d) {
    $grid.append('<div class="cal-day-name">' + d + '</div>');
  });

  var first = new Date(calYear, calMonth, 1);
  var last  = new Date(calYear, calMonth + 1, 0);

  // Empty cells before first day
  for (var e = 0; e < first.getDay(); e++) {
    $grid.append('<div class="cal-day empty"></div>');
  }

  // Day cells
  for (var d = 1; d <= last.getDate(); d++) {
    var date   = new Date(calYear, calMonth, d);
    var iso    = fmtISO(date);
    var status = dayStatus(date);
    var isPast = date < today;

    var cls = 'cal-day';

    if (isPast) {
      cls += ' past';
    } else if (status === 'approved') {
      cls += ' taken-approved';
    } else if (status === 'pending') {
      cls += ' taken-pending';
    } else {
      cls += ' avail';
    }

    // Today highlight
    if (fmtISO(date) === fmtISO(today)) cls += ' today';

    // Guest selection highlight
    if (isGuest && selStart && selEnd) {
      if (iso === fmtISO(selStart) || iso === fmtISO(selEnd)) {
        cls += ' selected-start';
      } else if (date > selStart && date < selEnd) {
        cls += ' selected-range';
      }
    } else if (isGuest && selStart && !selEnd) {
      if (iso === fmtISO(selStart)) cls += ' selected-start';
    }

    // Tooltip
    var title = '';
    if (isPast)               title = 'Past date';
    else if (status==='approved') title = 'Booked';
    else if (status==='pending')  title = 'Pending request';
    else                          title = iso;

    $grid.append(
      '<div class="' + cls + '" data-date="' + iso + '" title="' + title + '">' +
        d +
      '</div>'
    );
  }
}

// ── Day click (guest only) ───────────────────────────────────────
$(document).on('click', '.cal-day.avail', function () {
  if (!isGuest) return;
  var iso  = $(this).data('date');
  var date = parseDate(iso);

  if (picking === 'start' || (picking === 'end' && selStart && date <= selStart)) {
    // Start fresh
    selStart = date;
    selEnd   = null;
    picking  = 'end';
  } else {
    // End date
    selEnd  = date;
    picking = 'start';

    // Check no conflict in range
    if (rangeHasConflict(selStart, selEnd)) {
      showBanner('warn', '⚠ Your selected range includes taken dates. Please pick different dates.');
      selStart = null; selEnd = null; picking = 'start';
      updateForm();
      renderCalendar();
      return;
    }
  }

  updateForm();
  renderCalendar();
});

// ── Update hidden inputs + price breakdown ───────────────────────
function updateForm() {
  if (!selStart || !selEnd) {
    $('#f-checkin').val('');
    $('#f-checkout').val('');
    $('#selected-dates-display').text('Click the calendar to pick check-in → check-out').css('color','var(--text-muted)');
    $('#price-breakdown').hide();
    $('#bookBtn').prop('disabled', true).text('Select Dates First');
    hideBanner();
    return;
  }

  var ci = fmtISO(selStart);
  var co = fmtISO(selEnd);
  $('#f-checkin').val(ci);
  $('#f-checkout').val(co);

  var nights = Math.round((selEnd - selStart) / 86400000);
  var total  = (pricePerNight * nights) + cleaningFee;

  $('#selected-dates-display')
    .text(fmtPretty(selStart) + '  →  ' + fmtPretty(selEnd) + '  (' + nights + ' night' + (nights>1?'s':'') + ')')
    .css('color','var(--brown-dark)');

  $('#pb-rate').text('₱' + pricePerNight.toLocaleString() + ' × ' + nights + ' night' + (nights>1?'s':''));
  $('#pb-rate-val').text('₱' + (pricePerNight*nights).toLocaleString());
  $('#pb-total').text('₱' + total.toLocaleString());
  $('#price-breakdown').slideDown(180);
  $('#bookBtn').prop('disabled', false).text('Request Booking');

  showBanner('ok', '✔ These dates are available! Fill in the details and request your booking.');
}

// ── Banner helpers ───────────────────────────────────────────────
function showBanner(type, msg) {
  $('#avail-banner').removeClass('ok bad warn').addClass(type).text(msg).show();
}
function hideBanner() {
  $('#avail-banner').hide();
}

// ── Month navigation ─────────────────────────────────────────────
$('#calPrev').on('click', function () {
  calMonth--;
  if (calMonth < 0) { calMonth = 11; calYear--; }
  renderCalendar();
});
$('#calNext').on('click', function () {
  calMonth++;
  if (calMonth > 11) { calMonth = 0; calYear++; }
  renderCalendar();
});

// ── Submit booking form ──────────────────────────────────────────
$('#bookingForm').on('submit', function (e) {
  e.preventDefault();
  var ci = $('#f-checkin').val();
  var co = $('#f-checkout').val();

  if (!ci || !co) {
    showBanner('warn', '⚠ Please select check-in and check-out dates on the calendar.');
    return;
  }

  var $btn = $('#bookBtn').prop('disabled', true).text('Sending request…');

  $.ajax({
    url:      'api.php',
    method:   'POST',
    data:     $(this).serialize(),
    dataType: 'json',
    headers:  { 'X-CSRF-Token': CSRF },
    success: function (res) {
      if (res.success) {
        // Show success modal
        $('#successOverlay, #successModal').addClass('open');
        // Refresh booked ranges to show the new pending block on calendar
        bookedRanges.push({
          check_in:       ci,
          check_out:      co,
          booking_status: 'pending'
        });
        selStart = null; selEnd = null; picking = 'start';
        renderCalendar();
        updateForm();
      } else {
        showBanner('bad', '✘ ' + res.message);
        $btn.prop('disabled', false).text('Request Booking');
      }
    },
    error: function () {
      showBanner('bad', '✘ Server error. Please try again.');
      $btn.prop('disabled', false).text('Request Booking');
    }
  });
});

// ── Close success modal ──────────────────────────────────────────
$('#successOverlay').on('click', function () {
  $('#successOverlay, #successModal').removeClass('open');
});

// ── Init ─────────────────────────────────────────────────────────
$(function () {
  renderCalendar();
});
</script>
</body>
</html>