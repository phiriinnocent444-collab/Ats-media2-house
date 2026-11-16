-- ATS media house : SQL practice file (Unit 4). Run in phpMyAdmin > SQL tab.
USE ats_media_house;

-- 4.3 Basic SQL commands (SELECT / INSERT / UPDATE / DELETE)
SELECT code, label, price FROM services WHERE is_active = 1 ORDER BY price DESC;
-- INSERT INTO services (code,label,price) VALUES ('studio','Studio Session',600);
-- UPDATE services SET price = 750 WHERE code = 'graduation';
-- DELETE FROM services WHERE code = 'studio';

-- 4.4 Joins: bookings + customer + package in one result
SELECT b.booking_id, c.full_name, s.label, b.amount_due, b.event_date, b.status
FROM bookings b
JOIN customers c ON c.customer_id = b.customer_id
JOIN services  s ON s.service_id  = b.service_id
ORDER BY b.event_date;

-- LEFT JOIN keeps packages that have no bookings yet
SELECT s.label, COUNT(b.booking_id) AS bookings
FROM services s LEFT JOIN bookings b ON b.service_id = s.service_id
GROUP BY s.service_id, s.label;

-- 4.5 Group selected results (+ HAVING)
SELECT DATE_FORMAT(event_date,'%Y-%m') AS month, COUNT(*) AS bookings, SUM(amount_due) AS value
FROM bookings WHERE status <> 'cancelled'
GROUP BY month HAVING COUNT(*) >= 1 ORDER BY month;

-- 4.6 Indexes: see which index a query uses
EXPLAIN SELECT * FROM bookings WHERE service_id = 1 AND event_date >= CURDATE();
SHOW INDEX FROM bookings;

-- 4.7 FULLTEXT search over booking notes
SELECT booking_id, notes FROM bookings WHERE MATCH(notes) AGAINST ('+venue*' IN BOOLEAN MODE);

-- 4.8 Transactions: both statements succeed together or not at all
START TRANSACTION;
  UPDATE bookings SET status = 'cancelled' WHERE booking_id = 'BK_EXAMPLE' AND status = 'pending_payment';
  -- SELECT ... FOR UPDATE would lock the row first (see api/booking_lib.php)
ROLLBACK;   -- change to COMMIT to keep the change
