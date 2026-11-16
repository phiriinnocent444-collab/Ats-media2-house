<h2>Dashboard</h2>
<div class="stats">
  <div class="card"><strong><?= (int) $s['total'] ?></strong><span>bookings</span></div>
  <div class="card"><strong><?= (int) $s['pending'] ?></strong><span>awaiting payment</span></div>
  <div class="card"><strong><?= (int) $s['paid'] ?></strong><span>paid</span></div>
  <div class="card"><strong><?= money($s['revenue']) ?></strong><span>revenue (paid)</span></div>
</div>
<h3>Next shoots</h3>
<div class="scroll"><table class="table"><tr><th>When</th><th>Client</th><th>Package</th><th>Status</th></tr>
<?php foreach ($up as $b): ?><tr><td><?= nice_date($b['eventDate']) ?> <small>(<?= days_until($b['eventDate']) ?>d)</small></td><td><?= h($b['fullName']) ?></td><td><?= h($b['serviceLabel']) ?></td><td><span class="status-label <?= h($b['status']) ?>"><?= h($b['status']) ?></span></td></tr>
<?php endforeach; if (!$up): ?><tr><td colspan="4">No upcoming bookings.</td></tr><?php endif; ?></table></div>
<p><a class="btn btn-outline btn-sm" href="<?= url('admin/bookings', ['timing' => 'upcoming']) ?>">View all upcoming events</a></p>
