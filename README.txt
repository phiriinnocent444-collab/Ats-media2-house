ATS media house - Photography Booking Studio & Admin Portal (XAMPP Ready)
========================================================================

STACK
  HTML, CSS, JavaScript, PHP 8.0+ and MySQL / MariaDB (InnoDB).

QUICK START ON XAMPP (Windows / macOS / Linux)
  1. Copy this whole folder into your XAMPP web root:
       - Windows : C:\xampp\htdocs\ats-media-house\
       - macOS   : /Applications/XAMPP/htdocs/ats-media-house/
       - Linux   : /opt/lampp/htdocs/ats-media-house/
  2. Open the XAMPP Control Panel and Start "Apache" and "MySQL".
  3. Open your browser to:
       http://localhost/ats-media-house/setup.php
     Click "Initialize / Verify Database" (or visit http://localhost/ats-media-house/
     directly — the app auto-creates database "ats_media_house" on first run if MySQL
     uses default root / empty password).
     Alternatively, import database/schema.sql in http://localhost/phpmyadmin
     and optionally run:
       C:\xampp\php\php.exe C:\xampp\htdocs\ats-media-house\database\seed.php ats2026
  4. Visit the studio website:
       http://localhost/ats-media-house/

DEFAULT ADMIN CREDENTIALS
  Portal URL : http://localhost/ats-media-house/app/index.php?r=auth/login
  Username   : admin
  Password   : ats2026

XAMPP PHP.INI RECOMMENDATIONS
  - PDO MySQL is enabled by default in XAMPP.
  - Enable extension=gd in xampp\php\php.ini if you plan to upload and resize
    new gallery photos from the Admin Photos tab.
  - Enable extension=curl in xampp\php\php.ini when switching Airtel Money from
    UAT sandbox mode to live PROD API credentials in api/config.php.
  - Restart Apache after editing php.ini.

FOLDER LAYOUT
  index.php                 Public landing page + Contact Sheet + Airtel booking
  setup.php                 XAMPP environment check & 1-click database initializer
  .htaccess                 Apache DirectoryIndex & directory listing protection
  css/style.css             Studio stylesheet
  js/script.js              Client validation, package pick, Airtel Money polling
  js/app.js                 Price preview, char counter, table filter, inline delete confirm
  api/                      JSON REST endpoints (ACID transactions + UAT sandbox)
  app/                      PHP MVC booking & admin portal (?r=controller/action)
  database/schema.sql       6 InnoDB tables + default packages
  database/seed.php         CLI seeder for admin user, gallery photos, sample bookings
  uploads/                  Portfolio photos & thumbnails
  storage/                  Mail log (mail.log) protected by .htaccess
  cli/reminders.php         Scheduled reminders + stale payment cleanup
