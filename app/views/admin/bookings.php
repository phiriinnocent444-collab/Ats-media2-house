<p class="eyebrow">Studio manager</p>
<h2>Bookings &amp; upcoming events</h2>

<details class="new-booking-panel">
  <summary class="btn btn-outline btn-sm">+ New booking (walk-in / phone)</summary>
  <form method="post" action="<?= url('admin/booking_create') ?>" class="card" style="max-width:680px; margin-top:12px;">
    <?= csrf_field() ?>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px;">
      <div class="field"><label for="newFullName">Full name</label><input id="newFullName" name="fullName" required placeholder="e.g. Chanda Mwansa"></div>
      <div class="field"><label for="newPhone">Phone number</label><input id="newPhone" name="phone" type="tel" required placeholder="e.g. 0972971151"></div>
      <div class="field"><label for="newEmail">Email <span class="optional">(optional)</span></label><input id="newEmail" name="email" type="email" placeholder="client@example.com"></div>
      <div class="field"><label for="newService">Package</label>
        <select id="newService" name="service" required>
          <option value="">Choose a package&hellip;</option>
          <?php foreach ($services as $s): ?>
            <option value="<?= h($s['code']) ?>"><?= h($s['label']) ?> &mdash; <?= money($s['price']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="newEventDate">Preferred date</label><input id="newEventDate" name="eventDate" type="date" min="<?= date('Y-m-d') ?>" required></div>
      <div class="field"><label for="newNotes">Notes <span class="optional">(optional)</span></label><input id="newNotes" name="notes" maxlength="1000" placeholder="Venue, shoot details..."></div>
    </div>
    <div><button class="btn btn-solid" type="submit">Create booking</button></div>
  </form>
</details>

<form method="get" class="filters"><input type="hidden" name="r" value="admin/bookings"><?= admin_token_field() ?>
  <input name="q" value="<?= h($q) ?>" placeholder="Search name, reference or notes…" aria-label="Search">
  <select name="status" aria-label="Status"><option value="">All statuses</option>
    <?php foreach (['pending_payment', 'paid', 'cancelled'] as $st): ?><option <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select>
  <select name="timing" aria-label="Schedule">
    <option value="">All dates</option>
    <option value="upcoming" <?= ($timing ?? '') === 'upcoming' ? 'selected' : '' ?>>Upcoming events</option>
  </select>
  <button class="btn btn-solid" type="submit">Filter</button>
</form>

<div class="scroll"><table class="table">
<tr><th>Ref</th><th>Client</th><th>Package</th><th>Event Date</th><th>Amount</th><th>Status</th><th>Actions</th></tr>
<?php foreach ($rows as $b): $d = days_until($b['eventDate']); $when = $d === 0 ? 'today' : ($d > 0 ? "in {$d}d" : abs($d) . 'd ago'); ?>
<tr><td><small><?= h($b['id']) ?></small></td><td><?= h($b['fullName']) ?><br><small>0<?= h($b['phone']) ?><?= $b['email'] ? ' &middot; ' . h($b['email']) : '' ?></small></td><td><?= h($b['serviceLabel']) ?></td>
<td><?= nice_date($b['eventDate']) ?><br><small><?= h($when) ?></small></td><td><?= money($b['amount']) ?></td><td><span class="status-label <?= h($b['status']) ?>"><?= h($b['status']) ?></span></td>
<td class="actions">
<?php if ($b['status'] === 'pending_payment'): ?><a class="btn btn-outline" href="<?= url('admin/booking_edit', ['id' => $b['id']]) ?>" aria-label="Edit booking <?= h($b['id']) ?>">Edit</a><?php endif; ?>
<?php foreach (['pending_payment' => [['paid', 'Mark paid'], ['cancel', 'Cancel']], 'paid' => [], 'cancelled' => []][$b['status']] as [$do, $label]): ?>
  <form method="post" action="<?= url('admin/booking_action') ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($b['id']) ?>"><input type="hidden" name="do" value="<?= $do ?>"><button class="btn btn-outline" type="submit" aria-label="<?= h($label) ?> booking <?= h($b['id']) ?>"><?= $label ?></button></form>
<?php endforeach; ?>
<?php if ($b['status'] !== 'paid'): ?><form method="post" action="<?= url('admin/booking_action') ?>" class="inline" data-confirm="Delete this booking?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($b['id']) ?>"><input type="hidden" name="do" value="delete"><button class="btn btn-outline" type="submit" aria-label="Delete booking <?= h($b['id']) ?>">Delete</button></form><?php endif; ?>
</td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="7">No bookings found.</td></tr><?php endif; ?></table></div>
