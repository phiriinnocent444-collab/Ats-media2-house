-- ATS media house : PHYSICAL SCHEMA (MySQL 8 / MariaDB 10.4+, XAMPP)
-- All tables use InnoDB so transactions (ACID), row locks and foreign keys work.
-- Upgrading from the old 2-table schema? Uncomment the 4 DROPs first (this deletes data).
CREATE DATABASE IF NOT EXISTS ats_media_house CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ats_media_house;
-- DROP TABLE IF EXISTS gallery; DROP TABLE IF EXISTS admin_users; DROP TABLE IF EXISTS payments; DROP TABLE IF EXISTS bookings;
-- DROP TABLE IF EXISTS services; DROP TABLE IF EXISTS customers;

CREATE TABLE IF NOT EXISTS customers (
  customer_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name   VARCHAR(120) NOT NULL,
  phone       CHAR(9)      NOT NULL,              -- normalised MSISDN, e.g. 972971151
  email       VARCHAR(160) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  UNIQUE KEY uq_customers_phone (phone),
  CONSTRAINT ck_customers_phone CHECK (phone REGEXP '^[0-9]{9}$')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS services (
  service_id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code       VARCHAR(20)   NOT NULL,
  label      VARCHAR(60)   NOT NULL,
  price      DECIMAL(10,2) NOT NULL,              -- ZMW
  is_active  TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (service_id),
  UNIQUE KEY uq_services_code (code),
  CONSTRAINT ck_services_price CHECK (price > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
  booking_id  VARCHAR(32)   NOT NULL,
  customer_id INT UNSIGNED  NOT NULL,
  service_id  TINYINT UNSIGNED NOT NULL,
  amount_due  DECIMAL(10,2) NOT NULL,             -- price snapshot at booking time
  event_date  DATE NOT NULL,
  notes       TEXT NULL,
  status      ENUM('pending_payment','paid','cancelled') NOT NULL DEFAULT 'pending_payment',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (booking_id),
  KEY ix_bookings_customer (customer_id),
  KEY ix_bookings_service_date (service_id, event_date),
  KEY ix_bookings_status (status),
  FULLTEXT KEY ft_bookings_notes (notes),          -- Unit 4.7 full-text search
  CONSTRAINT ck_bookings_amount CHECK (amount_due > 0),
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_service  FOREIGN KEY (service_id)  REFERENCES services(service_id)   ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  transaction_id  VARCHAR(32)   NOT NULL,
  booking_id      VARCHAR(32)   NOT NULL,
  msisdn          CHAR(9)       NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  status          ENUM('PENDING','SUCCESS','FAILED') NOT NULL DEFAULT 'PENDING',
  airtel_response TEXT NULL,
  error           TEXT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_checked    TIMESTAMP NULL,
  PRIMARY KEY (transaction_id),
  KEY ix_payments_booking_status (booking_id, status),
  CONSTRAINT ck_payments_amount CHECK (amount > 0),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Unit 6: password-protected admin area (passwords stored with password_hash(), never plain text)
CREATE TABLE IF NOT EXISTS admin_users (
  admin_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(40)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB;

-- Unit 6.3 / 6.4: uploaded portfolio images (file names only; files live in /uploads)
CREATE TABLE IF NOT EXISTS gallery (
  photo_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(120) NOT NULL,
  filename    VARCHAR(64)  NOT NULL,
  thumb       VARCHAR(64)  NOT NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (photo_id),
  CONSTRAINT fk_gallery_admin FOREIGN KEY (uploaded_by) REFERENCES admin_users(admin_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO services (code, label, price) VALUES
 ('wedding','Wedding Photography',3500), ('corporate','Corporate Shoot',1800),
 ('event','Event Coverage',1500), ('family','Family Portrait Session',900),
 ('graduation','Graduation Shoot',700);
