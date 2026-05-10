-- ================================================================
--  rustys_rest_db.sql — Rusty's Rest and Lodging
--  Railway MySQL compatible schema
--
--  HOW TO IMPORT ON RAILWAY:
--  1. Go to your MySQL service on Railway dashboard
--  2. Click the "Query" tab
--  3. Paste this entire file and click Run
--
--  OR connect via a GUI client (TablePlus / DBeaver / MySQL Workbench):
--    Host, Port, User, Password, Database → copy from Railway
--    Variables tab of your MySQL service
-- ================================================================

CREATE DATABASE IF NOT EXISTS rustys_rest_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rustys_rest_db;

-- ================================================================
--  TABLE: users
-- ================================================================
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  first_name  VARCHAR(80)      NOT NULL,
  last_name   VARCHAR(80)      NOT NULL,
  email       VARCHAR(180)     NOT NULL UNIQUE,
  password    VARCHAR(255)     NOT NULL,
  phone       VARCHAR(30)      DEFAULT NULL,
  bio         TEXT             DEFAULT NULL,
  role        ENUM('guest','host','both','admin') NOT NULL DEFAULT 'guest',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
--  TABLE: listings
-- ================================================================
CREATE TABLE IF NOT EXISTS listings (
  id                INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  host_id           INT UNSIGNED   NOT NULL,
  title             VARCHAR(200)   NOT NULL,
  type              VARCHAR(50)    NOT NULL,
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
  amenities         JSON           DEFAULT NULL,
  cover_photo       VARCHAR(255)   DEFAULT NULL,
  rating            DECIMAL(3,2)   NOT NULL DEFAULT 0.00,
  review_count      INT UNSIGNED   NOT NULL DEFAULT 0,
  available         TINYINT(1)     NOT NULL DEFAULT 1,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_listing_host FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
--  TABLE: listing_photos
-- ================================================================
CREATE TABLE IF NOT EXISTS listing_photos (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT UNSIGNED  NOT NULL,
  file_name   VARCHAR(255)  NOT NULL,
  file_path   VARCHAR(500)  NOT NULL,
  alt_text    VARCHAR(200)  DEFAULT NULL,
  is_cover    TINYINT(1)    NOT NULL DEFAULT 0,
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
--  TABLE: bookings
--  IMPORTANT: column is named "booking_status" (not "status")
--  This matches api.php exactly.
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
  booking_status   ENUM('pending','approved','rejected','cancelled','completed')
                   NOT NULL DEFAULT 'pending',
  payment_method   VARCHAR(50)    DEFAULT NULL,
  special_requests TEXT           DEFAULT NULL,
  host_note        TEXT           DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_booking_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
--  TABLE: reviews
-- ================================================================
CREATE TABLE IF NOT EXISTS reviews (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  listing_id  INT UNSIGNED  NOT NULL,
  user_id     INT UNSIGNED  NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  comment     TEXT          DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  CONSTRAINT fk_review_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
--  STORED PROCEDURES
-- ================================================================
DROP PROCEDURE IF EXISTS approve_booking;
DROP PROCEDURE IF EXISTS reject_booking;
DROP PROCEDURE IF EXISTS toggle_availability;

DELIMITER $$

CREATE PROCEDURE approve_booking(
  IN p_booking_id INT UNSIGNED,
  IN p_host_note  TEXT
)
BEGIN
  UPDATE bookings
     SET booking_status = 'approved', host_note = p_host_note
   WHERE id = p_booking_id AND booking_status = 'pending';
END$$

CREATE PROCEDURE reject_booking(
  IN p_booking_id INT UNSIGNED,
  IN p_host_note  TEXT
)
BEGIN
  UPDATE bookings
     SET booking_status = 'rejected', host_note = p_host_note
   WHERE id = p_booking_id AND booking_status = 'pending';
END$$

CREATE PROCEDURE toggle_availability(
  IN p_listing_id INT UNSIGNED,
  IN p_available  TINYINT
)
BEGIN
  UPDATE listings SET available = p_available WHERE id = p_listing_id;
END$$

DELIMITER ;

-- ================================================================
--  VIEWS
-- ================================================================
CREATE OR REPLACE VIEW vw_listings_with_cover AS
SELECT
  l.*,
  CONCAT(u.first_name,' ',u.last_name) AS host_name,
  COALESCE(p.file_path,'')             AS cover_photo_path,
  p.alt_text                           AS cover_alt
FROM listings l
JOIN  users u ON u.id = l.host_id
LEFT JOIN listing_photos p ON p.listing_id = l.id AND p.is_cover = 1;

CREATE OR REPLACE VIEW vw_pending_requests AS
SELECT
  b.id AS booking_id, b.listing_id, b.user_id AS guest_id,
  b.check_in, b.check_out, b.guests, b.nights, b.total_price,
  b.booking_status, b.special_requests, b.created_at AS requested_at,
  l.title AS room_title, l.host_id,
  CONCAT(u.first_name,' ',u.last_name) AS guest_name,
  u.email AS guest_email
FROM bookings b
JOIN listings l ON l.id = b.listing_id
JOIN users    u ON u.id = b.user_id
WHERE b.booking_status = 'pending';

-- ================================================================
--  SEED DATA  (all passwords = 'password123')
-- ================================================================
INSERT INTO users (first_name, last_name, email, password, role) VALUES
  ('Rusty','Reyes',    'admin@rustysrest.com','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin'),
  ('Juan', 'dela Cruz','juan@example.com',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','both'),
  ('Maria','Santos',   'maria@example.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','host'),
  ('Pedro','Reyes',    'pedro@example.com',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','guest');

INSERT INTO listings
  (host_id,title,type,description,address,city,province,
   bedrooms,bathrooms,max_guests,price_per_night,cleaning_fee,
   security_deposit,amenities,rating,review_count,available)
VALUES
  (3,'Beachfront Suite','Suite',
   'Stunning ocean views with private balcony and beach access.',
   '1 Shoreline Dr','Panglao','Bohol',
   2,1,4,3500,400,1500,'["WiFi","Air Con","Beach Access","TV","Kitchen"]',4.90,87,1),
  (3,'Mountain View Cottage','Cottage',
   'Cool highland retreat surrounded by pine trees.',
   '22 Pine Rd','Malaybalay','Bukidnon',
   2,1,4,2200,300,1000,'["WiFi","Parking","Kitchen","TV"]',4.80,54,1),
  (3,'City Center Room','Room',
   'Cozy modern room steps from malls and restaurants.',
   '5 Divisoria St','Cagayan de Oro','Misamis Oriental',
   1,1,2,1200,150,500,'["WiFi","Air Con","TV"]',4.70,132,1),
  (2,'Family Villa','Villa',
   'Spacious villa with lush garden for large families.',
   '88 Orchard Ln','Opol','Misamis Oriental',
   4,2,8,4500,600,2000,'["WiFi","Pool","Parking","Kitchen","Air Con"]',4.60,41,1),
  (2,'Luxury Penthouse Suite','Suite',
   'Panoramic views, infinity pool and premium service.',
   '10 Skyview Ave','Initao','Misamis Oriental',
   3,3,6,7500,800,3000,'["WiFi","Pool","Parking","Air Con","Kitchen","TV","Gym"]',5.00,29,1),
  (3,'Garden Dormitory','Dormitory',
   'Budget dorm with garden view for backpackers.',
   '7 Bloom St','Mambajao','Camiguin',
   1,1,6,600,100,300,'["WiFi","Parking","Fan"]',4.50,68,0);

INSERT INTO bookings
  (listing_id,user_id,check_in,check_out,guests,nights,
   price_per_night,cleaning_fee,total_price,booking_status,payment_method)
VALUES
  (1,2,'2026-06-10','2026-06-15',3,5,3500,400,17900,'approved','GCash'),
  (3,2,'2026-07-01','2026-07-03',2,2,1200,150, 2550,'pending', 'Card'),
  (2,2,'2026-08-20','2026-08-25',4,5,2200,300,11300,'approved','GCash'),
  (4,4,'2026-06-20','2026-06-22',5,2,4500,600, 9600,'approved','Card'),
  (5,4,'2026-09-01','2026-09-05',6,4,7500,800,30800,'pending', 'GCash');

INSERT INTO reviews (listing_id,user_id,rating,comment) VALUES
  (1,2,5,'Absolutely stunning! The beach view was breathtaking.'),
  (2,2,5,'Perfect highland escape. Cool breeze and cozy. Highly recommend!'),
  (3,4,4,'Great location. Clean and modern. Would book again.');
