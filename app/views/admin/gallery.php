<p class="eyebrow">Portfolio admin</p>
<h2>Photos</h2>
<form method="post" enctype="multipart/form-data" action="<?= url('admin/gallery_upload') ?>" class="card" style="max-width:480px"><?= csrf_field() ?>
  <div class="field"><label for="title">Title</label><input id="title" name="title" required></div>
  <div class="field"><label for="photo">Image (JPG, PNG or WEBP, max 3 MB)</label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required></div>
  <button class="btn btn-solid" type="submit">Upload</button>
</form>
<div class="grid-photos">
<?php foreach ($photos as $p): ?>
  <div class="photo-card">
    <img src="../uploads/thumbs/<?= h($p['thumb']) ?>" alt="<?= h($p['title']) ?>">
    <div class="photo-card-body">
      <span class="photo-card-title"><?= h($p['title']) ?></span>
      <form method="post" action="<?= url('admin/gallery_delete') ?>" data-confirm="Delete this photo?" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $p['photo_id'] ?>">
        <button class="btn btn-outline btn-sm" type="submit">Delete</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
</div>
