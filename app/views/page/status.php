<p class="eyebrow">Booking lookup</p>
<h2>Check booking status &amp; payments</h2>
<p class="section-lede">Enter your booking reference (e.g. <code>BK...</code>) or phone number (e.g. <code>0972971151</code>) to view your session status, confirm Airtel Money payment, or cancel an unpaid booking.</p>

<form method="get" class="filters" style="max-width:560px">
  <input type="hidden" name="r" value="page/status">
  <input name="q" value="<?= h($q) ?>" placeholder="Booking reference or phone number…" aria-label="Booking reference or phone" required>
  <button class="btn btn-solid" type="submit">Find booking</button>
</form>

<?php if ($q !== '' && !$rows): ?>
  <p class="flash err">No booking found matching <strong><?= h($q) ?></strong>.</p>
<?php endif; ?>

<?php if ($rows): ?>
  <div class="scroll">
    <table class="table">
      <tr><th>Ref</th><th>Client</th><th>Package</th><th>Event Date</th><th>Amount</th><th>Status</th><th>Payments</th><th>Actions</th></tr>
      <?php foreach ($rows as $b): ?>
        <tr>
          <td><small><?= h($b['id']) ?></small></td>
          <td><?= h($b['fullName']) ?><br><small>0<?= h($b['phone']) ?></small></td>
          <td><?= h($b['serviceLabel']) ?></td>
          <td><?= nice_date($b['eventDate']) ?></td>
          <td><?= money($b['amount']) ?></td>
          <td><span class="status-label <?= h($b['status']) ?>"><?= h($b['status']) ?></span></td>
          <td>
            <?php if (!empty($b['payments'])): ?>
              <?php foreach ($b['payments'] as $p): ?>
                <div><small><?= h($p['transaction_id']) ?> &middot; <span class="status-label <?= h($p['status']) ?>"><?= h($p['status']) ?></span></small></div>
              <?php endforeach; ?>
            <?php else: ?>
              <small>No payments yet</small>
            <?php endif; ?>
          </td>
          <td class="actions">
            <?php if ($b['status'] === 'pending_payment'): ?>
              <form method="post" action="<?= url('page/status', ['q' => $q]) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= h($b['id']) ?>">
                <input type="hidden" name="q" value="<?= h($q) ?>">
                <input type="hidden" name="do" value="pay">
                <button class="btn btn-solid btn-sm" type="submit">Pay now (UAT)</button>
              </form>
              <form method="post" action="<?= url('page/status', ['q' => $q]) ?>" class="inline" data-confirm="Cancel this booking?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= h($b['id']) ?>">
                <input type="hidden" name="q" value="<?= h($q) ?>">
                <input type="hidden" name="do" value="cancel">
                <button class="btn btn-outline btn-sm" type="submit">Cancel</button>
              </form>
            <?php elseif ($b['status'] === 'paid'): ?>
              <?php $wa = 'https://wa.me/260772300053?text=' . rawurlencode("Hello ATS media house, my paid booking {$b['id']} ({$b['serviceLabel']}) is on {$b['eventDate']}."); ?>
              <a class="btn btn-outline btn-sm" target="_blank" rel="noopener" href="<?= h($wa) ?>">WhatsApp</a>
            <?php else: ?>
              <small>Closed</small>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endif; ?>
