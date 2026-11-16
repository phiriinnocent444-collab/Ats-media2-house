<p class="eyebrow">Confirmation</p>
<h2>Booking saved</h2>
<div class="card" style="max-width:560px">
  <p><strong><?= h($b['id']) ?></strong> &middot; <?= h($b['serviceLabel']) ?> &middot; <?= money($b['amount']) ?></p>
  <p>Date: <?= nice_date($b['eventDate']) ?> (<?php $d = days_until($b['eventDate']); echo $d === 0 ? 'today' : "in $d days"; ?>)</p>
  <p>Status: <span class="status-label <?= h($b['status']) ?>"><?= h($b['status']) ?></span></p>
  <?php $wa = 'https://wa.me/260772300053?text=' . rawurlencode("Hello ATS media house, booking {$b['id']} ({$b['serviceLabel']}, " . money($b['amount']) . ") on {$b['eventDate']}. I am {$b['fullName']}."); ?>
  <a class="btn btn-solid btn-block" target="_blank" rel="noopener" href="<?= h($wa) ?>">Confirm on WhatsApp</a>
  <a class="btn btn-outline btn-block" href="<?= url('page/status', ['q' => $b['id']]) ?>">View status / Pay online</a>
</div>
