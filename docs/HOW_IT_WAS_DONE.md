# How the ATS media house program was built from the WEB TECHNOLOGY course outline

Course: BSc 209 Web Technology (PHP and MySQL). Every unit of the outline is applied in this project; open `http://localhost/ats-media-house/app/index.php?r=page/course` for the same table inside the running app.


## Unit 1

| Topic | Where | How |
|---|---|---|
| Hardware and software basics | `README.txt, lessons/hello.php` | Explains client → Apache → PHP → MySQL. hello.php prints the server software, PHP version and which extensions are loaded. |
| Setting up the production environment | `README.txt, api/health.php` | XAMPP (Apache + PHP + MySQL), php.ini curl extension, health.php proves PHP, MySQL and InnoDB work. |
| Hello, World in PHP | `lessons/hello.php` | echo '<h1>Hello, World!</h1>' inside a <?php ?> block. |

## Unit 2

| Topic | Where | How |
|---|---|---|
| Basic syntax, sending data to the browser, comments | `lessons/unit2_basics.php` | <?php tags, echo and the short echo tag, //, # and /* */ comments throughout. |
| Variables, strings, numbers, constants | `lessons/unit2_basics.php` | $package, $price, $total, const STUDIO; strlen, substr, str_replace, number_format. |
| Single vs double quotation marks | `lessons/unit2_basics.php` | The same sentence printed with both quote types shows variable parsing. |
| HTML form, conditionals/operators, validating form data | `lessons/unit2_basics.php, app/core/Validator.php` | if/elseif, ternary, ctype_digit, trim; Validator uses preg_match and filter_var. |
| Arrays and loops | `lessons/unit2_basics.php, app/views/admin/*.php` | Associative array of packages; for, while and foreach; every admin table is a foreach. |

## Unit 3

| Topic | Where | How |
|---|---|---|
| Multiple files | `app/bootstrap.php, app/views/layout.php` | require_once pulls in shared code; one layout wraps every page; spl_autoload_register loads classes. |
| Handling HTML forms | `app/controllers/PageController.php::book` | Reads $_POST, validates, saves, then redirects. |
| Sticky forms | `app/views/partials/booking_form.php` | Each field is refilled from $_POST (or cookie/defaults) so users never retype after an error. |
| Creating your own functions | `app/functions.php` | h(), money(), nice_date(), days_until(), url(), redirect(), flash(), csrf_*(), send_mail(). |

## Unit 4

| Topic | Where | How |
|---|---|---|
| Databases and design | `database/schema.sql, docs/ats_database_design.html` | Conceptual (Chen ER), logical (3NF) and physical design for customers, services, bookings, payments, admin_users, gallery. |
| MySQL data types | `database/schema.sql` | DECIMAL for money, ENUM for status, CHAR(9) for phone, DATE, TIMESTAMP, TEXT, TINYINT(1). |
| Basic SQL commands | `database/queries.sql, app/models/*.php` | SELECT/INSERT/UPDATE/DELETE with prepared statements. |
| Performing joins | `app/models/Booking.php (BOOKING_SQL, topCustomers)` | INNER JOIN bookings-customers-services; LEFT JOIN to keep packages with no bookings. |
| Group selected results | `app/models/Booking.php::byService / byMonth, admin/reports` | GROUP BY with COUNT, SUM, CASE and HAVING. |
| Creating indexes | `database/schema.sql, database/queries.sql` | PRIMARY, UNIQUE, composite (service_id,event_date) and status indexes; EXPLAIN and SHOW INDEX examples. |
| FULLTEXT searches | `schema.sql (ft_bookings_notes), Booking::all` | MATCH(notes) AGAINST (... IN BOOLEAN MODE) on the admin search box. |
| Performing transactions | `api/helpers.php::with_tx, api/booking_lib.php` | BEGIN/COMMIT/ROLLBACK, SELECT ... FOR UPDATE row locks (ACID). |

## Unit 5

| Topic | Where | How |
|---|---|---|
| PHP interface to MySQL, connecting | `api/helpers.php::db, lessons/mysqli_demo.php` | PDO is used by the app; mysqli_demo.php shows the classic mysqli way. |
| Queries | `app/models/*.php` | prepare() + execute() + fetch()/fetchAll() everywhere. |
| Updating records with PHP | `Booking::markPaid, Service::save, update_booking()` | UPDATE inside transactions; affected rows checked in the mysqli demo. |

## Unit 6

| Topic | Where | How |
|---|---|---|
| Sending simple e-mail | `app/functions.php::send_mail, PageController::book, cli/reminders.php` | mail() with headers; every message is also logged to storage/mail.log (XAMPP has no mail server). |
| Date and time functions | `app/bootstrap.php, app/functions.php` | date_default_timezone_set('Africa/Lusaka'), DateTime::diff for days_until(), date()/strtotime() for nice_date(), SQL DATE_FORMAT. |
| Handling file uploads | `AdminController::gallery_upload, app/core/ImageTool.php` | multipart form, $_FILES, error codes, size limit, finfo real MIME check, random file name. |
| HTTP headers and redirection | `functions.php::redirect, AdminController` | header('Location'), Post/Redirect/Get after every form, Cache-Control: no-store on admin pages. |
| Cookies | `PageController::book, AuthController::login` | setcookie() remembers the client's name and the admin's username for 30 days (HttpOnly, SameSite). |
| Session management and password-protected pages | `AuthController, AdminController::__construct` | session_start, password_hash/password_verify, session_regenerate_id, login throttling, logout. |
| Image management | `app/core/ImageTool.php, views/page/gallery.php` | Validate, resize to 1600px, make 400px thumbnails, delete files with their database row. |
| Data population techniques | `database/seed.php` | CLI script creating the admin user and 15 sample bookings inside one transaction. |
| Miscellaneous tricks | `bootstrap.php, functions.php` | Output buffering (ob_start), CSRF tokens, htmlspecialchars against XSS, .htaccess hardening of uploads/ and storage/. |

## Unit 8

| Topic | Where | How |
|---|---|---|
| MVC and PHP frameworks | `app/index.php, core/Router.php, controllers/, models/, views/` | Front controller + router (?r=controller/action); models talk to MySQL, controllers decide, views display. |
| Command line scripting and cron | `cli/reminders.php, database/seed.php` | PHP_SAPI check; cron / Task Scheduler line is in the file header. |
| GD and image processing | `app/core/ImageTool.php` | imagecreatefromjpeg/png/webp, imagescale, imagestring watermark, imagejpeg. |
| Regular expressions and form parsing | `Validator.php, Booking::booleanQuery, Router.php` | preg_match for names, phones and routes; preg_split to turn a search phrase into a FULLTEXT query. |
| Using HTTP in PHP | `app/core/HttpClient.php, lessons/http_demo.php, api/airtel.php` | cURL client; the Airtel Money collection API is called the same way. |
| Object oriented design | `app/core, app/models, app/controllers` | Classes, static and instance methods, inheritance (Controller → *Controller), autoloading. |
| Web frameworks, MVC and Zend Framework | `app/ (hand-built MVC)` | Zend Framework (now Laminas) is installed with Composer, which this build did not use. The hand-built router/controller/view mirrors the same ideas so the framework is easy to adopt later. |

## Unit 9

| Topic | Where | How |
|---|---|---|
| Cascading style sheets | `css/style.css, lessons/unit9_dom_js.html` | Selectors, box model, grid/flex layout, media queries, custom properties. |
| JavaScript, embedding in HTML, functions | `js/script.js, js/app.js, lessons/unit9_dom_js.html` | External scripts with <script src>, inline script, named functions, events. |
| The object model and write method | `lessons/unit9_dom_js.html` | document.write, document.getElementById, classList, textContent. |
| Interacting with HTML forms | `js/script.js, js/app.js` | Client-side validation, live price preview, character counter, table filter, confirm() before deletes. |

## Architecture

```
Browser ──► index.html + js/script.js ──► api/*.php (JSON, Airtel Money, CRUD, ACID)
        └─► app/index.php?r=controller/action ──► Router ─► Controller ─► Model ─► MySQL
                                                              └──────────► View (layout + page)
```
Both entry points share `api/booking_lib.php`, so a booking is created, edited and deleted by exactly the same transactional code.

## Build order (what was done, step by step)
1. Unit 4: designed the database (Chen ER → relations → InnoDB tables, indexes, FULLTEXT, constraints).
2. Unit 5 + 4.8: PDO connection and `with_tx()` so every multi-step change is atomic; shared logic in `api/booking_lib.php`.
3. Unit 3 + 8: front controller, router, base controller, models, views, functions file, autoloader.
4. Unit 2 + 8.4: server-side validation with regular expressions; sticky forms.
5. Unit 6: sessions + password_hash login, cookies, redirects (PRG), e-mail, uploads, GD thumbnails, seed script.
6. Unit 8.2: CLI reminders for cron / Task Scheduler.
7. Unit 9: CSS for the app pages and DOM/JavaScript helpers.
8. Tested end to end against MariaDB (see TESTING in README.txt).
