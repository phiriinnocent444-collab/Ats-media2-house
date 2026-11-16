<p class="eyebrow">Studio portal</p>
<h2>Admin login</h2>
<form method="post" class="card" style="max-width:400px"><?= csrf_field() ?>
  <?php if ($error): ?><p class="flash err"><?= h($error) ?></p><?php endif; ?>
  <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= h($_POST['username'] ?? $remembered) ?>" autocomplete="username" required></div>
  <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
  <button class="btn btn-solid btn-block" type="submit">Log in</button>
</form>
