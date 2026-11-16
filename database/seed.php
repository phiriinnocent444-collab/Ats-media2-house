<?php
// Unit 6.5 Data population techniques.
// CLI usage: php database/seed.php [admin-password]
// Creates the admin login (password hashed), sample customers/bookings, and initial portfolio gallery rows.

if (PHP_SAPI !== 'cli') exit('CLI only.');

require __DIR__ . '/../app/bootstrap.php';

$password = $argv[1] ?? 'ats2026';
$pdo = db();

$pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)
               ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')
    ->execute(['admin', password_hash($password, PASSWORD_DEFAULT)]);
echo "Admin login  ->  username: admin   password: $password\n";

// Seed gallery photos if empty
if ((int) $pdo->query('SELECT COUNT(*) FROM gallery')->fetchColumn() === 0) {
  $adminId = (int) $pdo->query("SELECT admin_id FROM admin_users WHERE username = 'admin'")->fetchColumn();
  $photos = [
    ['Kabulonga Evening Reception', 'wedding_reception.jpg'],
    ['Lusaka Executive Portrait', 'corporate_portrait.jpg'],
    ['Annual Gala & Banquet', 'annual_gala.jpg'],
    ['UNZA Graduation Memories', 'unza_graduation.jpg'],
  ];
  $st = $pdo->prepare('INSERT INTO gallery (title, filename, thumb, uploaded_by) VALUES (?,?,?,?)');
  foreach ($photos as [$title, $file]) {
    if (is_file(__DIR__ . '/../uploads/gallery/' . $file)) {
      $st->execute([$title, $file, $file, $adminId ?: null]);
    }
  }
  echo "Gallery seeded.\n";
}

if ((int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn() > 0) {
  echo "Bookings already exist; sample bookings skipped.\n";
  exit;
}

$first = ['Chanda', 'Mwila', 'Bwalya', 'Natasha', 'Kondwani', 'Thandiwe', 'Mutale', 'Precious', 'Joseph', 'Esther'];
$last  = ['Mwansa', 'Phiri', 'Banda', 'Tembo', 'Zulu', 'Lungu', 'Mumba', 'Chisenga'];
$notes = [
  'Venue is Mulungushi Conference Centre',
  'Outdoor family shoot at Munda Wanga',
  'Need 200 edited photos',
  'Graduation at UNZA',
  null,
  'Corporate headshots for 12 staff',
  'Wedding venue near Kabulonga',
];
$codes = array_column(Service::active(), 'code');

with_tx(function () use ($first, $last, $notes, $codes) {
  mt_srand(42);
  for ($i = 0; $i < 15; $i++) {
    $f = [
      'fullName'  => $first[array_rand($first)] . ' ' . $last[array_rand($last)],
      'phone'     => sprintf('097%07d', mt_rand(0, 9999999)),
      'email'     => $i % 3 === 0 ? 'client' . $i . '@example.com' : '',
      'service'   => $codes[array_rand($codes)],
      'eventDate' => date('Y-m-d', strtotime('+' . mt_rand(1, 120) . ' days')),
      'notes'     => $notes[array_rand($notes)] ?? '',
    ];
    [$id] = create_booking(booking_input($f));
    if ($i % 2 === 0) Booking::markPaid($id);
  }
});

echo "Sample bookings inserted.\n";
