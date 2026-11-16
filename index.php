<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/api/airtel.php';

$services = Service::active();

$descriptions = [
  'wedding'    => 'Full-day coverage of your ceremony and reception, edited highlight gallery.',
  'corporate'  => 'Headshots, office and event photography for your business or team.',
  'event'      => 'Parties, launches and celebrations, delivered within 48 hours.',
  'family'     => 'A relaxed studio or outdoor session for the whole family.',
  'graduation' => 'Shoot your graduation memories with us — gown, cap and all.',
];

$errors = [];
$formValues = [
  'fullName'  => $_COOKIE['ats_name'] ?? '',
  'phone'     => '',
  'email'     => '',
  'service'   => (string) ($_GET['service'] ?? ''),
  'eventDate' => '',
  'notes'     => '',
];

$paymentStatus = null; // ['type' => 'pending'|'success'|'error', 'message' => string]
$createdBooking = null;
$activeTxnId = null;
$paymentPaid = false;

function airtel_is_configured(): bool {
  global $CONFIG;
  $cid = (string) ($CONFIG['airtel']['client_id'] ?? '');
  $sec = (string) ($CONFIG['airtel']['client_secret'] ?? '');
  return $cid !== '' && $sec !== '' && $cid !== 'your_client_id_here' && $sec !== 'your_client_secret_here';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($formValues as $k => $_) {
    if (isset($_POST[$k])) $formValues[$k] = trim((string) $_POST[$k]);
  }
  if (!csrf_verify()) {
    http_response_code(400);
    $paymentStatus = [
      'type' => 'error',
      'message' => 'Your form security token expired or was invalid. A fresh token has been issued — please submit again.',
    ];
  } else {
    $action = (string) ($_POST['action'] ?? 'book');

  if ($action === 'confirm_pin') {
    $txnId = trim((string) ($_POST['transactionId'] ?? ''));
    $bookingId = trim((string) ($_POST['bookingId'] ?? ''));
    try {
      $finalStatus = with_tx(function (PDO $pdo) use ($txnId) {
        $st = $pdo->prepare('SELECT booking_id, status FROM payments WHERE transaction_id = ? FOR UPDATE');
        $st->execute([$txnId]);
        $pay = $st->fetch();
        if (!$pay) throw new HttpError('Unknown transaction id.', 404);
        if ($pay['status'] === 'PENDING') {
          if (airtel_is_configured()) {
            $r = airtel_check_status($txnId);
            $raw = (string) ($r['data']['transaction']['status'] ?? '');
            $mapped = ['TS' => 'SUCCESS', 'SUCCESS' => 'SUCCESS', 'TF' => 'FAILED', 'TE' => 'FAILED', 'FAILED' => 'FAILED'][$raw] ?? 'PENDING';
          } else {
            $mapped = 'SUCCESS';
          }
          $pdo->prepare('UPDATE payments SET status = ?, last_checked = NOW() WHERE transaction_id = ?')->execute([$mapped, $txnId]);
          if ($mapped === 'SUCCESS') {
            $pdo->prepare("UPDATE bookings SET status = 'paid' WHERE booking_id = ? AND status = 'pending_payment'")->execute([$pay['booking_id']]);
          }
          return $mapped;
        }
        return $pay['status'];
      });

      $createdBooking = find_booking($bookingId);
      if ($finalStatus === 'SUCCESS') {
        $paymentPaid = true;
        $paymentStatus = [
          'type'    => 'success',
          'message' => 'Payment confirmed — your booking is secured! Tap the WhatsApp button to send your booking details to 0772 300 053.',
        ];
      } elseif ($finalStatus === 'FAILED') {
        $paymentStatus = [
          'type'    => 'error',
          'message' => 'Payment was not completed. Please try again.',
        ];
      } else {
        $activeTxnId = $txnId;
        $paymentStatus = [
          'type'    => 'pending',
          'message' => 'Waiting for Airtel Money confirmation on your phone…',
        ];
      }
    } catch (Throwable $e) {
      $paymentStatus = ['type' => 'error', 'message' => $e->getMessage()];
    }
  } else {
    foreach ($formValues as $k => $_) {
      $formValues[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    [$errors, $clean] = Validator::booking($_POST);
    if (!$errors) {
      try {
        [$bookingId, $wasCreated] = create_booking(booking_input($_POST));
        app_set_cookie('ats_name', $clean['name'], time() + 30 * 86400);
        $createdBooking = find_booking($bookingId);
        $msisdn = normalize_msisdn($formValues['phone']);

        if ($createdBooking['status'] === 'paid') {
          $paymentPaid = true;
          $paymentStatus = [
            'type'    => 'success',
            'message' => 'Payment confirmed — your booking is secured!',
          ];
        } else {
          [$amount, $txnId] = with_tx(function (PDO $pdo) use ($bookingId, $msisdn) {
            $st = $pdo->prepare('SELECT amount_due, status FROM bookings WHERE booking_id = ? FOR UPDATE');
            $st->execute([$bookingId]);
            $b = $st->fetch();
            if (!$b) throw new HttpError('Unknown booking.', 404);
            $txn = new_id('TXN');
            $pdo->prepare("INSERT INTO payments (transaction_id, booking_id, msisdn, amount, status) VALUES (?,?,?,?, 'PENDING')")
                ->execute([$txn, $bookingId, $msisdn, $b['amount_due']]);
            return [(float) $b['amount_due'], $txn];
          });

          if (airtel_is_configured()) {
            try {
              $resp = airtel_request_payment($msisdn, $amount, $txnId);
              db()->prepare('UPDATE payments SET airtel_response = ? WHERE transaction_id = ?')->execute([json_encode($resp), $txnId]);
            } catch (Throwable $e) {
              db()->prepare("UPDATE payments SET status = 'FAILED', error = ? WHERE transaction_id = ?")->execute([$e->getMessage(), $txnId]);
              throw new HttpError('Could not reach Airtel Money. Check API credentials in api/config.php.', 502);
            }
          } else {
            db()->prepare('UPDATE payments SET airtel_response = ? WHERE transaction_id = ?')
              ->execute([json_encode(['mode' => 'uat_sandbox', 'msisdn' => $msisdn]), $txnId]);
          }

          $activeTxnId = $txnId;
          $paymentStatus = [
            'type'    => 'pending',
            'message' => "Booking {$createdBooking['id']} created for " . money($createdBooking['amount']) . ". Prompt sent to 0{$msisdn} — confirm Airtel Money PIN below.",
          ];
        }
      } catch (Throwable $e) {
        $paymentStatus = ['type' => 'error', 'message' => $e->getMessage()];
      }
    }
  }
  }
}

$waHref = '#';
if ($createdBooking) {
  $waText =
    'Hello ATS media house, ' . ($paymentPaid ? 'I have paid for' : 'I have made') . " a booking.\n" .
    'Reference: ' . $createdBooking['id'] . "\n" .
    'Name: ' . $createdBooking['fullName'] . "\n" .
    'Package: ' . $createdBooking['serviceLabel'] . ' (K' . number_format((float) $createdBooking['amount'], 0) . ")\n" .
    'Date: ' . $createdBooking['eventDate'];
  $waHref = 'https://wa.me/260772300053?text=' . rawurlencode($waText);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="ATS media house - Wedding, Corporate, Family and Event photography in Lusaka, Zambia. Book online and pay securely with Airtel Money.">
  <meta property="og:title" content="ATS media house | Lusaka, Zambia">
  <meta property="og:description" content="Wedding, Corporate, Family and Event photography in Lusaka, Zambia. Book online and pay securely with Airtel Money.">
  <meta property="og:type" content="website">
  <title>ATS media house | Lusaka, Zambia</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Work+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="site-header">
    <div class="frame">
      <a href="#top" class="brand">
        <span class="brand-mark" aria-hidden="true">◎</span>
        <span class="brand-text">ATS <em>media house</em></span>
      </a>
      <nav aria-label="Primary">
        <a href="#services">Services</a>
        <a href="app/index.php?r=page/gallery">Gallery</a>
        <a href="#about">About</a>
        <a href="app/index.php?r=page/book">Book</a>
        <a href="app/index.php?r=auth/login">Admin</a>
      </nav>
      <div class="header-actions">
        <a class="btn btn-ghost header-cta" href="https://wa.me/260772300053" target="_blank" rel="noopener">WhatsApp us</a>
        <details class="mobile-nav">
          <summary aria-label="Open navigation menu">Menu</summary>
          <div class="mobile-nav-drawer">
            <a href="#services">Services</a>
            <a href="app/index.php?r=page/gallery">Gallery</a>
            <a href="#about">About</a>
            <a href="app/index.php?r=page/book">Book</a>
            <a href="app/index.php?r=auth/login">Admin</a>
            <a class="btn btn-solid btn-block" href="https://wa.me/260772300053" target="_blank" rel="noopener">WhatsApp us</a>
          </div>
        </details>
      </div>
    </div>
  </header>

  <main id="top">
    <!-- HERO -->
    <section class="hero">
      <div class="frame">
        <div class="hero-grid">
          <div class="hero-copy">
            <p class="eyebrow">Photographer · Lusaka, Zambia · Always open</p>
            <h1>Your moments,<br>held in <em>frame</em>.</h1>
            <p class="lede">We invest noteworthy energy in Wedding Photography, Corporate, Family and Event pictures &mdash; the kind you&rsquo;ll still be looking at in twenty years.</p>
            <div class="hero-actions">
              <a href="#book" class="btn btn-solid">Book a session</a>
              <a href="#services" class="btn btn-outline">See packages</a>
            </div>
          </div>

          <div class="hero-plate">
            <div class="plate-sprockets" aria-hidden="true"></div>
            <div class="plate-frame">
              <img src="assets/images/hero_studio_frame.jpg" alt="Bride and groom photographed by ATS media house in Lusaka" class="plate-photo">
              <div class="plate-scrim" aria-hidden="true"></div>
              <div class="plate-caption">
                <div>
                  <span class="plate-label">ATS</span>
                  <span class="plate-sub">media house · Lusaka</span>
                </div>
                <span class="plate-archive">35mm Digital Archive</span>
              </div>
            </div>
            <div class="plate-sprockets" aria-hidden="true"></div>
          </div>
        </div>

        <ul class="hero-stats" aria-label="Studio stats">
          <li><strong>4.4K</strong><span>followers</span></li>
          <li><strong>259</strong><span>posts</span></li>
          <li><strong>Kwacha</strong><span>ZMW pricing</span></li>
          <li><strong>24/7</strong><span>always open</span></li>
        </ul>
      </div>
    </section>

    <div class="filmstrip" aria-hidden="true"></div>

    <!-- SERVICES / CONTACT SHEET -->
    <section id="services" class="section">
      <div class="frame">
        <p class="eyebrow">Contact sheet</p>
        <h2>Choose your frame</h2>
        <p class="section-lede">Every package includes edited digital delivery. Prices are in Zambian Kwacha (ZMW). Select a frame below to pre-fill your booking.</p>

        <div class="sheet-grid">
          <?php foreach ($services as $idx => $svc): ?>
            <?php $frameNo = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT); ?>
            <a href="?service=<?= urlencode($svc['code']) ?>#book" class="frame-card" data-package-code="<?= h($svc['code']) ?>">
              <div>
                <span class="frame-no"><?= h($frameNo) ?></span>
                <h3><?= h($svc['label']) ?></h3>
                <p><?= h($descriptions[$svc['code']] ?? ($svc['label'] . ' photography session with edited digital delivery.')) ?></p>
              </div>
              <p class="price">K<?= number_format((float) $svc['price'], 0) ?></p>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <div class="filmstrip" aria-hidden="true"></div>

    <!-- ABOUT -->
    <section id="about" class="section section-dark">
      <div class="frame about-grid">
        <div>
          <p class="eyebrow">About the studio</p>
          <h2>ATS media house</h2>
          <p class="section-lede">We invest noteworthy energy in Wedding Photography, Corporate, Family and Event pictures. Based in Lusaka, always open, and followed by a growing circle of Zambian couples, families and businesses.</p>
          <ul class="about-facts">
            <li><span>Category</span>Photographer</li>
            <li><span>Location</span>Lusaka, Zambia</li>
            <li><span>Hours</span>Always open</li>
            <li><span>Currency</span>Kwacha (ZMW)</li>
            <li><span>Instagram</span>@atsmedia79</li>
          </ul>
        </div>

        <blockquote class="latest-post">
          <p class="eyebrow">Latest pinned post &middot; Sep 24, 2022</p>
          <p>&ldquo;Shoot your Graduation Memories with us.&rdquo;</p>
        </blockquote>
      </div>
    </section>

    <div class="filmstrip" aria-hidden="true"></div>

    <!-- BOOKING + PAYMENT -->
    <section id="book" class="section">
      <div class="frame book-grid">
        <div class="book-copy">
          <p class="eyebrow">Reserve your date</p>
          <h2>Book &amp; pay with Airtel Money</h2>
          <p class="section-lede">Fill in your details, pick a package, then confirm payment straight from your phone &mdash; no cash, no queue.</p>
          <ol class="pay-steps">
            <li>Submit the booking form.</li>
            <li>We send a payment prompt to your Airtel Money line.</li>
            <li>Enter your Airtel Money PIN on your phone to confirm.</li>
            <li>Send your booking reference on WhatsApp to <strong>0772 300 053</strong>. All booking transactions are handled there.</li>
          </ol>
          <p class="portal-note">
            <a href="app/index.php?r=page/book">Open standalone sticky booking page</a> &middot;
            <a href="app/index.php?r=page/status">Check booking status</a>
          </p>
        </div>

        <div class="card">
          <form id="booking-form" method="post" action="index.php#book" class="form-stack" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="book">

            <div class="field">
              <label for="fullName">Full name</label>
              <input type="text" id="fullName" name="fullName" required value="<?= h($formValues['fullName']) ?>" placeholder="e.g. Chanda Mwansa">
              <p class="error" id="err-fullName"><?= h($errors['fullName'] ?? '') ?></p>
            </div>

            <div class="field">
              <label for="phone">Airtel Money number</label>
              <input type="tel" id="phone" name="phone" required value="<?= h($formValues['phone']) ?>" placeholder="e.g. 0972971151">
              <p class="error" id="err-phone"><?= h($errors['phone'] ?? '') ?></p>
            </div>

            <div class="field">
              <label for="email">Email <span class="optional">(optional)</span></label>
              <input type="email" id="email" name="email" value="<?= h($formValues['email']) ?>" placeholder="you@example.com">
              <p class="error" id="err-email"><?= h($errors['email'] ?? '') ?></p>
            </div>

            <div class="field">
              <label for="service">Package</label>
              <select id="service" name="service" required>
                <option value="">Choose a package&hellip;</option>
                <?php foreach ($services as $svc): ?>
                  <option value="<?= h($svc['code']) ?>" <?= $formValues['service'] === $svc['code'] ? 'selected' : '' ?>>
                    <?= h($svc['label']) ?> &mdash; K<?= number_format((float) $svc['price'], 0) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p class="error" id="err-service"><?= h($errors['service'] ?? '') ?></p>
            </div>

            <div class="field">
              <label for="eventDate">Preferred date</label>
              <input type="date" id="eventDate" name="eventDate" required min="<?= date('Y-m-d') ?>" value="<?= h($formValues['eventDate']) ?>">
              <p class="error" id="err-eventDate"><?= h($errors['eventDate'] ?? '') ?></p>
            </div>

            <div class="field">
              <label for="notes">Notes <span class="optional">(optional)</span></label>
              <textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Venue, number of guests, style references..."><?= h($formValues['notes']) ?></textarea>
              <p class="error" id="err-notes"><?= h($errors['notes'] ?? '') ?></p>
            </div>

            <button type="submit" id="submit-btn" class="btn btn-solid btn-block">Book &amp; pay with Airtel Money</button>
          </form>

          <div id="payment-status" class="payment-status<?= $paymentStatus ? ' show ' . h($paymentStatus['type']) : '' ?>" role="status" aria-live="polite">
            <?php if ($paymentStatus): ?>
              <p><?= h($paymentStatus['message']) ?></p>
              <?php if ($activeTxnId && $createdBooking): ?>
                <form method="post" action="index.php#book" class="pin-confirm-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="confirm_pin">
                  <input type="hidden" name="transactionId" value="<?= h($activeTxnId) ?>">
                  <input type="hidden" name="bookingId" value="<?= h($createdBooking['id']) ?>">
                  <button type="submit" class="btn btn-outline btn-sm">Confirm Airtel Money PIN</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <a id="wa-confirm" class="btn btn-outline btn-block" href="<?= h($waHref) ?>" target="_blank" rel="noopener"<?= $createdBooking ? '' : ' style="display:none"' ?>>Send booking details on WhatsApp</a>
        </div>
      </div>
    </section>

    <div class="filmstrip" aria-hidden="true"></div>

    <!-- CONTACT -->
    <footer id="contact" class="section section-dark footer">
      <div class="frame footer-grid">
        <div>
          <p class="brand-text footer-brand">ATS <em>media house</em></p>
          <p class="section-lede">Lusaka, Zambia &middot; Always open</p>
          <p class="footer-links">
            <a href="app/index.php?r=page/book">Book</a> &middot;
            <a href="app/index.php?r=page/status">Status</a> &middot;
            <a href="app/index.php?r=page/gallery">Gallery</a> &middot;
            <a href="app/index.php?r=auth/login">Admin</a>
          </p>
        </div>
        <address>
          <a href="tel:+260972971151">+260 972 971 151</a>
          <a href="https://wa.me/260772300053" target="_blank" rel="noopener">WhatsApp (bookings): +260 772 300 053</a>
          <a href="mailto:alinanits287@gmail.com">alinanits287@gmail.com</a>
          <a href="https://instagram.com/atsmedia79" target="_blank" rel="noopener">Instagram &commat;atsmedia79</a>
        </address>
        <p class="fine-print">&copy; 2026 ATS media house. Payments processed via Airtel Money.</p>
      </div>
    </footer>
  </main>
  <script src="js/script.js"></script>
</body>
</html>
