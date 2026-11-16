<p class="eyebrow">Package catalog</p>
<h2>Service packages</h2>
<p class="section-lede">Create, edit, or delete studio photography packages. Packages with existing bookings are safely deactivated to preserve history.</p>
<div class="scroll"><table class="table"><tr><th>Code</th><th>Label</th><th>Price (ZMW)</th><th>Active</th><th>Actions</th></tr>
<?php foreach (array_merge($rows, [['id' => 0, 'code' => '', 'label' => '', 'price' => '', 'is_active' => 1]]) as $r): $f = 'svc' . (int) $r['id']; $new = !$r['id']; ?>
<tr>
  <td><form id="<?= $f ?>" method="post" action="<?= url('admin/service_save') ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"></form>
      <input form="<?= $f ?>" name="code" value="<?= h($r['code']) ?>" placeholder="<?= $new ? 'new_code' : '' ?>"></td>
  <td><input form="<?= $f ?>" name="label" value="<?= h($r['label']) ?>" placeholder="<?= $new ? 'New package' : '' ?>"></td>
  <td><input form="<?= $f ?>" name="price" value="<?= h($r['price']) ?>" size="6" placeholder="<?= $new ? '0.00' : '' ?>"></td>
  <td><input form="<?= $f ?>" type="checkbox" name="is_active" value="1" <?= $r['is_active'] ? 'checked' : '' ?>></td>
  <td class="actions">
    <button form="<?= $f ?>" class="btn <?= $new ? 'btn-solid' : 'btn-outline' ?>" type="submit" aria-label="<?= $new ? 'Add new package' : 'Save package ' . h($r['code']) ?>"><?= $new ? 'Add' : 'Save' ?></button>
    <?php if (!$new): ?>
      <form method="post" action="<?= url('admin/service_delete') ?>" class="inline" data-confirm="Delete this package?">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <button class="btn btn-outline" type="submit" aria-label="Delete package <?= h($r['code']) ?>">Delete</button>
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?></table></div>
