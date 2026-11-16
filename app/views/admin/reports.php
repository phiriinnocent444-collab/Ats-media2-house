<h2>Reports</h2>
<h3>Revenue by package <small>(JOIN + GROUP BY)</small></h3>
<div class="scroll"><table class="table"><tr><th>Package</th><th>Bookings</th><th>Paid revenue</th></tr>
<?php foreach ($svc as $r): ?><tr><td><?= h($r['label']) ?></td><td><?= (int) $r['bookings'] ?></td><td><?= money($r['revenue']) ?></td></tr><?php endforeach; ?></table></div>
<h3>Bookings by month <small>(GROUP BY + HAVING)</small></h3>
<div class="scroll"><table class="table"><tr><th>Month</th><th>Bookings</th><th>Value</th></tr>
<?php foreach ($month as $r): ?><tr><td><?= h($r['month']) ?></td><td><?= (int) $r['bookings'] ?></td><td><?= money($r['value']) ?></td></tr><?php endforeach; ?></table></div>
<h3>Top customers</h3>
<div class="scroll"><table class="table"><tr><th>Name</th><th>Bookings</th><th>Value</th></tr>
<?php foreach ($top as $r): ?><tr><td><?= h($r['full_name']) ?></td><td><?= (int) $r['bookings'] ?></td><td><?= money($r['value']) ?></td></tr><?php endforeach; ?></table></div>
