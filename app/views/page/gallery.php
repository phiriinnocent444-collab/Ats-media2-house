<p class="eyebrow">Portfolio</p>
<h2>Gallery</h2>
<p class="section-lede">Selected frames from weddings, portraits, graduations, and corporate commissions in Lusaka.</p>
<?php if (!$photos): ?><p>No photos yet. An admin can upload some.</p><?php endif; ?>
<div class="grid-photos">
<?php foreach ($photos as $p): $pid = (int) $p['photo_id']; ?>
  <a href="#photo-<?= $pid ?>" class="photo-card">
    <img src="../uploads/thumbs/<?= h($p['thumb']) ?>" alt="<?= h($p['title']) ?>" loading="lazy">
    <div class="photo-card-body">
      <span class="photo-card-title"><?= h($p['title']) ?></span>
      <small>View frame</small>
    </div>
  </a>
<?php endforeach; ?>
</div>

<?php foreach ($photos as $p): $pid = (int) $p['photo_id']; ?>
  <div id="photo-<?= $pid ?>" class="lightbox" role="dialog" aria-label="<?= h($p['title']) ?>">
    <div class="lightbox-inner">
      <img src="../uploads/gallery/<?= h($p['filename']) ?>" alt="<?= h($p['title']) ?>">
      <div class="lightbox-bar">
        <span><?= h($p['title']) ?></span>
        <a href="#top" class="lightbox-close">Close &times;</a>
      </div>
    </div>
  </div>
<?php endforeach; ?>
