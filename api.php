<?php
// ================================================================
//  api.php — Rusty's Rest and Lodging  (v2 – Host Approval Flow)
//
//  GET  ?action=listings              → public room list
//  GET  ?action=host_listings         → host's own rooms only
//  GET  ?action=check_availability    → date availability for a room
//  GET  ?action=bookings              → guest's own bookings
//  GET  ?action=host_requests         → host sees all booking requests
//  POST ?action=create_listing        → host adds room
//  POST ?action=toggle_availability   → host flips available flag
//  POST ?action=delete_listing        → host/admin deletes room
//  POST ?action=create_booking        → guest sends booking REQUEST
//  POST ?action=cancel_booking        → guest cancels pending booking
//  POST ?action=approve_booking       → HOST APPROVES guest request
//  POST ?action=reject_booking        → HOST REJECTS guest request
//  GET  ?action=admin_bookings        → all bookings (admin)
//  GET  ?action=admin_listings        → all listings (admin)
//  GET  ?action=admin_users           → all users (admin)
//  POST ?action=delete_user           → (admin)
//  POST ?action=update_booking_status → (admin)
// ================================================================

require_once 'db.php';
startSession();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

  // ── PUBLIC: Room list ─────────────────────────────────────────
  case 'listings':
    $db     = getDB();
    $sql    = "SELECT l.id, l.title, l.type, l.city, l.province,
                      l.bedrooms, l.bathrooms, l.max_guests,
                      l.price_per_night, l.cleaning_fee,
                      l.amenities, l.rating, l.review_count,
                      l.available, l.created_at,
                      CONCAT(u.first_name,' ',u.last_name) AS host_name,
                      COALESCE(p.file_path,'') AS cover_photo_path
               FROM listings l
               JOIN users u ON u.id = l.host_id
               LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.is_cover = 1
               WHERE 1=1";
    $params = [];

    if (!empty($_GET['location'])) {
      $loc = '%'.clean($_GET['location']).'%';
      $sql .= " AND (l.city LIKE ? OR l.province LIKE ? OR l.title LIKE ?)";
      array_push($params, $loc, $loc, $loc);
    }
    if (!empty($_GET['type'])) {
      $sql .= " AND l.type = ?"; $params[] = clean($_GET['type']);
    }
    if (!empty($_GET['guests'])) {
      $sql .= " AND l.max_guests >= ?"; $params[] = cleanInt($_GET['guests']);
    }
    if (!empty($_GET['min_price'])) {
      $sql .= " AND l.price_per_night >= ?"; $params[] = cleanFloat($_GET['min_price']);
    }
    if (!empty($_GET['max_price'])) {
      $sql .= " AND l.price_per_night <= ?"; $params[] = cleanFloat($_GET['max_price']);
    }
    if (isset($_GET['available']) && $_GET['available'] !== '') {
      $sql .= " AND l.available = ?"; $params[] = cleanInt($_GET['available']);
    }
    // Date filter: exclude rooms with approved bookings overlapping selected range
    if (!empty($_GET['check_in']) && !empty($_GET['check_out'])) {
      $ci = clean($_GET['check_in']);
      $co = clean($_GET['check_out']);
      $sql .= " AND l.id NOT IN (
                  SELECT listing_id FROM bookings
                  WHERE booking_status IN ('pending','approved')
                    AND NOT (check_out <= ? OR check_in >= ?)
                )";
      array_push($params, $ci, $co);
    }

    $sql .= " ORDER BY " . match($_GET['sort'] ?? '') {
      'price_desc' => 'l.price_per_night DESC',
      'rating'     => 'l.rating DESC',
      'newest'     => 'l.created_at DESC',
      default      => 'l.price_per_night ASC',
    };

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
      $r['amenities'] = json_decode($r['amenities'] ?? '[]', true);
    }
    jsonResponse(true, 'OK', $rows);

  // ── HOST ONLY: Their own listings ────────────────────────────
  case 'host_listings':
    requireLogin('dashboard.php');
    $user = currentUser();
    $db   = getDB();
    $stmt = $db->prepare("
      SELECT l.id, l.title, l.type, l.city, l.province,
             l.bedrooms, l.bathrooms, l.max_guests,
             l.price_per_night, l.cleaning_fee, l.security_deposit,
             l.amenities, l.rating, l.review_count,
             l.available, l.created_at,
             COALESCE(p.file_path,'') AS cover_photo_path,
             (SELECT COUNT(*) FROM bookings b
              WHERE b.listing_id = l.id AND b.booking_status = 'pending') AS pending_count
      FROM listings l
      LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.is_cover = 1
      WHERE l.host_id = ?
      ORDER BY l.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
      $r['amenities'] = json_decode($r['amenities'] ?? '[]', true);
    }
    jsonResponse(true, 'OK', $rows);

  // ── HOST: All booking REQUESTS for their listings ─────────────
  case 'host_requests':
    requireLogin('dashboard.php');
    $user = currentUser();
    $db   = getDB();
    $stmt = $db->prepare("
      SELECT b.id, b.listing_id, b.check_in, b.check_out,
             b.guests, b.nights, b.total_price,
             b.booking_status, b.payment_method,
             b.special_requests, b.host_note, b.created_at,
             l.title AS room_title, l.city,
             CONCAT(u.first_name,' ',u.last_name) AS guest_name,
             u.email AS guest_email
      FROM bookings b
      JOIN listings l ON l.id = b.listing_id
      JOIN users    u ON u.id = b.user_id
      WHERE l.host_id = ?
      ORDER BY
        FIELD(b.booking_status,'pending','approved','rejected','cancelled','completed'),
        b.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    jsonResponse(true, 'OK', $stmt->fetchAll());

  // ── CHECK AVAILABILITY for a room on given dates ──────────────
  case 'check_availability':
    $listingId = cleanInt($_GET['id']       ?? 0);
    $checkIn   = clean($_GET['check_in']    ?? '');
    $checkOut  = clean($_GET['check_out']   ?? '');
    if (!$listingId) jsonResponse(false, 'Invalid listing.', [], 400);

    $db  = getDB();
    $lst = $db->prepare("SELECT available FROM listings WHERE id = ? LIMIT 1");
    $lst->execute([$listingId]);
    $listing = $lst->fetch();
    if (!$listing) jsonResponse(false, 'Listing not found.', [], 404);

    // All active (pending + approved) booking date ranges
    $bk = $db->prepare("
      SELECT check_in, check_out, booking_status FROM bookings
      WHERE listing_id = ? AND booking_status IN ('pending','approved')
      ORDER BY check_in
    ");
    $bk->execute([$listingId]);
    $bookedRanges = $bk->fetchAll();

    // Check if the requested dates overlap any booked range
    $dateAvailable = true;
    if ($checkIn && $checkOut) {
      foreach ($bookedRanges as $range) {
        if (!($checkOut <= $range['check_in'] || $checkIn >= $range['check_out'])) {
          $dateAvailable = false;
          break;
        }
      }
    }

    jsonResponse(true, 'OK', [
      'listing_available' => (int)$listing['available'],
      'date_available'    => $dateAvailable,
      'booked_ranges'     => $bookedRanges,
    ]);

  // ── CREATE LISTING ────────────────────────────────────────────
  case 'create_listing':
    requireLogin('host.php');
    verifyCsrf();
    $user  = currentUser();
    $title    = clean($_POST['title']        ?? '');
    $type     = clean($_POST['type']         ?? '');
    $desc     = clean($_POST['description']  ?? '');
    $address  = clean($_POST['address']      ?? '');
    $city     = clean($_POST['city']         ?? '');
    $province = clean($_POST['province']     ?? '');
    $beds     = cleanInt($_POST['bedrooms']  ?? 1, 1);
    $baths    = cleanInt($_POST['bathrooms'] ?? 1, 1);
    $guests   = cleanInt($_POST['max_guests']?? 2, 2);
    $price    = cleanFloat($_POST['price']   ?? 0);
    $cleaning = cleanFloat($_POST['cleaning']?? 0);
    $deposit  = cleanFloat($_POST['deposit'] ?? 0);
    $avail    = cleanInt($_POST['available'] ?? 1);
    $amenArr  = is_array($_POST['amenities'] ?? null)
                  ? array_map('clean', $_POST['amenities']) : [];
    if (!$title || !$city || $price <= 0) {
      jsonResponse(false, 'Room title, city and price are required.', [], 422);
    }
    $db   = getDB();
    $stmt = $db->prepare("
      INSERT INTO listings
        (host_id,title,type,description,address,city,province,
         bedrooms,bathrooms,max_guests,price_per_night,
         cleaning_fee,security_deposit,amenities,available)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
      $user['id'],$title,$type,$desc,$address,$city,$province,
      $beds,$baths,$guests,$price,$cleaning,$deposit,
      json_encode($amenArr),$avail
    ]);
    jsonResponse(true, 'Room listed successfully!', ['id' => $db->lastInsertId()]);

  // ── TOGGLE AVAILABILITY ───────────────────────────────────────
  case 'toggle_availability':
    requireLogin(); verifyCsrf();
    $id    = cleanInt($_POST['id']        ?? 0);
    $avail = cleanInt($_POST['available'] ?? 0);
    $user  = currentUser();
    $db    = getDB();
    if ($user['role'] !== 'admin') {
      $c = $db->prepare("SELECT id FROM listings WHERE id=? AND host_id=?");
      $c->execute([$id, $user['id']]);
      if (!$c->fetch()) jsonResponse(false, 'Unauthorized.', [], 403);
    }
    $db->prepare("UPDATE listings SET available=? WHERE id=?")->execute([$avail,$id]);
    jsonResponse(true, $avail ? 'Room marked as Available.' : 'Room marked as Not Available.',
                 ['available' => $avail]);

  // ── GET SINGLE LISTING: for edit form pre-fill ───────────────
  case 'get_listing':
    requireLogin('dashboard.php');
    $id   = cleanInt($_GET['id'] ?? 0);
    $user = currentUser();
    $db   = getDB();

    // Only the owner or admin may fetch full details for editing
    if ($user['role'] !== 'admin') {
      $own = $db->prepare("SELECT id FROM listings WHERE id=? AND host_id=?");
      $own->execute([$id, $user['id']]);
      if (!$own->fetch()) jsonResponse(false, 'Unauthorized.', [], 403);
    }

    $stmt = $db->prepare("
      SELECT id, title, type, description, address, city, province,
             bedrooms, bathrooms, max_guests,
             price_per_night, cleaning_fee, security_deposit,
             amenities, available
      FROM listings WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(false, 'Listing not found.', [], 404);

    $row['amenities'] = json_decode($row['amenities'] ?? '[]', true);
    jsonResponse(true, 'OK', $row);

  // ── UPDATE LISTING: host edits their own room ─────────────────
  case 'update_listing':
    requireLogin('dashboard.php');
    verifyCsrf();

    $id   = cleanInt($_POST['id'] ?? 0);
    $user = currentUser();
    $db   = getDB();

    // Ownership check — only owner or admin
    if ($user['role'] !== 'admin') {
      $own = $db->prepare("SELECT id FROM listings WHERE id=? AND host_id=?");
      $own->execute([$id, $user['id']]);
      if (!$own->fetch()) jsonResponse(false, 'You can only edit your own listings.', [], 403);
    }

    $title    = clean($_POST['title']        ?? '');
    $type     = clean($_POST['type']         ?? '');
    $desc     = clean($_POST['description']  ?? '');
    $address  = clean($_POST['address']      ?? '');
    $city     = clean($_POST['city']         ?? '');
    $province = clean($_POST['province']     ?? '');
    $beds     = cleanInt($_POST['bedrooms']  ?? 1, 1);
    $baths    = cleanInt($_POST['bathrooms'] ?? 1, 1);
    $guests   = cleanInt($_POST['max_guests']?? 2, 2);
    $price    = cleanFloat($_POST['price']   ?? 0);
    $cleaning = cleanFloat($_POST['cleaning']?? 0);
    $deposit  = cleanFloat($_POST['deposit'] ?? 0);
    $avail    = cleanInt($_POST['available'] ?? 1);
    $amenArr  = is_array($_POST['amenities'] ?? null)
                  ? array_map('clean', $_POST['amenities']) : [];

    if (!$title || !$city || $price <= 0) {
      jsonResponse(false, 'Room title, city and price are required.', [], 422);
    }

    $stmt = $db->prepare("
      UPDATE listings SET
        title=?, type=?, description=?, address=?, city=?, province=?,
        bedrooms=?, bathrooms=?, max_guests=?,
        price_per_night=?, cleaning_fee=?, security_deposit=?,
        amenities=?, available=?
      WHERE id=?
    ");
    $stmt->execute([
      $title, $type, $desc, $address, $city, $province,
      $beds, $baths, $guests,
      $price, $cleaning, $deposit,
      json_encode($amenArr), $avail,
      $id
    ]);
    jsonResponse(true, 'Room updated successfully!');

  // ── DELETE LISTING ────────────────────────────────────────────
  case 'delete_listing':
    requireLogin(); verifyCsrf();
    $id   = cleanInt($_POST['id'] ?? 0);
    $user = currentUser();
    $db   = getDB();
    if ($user['role'] !== 'admin') {
      $c = $db->prepare("SELECT id FROM listings WHERE id=? AND host_id=?");
      $c->execute([$id, $user['id']]);
      if (!$c->fetch()) jsonResponse(false, 'Unauthorized.', [], 403);
    }
    $db->prepare("DELETE FROM listings WHERE id=?")->execute([$id]);
    jsonResponse(true, 'Room deleted.');

  // ── GUEST: Send booking REQUEST (starts as pending) ───────────
  case 'create_booking':
    requireLogin();
    verifyCsrf();

    $listingId = cleanInt($_POST['listing_id']    ?? 0);
    $checkIn   = clean($_POST['check_in']         ?? '');
    $checkOut  = clean($_POST['check_out']         ?? '');
    $guests    = cleanInt($_POST['guests']         ?? 1, 1);
    $payment   = clean($_POST['payment_method']   ?? 'GCash');
    $special   = clean($_POST['special_requests'] ?? '');

    if (!$listingId || !$checkIn || !$checkOut) {
      jsonResponse(false, 'Please fill in all required fields.', [], 422);
    }

    $in  = strtotime($checkIn);
    $out = strtotime($checkOut);

    // Dates must be valid and not in the past
    if (!$in || !$out || $out <= $in) {
      jsonResponse(false, 'Check-out must be after check-in.', [], 422);
    }
    if ($in < strtotime('today')) {
      jsonResponse(false, 'Check-in date cannot be in the past.', [], 422);
    }

    $db  = getDB();
    $lst = $db->prepare("SELECT price_per_night, cleaning_fee, available FROM listings WHERE id=? LIMIT 1");
    $lst->execute([$listingId]);
    $listing = $lst->fetch();
    if (!$listing)           jsonResponse(false, 'Room not found.',                          [], 404);
    if (!$listing['available']) jsonResponse(false, 'This room is currently not accepting requests.', [], 409);

    // Overlap check against pending + approved bookings
    $ovl = $db->prepare("
      SELECT id FROM bookings
      WHERE listing_id=? AND booking_status IN ('pending','approved')
        AND NOT (check_out <= ? OR check_in >= ?)
    ");
    $ovl->execute([$listingId, $checkIn, $checkOut]);
    if ($ovl->fetch()) {
      jsonResponse(false, 'Those dates overlap an existing booking. Please choose different dates.', [], 409);
    }

    $nights = (int)(($out - $in) / 86400);
    $total  = ($listing['price_per_night'] * $nights) + $listing['cleaning_fee'];
    $user   = currentUser();

    $db->prepare("
      INSERT INTO bookings
        (listing_id,user_id,check_in,check_out,guests,nights,
         price_per_night,cleaning_fee,total_price,payment_method,special_requests,booking_status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending')
    ")->execute([
      $listingId,$user['id'],$checkIn,$checkOut,$guests,$nights,
      $listing['price_per_night'],$listing['cleaning_fee'],
      $total,$payment,$special
    ]);

    jsonResponse(true,
      'Booking request sent! The host will approve or decline it soon. Check My Bookings for updates.',
      ['booking_id' => $db->lastInsertId(), 'total' => $total, 'nights' => $nights]
    );

  // ── GUEST: Cancel their own pending booking ───────────────────
  case 'cancel_booking':
    requireLogin(); verifyCsrf();
    $id   = cleanInt($_POST['id'] ?? 0);
    $user = currentUser();
    $db   = getDB();
    $stmt = $db->prepare("
      UPDATE bookings SET booking_status='cancelled'
      WHERE id=? AND user_id=? AND booking_status='pending'
    ");
    $stmt->execute([$id, $user['id']]);
    if (!$stmt->rowCount()) {
      jsonResponse(false, 'Only pending requests can be cancelled.', [], 400);
    }
    jsonResponse(true, 'Booking request cancelled.');

  // ── HOST: Approve a booking request ──────────────────────────
  case 'approve_booking':
    requireLogin(); verifyCsrf();
    $id       = cleanInt($_POST['id']        ?? 0);
    $note     = clean($_POST['host_note']    ?? '');
    $user     = currentUser();
    $db       = getDB();

    // Verify the booking belongs to one of this host's listings
    $chk = $db->prepare("
      SELECT b.id FROM bookings b
      JOIN listings l ON l.id = b.listing_id
      WHERE b.id=? AND l.host_id=? AND b.booking_status='pending'
    ");
    $chk->execute([$id, $user['id']]);
    if (!$chk->fetch()) jsonResponse(false, 'Booking not found or already actioned.', [], 404);

    // Use the stored procedure
    $db->prepare("CALL approve_booking(?,?)")->execute([$id, $note]);
    jsonResponse(true, 'Booking approved! The guest has been notified.');

  // ── HOST: Reject a booking request ───────────────────────────
  case 'reject_booking':
    requireLogin(); verifyCsrf();
    $id   = cleanInt($_POST['id']     ?? 0);
    $note = clean($_POST['host_note'] ?? '');
    $user = currentUser();
    $db   = getDB();

    $chk = $db->prepare("
      SELECT b.id FROM bookings b
      JOIN listings l ON l.id = b.listing_id
      WHERE b.id=? AND l.host_id=? AND b.booking_status='pending'
    ");
    $chk->execute([$id, $user['id']]);
    if (!$chk->fetch()) jsonResponse(false, 'Booking not found or already actioned.', [], 404);

    $db->prepare("CALL reject_booking(?,?)")->execute([$id, $note]);
    jsonResponse(true, 'Booking request rejected.');

  // ── GUEST: Their own bookings ─────────────────────────────────
  case 'bookings':
    requireLogin();
    $user = currentUser();
    $db   = getDB();
    $stmt = $db->prepare("
      SELECT b.id, b.listing_id, b.check_in, b.check_out,
             b.guests, b.nights, b.price_per_night,
             b.cleaning_fee, b.total_price,
             b.booking_status, b.payment_method,
             b.special_requests, b.host_note, b.created_at,
             l.title AS listing_title, l.city, l.available,
             COALESCE(p.file_path,'') AS cover_photo
      FROM bookings b
      JOIN listings l ON l.id = b.listing_id
      LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.is_cover = 1
      WHERE b.user_id = ?
      ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    jsonResponse(true, 'OK', $stmt->fetchAll());

  // ── ADMIN: All bookings ───────────────────────────────────────
  case 'admin_bookings':
    requireAdmin();
    $rows = getDB()->query("
      SELECT b.*, l.title AS listing_title,
             CONCAT(u.first_name,' ',u.last_name) AS guest_name
      FROM bookings b
      JOIN listings l ON l.id=b.listing_id
      JOIN users u ON u.id=b.user_id
      ORDER BY b.created_at DESC
    ")->fetchAll();
    jsonResponse(true, 'OK', $rows);

  // ── ADMIN: All listings ───────────────────────────────────────
  case 'admin_listings':
    requireAdmin();
    $rows = getDB()->query("
      SELECT l.id,l.title,l.type,l.city,l.price_per_night,
             l.available,l.rating,l.review_count,
             CONCAT(u.first_name,' ',u.last_name) AS host_name
      FROM listings l JOIN users u ON u.id=l.host_id
      ORDER BY l.created_at DESC
    ")->fetchAll();
    jsonResponse(true, 'OK', $rows);

  // ── ADMIN: All users ──────────────────────────────────────────
  case 'admin_users':
    requireAdmin();
    $rows = getDB()->query("SELECT id,first_name,last_name,email,role,created_at FROM users ORDER BY id")->fetchAll();
    jsonResponse(true, 'OK', $rows);

  case 'delete_user':
    requireAdmin(); verifyCsrf();
    $id = cleanInt($_POST['id'] ?? 0);
    if ($id === (int)$_SESSION['user_id']) jsonResponse(false, 'Cannot delete your own account.', [], 400);
    getDB()->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
    jsonResponse(true, 'User deleted.');

  case 'update_booking_status':
    requireAdmin(); verifyCsrf();
    $id     = cleanInt($_POST['id'] ?? 0);
    $status = clean($_POST['status'] ?? '');
    if (!in_array($status, ['pending','approved','rejected','cancelled','completed'])) {
      jsonResponse(false, 'Invalid status.', [], 422);
    }
    getDB()->prepare("UPDATE bookings SET booking_status=? WHERE id=?")->execute([$status,$id]);
    jsonResponse(true, 'Booking updated.');

  default:
    jsonResponse(false, 'Unknown action.', [], 404);
}