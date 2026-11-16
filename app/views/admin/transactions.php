<p class="eyebrow">ACID Ledger</p>
<h2>Payment transactions</h2>
<p class="section-lede">Every payment initiates a row-locked InnoDB transaction (<code>BEGIN</code> &rarr; <code>FOR UPDATE</code> &rarr; <code>COMMIT</code> or automatic <code>ROLLBACK</code>). Pending transactions can be settled or marked failed below.</p>
<div class="scroll">
  <table class="table">
    <tr><th>Txn Ref</th><th>Booking</th><th>Client</th><th>Package</th><th>Amount</th><th>Txn Status</th><th>Booking Status</th><th>Timestamp</th><th>Actions</th></tr>
    <?php foreach ($rows as $t): ?>
      <tr>
        <td><small><?= h($t['transaction_id']) ?></small></td>
        <td><small><?= h($t['booking_id']) ?></small><br><small><?= nice_date($t['event_date']) ?></small></td>
        <td><?= h($t['full_name']) ?><br><small>0<?= h($t['msisdn']) ?></small></td>
        <td><?= h($t['service_label']) ?></td>
        <td><?= money($t['amount']) ?></td>
        <td><span class="status-label <?= h($t['status']) ?>"><?= h($t['status']) ?></span></td>
        <td><span class="status-label <?= h($t['booking_status']) ?>"><?= h($t['booking_status']) ?></span></td>
        <td><small><?= h($t['created_at']) ?></small><?php if (!empty($t['error'])): ?><br><small><?= h($t['error']) ?></small><?php endif; ?></td>
        <td class="actions">
          <?php if ($t['status'] === 'PENDING'): ?>
            <form method="post" action="<?= url('admin/transaction_action') ?>" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= h($t['transaction_id']) ?>">
              <input type="hidden" name="outcome" value="SUCCESS">
              <button class="btn btn-solid btn-sm" type="submit" aria-label="Confirm <?= h($t['transaction_id']) ?> paid">Confirm (Paid)</button>
            </form>
            <form method="post" action="<?= url('admin/transaction_action') ?>" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= h($t['transaction_id']) ?>">
              <input type="hidden" name="outcome" value="FAILED">
              <button class="btn btn-outline btn-sm" type="submit" aria-label="Mark <?= h($t['transaction_id']) ?> failed">Mark Failed</button>
            </form>
          <?php else: ?>
            <small>Settled</small>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="9">No payment transactions recorded yet.</td></tr>
    <?php endif; ?>
  </table>
</div>
