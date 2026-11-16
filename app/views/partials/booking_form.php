<?php /* Sticky form (Unit 3.3): every field is refilled from $_POST (or $defaults) so nothing is lost when validation fails. */
$v = fn(string $k) => h($_POST[$k] ?? ($defaults[$k] ?? ''));
$err = fn(string $k) => isset($errors[$k]) ? '<p class="error">' . h($errors[$k]) . '</p>' : ''; ?>
<form method="post" action="<?= h($action) ?>" class="card" novalidate>
  <?= csrf_field() ?>
  <?php if (!empty($errors['form'])): ?><p class="flash err"><?= h($errors['form']) ?></p><?php endif; ?>
  <div class="field"><label for="fullName">Full name</label><input id="fullName" name="fullName" value="<?= $v('fullName') ?>" placeholder="e.g. Chanda Mwansa"><?= $err('fullName') ?></div>
  <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" value="<?= $v('phone') ?>" placeholder="e.g. 0972971151"><?= $err('phone') ?></div>
  <div class="field"><label for="email">Email <span class="optional">(optional)</span></label><input id="email" name="email" type="email" value="<?= $v('email') ?>"><?= $err('email') ?></div>
  <div class="field"><label for="service">Package</label>
    <select id="service" name="service" class="js-price-source">
      <option value="">Choose a package…</option>
      <?php foreach ($services as $s): ?>   <!-- options come from the database (Unit 5) -->
        <option value="<?= h($s['code']) ?>" data-price="<?= h($s['price']) ?>" <?= $v('service') === h($s['code']) ? 'selected' : '' ?>><?= h($s['label']) ?> — <?= money($s['price']) ?></option>
      <?php endforeach; ?>
    </select><?= $err('service') ?>
    <p class="price" id="price-preview" aria-live="polite"></p>
  </div>
  <div class="field"><label for="eventDate">Preferred date</label><input id="eventDate" name="eventDate" type="date" min="<?= date('Y-m-d') ?>" value="<?= $v('eventDate') ?>"><?= $err('eventDate') ?></div>
  <div class="field"><label for="notes">Notes <span class="optional">(optional)</span></label><textarea id="notes" name="notes" rows="3" maxlength="1000" data-counter="notes-count"><?= $v('notes') ?></textarea><small id="notes-count"></small><?= $err('notes') ?></div>
  <button class="btn btn-solid btn-block" type="submit"><?= h($submit) ?></button>
</form>
