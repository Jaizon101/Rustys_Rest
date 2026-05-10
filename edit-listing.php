<?php
// ============================================================
//  edit-listing.php — Rusty's Rest and Lodging
//  Allows a host to edit their own room listing.
//  Protected: host must be logged in and must own the listing.
// ============================================================
require_once 'db.php';
startSession();
requireLogin('login.php');

$user      = currentUser();
$listingId = cleanInt($_GET['id'] ?? 0);

if (!$listingId) {
    header('Location: dashboard.php?tab=listings');
    exit;
}

// Load listing from DB — verify ownership
try {
    $db   = getDB();
    $stmt = $db->prepare("
        SELECT * FROM listings WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch();

    if (!$listing) {
        header('Location: dashboard.php?tab=listings');
        exit;
    }

    // Security: only owner or admin may edit
    if ($user['role'] !== 'admin' && $listing['host_id'] != $user['id']) {
        header('Location: dashboard.php?tab=listings');
        exit;
    }
} catch (PDOException $e) {
    header('Location: dashboard.php?tab=listings');
    exit;
}

// Decode amenities JSON for pre-filling checkboxes
$currentAmenities = json_decode($listing['amenities'] ?? '[]', true) ?: [];

// Load types and amenities from data.json
$jsonData  = json_decode(file_get_contents(__DIR__ . '/data.json'), true);
$allTypes  = $jsonData['property_types'] ?? [];
$allAmen   = $jsonData['amenities']      ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Room – Rusty's Rest &amp; Lodging</title>
  <?= csrfMeta() ?>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .edit-page        { max-width: 780px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }
    .edit-header      { margin-bottom: 2rem; }
    .edit-header h1   { font-size: 1.9rem; margin-bottom: .3rem; }
    .edit-header p    { color: var(--text-muted); font-size: .9rem; }
    .edit-card        { background: var(--white); border-radius: var(--radius); padding: 2rem;
                        box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 1.5rem; }
    .edit-card h2     { font-size: 1.15rem; margin-bottom: 1.25rem; padding-bottom: .6rem;
                        border-bottom: 1px solid var(--border); color: var(--brown-dark); }
    .form-row-3       { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    .section-actions  { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: .5rem; }
    .alert-success    { background:#eaf7ee;border:1px solid #a8ddb5;color:#1e7e34;border-radius:8px;
                        padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.9rem;display:none; }
    .alert-error      { background:#fdecea;border:1px solid #f5c6c6;color:#a61c00;border-radius:8px;
                        padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.9rem;display:none; }
    .char-count       { font-size:.75rem;color:var(--text-muted);text-align:right;margin-top:.2rem; }
    .price-preview    { background:var(--beige);border-radius:var(--radius-sm);padding:.75rem 1rem;
                        font-size:.88rem;color:var(--brown);margin-top:.75rem;display:none; }
    /* Amenity grid */
    .amenity-grid     { display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.5rem; }
    .amenity-check    { display:flex;align-items:center;gap:.5rem;padding:.55rem .9rem;
                        border:1.5px solid var(--border);border-radius:var(--radius-sm);
                        cursor:pointer;transition:all var(--t);font-size:.88rem; }
    .amenity-check:has(input:checked) { border-color:var(--accent);background:rgba(196,113,58,.07); }
    .amenity-check input { width:auto;accent-color:var(--accent); }
    /* Availability radios */
    .avail-radio-wrap { display:flex;gap:1rem;flex-wrap:wrap;margin-top:.4rem; }
    .avail-radio-opt  { display:flex;align-items:center;gap:.5rem;cursor:pointer;margin-bottom:0; }
    @media(max-width:600px){
      .form-row-3   { grid-template-columns:1fr 1fr; }
      .form-row     { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="edit-page">

  <!-- ── HEADER ──────────────────────────────────────────────── -->
  <div class="edit-header">
    <a href="dashboard.php?tab=listings"
       style="font-size:.85rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:.35rem;margin-bottom:.75rem">
      ← Back to My Listings
    </a>
    <h1>Edit Room Listing</h1>
    <p>Update the details below. Changes are saved to the database immediately.</p>
  </div>

  <div id="alertSuccess" class="alert-success">
    ✅ Room updated successfully!
    <a href="dashboard.php?tab=listings" style="margin-left:.5rem;font-weight:600">← Back to My Listings</a>
  </div>
  <div id="alertError" class="alert-error"></div>

  <!-- ══ SECTION 1: Basic Info ════════════════════════════════ -->
  <div class="edit-card">
    <h2>📋 Basic Information</h2>

    <div class="form-group">
      <label>Room Title <span style="color:#c0392b">*</span></label>
      <input type="text" id="e-title" maxlength="200"
             value="<?= htmlspecialchars($listing['title']) ?>"
             placeholder="e.g. Cozy Beachfront Suite with Ocean View"/>
      <div class="char-count"><span id="titleCount"><?= strlen($listing['title']) ?></span>/200</div>
    </div>

    <div class="form-group">
      <label>Room Type <span style="color:#c0392b">*</span></label>
      <select id="e-type">
        <?php foreach ($allTypes as $t): ?>
          <option value="<?= htmlspecialchars($t) ?>"
                  <?= $listing['type'] === $t ? 'selected' : '' ?>>
            <?= htmlspecialchars($t) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label>Description</label>
      <textarea id="e-desc" rows="5" maxlength="2000"
                placeholder="Describe your room — views, vibe, nearby attractions…"><?= htmlspecialchars($listing['description'] ?? '') ?></textarea>
      <div class="char-count"><span id="descCount"><?= strlen($listing['description'] ?? '') ?></span>/2000</div>
    </div>

    <div class="form-row-3">
      <div class="form-group">
        <label>Bedrooms</label>
        <input type="number" id="e-beds" min="1" max="20"
               value="<?= (int)$listing['bedrooms'] ?>"/>
      </div>
      <div class="form-group">
        <label>Bathrooms</label>
        <input type="number" id="e-baths" min="1" max="20"
               value="<?= (int)$listing['bathrooms'] ?>"/>
      </div>
      <div class="form-group">
        <label>Max Guests</label>
        <input type="number" id="e-guests" min="1" max="30"
               value="<?= (int)$listing['max_guests'] ?>"/>
      </div>
    </div>
  </div>

  <!-- ══ SECTION 2: Location ══════════════════════════════════ -->
  <div class="edit-card">
    <h2>📍 Location</h2>

    <div class="form-group">
      <label>Full Address</label>
      <input type="text" id="e-address"
             value="<?= htmlspecialchars($listing['address'] ?? '') ?>"
             placeholder="e.g. 1 Shoreline Dr, Panglao, Bohol"/>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>City <span style="color:#c0392b">*</span></label>
        <input type="text" id="e-city"
               value="<?= htmlspecialchars($listing['city']) ?>"
               placeholder="e.g. Panglao"/>
      </div>
      <div class="form-group">
        <label>Province</label>
        <input type="text" id="e-province"
               value="<?= htmlspecialchars($listing['province'] ?? '') ?>"
               placeholder="e.g. Bohol"/>
      </div>
    </div>
  </div>

  <!-- ══ SECTION 3: Pricing ═══════════════════════════════════ -->
  <div class="edit-card">
    <h2>💰 Pricing</h2>

    <div class="form-row">
      <div class="form-group">
        <label>Price Per Night (₱) <span style="color:#c0392b">*</span></label>
        <input type="number" id="e-price" min="1" step="50"
               value="<?= (float)$listing['price_per_night'] ?>"
               placeholder="e.g. 2500"/>
      </div>
      <div class="form-group">
        <label>Cleaning Fee (₱)</label>
        <input type="number" id="e-cleaning" min="0" step="50"
               value="<?= (float)$listing['cleaning_fee'] ?>"
               placeholder="e.g. 300"/>
      </div>
    </div>

    <div class="form-group">
      <label>Security Deposit (₱)</label>
      <input type="number" id="e-deposit" min="0" step="100"
             value="<?= (float)$listing['security_deposit'] ?>"
             placeholder="e.g. 1000"/>
    </div>

    <!-- Live price preview -->
    <div class="price-preview" id="pricePreview">
      <strong>Guest sees:</strong>
      ₱<span id="prev-night">0</span>/night
      + ₱<span id="prev-clean">0</span> cleaning fee
      = ₱<span id="prev-total-1">0</span> for 1 night,
        ₱<span id="prev-total-3">0</span> for 3 nights
    </div>
  </div>

  <!-- ══ SECTION 4: Amenities ═════════════════════════════════ -->
  <div class="edit-card">
    <h2>✨ Amenities</h2>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
      Check all amenities available in your room.
    </p>
    <div class="amenity-grid">
      <?php foreach ($allAmen as $a): ?>
        <label class="amenity-check">
          <input type="checkbox" class="e-amenity" value="<?= htmlspecialchars($a['name']) ?>"
                 <?= in_array($a['name'], $currentAmenities) ? 'checked' : '' ?>>
          <?= $a['icon'] ?> <?= htmlspecialchars($a['name']) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ══ SECTION 5: Availability ══════════════════════════════ -->
  <div class="edit-card">
    <h2>📅 Availability Status</h2>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:.85rem">
      Set to <strong>Not Available</strong> to temporarily hide the room from booking requests.
    </p>
    <div class="avail-radio-wrap">
      <label class="avail-radio-opt">
        <input type="radio" name="e-available" value="1"
               <?= $listing['available'] ? 'checked' : '' ?>>
        <span class="avail-badge-inline avail-yes">✔ Available</span>
      </label>
      <label class="avail-radio-opt">
        <input type="radio" name="e-available" value="0"
               <?= !$listing['available'] ? 'checked' : '' ?>>
        <span class="avail-badge-inline avail-no">✘ Not Available</span>
      </label>
    </div>
  </div>

  <!-- ══ SAVE ACTIONS ═════════════════════════════════════════ -->
  <div class="section-actions">
    <button class="btn-primary" id="saveBtn" style="padding:.8rem 2.5rem;font-size:1rem">
      💾 Save Changes
    </button>
    <a href="dashboard.php?tab=listings" class="btn-ghost">Cancel</a>
    <span id="savingSpinner" style="display:none;color:var(--text-muted);font-size:.88rem">
      ⏳ Saving…
    </span>
  </div>

</div><!-- /.edit-page -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
var CSRF      = $('meta[name="csrf-token"]').attr('content');
var listingId = <?= $listingId ?>;

// ── Character counters ──────────────────────────────────────────
$('#e-title').on('input', function () {
  $('#titleCount').text($(this).val().length);
});
$('#e-desc').on('input', function () {
  $('#descCount').text($(this).val().length);
});

// ── Live price preview ──────────────────────────────────────────
function updatePricePreview() {
  var night = parseFloat($('#e-price').val())   || 0;
  var clean = parseFloat($('#e-cleaning').val()) || 0;
  if (night > 0) {
    $('#prev-night').text(night.toLocaleString());
    $('#prev-clean').text(clean.toLocaleString());
    $('#prev-total-1').text((night + clean).toLocaleString());
    $('#prev-total-3').text((night * 3 + clean).toLocaleString());
    $('#pricePreview').slideDown(150);
  } else {
    $('#pricePreview').hide();
  }
}
$('#e-price, #e-cleaning').on('input', updatePricePreview);
updatePricePreview(); // run on page load

// ── Validate before save ────────────────────────────────────────
function validate() {
  var ok = true;
  var fields = [
    { id: '#e-title', label: 'Room title' },
    { id: '#e-city',  label: 'City' },
    { id: '#e-price', label: 'Price per night', numeric: true }
  ];
  fields.forEach(function (f) {
    var $el = $(f.id);
    var val = $el.val().trim();
    if (!val || (f.numeric && parseFloat(val) <= 0)) {
      $el.addClass('input-error');
      ok = false;
    } else {
      $el.removeClass('input-error');
    }
  });
  return ok;
}

// ── Save changes → POST to api.php?action=update_listing ────────
$('#saveBtn').on('click', function () {
  $('#alertSuccess, #alertError').hide();

  if (!validate()) {
    $('#alertError').text('Please fill in all required fields (Title, City, Price).').slideDown();
    $('html,body').animate({ scrollTop: 0 }, 300);
    return;
  }

  // Collect selected amenities
  var amenities = [];
  $('.e-amenity:checked').each(function () { amenities.push($(this).val()); });

  var $btn = $('#saveBtn').prop('disabled', true);
  $('#savingSpinner').show();

  // Build data object — arrays need special jQuery serialization
  var data = {
    action:        'update_listing',
    csrf_token:    CSRF,
    id:            listingId,
    title:         $('#e-title').val(),
    type:          $('#e-type').val(),
    description:   $('#e-desc').val(),
    address:       $('#e-address').val(),
    city:          $('#e-city').val(),
    province:      $('#e-province').val(),
    bedrooms:      $('#e-beds').val(),
    bathrooms:     $('#e-baths').val(),
    max_guests:    $('#e-guests').val(),
    price:         $('#e-price').val(),
    cleaning:      $('#e-cleaning').val(),
    deposit:       $('#e-deposit').val(),
    available:     $('input[name="e-available"]:checked').val(),
    'amenities[]': amenities
  };

  $.ajax({
    url:      'api.php',
    method:   'POST',
    data:     data,
    dataType: 'json',
    headers:  { 'X-CSRF-Token': CSRF },
    success: function (res) {
      $('#savingSpinner').hide();
      $btn.prop('disabled', false);
      if (res.success) {
        $('#alertSuccess').slideDown();
        $('html,body').animate({ scrollTop: 0 }, 300);
      } else {
        $('#alertError').text('❌ ' + res.message).slideDown();
        $('html,body').animate({ scrollTop: 0 }, 300);
      }
    },
    error: function () {
      $('#savingSpinner').hide();
      $btn.prop('disabled', false);
      $('#alertError').text('❌ Server error. Please try again.').slideDown();
      $('html,body').animate({ scrollTop: 0 }, 300);
    }
  });
});

// Clear error highlight on input
$(document).on('input change', '.input-error', function () {
  $(this).removeClass('input-error');
});
</script>
</body>
</html>