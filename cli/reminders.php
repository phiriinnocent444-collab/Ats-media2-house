<?php
// Unit 8.2 Command-line scripting + cron.  Run:   php cli/reminders.php
//   Linux cron (every day 08:00):   0 8 * * * /usr/bin/php /var/www/ats-media-house/cli/reminders.php
//   Windows Task Scheduler:         C:\xampp\php\php.exe C:\xampp\htdocs\ats-media-house\cli\reminders.php
// 1) e-mails clients whose paid shoot is TOMORROW   2) closes Airtel prompts left PENDING for > 15 minutes
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require __DIR__ . '/../app/bootstrap.php';

$st = db()->prepare(BOOKING_SQL . " WHERE b.status = 'paid' AND b.event_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
$st->execute();
$sent = 0;
foreach ($st->fetchAll() as $b) {
  if (!$b['email']) continue;
  send_mail($b['email'], 'Reminder: your ATS media house shoot is tomorrow',
    "Hello {$b['fullName']},\n\nThis is a reminder of your {$b['serviceLabel']} on " . nice_date($b['eventDate']) . ".\nBooking: {$b['id']}\n\nATS media house");
  $sent++;
}
$expired = with_tx(function (PDO $pdo) {
  $s = $pdo->prepare("UPDATE payments SET status = 'FAILED', error = 'Expired: no PIN confirmation within 15 minutes'
                      WHERE status = 'PENDING' AND created_at < NOW() - INTERVAL 15 MINUTE");
  $s->execute();
  return $s->rowCount();
});
echo date('c') . " reminders sent: $sent, stale payments closed: $expired\n";
