<?php
require_once 'db.php';
startSession();
$user     = currentUser();
$jsonData = json_decode(file_get_contents(__DIR__ . '/data.json'), true);
$types    = $jsonData['property_types'] ?? [];
$today    = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rusty's Rest &amp; Lodging – Browse Rooms</title>
  <?= csrfMeta() ?>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Date-gate overlay shown before user picks dates */
    .date-gate {
      background: var(--white);
      border: 2px solid var(--accent);
      border-radius: var(--radius);
      padding: 2.5rem 2rem;
      max-width: 520px;
      margin: 4rem auto;
      text-align: center;
      box-shadow: var(--shadow-lg);
    }
    .date-gate h2   { font-size: 1.6rem; margin-bottom: .5rem; }
    .date-gate p    { color: var(--text-muted); margin-bottom: 1.5rem; }
    .date-gate-row  { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .date-gate-err  {
      color: #a61c00; background: #fdecea;
      border: 1px solid #f5c6c6; border-radius: 6px;
      padding: .5rem .85rem; font-size: .85rem;
      margin-bottom: .75rem; display: none;
    }
    /* Hidden until dates are confirmed */
    #listingsSection { display: none; }
    .date-confirmed-bar {
      background: var(--beige-dark); border-radius: var(--radius-sm);
      padding: .65rem 1.25rem; margin-bottom: 1.25rem;
      display: flex; justify-content: space-between; align-items: center;
      font-size: .88rem; color: var(--brown-dark);
    }
    .date-confirmed-bar button {
      background: none; border: 1px solid var(--border);
      border-radius: 6px; padding: .25rem .7rem;
      cursor: pointer; font-size: .8rem; color: var(--brown);
      transition: background var(--t);
    }
    .date-confirmed-bar button:hover { background: var(--border); }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container" style="padding-top:2rem;padding-bottom:4rem">

  <!-- ══ STEP 1: DATE GATE — user must pick dates first ════════ -->
  <div id="dateGate" class="date-gate">
    <div style="font-size:2.5rem;margin-bottom:.75rem">📅</div>
    <h2>When are you staying?</h2>
    <p>Pick your check-in and check-out dates to see which rooms are available for your trip.</p>

    <div class="date-gate-row">
      <div class="form-group" style="text-align:left">
        <label>Check-in Date *</label>
        <input type="date" id="gate-checkin" min="<?= $today ?>"
               value="" style="font-size:.95rem"/>
      </div>
      <div class="form-group" style="text-align:left">
        <label>Check-out Date *</label>
        <input type="date" id="gate-checkout" min="<?= $today ?>"
               value="" style="font-size:.95rem"/>
      </div>
    </div>

    <div class="date-gate-err" id="gate-err"></div>

    <button class="btn-primary w-full" id="gateContinue" style="font-size:1rem;padding:.85rem 1rem">
      Search Available Rooms →
    </button>

    <p style="margin-top:1rem;font-size:.82rem;color:var(--text-muted)">
      Dates can be changed after searching.
    </p>
  </div>

  <!-- ══ STEP 2: LISTINGS (shown after date confirmed) ═════════ -->
  <div id="listingsSection">

    <!-- Confirmed date bar with option to change -->
    <div class="date-confirmed-bar">
      <span id="confirmedDateLabel">📅 Showing availability for your dates</span>
      <button id="changeDates">Change Dates</button>
    </div>

    <div style="display:flex;gap:2rem;align-items:flex-start;flex-wrap:wrap">

      <!-- ── FILTERS ──────────────────────────────────────────── -->
      <aside class="filters-panel" style="width:240px;flex-shrink:0">
        <h3>Filters</h3>

        <div class="filter-group">
          <label>Search</label>
          <input type="text" id="f-location" placeholder="City or room name…"/>
        </div>
        <div class="filter-group">
          <label>Price / Night</label>
          <div class="price-range">
            <input type="number" id="f-min" placeholder="Min ₱" style="text-align:center">
            <span>–</span>
            <input type="number" id="f-max" placeholder="Max ₱" style="text-align:center">
          </div>
        </div>
        <div class="filter-group">
          <label>Room Type</label>
          <select id="f-type">
            <option value="">Any</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>Min. Guests</label>
          <input type="number" id="f-guests" min="1" placeholder="Any"/>
        </div>
        <div class="filter-group">
          <label>Availability</label>
          <div class="checkbox-group">
            <label><input type="radio" name="f-avail" value="" checked> All</label>
            <label>
              <input type="radio" name="f-avail" value="1">
              <span class="avail-badge-inline avail-yes">✔ Available</span>
            </label>
            <label>
              <input type="radio" name="f-avail" value="0">
              <span class="avail-badge-inline avail-no">✘ Not Available</span>
            </label>
          </div>
        </div>
        <div class="filter-group">
          <label>Sort By</label>
          <select id="f-sort">
            <option value="price_asc">Price: Low → High</option>
            <option value="price_desc">Price: High → Low</option>
            <option value="rating">Top Rated</option>
            <option value="newest">Newest</option>
          </select>
        </div>
        <button class="btn-primary w-full" id="applyFilters">Apply</button>
        <button class="btn-ghost   w-full" id="resetFilters" style="margin-top:.5rem">Reset</button>
      </aside>

      <!-- ── MAIN GRID ─────────────────────────────────────────── -->
      <div style="flex:1;min-width:0">
        <div class="listings-header">
          <h2>Available Rooms <span class="count-badge" id="listingCount">0</span></h2>
        </div>
        <div class="listings-grid" id="listingsGrid">
          <div class="loading-spinner">Loading rooms…</div>
        </div>
      </div>
    </div>
  </div><!-- /#listingsSection -->

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
var loggedIn = <?= $user ? 'true' : 'false' ?>;
var TODAY    = '<?= $today ?>';
var gCheckIn = '', gCheckOut = '';

// ── CSRF on every AJAX request ──────────────────────────────────
var CSRF = $('meta[name="csrf-token"]').attr('content') || '';
$.ajaxSetup({ headers: { 'X-CSRF-Token': CSRF } });

// ── Toast ───────────────────────────────────────────────────────
function toast(msg, type) {
  type = type || 'success';
  var colors = { success:'#27ae60', error:'#c0392b', info:'#2980b9', warn:'#e67e22' };
  var $t = $('<div class="toast-msg">').text(msg)
             .css('background', colors[type]).appendTo('body');
  setTimeout(function(){ $t.addClass('toast-hide'); }, 2800);
  setTimeout(function(){ $t.remove(); }, 3400);
}

// ── Date gate: enforce min dates ─────────────────────────────────
$('#gate-checkin').attr('min', TODAY);
$('#gate-checkout').attr('min', TODAY);

// When check-in changes, checkout min = checkin + 1
$('#gate-checkin').on('change', function () {
  var ci = $(this).val();
  if (ci) {
    var next = new Date(ci + 'T00:00:00');
    next.setDate(next.getDate() + 1);
    var nextStr = next.toISOString().split('T')[0];
    $('#gate-checkout').attr('min', nextStr);
    if ($('#gate-checkout').val() && $('#gate-checkout').val() <= ci) {
      $('#gate-checkout').val('');
    }
  }
  $('#gate-err').hide();
});

// Continue button — validate dates then show listings
$('#gateContinue').on('click', function () {
  var ci = $('#gate-checkin').val();
  var co = $('#gate-checkout').val();

  if (!ci) {
    $('#gate-err').text('Please select a check-in date.').show(); return;
  }
  if (!co) {
    $('#gate-err').text('Please select a check-out date.').show(); return;
  }
  if (ci < TODAY) {
    $('#gate-err').text('Check-in date cannot be in the past.').show(); return;
  }
  if (co <= ci) {
    $('#gate-err').text('Check-out must be after check-in.').show(); return;
  }

  gCheckIn  = ci;
  gCheckOut = co;

  // Build a pretty label
  var nights = Math.round((new Date(co) - new Date(ci)) / 86400000);
  var fmt = function(s) {
    var d = new Date(s + 'T00:00:00');
    return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()]
           + ' ' + d.getDate() + ', ' + d.getFullYear();
  };
  $('#confirmedDateLabel').text(
    '📅 ' + fmt(ci) + ' → ' + fmt(co) + ' (' + nights + ' night' + (nights>1?'s':'') + ')'
  );

  $('#dateGate').slideUp(250, function () {
    $('#listingsSection').slideDown(250);
    loadListings();
  });
});

// Change dates — go back to gate
$('#changeDates').on('click', function () {
  $('#listingsSection').slideUp(200, function () {
    $('#dateGate').slideDown(200);
  });
});

// ── Load listings via AJAX ───────────────────────────────────────
function loadListings() {
  var params = {
    action:    'listings',
    check_in:  gCheckIn,
    check_out: gCheckOut,
    location:  $('#f-location').val(),
    min_price: $('#f-min').val(),
    max_price: $('#f-max').val(),
    type:      $('#f-type').val(),
    guests:    $('#f-guests').val(),
    available: $('input[name="f-avail"]:checked').val(),
    sort:      $('#f-sort').val()
  };

  $('#listingsGrid').html('<div class="loading-spinner">Loading rooms…</div>');

  $.getJSON('api.php', params, function (res) {
    if (!res.success) { toast(res.message,'error'); return; }
    var listings = res.data;
    $('#listingCount').text(listings.length);
    renderListings(listings, $('#listingsGrid'));
  }).fail(function () { toast('Could not load rooms.','error'); });
}

// ── Render room cards (clicking opens room.php) ──────────────────
function renderListings(listings, $container) {
  $container.empty();
  if (!listings || !listings.length) {
    $container.html(
      '<div class="empty-state" style="grid-column:1/-1">' +
      '<div style="font-size:3rem">🔍</div>' +
      '<h3>No rooms available</h3>' +
      '<p>Try adjusting your filters or changing your dates.</p></div>'
    );
    return;
  }

  listings.forEach(function (l) {
    var isAvail = parseInt(l.available) === 1;
    var badge   = isAvail
      ? '<span class="avail-badge avail-yes">✔ Available</span>'
      : '<span class="avail-badge avail-no">✘ Not Available</span>';

    var imgHtml = (l.cover_photo_path && l.cover_photo_path !== '')
      ? '<img src="' + l.cover_photo_path + '" class="card-photo" loading="lazy" alt="' + $('<div>').text(l.title).html() + '">'
      : '<div class="card-emoji-placeholder">🏠</div>';

    var amenTags = '';
    (l.amenities || []).slice(0,3).forEach(function(a){
      amenTags += '<span class="listing-tag">' + $('<div>').text(a).html() + '</span>';
    });

    // Card click → room detail page with calendar
    var card = $(
      '<div class="listing-card" style="cursor:pointer">' +
        '<div class="listing-img-wrap">' + imgHtml + badge + '</div>' +
        '<div class="listing-body">' +
          '<div class="listing-location">📍 ' +
            $('<div>').text(l.city + (l.province?', '+l.province:'')).html() +
          '</div>' +
          '<div class="listing-title">' + $('<div>').text(l.title).html() + '</div>' +
          '<div class="listing-meta">' +
            '<span class="listing-rating">★ ' + parseFloat(l.rating).toFixed(1) + '</span>' +
            '<span>(' + l.review_count + ' reviews)</span>' +
            '<span>· ' + l.bedrooms + ' bed · ' + l.max_guests + ' guests max</span>' +
          '</div>' +
          '<div class="amenity-tags">' + amenTags + '</div>' +
          '<div class="listing-footer">' +
            '<div class="listing-price">' +
              '<strong>₱' + Number(l.price_per_night).toLocaleString() + '</strong>' +
              '<span>/ night</span>' +
            '</div>' +
            '<span class="book-btn" style="pointer-events:none">View Room →</span>' +
          '</div>' +
        '</div>' +
      '</div>'
    );

    // Navigate to room detail page, carry dates in URL
    card.on('click', function () {
      window.location.href = 'room.php?id=' + l.id +
        '&check_in=' + encodeURIComponent(gCheckIn) +
        '&check_out=' + encodeURIComponent(gCheckOut);
    });

    $container.append(card);
  });
}

// ── Filter controls ──────────────────────────────────────────────
$('#applyFilters').on('click', loadListings);
$('#f-location').on('keypress', function(e){ if(e.which===13) loadListings(); });
$('input[name="f-avail"]').on('change', loadListings);
$('#f-sort').on('change', loadListings);

$('#resetFilters').on('click', function () {
  $('#f-location,#f-min,#f-max,#f-guests').val('');
  $('#f-type,#f-sort').val('');
  $('#f-sort').val('price_asc');
  $('input[name="f-avail"][value=""]').prop('checked',true);
  loadListings();
});
</script>
</body>
</html>