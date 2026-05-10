-- ================================================================
--  rustys_rest_db.sql — Rusty's Rest and Lodging
--  Full Database Schema with Listing Photos & Availability
--
--  Import via phpMyAdmin (XAMPP) or run:
--    mysql -u root < rustys_rest_db.sql
--
--  Demo password for ALL seed users: password123
-- ================================================================

CREATE DATABASE IF NOT EXISTS rustys_rest_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rustys_rest_db;

-- ================================================================
--  TABLE: users
--  Stores all registered users (guests, hosts, admins)
-- ================================================================
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  first_name  VARCHAR(80)      NOT NULL,
  last_name   VARCHAR(80)      NOT NULL,
  email       VARCHAR(180)     NOT NULL UNIQUE,
  password    VARCHAR(255)     NOT NULL,                       -- bcrypt hash
  phone       VARCHAR(30)      DEFAULT NULL,
  bio         TEXT             DEFAULT NULL,
  role        ENUM('guest','host','both','admin')
              NOT NULL DEFAULT 'guest',
  created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
--  TABLE: listings
--  Stores every room/property listed by a host.
--  available = 1 → open for booking
--  available = 0 → host has marked it unavailable
-- ================================================================
CREATE TABLE IF NOT EXISTS listings (
  id                INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  host_id           INT UNSIGNED   NOT NULL,
  title             VARCHAR(200)   NOT NULL,
  type              VARCHAR(50)    NOT NULL
                    COMMENT 'Room, Suite, Cottage, Villa, Dormitory…',
  description       TEXT           DEFAULT NULL,
  address           VARCHAR(255)   DEFAULT NULL,
  city              VARCHAR(100)   NOT NULL,
  province          VARCHAR(100)   DEFAULT NULL,
  bedrooms          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  bathrooms         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  max_guests        TINYINT UNSIGNED NOT NULL DEFAULT 2,
  price_per_night   DECIMAL(10,2)  NOT NULL,
  cleaning_fee      DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  security_deposit  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  amenities         JSON           DEFAULT NULL
                    COMMENT 'e.g. ["WiFi","Pool","Air Con"]',
  cover_photo       VARCHAR(255)   DEFAULT NULL
                    COMMENT 'Path to cover image (from listing_photos)',
  rating            DECIMAL(3,2)   NOT NULL DEFAULT 0.00,
  review_count      INT UNSIGNED   NOT NULL DEFAULT 0,

  -- ── AVAILABILITY ──────────────────────────────────────────────
  -- 1 = Available  |  0 = Not Available (hidden from booking)
  available         TINYINT(1)     NOT NULL DEFAULT 1,

  created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_listing_host
    FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for fast availability + host queries
CREATE INDEX idx_listing_available ON listings (available);
CREATE INDEX idx_listing_host      ON listings (host_id);
CREATE INDEX idx_listing_city      ON listings (city);

-- ================================================================
--  TABLE: listing_photos
--  Stores uploaded images for each listing.
--  is_cover = 1  → this is the card thumbnail shown in listings.
--  sort_order     → controls display order in gallery.
-- ================================================================
CREATE TABLE IF NOT EXISTS listing_photos (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT UNSIGNED  NOT NULL,
  file_name   VARCHAR(255)  NOT NULL
              COMMENT 'Stored filename on disk, e.g. 1_abc123.jpg',
  file_path   VARCHAR(500)  NOT NULL
              COMMENT 'Relative path, e.g. uploads/listings/1_abc123.jpg',
  alt_text    VARCHAR(200)  DEFAULT NULL
              COMMENT 'Accessible description of the image',
  is_cover    TINYINT(1)    NOT NULL DEFAULT 0
              COMMENT '1 = thumbnail used on listing cards',
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_photo_listing
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_photo_listing ON listing_photos (listing_id);
CREATE INDEX idx_photo_cover   ON listing_photos (listing_id, is_cover);

-- ================================================================
--  TABLE: bookings
--  Transactional record of every reservation.
-- ================================================================
CREATE TABLE IF NOT EXISTS bookings (
  id               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  listing_id       INT UNSIGNED   NOT NULL,
  user_id          INT UNSIGNED   NOT NULL,
  check_in         DATE           NOT NULL,
  check_out        DATE           NOT NULL,
  guests           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  nights           SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  price_per_night  DECIMAL(10,2)  NOT NULL,
  cleaning_fee     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  total_price      DECIMAL(10,2)  NOT NULL,
  status           ENUM('pending','confirmed','cancelled','completed')
                   NOT NULL DEFAULT 'pending',
  payment_method   VARCHAR(50)    DEFAULT NULL,
  special_requests TEXT           DEFAULT NULL,
  created_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_booking_listing
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_booking_user    ON bookings (user_id);
CREATE INDEX idx_booking_listing ON bookings (listing_id);

-- ================================================================
--  TABLE: reviews
--  Guest reviews tied to a listing (not per booking to keep simple).
-- ================================================================
CREATE TABLE IF NOT EXISTS reviews (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT UNSIGNED  NOT NULL,
  user_id     INT UNSIGNED  NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL
              COMMENT '1–5 stars',
  comment     TEXT          DEFAULT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_review_listing
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
--  STORED PROCEDURE: toggle_availability
--  Lets a host flip a listing available/unavailable in one call.
--
--  Usage (phpMyAdmin or code):
--    CALL toggle_availability(3, 1);   -- set listing 3 available
--    CALL toggle_availability(3, 0);   -- set listing 3 unavailable
-- ================================================================
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS toggle_availability(
    IN p_listing_id INT UNSIGNED,
    IN p_available  TINYINT
)
BEGIN
    UPDATE listings
       SET available = p_available
     WHERE id = p_listing_id;
END$$
DELIMITER ;

-- ================================================================
--  VIEW: vw_listings_with_cover
--  Joins listings with their cover photo in one query.
--  Used by api.php for the listings feed.
-- ================================================================
CREATE OR REPLACE VIEW vw_listings_with_cover AS
SELECT
    l.*,
    CONCAT(u.first_name, ' ', u.last_name) AS host_name,
    COALESCE(p.file_path, '')              AS cover_photo_path,
    p.alt_text                             AS cover_alt
FROM listings l
JOIN users u ON u.id = l.host_id
LEFT JOIN listing_photos p
       ON p.listing_id = l.id AND p.is_cover = 1;

-- ================================================================
--  SEED DATA
--  All passwords = 'password123'
--  Hash generated by: password_hash('password123', PASSWORD_DEFAULT)
-- ================================================================

-- Users ──────────────────────────────────────────────────────────
INSERT INTO users (first_name, last_name, email, password, role) VALUES
  ('Rusty',  'Reyes',     'admin@rustysrest.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
  ('Juan',   'dela Cruz', 'juan@example.com',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'both'),
  ('Maria',  'Santos',    'maria@example.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'host'),
  ('Pedro',  'Reyes',     'pedro@example.com',    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guest');

-- Listings ───────────────────────────────────────────────────────
INSERT INTO listings
  (host_id, title, type, description, address, city, province,
   bedrooms, bathrooms, max_guests,
   price_per_night, cleaning_fee, security_deposit,
   amenities, rating, review_count, available)
VALUES
  (3, 'Beachfront Suite', 'Suite',
   'Stunning ocean views with private balcony and direct beach access.',
   '1 Shoreline Dr', 'Panglao', 'Bohol',
   2, 1, 4, 3500.00, 400.00, 1500.00,
   '["WiFi","Air Con","Beach Access","TV","Kitchen"]', 4.90, 87, 1),

  (3, 'Mountain View Cottage', 'Cottage',
   'Cool highland retreat surrounded by pine trees and fresh air.',
   '22 Pine Rd', 'Malaybalay', 'Bukidnon',
   2, 1, 4, 2200.00, 300.00, 1000.00,
   '["WiFi","Parking","Kitchen","TV"]', 4.80, 54, 1),

  (3, 'City Center Room', 'Room',
   'Cozy and modern room steps away from malls and restaurants.',
   '5 Divisoria St', 'Cagayan de Oro', 'Misamis Oriental',
   1, 1, 2, 1200.00, 150.00, 500.00,
   '["WiFi","Air Con","TV"]', 4.70, 132, 1),

  (2, 'Family Villa', 'Villa',
   'Spacious villa with lush garden, ideal for large family gatherings.',
   '88 Orchard Ln', 'Opol', 'Misamis Oriental',
   4, 2, 8, 4500.00, 600.00, 2000.00,
   '["WiFi","Pool","Parking","Kitchen","Air Con"]', 4.60, 41, 1),

  (2, 'Luxury Penthouse Suite', 'Suite',
   'Panoramic views, infinity pool, and premium butler service.',
   '10 Skyview Ave', 'Initao', 'Misamis Oriental',
   3, 3, 6, 7500.00, 800.00, 3000.00,
   '["WiFi","Pool","Parking","Air Con","Kitchen","TV","Gym"]', 5.00, 29, 1),

  (3, 'Garden Dormitory', 'Dormitory',
   'Budget-friendly dorm with garden view — perfect for backpackers.',
   '7 Bloom St', 'Mambajao', 'Camiguin',
   1, 1, 6, 600.00, 100.00, 300.00,
   '["WiFi","Parking","Fan"]', 4.50, 68, 0);
   -- ^ available=0 : NOT AVAILABLE — demonstrates availability badge

-- listing_photos — cover images for each listing ─────────────────
-- In production these would be real uploaded files.
-- For demo we use placeholder paths; swap with real uploads.
INSERT INTO listing_photos (listing_id, file_name, file_path, alt_text, is_cover, sort_order) VALUES
  (1, 'beach_cover.jpg',   'uploads/listings/beach_cover.jpg',   'Beachfront Suite exterior',       1, 1),
  (1, 'beach_room.jpg',    'uploads/listings/beach_room.jpg',    'Beachfront Suite bedroom',        0, 2),
  (2, 'mountain_cover.jpg','uploads/listings/mountain_cover.jpg','Mountain View Cottage front',     1, 1),
  (3, 'city_cover.jpg',    'uploads/listings/city_cover.jpg',    'City Center Room interior',       1, 1),
  (4, 'villa_cover.jpg',   'uploads/listings/villa_cover.jpg',   'Family Villa garden side',        1, 1),
  (5, 'penthouse_cover.jpg','uploads/listings/penthouse_cover.jpg','Luxury Penthouse Suite pool',   1, 1),
  (6, 'dorm_cover.jpg',    'uploads/listings/dorm_cover.jpg',    'Garden Dormitory bunks',          1, 1);

-- Bookings ───────────────────────────────────────────────────────
INSERT INTO bookings
  (listing_id, user_id, check_in, check_out, guests, nights,
   price_per_night, cleaning_fee, total_price, status, payment_method)
VALUES
  (1, 2, '2026-05-10', '2026-05-15', 3, 5, 3500.00, 400.00, 17900.00, 'confirmed', 'GCash'),
  (3, 2, '2026-06-01', '2026-06-03', 2, 2, 1200.00, 150.00,  2550.00, 'pending',   'Card'),
  (2, 2, '2026-07-20', '2026-07-25', 4, 5, 2200.00, 300.00, 11300.00, 'confirmed', 'GCash'),
  (4, 4, '2026-05-20', '2026-05-22', 5, 2, 4500.00, 600.00,  9600.00, 'confirmed', 'Card'),
  (5, 4, '2026-08-01', '2026-08-05', 6, 4, 7500.00, 800.00, 30800.00, 'pending',   'GCash');

-- Reviews ────────────────────────────────────────────────────────
INSERT INTO reviews (listing_id, user_id, rating, comment) VALUES
  (1, 2, 5, 'Absolutely stunning! The beach view from the balcony was breathtaking.'),
  (2, 2, 5, 'Perfect highland escape. Cool breeze, cozy cottage — highly recommend!'),
  (3, 4, 4, 'Great location. Clean and modern room. Would book again.');