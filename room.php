<?php
// ============================================================
//  room.php — Rusty's Rest and Lodging
//  Room detail + interactive availability calendar
// ============================================================
require_once 'db.php';
startSession();

$user      = currentUser();
$listingId = cleanInt($_GET['id'] ?? 0);

if (!$listingId) { header('Location: listings.php'); exit; }

try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS host_name,
               u.email AS host_email
        FROM listings l
        JOIN users u ON u.id = l.host_id
        WHERE l.id = ? LIMIT 1
    ");
    $stmt->execute([$listingId]);
    $room = $stmt->fetch();
    if (!$room) { header('Location: listings.php'); exit; }

    // All booked ranges (pending + approved block the calendar)
    $bkStmt = $db->prepare("
        SELECT check_in, check_out, booking_status
        FROM bookings
        WHERE listing_id = ? AND booking_status IN ('pending','approved')
        ORDER BY check_in
    ");
    $bkStmt->execute([$listingId]);
    $bookedRanges = $bkStmt->fetchAll();

    $amenities = json_decode($room['amenities'] ?? '[]', true) ?: [];

    $revStmt = $db->prepare("
        SELECT r.rating, r.comment, r.created_at,
               CONCAT(u.first_name,' ',u.last_name) AS reviewer
        FROM reviews r JOIN users u ON u.id = r.user_id
        WHERE r.listing_id = ? ORDER BY r.created_at DESC LIMIT 10
    ");
    $revStmt->execute([$listingId]);
    $reviews = $revStmt->fetchAll();

} catch (PDOException $e) {
    header('Location: listings.php'); exit;
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
  .room-page  { max-width:1100px; margin:2rem auto; padding:0 1.5rem 4rem; }
  .room-hero  { width:100%; height:340px; border-radius:var(--radius); background:var(--beige-dark);
                display:flex; align-items:center; justify-content:center; font-size:6rem;
                margin-bottom:2rem; overflow:hidden; position:relative; }
  .room-hero img { width:100%; height:100%; object-fit:cover; }
  .room-layout { display:grid; grid-template-columns:1fr 360px; gap:2rem; align-items:start; }
  .room-title  { font-size:2rem; margin-bottom:.5rem; }
  .room-loc    { color:var(--text-muted); margin-bottom:1rem; }
  .room-specs  { display:flex; gap:1.5rem; flex-wrap:wrap; padding:1rem 0;
                 border-top:1px solid var(--border); border-bottom:1px solid var(--border);
                 margin-bottom:1.25rem; }
  .room-spec strong { font-size:1rem; color:var(--brown-dark); display:block; }
  .room-spec span   { font-size:.8rem; color:var(--text-muted); }
  .room-desc  { line-height:1.8; color:var(--text); margin-bottom:1.5rem; }
  .amenity-list { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:2rem; }
  .amenity-pill { background:var(--beige); border:1px solid var(--border); border-radius:50px;
                  padding:.35rem .85rem; font-size:.85rem; color:var(--brown); }
  .reviews-section h3 { font-size:1.3rem; margin-bottom:1rem; }
  .review-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-sm);
                 padding:1rem; margin-bottom:.85rem; }
  .review-header { display:flex; justify-content:space-between; margin-bottom:.3rem; }
  .review-name { font-weight:600; font-size:.9rem; }
  .review-stars { color:var(--accent); }
  .review-date { font-size:.78rem; color:var(--text-muted); }
  .review-text { font-size:.88rem; color:var(--text); line-height:1.6; margin-top:.35rem; }

  /* Sidebar */
  .booking-sidebar { background:var(--white); border:1.5px solid var(--border);
                     border-radius:var(--radius); padding:1.5rem;
                     box-shadow:var(--shadow); position:sticky; top:88px; }
  .sidebar-price   { font-size:1.5rem; font-weight:700; color:var(--brown-dark); margin-bottom:.25rem; }
  .sidebar-price span { font-size:.9rem; font-weight:400; color:var(--text-muted); }

  /* ── CALENDAR ─────────────────────────────────────────── */
  .cal-wrap    { margin:1.1rem 0; user-select:none; }
  .cal-header  { display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem; }
  .cal-header h4 { font-size:.95rem; font-family:'Playfair Display',serif; }
  .cal-nav     { background:var(--beige-dark); border:none; border-radius:6px;
                 padding:.28rem .65rem; cursor:pointer; font-size:.88rem; font-weight:700;
                 color:var(--brown-dark); transition:background var(--t); }
  .cal-nav:hover { background:var(--border); }
  .cal-grid    { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }
  .cal-day-name { text-align:center; font-size:.68rem; font-weight:700; color:var(--text-muted);
                  text-transform:uppercase; padding:.25rem 0; }
  .cal-day     { text-align:center; padding:.4rem .2rem; border-radius:5px; font-size:.8rem;
                 cursor:pointer; transition:background .12s,color .12s;
                 border:1.5px solid transparent; position:relative; line-height:1.3; }
  .cal-day.empty   { cursor:default; }
  .cal-day.past    { color:#ccc !important; cursor:not-allowed !important; background:none !important; }
  .cal-day.today   { border-color:var(--accent); font-weight:700; }

  /* Available */
  .cal-day.avail:hover { background:var(--beige-dark); }

  /* Booked / Taken */
  .cal-day.taken-approved {
    background:#fdecea !important; color:#a61c00 !important;
    cursor:not-allowed !important; font-weight:600;
  }
  .cal-day.taken-pending {
    background:#fff8ea !important; color:#9c6400 !important;
    cursor:not-allowed !important; font-weight:600;
  }
  .cal-day.taken-approved::after { content:'✕'; font-size:.55rem; position:absolute;
    top:1px; right:2px; opacity:.7; }

  /* Guest selection */
  .cal-day.sel-start, .cal-day.sel-end {
    background:var(--accent) !important; color:#fff !important; font-weight:700; }
  .cal-day.sel-range {
    background:rgba(196,113,58,.2) !important; color:var(--brown-dark) !important; }

  /* Legend */
  .cal-legend { display:flex; flex-wrap:wrap; gap:.4rem; margin:.65rem 0 0; }
  .leg-item   { display:flex; align-items:center; gap:.3rem; font-size:.72rem; color:var(--text-muted); }
  .leg-dot    { width:11px; height:11px; border-radius:3px; flex-shrink:0; }
  .leg-free   { background:var(--beige-dark); border:1px solid var(--border); }
  .leg-appr   { background:#fdecea; border:1px solid #f5c6c6; }
  .leg-pend   { background:#fff8ea; border:1px solid #f5d88e; }
  .leg-sel    { background:var(--accent); }

  /* Date hint bar */
  .date-hint  { font-size:.8rem; font-weight:500; padding:.45rem .75rem; border-radius:6px;
                margin:.6rem 0; border:1.5px solid transparent; display:none; }
  .date-hint.ok   { background:#eaf7ee; color:#1e7e34; border-color:#a8ddb5; }
  .date-hint.bad  { background:#fdecea; color:#a61c00; border-color:#f5c6c6; }
  .date-hint.warn { background:#fff8ea; color:#9c6400; border-color:#f5d88e; }

  /* Price breakdown */
  .price-breakdown { background:var(--beige-light); border-radius:var(--radius-sm);
                     padding:1rem; margin:.75rem 0; font-size:.88rem; border:1px solid var(--border); }
  .price-line      { display:flex; justify-content:space-between; padding:.22rem 0; }
  .price-line.total { font-weight:700; border-top:1px solid var(--border); margin-top:.4rem; padding-top:.5rem; }

  /* Host notice */
  .host-notice { background:var(--beige); border:1.5px solid var(--border);
                 border-radius:var(--radius-sm); padding:1rem; font-size:.88rem;
                 color:var(--brown); line-height:1.6; margin-top:.75rem; }

  @media(max-width:768px){
    .room-layout { grid-template-columns:1fr; }
    .booking-sidebar { position:static; }
    .room-hero { height:200px; font-size:4rem; }
  }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="room-page">

  <!-- Hero -->
  <div class="room-hero">
    <?php if (!empty($room['cover_photo'])): ?>
      <img src="<?= htmlspecialchars($room['cover_photo']) ?>" alt="<?= htmlspecialchars($room['title']) ?>">
    <?php else: ?>🏠<?php endif; ?>
    <div style="position:absolute;top:1rem;left:1rem">
      <?php if ($room['available']): ?>
        <span class="avail-badge avail-yes">✔ Available</span>
      <?php else: ?>
        <span class="avail-badge avail-no">✘ Not Available</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="room-layout">

    <!-- Left: Info -->
    <div class="room-info">
      <h1 class="room-title"><?= htmlspecialchars($room['title']) ?></h1>
      <div class="room-loc">
        📍 <?= htmlspecialchars($room['city']) ?><?= $room['province'] ? ', '.htmlspecialchars($room['province']) : '' ?>
        &nbsp;·&nbsp; 🏷 <?= htmlspecialchars($room['type']) ?>
        &nbsp;·&nbsp; ★ <?= number_format($room['rating'],1) ?> (<?= $room['review_count'] ?> reviews)
      </div>
      <div class="room-specs">
        <div class="room-spec"><strong><?= $room['bedrooms'] ?></strong><span>Bedroom<?= $room['bedrooms']>1?'s':'' ?></span></div>
        <div class="room-spec"><strong><?= $room['bathrooms'] ?></strong><span>Bathroom<?= $room['bathrooms']>1?'s':'' ?></span></div>
        <div class="room-spec"><strong><?= $room['max_guests'] ?></strong><span>Max Guests</span></div>
        <div class="room-spec"><strong>₱<?= number_format($room['price_per_night']) ?></strong><span>/night</span></div>
        <?php if ($room['cleaning_fee'] > 0): ?>
        <div class="room-spec"><strong>₱<?= number_format($room['cleaning_fee']) ?></strong><span>Cleaning</span></div>
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

      <!-- Reviews -->
      <?php if ($reviews): ?>
      <div class="reviews-section" style="margin-top:2rem">
        <h3>Guest Reviews</h3>
        <?php foreach ($reviews as $r): ?>
          <div class="review-card">
            <div class="review-header">
              <span class="review-name"><?= htmlspecialchars($r['reviewer']) ?></span>
              <span class="review-stars"><?= str_repeat('★',$r['rating']).str_repeat('☆',5-$r['rating']) ?></span>
            </div>
            <div class="review-date"><?= date('M j, Y',strtotime($r['created_at'])) ?></div>
            <?php if ($r['comment']): ?>
              <p class="review-text"><?= htmlspecialchars($r['comment']) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right: Sidebar + Calendar -->
    <div class="booking-sidebar">
      <div class="sidebar-price">
        ₱<?= number_format($room['price_per_night']) ?><span>/night</span>
      </div>
      <div style="margin-bottom:.75rem">
        <?php if ($room['available']): ?>
          <span class="avail-badge-inline avail-yes">✔ Accepting requests</span>
        <?php else: ?>
          <span class="avail-badge-inline avail-no">✘ Not accepting bookings</span>
        <?php endif; ?>
      </div>

      <!-- Instruction -->
      <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem">
        <?php if ($isGuest && $room['available']): ?>
          Click a date to set <strong>check-in</strong>, then click another for <strong>check-out</strong>.
        <?php else: ?>
          Calendar shows all booked dates for this room.
        <?php endif; ?>
      </p>

      <!-- ── CALENDAR ────────────────────────────────────────── -->
      <div class="cal-wrap">
        <div class="cal-header">
          <button class="cal-nav" id="calPrev">◀</button>
          <h4 id="calMonthLabel"></h4>
          <button class="cal-nav" id="calNext">▶</button>
        </div>
        <div class="cal-grid" id="calGrid"></div>
        <div class="cal-legend">
          <div class="leg-item"><div class="leg-dot leg-free"></div> Available</div>
          <div class="leg-item"><div class="leg-dot leg-appr"></div> Booked</div>
          <div class="leg-item"><div class="leg-dot leg-pend"></div> Pending</div>
          <?php if ($isGuest): ?>
          <div class="leg-item"><div class="leg-dot leg-sel"></div> Your pick</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Date hint -->
      <div class="date-hint" id="dateHint"></div>

      <?php if ($isGuest && $room['available']): ?>
      <!-- ── BOOKING FORM ───────────────────────────────────── -->
      <form id="bookingForm">
        <?= csrfField() ?>
        <input type="hidden" name="action"     value="create_booking">
        <input type="hidden" name="listing_id" value="<?= $room['id'] ?>">
        <input type="hidden" name="check_in"   id="f-checkin">
        <input type="hidden" name="check_out"  id="f-checkout">

        <!-- Selected dates display -->
        <div id="selected-dates-display" style="background:var(--beige);border-radius:var(--radius-sm);
             padding:.6rem .85rem;font-size:.85rem;color:var(--text-muted);margin-bottom:.75rem">
          Pick dates on the calendar above
        </div>

        <div class="form-group" style="margin-bottom:.75rem">
          <label>Guests</label>
          <select name="guests" id="f-guests">
            <?php for ($i=1;$i<=$room['max_guests'];$i++): ?>
              <option value="<?= $i ?>"><?= $i ?> Guest<?= $i>1?'s':'' ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:.75rem">
          <label>Payment Method</label>
          <select name="payment_method">
            <option value="GCash">GCash</option>
            <option value="Card">Credit / Debit Card</option>
            <option value="Cash">Cash on Arrival</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:.75rem">
          <label>Special Requests</label>
          <textarea name="special_requests" rows="2" placeholder="Early check-in, extra pillows…"></textarea>
        </div>

        <!-- Price breakdown (live) -->
        <div class="price-breakdown" id="price-breakdown" style="display:none">
          <div class="price-line"><span id="pb-rate">Rate</span><span id="pb-rate-val"></span></div>
          <div class="price-line"><span>Cleaning Fee</span><span>₱<?= number_format($room['cleaning_fee']) ?></span></div>
          <div class="price-line total"><strong>Total</strong><strong id="pb-total"></strong></div>
        </div>

        <button type="submit" class="btn-primary w-full" id="bookBtn"
                style="margin-top:.75rem" disabled>
          Pick Dates on Calendar
        </button>
      </form>

      <?php elseif (!$user): ?>
      <div style="text-align:center;padding:.75rem 0">
        <p style="color:var(--text-muted);margin-bottom:.75rem;font-size:.9rem">
          Log in to send a booking request.
        </p>
        <a href="login.php?redirect=room.php%3Fid=<?= $room['id'] ?>" class="btn-primary">
          Log In to Book
        </a>
      </div>

      <?php elseif ($isHost): ?>
      <div class="host-notice">
        📋 <strong>You are viewing your own listing.</strong><br>
        Red = confirmed booking &nbsp;·&nbsp; Yellow = pending request.<br>
        <a href="dashboard.php?tab=listings" style="font-weight:600">Manage in Dashboard →</a>
      </div>

      <?php else: ?>
      <div style="text-align:center;padding:.75rem;color:#a61c00;font-weight:600;font-size:.9rem">
        ✘ This room is not currently accepting booking requests.
      </div>
      <?php endif; ?>

    </div><!-- /.booking-sidebar -->
  </div>
</div>

<!-- ── SUCCESS MODAL ─────────────────────────────────────────── -->
<div class="modal-overlay" id="successOverlay"></div>
<div class="modal open" id="successModal"
     style="display:none;max-width:440px;text-align:center;padding:2.5rem">
  <div style="font-size:3rem;margin-bottom:1rem">🎉</div>
  <h2 style="margin-bottom:.5rem">Request Sent!</h2>
  <p style="color:var(--text-muted);margin-bottom:.5rem">
    Your booking request is now <strong>pending approval</strong> from the host.<br>
    You can see it in <strong>My Bookings</strong>.
  </p>
  <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.5rem">
    The booked dates are now <span style="color:#a61c00;font-weight:600">marked on the calendar</span>
    so other guests can see they are taken.
  </p>
  <a href="dashboard.php?tab=bookings" class="btn-primary">View My Bookings</a><br><br>
  <a href="listings.php" class="link-muted">Browse more rooms</a>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// ── Data from PHP ───────────────────────────────────────────────
var bookedRanges  = <?= json_encode(array_values($bookedRanges)) ?>;
var pricePerNight = <?= (float)$room['price_per_night'] ?>;
var cleaningFee   = <?= (float)$room['cleaning_fee'] ?>;
var isGuest       = <?= $isGuest ? 'true' : 'false' ?>;
var CSRF          = $('meta[name="csrf-token"]').attr('content');

// ── Calendar state ──────────────────────────────────────────────
var today    = new Date(); today.setHours(0,0,0,0);
var calYear  = today.getFullYear();
var calMonth = today.getMonth();
var selStart = null;
var selEnd   = null;
var picking  = 'start'; // 'start' or 'end'

var MONTHS = ['January','February','March','April','May','June',
              'July','August','September','October','November','December'];
var DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

function parseDate(s) {
  var p = s.split('-');
  return new Date(+p[0], +p[1]-1, +p[2]);
}
function fmtISO(d) {
  return d.getFullYear() + '-' +
    String(d.getMonth()+1).padStart(2,'0') + '-' +
    String(d.getDate()).padStart(2,'0');
}
function fmtPretty(d) {
  return MONTHS[d.getMonth()].slice(0,3)+' '+d.getDate()+', '+d.getFullYear();
}

// ── Status of a calendar day ────────────────────────────────────
// Returns 'approved', 'pending', or 'free'
function dayStatus(date) {
  var ds = fmtISO(date);
  for (var i = 0; i < bookedRanges.length; i++) {
    var r = bookedRanges[i];
    if (ds >= r.check_in && ds < r.check_out) {
      return r.booking_status;
    }
  }
  return 'free';
}

// ── Does range [a, b) contain any booked day? ───────────────────
function rangeHasConflict(a, b) {
  var cur = new Date(a);
  while (cur < b) {
    if (dayStatus(cur) !== 'free') return true;
    cur.setDate(cur.getDate() + 1);
  }
  return false;
}

// ── Is the day immediately after checkout free? (extend hint) ───
function nextDayFree(checkoutISO) {
  var next = parseDate(checkoutISO);
  next.setDate(next.getDate() + 1);
  return dayStatus(next) === 'free';
}

// ── Render calendar ─────────────────────────────────────────────
function renderCalendar() {
  $('#calMonthLabel').text(MONTHS[calMonth] + ' ' + calYear);
  var $grid = $('#calGrid').empty();

  DAYS.forEach(function(d) { $grid.append('<div class="cal-day-name">'+d+'</div>'); });

  var first  = new Date(calYear, calMonth, 1);
  var last   = new Date(calYear, calMonth + 1, 0);

  for (var e = 0; e < first.getDay(); e++) {
    $grid.append('<div class="cal-day empty"></div>');
  }

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

    if (fmtISO(date) === fmtISO(today)) cls += ' today';

    // Guest selection highlight
    if (isGuest) {
      if (selStart && selEnd) {
        if (iso === fmtISO(selStart) || iso === fmtISO(selEnd)) {
          cls += ' sel-start';
        } else if (date > selStart && date < selEnd) {
          cls += ' sel-range';
        }
      } else if (selStart && !selEnd && iso === fmtISO(selStart)) {
        cls += ' sel-start';
      }
    }

    // Tooltip
    var title = isPast ? 'Past date'
              : status === 'approved' ? '✕ Booked — not available'
              : status === 'pending'  ? '⏳ Pending booking'
              : iso;

    $grid.append(
      '<div class="'+cls+'" data-date="'+iso+'" title="'+title+'">'+d+'</div>'
    );
  }
}

// ── Date click (guest only) ─────────────────────────────────────
$(document).on('click', '.cal-day.avail', function () {
  if (!isGuest) return;
  var iso  = $(this).data('date');
  var date = parseDate(iso);

  if (picking === 'start' || (selStart && date <= selStart)) {
    // Set check-in
    selStart = date;
    selEnd   = null;
    picking  = 'end';
    showHint('warn', '📅 Check-in: '+fmtPretty(selStart)+'. Now pick a check-out date.');
  } else {
    // Set check-out
    selEnd  = date;
    picking = 'start';

    if (rangeHasConflict(selStart, selEnd)) {
      showHint('bad', '✘ Your selected range includes taken dates. Please choose different dates.');
      selStart = null; selEnd = null; picking = 'start';
      updateForm();
      renderCalendar();
      return;
    }
  }

  updateForm();
  renderCalendar();
});

// ── Update form + price breakdown ───────────────────────────────
function updateForm() {
  if (!selStart || !selEnd) {
    $('#f-checkin, #f-checkout').val('');
    $('#selected-dates-display')
      .text('Pick dates on the calendar above')
      .css('color','var(--text-muted)');
    $('#price-breakdown').hide();
    $('#bookBtn').prop('disabled', true).text('Pick Dates on Calendar');
    return;
  }

  var ci = fmtISO(selStart);
  var co = fmtISO(selEnd);
  $('#f-checkin').val(ci);
  $('#f-checkout').val(co);

  var nights = Math.round((selEnd - selStart) / 86400000);
  var total  = (pricePerNight * nights) + cleaningFee;

  $('#selected-dates-display')
    .html('<strong>' + fmtPretty(selStart) + ' → ' + fmtPretty(selEnd) + '</strong>' +
          '<br><small style="color:var(--text-muted)">' + nights + ' night'+(nights>1?'s':'')+
          ' · ₱'+total.toLocaleString()+' total</small>')
    .css('color','var(--brown-dark)');

  $('#pb-rate').text('₱'+pricePerNight.toLocaleString()+' × '+nights+' night'+(nights>1?'s':''));
  $('#pb-rate-val').text('₱'+(pricePerNight*nights).toLocaleString());
  $('#pb-total').text('₱'+total.toLocaleString());
  $('#price-breakdown').slideDown(160);

  // Check if checkout+1 is free to show extend hint
  var nextFree = nextDayFree(co);
  showHint('ok',
    '✔ Dates available! ' + (nextFree
      ? 'Tip: the day after your checkout is also free — you can extend in My Bookings after booking.'
      : '')
  );
  $('#bookBtn').prop('disabled', false).text('Send Booking Request');
}

// ── Hint bar ────────────────────────────────────────────────────
function showHint(type, msg) {
  $('#dateHint').removeClass('ok bad warn').addClass(type).html(msg).show();
}

// ── Calendar nav ────────────────────────────────────────────────
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

// ── Submit booking ───────────────────────────────────────────────
$('#bookingForm').on('submit', function (e) {
  e.preventDefault();
  var ci = $('#f-checkin').val();
  var co = $('#f-checkout').val();

  if (!ci || !co) {
    showHint('bad', '⚠ Please select check-in and check-out on the calendar first.');
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
        // ── KEY: add the new booking to bookedRanges immediately
        // so the calendar blocks those days for anyone still on the page
        bookedRanges.push({
          check_in:       ci,
          check_out:      co,
          booking_status: 'pending'
        });

        // Reset selection
        selStart = null; selEnd = null; picking = 'start';
        renderCalendar();
        updateForm();
        showHint('ok', '✔ Request sent! Dates are now shown as pending on the calendar.');

        // Show success modal
        $('#successOverlay').addClass('open');
        $('#successModal').show();
      } else {
        showHint('bad', '✘ ' + res.message);
        $btn.prop('disabled', false).text('Send Booking Request');
      }
    },
    error: function () {
      showHint('bad', '✘ Server error. Please try again.');
      $btn.prop('disabled', false).text('Send Booking Request');
    }
  });
});

// ── Close success modal ─────────────────────────────────────────
$('#successOverlay').on('click', function () {
  $(this).removeClass('open');
  $('#successModal').hide();
});

// ── Init ────────────────────────────────────────────────────────
$(function () { renderCalendar(); });
</script>
</body>
</html>
