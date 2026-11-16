<?php $flash = flash(); $admin = $_SESSION['admin'] ?? null; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> | ATS media house</title>
  <meta name="description" content="ATS media house — Wedding, Corporate, Family and Event photography in Lusaka, Zambia.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=Work+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body class="app">
  <header class="site-header">
    <div class="frame">
      <a class="brand" href="../index.php">
        <span class="brand-mark" aria-hidden="true">◎</span>
        <span class="brand-text">ATS <em>media house</em></span>
      </a>
      <nav aria-label="Primary">
        <a href="../index.php">Home</a>
        <a href="<?= url('page/book') ?>">Book</a>
        <a href="<?= url('page/status') ?>">Status</a>
        <a href="<?= url('page/gallery') ?>">Gallery</a>
        <?php if ($admin): ?>
          <a href="<?= url('admin/dashboard') ?>">Dashboard</a>
          <a href="<?= url('admin/bookings') ?>">Bookings</a>
          <a href="<?= url('admin/services') ?>">Services</a>
          <a href="<?= url('admin/transactions') ?>">Payments</a>
          <a href="<?= url('admin/gallery') ?>">Photos</a>
          <a href="<?= url('admin/reports') ?>">Reports</a>
        <?php else: ?>
          <a href="<?= url('auth/login') ?>">Admin</a>
        <?php endif; ?>
      </nav>
      <div class="header-actions">
        <?php if ($admin): ?>
          <form method="post" action="<?= url('auth/logout') ?>" class="inline header-cta">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" type="submit">Log out (<?= h($admin['name']) ?>)</button>
          </form>
        <?php else: ?>
          <a class="btn btn-ghost header-cta" href="https://wa.me/260772300053" target="_blank" rel="noopener">WhatsApp us</a>
        <?php endif; ?>
        <details class="mobile-nav">
          <summary aria-label="Open navigation menu">Menu</summary>
          <div class="mobile-nav-drawer">
            <a href="../index.php">Home</a>
            <a href="<?= url('page/book') ?>">Book</a>
            <a href="<?= url('page/status') ?>">Status</a>
            <a href="<?= url('page/gallery') ?>">Gallery</a>
            <?php if ($admin): ?>
              <a href="<?= url('admin/dashboard') ?>">Dashboard</a>
              <a href="<?= url('admin/bookings') ?>">Bookings</a>
              <a href="<?= url('admin/services') ?>">Services</a>
              <a href="<?= url('admin/transactions') ?>">Payments</a>
              <a href="<?= url('admin/gallery') ?>">Photos</a>
              <a href="<?= url('admin/reports') ?>">Reports</a>
              <form method="post" action="<?= url('auth/logout') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline btn-block btn-sm" type="submit">Log out (<?= h($admin['name']) ?>)</button>
              </form>
            <?php else: ?>
              <a href="<?= url('auth/login') ?>">Admin</a>
              <a class="btn btn-solid btn-block" href="https://wa.me/260772300053" target="_blank" rel="noopener">WhatsApp us</a>
            <?php endif; ?>
          </div>
        </details>
      </div>
    </div>
  </header>

  <main class="section">
    <div class="frame">
      <?php if ($flash): ?><p class="flash <?= h($flash[0]) ?>" role="status"><?= h($flash[1]) ?></p><?php endif; ?>
      <?php require $viewFile; ?>
    </div>
  </main>

  <div class="filmstrip" aria-hidden="true"></div>
  <footer class="section section-dark footer">
    <div class="frame footer-grid">
      <div>
        <p class="brand-text footer-brand">ATS <em>media house</em></p>
        <p class="section-lede">Lusaka, Zambia &middot; Always open</p>
      </div>
      <address>
        <a href="tel:+260972971151">+260 972 971 151</a>
        <a href="https://wa.me/260772300053" target="_blank" rel="noopener">WhatsApp: +260 772 300 053</a>
        <a href="mailto:alinanits287@gmail.com">alinanits287@gmail.com</a>
      </address>
      <p class="fine-print">&copy; 2026 ATS media house.</p>
    </div>
  </footer>
  <script src="../js/app.js"></script>
</body>
</html>
