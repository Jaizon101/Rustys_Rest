<?php
// ============================================================
//  api.php — Rusty's Rest and Lodging
//  JSON API endpoint for all AJAX requests
// ============================================================
require_once 'db.php';
startSession();

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$user   = currentUser();

// ── Public actions (no login required) ──────────────────────
if ($action === 'listings') {
    try {
        $db   = getDB();
        $sort = $_GET['sort'] ?? 'rating';
        $orderBy = match($sort) {
            'price_asc'  => 'l.price_per_night ASC',
            'price_desc' => 'l.price_per_night DESC',
            'newest'     => 'l.created_at DESC',
            default      => 'l.rating DESC',
        };
        $type     = $_GET['type']     ?? '';
        $location = $_GET['location'] ?? $_GET['city'] ?? '';
        $minPrice = (float)($_GET['min_price'] ?? 0);
        $maxPrice = (float)($_GET['max_price'] ?? 999999);
        $where  = ['l.available = 1'];
        $params = [];
        if ($type)     { $where[] = 'l.type = ?'; $params[] = $type; }
        if ($location) { $where[] = '(l.city LIKE ? OR l.title LIKE ?)'; $params[] = "%$location%"; $params[] = "%$location%"; }
        if ($minPrice > 0) { $where[] = 'l.price_per_night >= ?'; $params[] = $minPrice; }
        if ($maxPrice < 999999) { $where[] = 'l.price_per_night <= ?'; $params[] = $maxPrice; }
        $sql = "SELECT l.id, l.title, l.type, l.city, l.province,
                   l.price_per_night, l.cleaning_fee, l.bedrooms,
                   l.bathrooms, l.max_guests, l.rating, l.review_count,
                   l.cover_photo, l.cover_photo AS cover_photo_path,
                   l.amenities, l.available,
                   CONCAT(u.first_name,' ',u.last_name) AS host_name
            FROM listings l
            JOIN users u ON u.id = l.host_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $orderBy LIMIT 50";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        // Decode amenities JSON into array for JS
        foreach ($rows as &$row) {
            $row['amenities'] = json_decode($row['amenities'] ?? '[]', true) ?: [];
        }
        jsonResponse(true, 'ok', $rows);
    } catch (PDOException $e) {
        jsonResponse(false, 'Failed to load listings: ' . $e->getMessage(), [], 500);
    }
}

// ── Check availability (public) ──────────────────────────────
if ($action === 'check_availability') {
    try {
        $db        = getDB();
        $listingId = cleanInt($_GET['id'] ?? 0);
        $checkIn   = $_GET['check_in']  ?? '';
        $checkOut  = $_GET['check_out'] ?? '';
        $listing = $db->prepare("SELECT available FROM listings WHERE id = ? LIMIT 1");
        $listing->execute([$listingId]);
        $l = $listing->fetch();
        if (!$l) jsonResponse(false, 'Listing not found.');
        $conflict = $db->prepare("SELECT id FROM bookings
            WHERE listing_id = ? AND booking_status IN ('pending','approved')
            AND check_in < ? AND check_out > ? LIMIT 1");
        $conflict->execute([$listingId, $checkOut, $checkIn]);
        jsonResponse(true, 'ok', [
            'date_available'    => !$conflict->fetch(),
            'listing_available' => (bool)$l['available'],
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, 'Failed to check availability.', [], 500);
    }
}

// ── Auth required below this point ──────────────────────────
if (!$user) {
    jsonResponse(false, 'Please log in to continue.', [], 401);
}

// ── CSRF check for all POST actions ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
}

$db = getDB();

switch ($action) {

    case 'bookings':
    case 'my_bookings':
        try {
            $stmt = $db->prepare("SELECT b.*, DATEDIFF(b.check_out, b.check_in) AS nights, l.title AS listing_title, l.city, l.cover_photo, l.price_per_night,
                       CONCAT(u.first_name,' ',u.last_name) AS host_name
                FROM bookings b
                JOIN listings l ON l.id = b.listing_id
                JOIN users u ON u.id = l.host_id
                WHERE b.user_id = ? ORDER BY b.created_at DESC");
            $stmt->execute([$user['id']]);
            jsonResponse(true, 'ok', $stmt->fetchAll());
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to load bookings: ' . $e->getMessage(), [], 500);
        }
        break;

    case 'host_listings':
        try {
            $stmt = $db->prepare("SELECT id, title, type, city, province, price_per_night,
                       available, rating, review_count, cover_photo, created_at
                FROM listings WHERE host_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user['id']]);
            jsonResponse(true, 'ok', $stmt->fetchAll());
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to load listings.', [], 500);
        }
        break;

    case 'host_bookings':
        try {
            $stmt = $db->prepare("SELECT b.*, l.title, l.city,
                       CONCAT(u.first_name,' ',u.last_name) AS guest_name, u.email AS guest_email
                FROM bookings b
                JOIN listings l ON l.id = b.listing_id
                JOIN users u ON u.id = b.user_id
                WHERE l.host_id = ? ORDER BY b.created_at DESC");
            $stmt->execute([$user['id']]);
            jsonResponse(true, 'ok', $stmt->fetchAll());
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to load bookings: ' . $e->getMessage(), [], 500);
        }
        break;

    case 'create_booking':
        $listingId       = cleanInt($_POST['listing_id'] ?? 0);
        $checkIn         = $_POST['check_in']  ?? '';
        $checkOut        = $_POST['check_out'] ?? '';
        $guests          = cleanInt($_POST['guests'] ?? 1);
        $paymentMethod   = clean($_POST['payment_method'] ?? 'Cash');
        $specialRequests = clean($_POST['special_requests'] ?? '');
        if (!$listingId || !$checkIn || !$checkOut) jsonResponse(false, 'Missing required fields.');
        try {
            $stmt = $db->prepare("SELECT * FROM listings WHERE id = ? AND available = 1 LIMIT 1");
            $stmt->execute([$listingId]);
            $listing = $stmt->fetch();
            if (!$listing) jsonResponse(false, 'Listing not found or not available.');
            if ($listing['host_id'] == $user['id']) jsonResponse(false, 'You cannot book your own listing.');
            $ci = new DateTime($checkIn); $co = new DateTime($checkOut); $today = new DateTime('today');
            if ($ci < $today) jsonResponse(false, 'Check-in cannot be in the past.');
            if ($co <= $ci)   jsonResponse(false, 'Check-out must be after check-in.');
            $nights = $ci->diff($co)->days;
            $conflict = $db->prepare("SELECT id FROM bookings WHERE listing_id = ?
                AND booking_status IN ('pending','approved') AND check_in < ? AND check_out > ? LIMIT 1");
            $conflict->execute([$listingId, $checkOut, $checkIn]);
            if ($conflict->fetch()) jsonResponse(false, 'Those dates are already booked.');
            $total = ($listing['price_per_night'] * $nights) + $listing['cleaning_fee'];
            $insert = $db->prepare("INSERT INTO bookings
                (listing_id, user_id, check_in, check_out, guests, total_price, payment_method, special_requests, booking_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $insert->execute([$listingId, $user['id'], $checkIn, $checkOut, $guests, $total, $paymentMethod, $specialRequests]);
            jsonResponse(true, 'Booking request sent!', ['booking_id' => $db->lastInsertId(), 'total' => $total, 'nights' => $nights]);
        } catch (PDOException $e) {
            jsonResponse(false, 'Booking failed. Please try again.', [], 500);
        }
        break;

    case 'cancel_booking':
        $bookingId = cleanInt($_POST['id'] ?? $_POST['booking_id'] ?? 0);
        try {
            $stmt = $db->prepare("UPDATE bookings SET booking_status = 'cancelled'
                WHERE id = ? AND user_id = ? AND booking_status = 'pending'");
            $stmt->execute([$bookingId, $user['id']]);
            jsonResponse($stmt->rowCount() ? true : false,
                         $stmt->rowCount() ? 'Booking cancelled.' : 'Not found or cannot be cancelled.');
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to cancel booking.', [], 500);
        }
        break;

    case 'extend_booking':
        $bookingId = cleanInt($_POST['id'] ?? 0);
        $newOut    = $_POST['new_checkout'] ?? '';
        try {
            $stmt = $db->prepare("UPDATE bookings SET check_out = ?, booking_status = 'pending'
                WHERE id = ? AND user_id = ? AND booking_status = 'approved'");
            $stmt->execute([$newOut, $bookingId, $user['id']]);
            jsonResponse($stmt->rowCount() ? true : false,
                         $stmt->rowCount() ? 'Stay extended successfully.' : 'Cannot extend this booking.');
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to extend booking.', [], 500);
        }
        break;

    case 'update_booking_status':
        $bookingId = cleanInt($_POST['booking_id'] ?? $_POST['id'] ?? 0);
        $status    = $_POST['status'] ?? '';
        if (!in_array($status, ['approved','rejected','cancelled'])) jsonResponse(false, 'Invalid status.');
        try {
            $stmt = $db->prepare("UPDATE bookings b JOIN listings l ON l.id = b.listing_id
                SET b.booking_status = ? WHERE b.id = ? AND l.host_id = ?");
            $stmt->execute([$status, $bookingId, $user['id']]);
            jsonResponse($stmt->rowCount() ? true : false,
                         $stmt->rowCount() ? 'Booking updated.' : 'Not found or permission denied.');
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to update booking.', [], 500);
        }
        break;

    case 'toggle_availability':
        $listingId = cleanInt($_POST['id'] ?? $_POST['listing_id'] ?? 0);
        $available = cleanInt($_POST['available'] ?? 0);
        try {
            $stmt = $db->prepare("UPDATE listings SET available = ? WHERE id = ? AND host_id = ?");
            $stmt->execute([$available, $listingId, $user['id']]);
            jsonResponse($stmt->rowCount() ? true : false,
                         $stmt->rowCount() ? 'Availability updated.' : 'Not found or permission denied.');
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to update availability.', [], 500);
        }
        break;

    case 'delete_listing':
        $listingId = cleanInt($_POST['id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM listings WHERE id = ? AND host_id = ?");
            $stmt->execute([$listingId, $user['id']]);
            jsonResponse($stmt->rowCount() ? true : false,
                         $stmt->rowCount() ? 'Listing deleted.' : 'Not found or permission denied.');
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to delete listing.', [], 500);
        }
        break;

    case 'update_listing':
        $listingId = cleanInt($_POST['id'] ?? 0);
        $title     = clean($_POST['title']       ?? '');
        $type      = clean($_POST['type']        ?? '');
        $desc      = clean($_POST['description'] ?? '');
        $address   = clean($_POST['address']     ?? '');
        $city      = clean($_POST['city']        ?? '');
        $province  = clean($_POST['province']    ?? '');
        $bedrooms  = cleanInt($_POST['bedrooms']   ?? 1);
        $bathrooms = cleanInt($_POST['bathrooms']  ?? 1);
        $maxGuests = cleanInt($_POST['max_guests'] ?? 1);
        $price     = cleanFloat($_POST['price']    ?? 0);
        $cleaning  = cleanFloat($_POST['cleaning'] ?? 0);
        $deposit   = cleanFloat($_POST['deposit']  ?? 0);
        $available = cleanInt($_POST['available']  ?? 1);
        $amenities = $_POST['amenities'] ?? [];
        if (!$listingId || !$title || !$city || $price <= 0) jsonResponse(false, 'Title, city, and price are required.');
        try {
            $stmt = $db->prepare("UPDATE listings SET title=?, type=?, description=?, address=?, city=?, province=?, bedrooms=?, bathrooms=?, max_guests=?, price_per_night=?, cleaning_fee=?, security_deposit=?, amenities=?, available=? WHERE id=? AND host_id=?");
            $stmt->execute([$title, $type, $desc, $address, $city, $province, $bedrooms, $bathrooms, $maxGuests, $price, $cleaning, $deposit, json_encode(array_values($amenities)), $available, $listingId, $user['id']]);
            if ($stmt->rowCount()) {
                jsonResponse(true, 'Listing updated successfully.');
            } else {
                jsonResponse(false, 'No changes made or listing not found.');
            }
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to update listing: ' . $e->getMessage(), [], 500);
        }
        break;

    case 'create_listing':
        $title     = clean($_POST['title']       ?? '');
        $type      = clean($_POST['type']        ?? '');
        $desc      = clean($_POST['description'] ?? '');
        $address   = clean($_POST['address']     ?? '');
        $city      = clean($_POST['city']        ?? '');
        $province  = clean($_POST['province']    ?? '');
        $bedrooms  = cleanInt($_POST['bedrooms']   ?? 1);
        $bathrooms = cleanInt($_POST['bathrooms']  ?? 1);
        $maxGuests = cleanInt($_POST['max_guests'] ?? 1);
        $price     = cleanFloat($_POST['price']    ?? 0);
        $cleaning  = cleanFloat($_POST['cleaning'] ?? 0);
        $deposit   = cleanFloat($_POST['deposit']  ?? 0);
        $available = cleanInt($_POST['available']  ?? 1);
        $amenities = $_POST['amenities'] ?? [];
        if (!$title || !$city || $price <= 0) jsonResponse(false, 'Title, city, and price are required.');
        try {
            $stmt = $db->prepare("INSERT INTO listings
                (host_id, title, type, description, address, city, province,
                 bedrooms, bathrooms, max_guests, price_per_night,
                 cleaning_fee, security_deposit, amenities, available)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $title, $type, $desc, $address, $city, $province,
                $bedrooms, $bathrooms, $maxGuests, $price, $cleaning, $deposit,
                json_encode(array_values($amenities)), $available]);
            jsonResponse(true, 'Listing created!', ['listing_id' => $db->lastInsertId()]);
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to create listing. Please try again.', [], 500);
        }
        break;

    case 'submit_review':
        $listingId = cleanInt($_POST['listing_id'] ?? 0);
        $rating    = cleanInt($_POST['rating'] ?? 0);
        $comment   = clean($_POST['comment'] ?? '');
        if (!$listingId || $rating < 1 || $rating > 5) jsonResponse(false, 'Invalid review data.');
        try {
            $check = $db->prepare("SELECT id FROM bookings
                WHERE listing_id = ? AND user_id = ? AND booking_status = 'approved' LIMIT 1");
            $check->execute([$listingId, $user['id']]);
            if (!$check->fetch()) jsonResponse(false, 'You can only review listings you have stayed at.');
            $stmt = $db->prepare("INSERT INTO reviews (listing_id, user_id, rating, comment)
                VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)");
            $stmt->execute([$listingId, $user['id'], $rating, $comment]);
            $db->prepare("UPDATE listings SET
                rating = (SELECT AVG(rating) FROM reviews WHERE listing_id = ?),
                review_count = (SELECT COUNT(*) FROM reviews WHERE listing_id = ?)
                WHERE id = ?")->execute([$listingId, $listingId, $listingId]);
            jsonResponse(true, 'Review submitted. Thank you!');
        } catch (PDOException $e) {
            jsonResponse(false, 'Failed to submit review.', [], 500);
        }
        break;

    default:
        jsonResponse(false, 'Unknown action: ' . htmlspecialchars($action), [], 400);
}
