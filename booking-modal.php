<?php // booking-modal.php — shown when user clicks "Book Now" ?>
<div class="modal-overlay" id="modalOverlay"></div>
<div class="modal" id="bookingModal">
  <button class="modal-close" id="modalClose">✕</button>
  <div class="modal-content">

    <h2 class="modal-title" id="modal-listing-title">Book Your Room</h2>
    <p style="color:var(--text-muted);margin-bottom:1.25rem" id="modal-price-per-night"></p>

    <!-- Availability status banner (updates on date change) -->
    <div id="modal-avail-banner" style="display:none;padding:.7rem 1rem;border-radius:8px;
         margin-bottom:1rem;font-size:.88rem;font-weight:500;border:1.5px solid transparent"></div>

    <form id="bookingForm" class="booking-form">
      <?= csrfField() ?>
      <input type="hidden" name="action"     value="create_booking">
      <input type="hidden" name="listing_id" id="booking-listing-id">
      <input type="hidden" id="booking-price">
      <input type="hidden" id="booking-cleaning">

      <div class="booking-row">
        <div class="form-group">
          <label>Check-in <span style="color:#c0392b">*</span></label>
          <input type="date" name="check_in" id="booking-checkin" required>
        </div>
        <div class="form-group">
          <label>Check-out <span style="color:#c0392b">*</span></label>
          <input type="date" name="check_out" id="booking-checkout" required>
        </div>
      </div>

      <div class="form-group">
        <label>Number of Guests</label>
        <input type="number" name="guests" id="booking-guests" min="1" max="20" value="1">
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
        <textarea name="special_requests" rows="2" placeholder="Early check-in, extra pillows…"></textarea>
      </div>

      <!-- Live price breakdown (shows after dates selected) -->
      <div id="breakdown-box" class="price-breakdown" style="display:none">
        <div class="price-line"><span>Nightly rate</span><span id="breakdown-nights"></span></div>
        <div class="price-line"><span>Cleaning fee</span><span id="breakdown-cleaning"></span></div>
        <div class="price-line total">
          <strong>Total</strong><strong id="breakdown-total"></strong>
        </div>
      </div>

      <button type="submit" class="btn-primary w-full" id="confirmBookBtn" style="margin-top:1rem">
        Confirm Booking
      </button>
    </form>
  </div>
</div>