/**
 * main.js — Rusty's Rest and Lodging
 * jQuery 3.7 — CSRF, listings render, booking modal with
 * live availability check, toast, form validation
 */
$(function () {

  /* ── 1. CSRF on every AJAX request ─────────────────────────── */
  var CSRF = $('meta[name="csrf-token"]').attr('content') || '';
  $.ajaxSetup({ headers: { 'X-CSRF-Token': CSRF } });

  /* ── 2. Toast ───────────────────────────────────────────────── */
  window.toast = function (msg, type) {
    type = type || 'success';
    var colors = { success:'#27ae60', error:'#c0392b', info:'#2980b9', warn:'#e67e22' };
    var $t = $('<div class="toast-msg">').text(msg)
               .css('background', colors[type] || colors.success)
               .appendTo('body');
    setTimeout(function(){ $t.addClass('toast-hide'); }, 2800);
    setTimeout(function(){ $t.remove(); }, 3400);
  };

  /* ── 3. API helper ──────────────────────────────────────────── */
  window.api = function (params, onSuccess, onError) {
    $.ajax({
      url:      'api.php',
      method:   params.method || 'GET',
      data:     params.data   || {},
      dataType: 'json',
      success: function (res) {
        if (res.success) { onSuccess && onSuccess(res.data, res.message); }
        else             { toast(res.message, 'error'); onError && onError(res.message); }
      },
      error: function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Server error. Please try again.';
        toast(msg, 'error');
        onError && onError(msg);
      }
    });
  };

  /* ── 4. Availability badge (card corner) ───────────────────── */
  function availBadge(available) {
    if (parseInt(available) === 1) {
      return '<span class="avail-badge avail-yes">✔ Available</span>';
    }
    return '<span class="avail-badge avail-no">✘ Not Available</span>';
  }

  /* ── 5. Render listing cards ────────────────────────────────── */
  window.renderListings = function (listings, $container, loggedIn) {
    $container.empty();
    if (!listings || listings.length === 0) {
      $container.html(
        '<div class="empty-state">' +
        '<div style="font-size:3rem">🔍</div>' +
        '<h3>No rooms found</h3>' +
        '<p>Try adjusting your filters or dates.</p></div>'
      );
      return;
    }

    $.each(listings, function (_, l) {
      var isAvail = parseInt(l.available) === 1;

      // Photo: use cover path if stored, otherwise emoji placeholder
      var imgHtml = (l.cover_photo_path && l.cover_photo_path !== '')
        ? '<img src="' + l.cover_photo_path + '" alt="' + $('<div>').text(l.title).html() + '" class="card-photo" loading="lazy">'
        : '<div class="card-emoji-placeholder">🏠</div>';

      // Book / not-available / login button
      var btnHtml;
      if (!isAvail) {
        btnHtml = '<button class="book-btn book-btn-disabled" disabled>Not Available</button>';
      } else if (loggedIn) {
        btnHtml =
          '<button class="book-btn js-open-booking"' +
          ' data-id="'       + l.id                                   + '"' +
          ' data-price="'    + l.price_per_night                      + '"' +
          ' data-title="'    + $('<div>').text(l.title).html()         + '"' +
          ' data-cleaning="' + l.cleaning_fee                          + '"' +
          '>Book Now</button>';
      } else {
        btnHtml = '<a href="login.php" class="book-btn">Log in to Book</a>';
      }

      // First 3 amenities as tags
      var amenTags = '';
      $.each((l.amenities || []).slice(0, 3), function (_, a) {
        amenTags += '<span class="listing-tag">' + $('<div>').text(a).html() + '</span>';
      });

      var card = $(
        '<div class="listing-card" data-id="' + l.id + '">' +
          '<div class="listing-img-wrap">' +
            imgHtml +
            availBadge(l.available) +
          '</div>' +
          '<div class="listing-body">' +
            '<div class="listing-location">📍 ' +
              $('<div>').text(l.city + (l.province ? ', ' + l.province : '')).html() +
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
              btnHtml +
            '</div>' +
          '</div>' +
        '</div>'
      );
      $container.append(card);
    });
  };

  /* ── 6. Open booking modal ──────────────────────────────────── */
  $(document).on('click', '.js-open-booking', function () {
    var $b       = $(this);
    var id       = $b.data('id');
    var price    = parseFloat($b.data('price'));
    var cleaning = parseFloat($b.data('cleaning')) || 0;

    $('#modal-listing-title').text($b.data('title'));
    $('#modal-price-per-night').text('₱' + price.toLocaleString() + ' / night');
    $('#booking-listing-id').val(id);
    $('#booking-price').val(price);
    $('#booking-cleaning').val(cleaning);
    $('#booking-checkin, #booking-checkout').val('');
    $('#breakdown-box').hide();
    $('#modal-avail-banner').hide();
    $('#confirmBookBtn').prop('disabled', false).text('Confirm Booking');

    $('#bookingModal, #modalOverlay').addClass('open');
  });

  // Close modal
  $(document).on('click', '#modalOverlay, #modalClose', function () {
    $('#bookingModal, #modalOverlay').removeClass('open');
  });

  /* ── 7. Date change: check availability + price breakdown ───── */
  $(document).on('change', '#booking-checkin, #booking-checkout', function () {
    var ci   = $('#booking-checkin').val();
    var co   = $('#booking-checkout').val();
    var id   = $('#booking-listing-id').val();
    var prc  = parseFloat($('#booking-price').val())   || 0;
    var fee  = parseFloat($('#booking-cleaning').val()) || 0;

    var $banner = $('#modal-avail-banner');
    var $btn    = $('#confirmBookBtn');

    if (!ci || !co) { $banner.hide(); $('#breakdown-box').hide(); return; }
    if (new Date(co) <= new Date(ci)) {
      $banner.text('⚠ Check-out must be after check-in.')
        .css({ background:'#fff8ea', color:'#e67e22', border:'1.5px solid #f5d88e' })
        .show();
      $btn.prop('disabled', true);
      $('#breakdown-box').hide();
      return;
    }

    // Live price breakdown
    var nights = Math.round((new Date(co) - new Date(ci)) / 86400000);
    var total  = (prc * nights) + fee;
    $('#breakdown-nights').text('₱' + prc.toLocaleString() + ' × ' + nights + ' night' + (nights>1?'s':''));
    $('#breakdown-cleaning').text('₱' + fee.toLocaleString());
    $('#breakdown-total').text('₱' + total.toLocaleString());
    $('#breakdown-box').slideDown(180);

    // AJAX: check if those dates are actually free
    $banner.text('Checking availability…')
      .css({ background:'#f5f5f5', color:'#555', border:'1.5px solid #ddd' }).show();
    $btn.prop('disabled', true);

    $.getJSON('api.php', {
      action:    'check_availability',
      id:        id,
      check_in:  ci,
      check_out: co
    }, function (res) {
      if (!res.success) {
        $banner.text('Could not verify availability.').show();
        return;
      }
      var d = res.data;
      if (parseInt(d.listing_available) !== 1) {
        $banner.text('✘ This room is currently marked as Not Available by the host.')
          .css({ background:'#fdecea', color:'#a61c00', border:'1.5px solid #f5c6c6' }).show();
        $btn.prop('disabled', true);
      } else if (!d.date_available) {
        $banner.text('✘ Those dates overlap with an existing booking. Please choose different dates.')
          .css({ background:'#fdecea', color:'#a61c00', border:'1.5px solid #f5c6c6' }).show();
        $btn.prop('disabled', true);
      } else {
        $banner.text('✔ This room is available for your selected dates!')
          .css({ background:'#eaf7ee', color:'#1e7e34', border:'1.5px solid #a8ddb5' }).show();
        $btn.prop('disabled', false);
      }
    }).fail(function () {
      $banner.text('Could not verify availability.').show();
    });
  });

  /* ── 8. Submit booking ──────────────────────────────────────── */
  $(document).on('submit', '#bookingForm', function (e) {
    e.preventDefault();
    var $btn = $('#confirmBookBtn').prop('disabled', true).text('Booking…');

    api({
      method: 'POST',
      data:   $(this).serialize() + '&action=create_booking'
    },
    function (data, msg) {
      toast(msg || 'Booking confirmed! 🎉', 'success');
      $('#bookingModal, #modalOverlay').removeClass('open');
      // Redirect to My Bookings after short delay
      setTimeout(function () {
        window.location.href = 'dashboard.php?tab=bookings';
      }, 1400);
    },
    function () {
      $btn.prop('disabled', false).text('Confirm Booking');
    });
  });

  /* ── 9. Password strength meter ─────────────────────────────── */
  $(document).on('input', '#reg-password', function () {
    var v = $(this).val();
    var strength = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/, /.{8,}/]
                     .filter(function (r) { return r.test(v); }).length;
    var labels = ['','Weak','Fair','Good','Strong','Very Strong'];
    var colors = ['','#c0392b','#e67e22','#f1c40f','#27ae60','#1e8449'];
    $('#pwd-strength').text(labels[strength]||'').css('color', colors[strength]||'');
  });

  /* ── 10. Required field validation ──────────────────────────── */
  $(document).on('submit', '.validated-form', function (e) {
    var ok = true;
    $(this).find('[required]').each(function () {
      if (!$(this).val().trim()) { $(this).addClass('input-error'); ok = false; }
      else                       { $(this).removeClass('input-error'); }
    });
    if (!ok) { e.preventDefault(); toast('Please fill in all required fields.', 'error'); }
  });

  /* ── 11. Smooth scroll ──────────────────────────────────────── */
  $(document).on('click', 'a[href^="#"]', function (e) {
    var $t = $($(this).attr('href'));
    if ($t.length) {
      e.preventDefault();
      $('html,body').animate({ scrollTop: $t.offset().top - 80 }, 400);
    }
  });

});