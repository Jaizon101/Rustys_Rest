<?php
require_once 'db.php';
startSession();
requireLogin('host.php');   // Must be logged in

$user      = currentUser();
$jsonData  = json_decode(file_get_contents(__DIR__ . '/data.json'), true);
$amenities = $jsonData['amenities']      ?? [];
$types     = $jsonData['property_types'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Rusty's Rest &amp; Lodging – List a Room</title>
  <?= csrfMeta() ?>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="host-page">
  <div class="host-header">
    <h1>List Your Room</h1>
    <p>Fill in the details below and your room will appear in <strong>My Listings</strong> right away.</p>
  </div>

  <div id="formSuccess" class="alert-success" style="display:none">
    🎉 Your room has been listed and saved to My Listings!
    <a href="dashboard.php?tab=listings" style="font-weight:600;margin-left:.5rem">View My Listings →</a>
  </div>
  <div id="formError" class="alert-error" style="display:none"></div>

  <div class="host-form-wrapper">

    <!-- Step indicators -->
    <div class="steps-indicator">
      <div class="step-dot active" id="dot1">1</div>
      <div class="step-line"></div>
      <div class="step-dot" id="dot2">2</div>
      <div class="step-line"></div>
      <div class="step-dot" id="dot3">3</div>
    </div>

    <!-- ── STEP 1: Basic Info ─────────────────────────────────── -->
    <div class="host-step" id="step1">
      <h2>Basic Information</h2>

      <div class="form-group">
        <label>Room Title <span style="color:#c0392b">*</span></label>
        <input type="text" id="f-title" placeholder="e.g. Cozy Beachfront Suite with Ocean View"/>
      </div>

      <div class="form-group">
        <label>Room Type</label>
        <select id="f-type">
          <?php foreach ($types as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea id="f-desc" rows="4" placeholder="Describe what makes your room special — views, amenities, nearby spots…"></textarea>
      </div>

      <div class="form-row-3">
        <div class="form-group">
          <label>Bedrooms</label>
          <input type="number" id="f-beds" min="1" value="1"/>
        </div>
        <div class="form-group">
          <label>Bathrooms</label>
          <input type="number" id="f-baths" min="1" value="1"/>
        </div>
        <div class="form-group">
          <label>Max Guests</label>
          <input type="number" id="f-guests" min="1" value="2"/>
        </div>
      </div>

      <!-- Availability status on listing creation -->
      <div class="form-group">
        <label>Availability Status</label>
        <div style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;margin-top:.4rem">
          <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:0;cursor:pointer">
            <input type="radio" name="f-available" value="1" checked>
            <span class="avail-badge-inline avail-yes">✔ Available</span>
          </label>
          <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:0;cursor:pointer">
            <input type="radio" name="f-available" value="0">
            <span class="avail-badge-inline avail-no">✘ Not Available</span>
          </label>
        </div>
        <small style="color:var(--text-muted);margin-top:.4rem;display:block">
          You can change this anytime from <strong>My Listings</strong> in your Dashboard.
        </small>
      </div>

      <div class="step-actions">
        <button type="button" class="btn-primary" id="step1Next">Next →</button>
      </div>
    </div>

    <!-- ── STEP 2: Location & Pricing ────────────────────────── -->
    <div class="host-step hidden" id="step2">
      <h2>Location &amp; Pricing</h2>

      <div class="form-group">
        <label>Full Address</label>
        <input type="text" id="f-address" placeholder="e.g. 1 Shoreline Dr, Panglao, Bohol"/>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>City <span style="color:#c0392b">*</span></label>
          <input type="text" id="f-city" placeholder="e.g. Panglao"/>
        </div>
        <div class="form-group">
          <label>Province</label>
          <input type="text" id="f-province" placeholder="e.g. Bohol"/>
        </div>
      </div>
      <div class="form-group">
        <label>Price Per Night (₱) <span style="color:#c0392b">*</span></label>
        <input type="number" id="f-price" min="100" placeholder="e.g. 2500"/>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Cleaning Fee (₱)</label>
          <input type="number" id="f-cleaning" min="0" placeholder="e.g. 300"/>
        </div>
        <div class="form-group">
          <label>Security Deposit (₱)</label>
          <input type="number" id="f-deposit" min="0" placeholder="e.g. 1000"/>
        </div>
      </div>

      <div class="step-actions">
        <button type="button" class="btn-ghost" onclick="goStep(1)">← Back</button>
        <button type="button" class="btn-primary" id="step2Next">Next →</button>
      </div>
    </div>

    <!-- ── STEP 3: Amenities ──────────────────────────────────── -->
    <div class="host-step hidden" id="step3">
      <h2>Amenities</h2>

      <div class="form-group">
        <label>Select available amenities</label>
        <div class="amenity-grid">
          <?php foreach ($amenities as $a): ?>
            <label class="amenity-check">
              <input type="checkbox" class="f-amenity" value="<?= htmlspecialchars($a['name']) ?>">
              <?= $a['icon'] ?> <?= htmlspecialchars($a['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="step-actions">
        <button type="button" class="btn-ghost" onclick="goStep(2)">← Back</button>
        <button type="button" class="btn-primary" id="publishBtn">🏠 Publish Listing</button>
      </div>
    </div>

  </div><!-- /.host-form-wrapper -->
</div><!-- /.host-page -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="main.js"></script>
<script>
var currentStep = 1;

function goStep(step) {
  $('#step' + currentStep).addClass('hidden');
  $('#dot' + currentStep).removeClass('active');
  currentStep = step;
  $('#step' + currentStep).removeClass('hidden');
  $('#dot' + currentStep).addClass('active');
  $('html,body').animate({ scrollTop: 0 }, 280);
}

// Step 1 → 2: require title
$('#step1Next').on('click', function () {
  if (!$('#f-title').val().trim()) {
    toast('Please enter a room title.', 'error'); return;
  }
  goStep(2);
});

// Step 2 → 3: require city & price
$('#step2Next').on('click', function () {
  if (!$('#f-city').val().trim()) {
    toast('Please enter the city.', 'error'); return;
  }
  if (!$('#f-price').val() || parseFloat($('#f-price').val()) <= 0) {
    toast('Please enter a valid price per night.', 'error'); return;
  }
  goStep(3);
});

// Publish: POST to api.php?action=create_listing then redirect to My Listings
$('#publishBtn').on('click', function () {
  var $btn = $(this).prop('disabled', true).text('Publishing…');
  $('#formError').hide();

  var amenities = [];
  $('.f-amenity:checked').each(function () { amenities.push($(this).val()); });

  var payload = {
    action:        'create_listing',
    csrf_token:    $('meta[name="csrf-token"]').attr('content'),
    title:         $('#f-title').val(),
    type:          $('#f-type').val(),
    description:   $('#f-desc').val(),
    address:       $('#f-address').val(),
    city:          $('#f-city').val(),
    province:      $('#f-province').val(),
    bedrooms:      $('#f-beds').val(),
    bathrooms:     $('#f-baths').val(),
    max_guests:    $('#f-guests').val(),
    price:         $('#f-price').val(),
    cleaning:      $('#f-cleaning').val(),
    deposit:       $('#f-deposit').val(),
    available:     $('input[name="f-available"]:checked').val(),
    'amenities[]': amenities
  };

  $.ajax({
    url:      'api.php',
    method:   'POST',
    data:     payload,
    dataType: 'json',
    success: function (res) {
      if (res.success) {
        // Show success and redirect to My Listings after 2s
        $('#formSuccess').slideDown();
        $btn.hide();
        setTimeout(function () {
          window.location.href = 'dashboard.php?tab=listings';
        }, 2000);
      } else {
        $('#formError').text(res.message).slideDown();
        $btn.prop('disabled', false).text('🏠 Publish Listing');
      }
    },
    error: function () {
      $('#formError').text('Server error. Please try again.').slideDown();
      $btn.prop('disabled', false).text('🏠 Publish Listing');
    }
  });
});
</script>
</body>
</html>