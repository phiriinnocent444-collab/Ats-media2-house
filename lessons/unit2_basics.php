<?php
// UNIT 2: principles of PHP (single-file demo, nothing here needs the database)
// 2.2 Writing comments: // single line, # also single line, /* ... */ multi-line

/* 2.3.1 Variables, 2.3.2 Strings, 2.3.5 Numbers, constants */
const STUDIO = 'ATS media house';          // constant
$package  = 'Graduation Shoot';            // string variable
$price    = 700;                           // integer
$discount = 0.1;                           // float
$total    = $price * (1 - $discount);      // arithmetic operators
$isOpen   = true;                          // boolean

/* 2.3.7 Single vs double quotes: double quotes parse variables, single quotes do not */
$double = "Package: $package costs K$total";
$single = 'Package: $package costs K$total';

/* 2.3.11 Arrays (indexed + associative) and 2.3.4 Loops */
$packages = ['wedding' => 3500, 'corporate' => 1800, 'event' => 1500, 'family' => 900, 'graduation' => 700];

/* 2.3.8 HTML form + 2.3.9 conditionals/operators + 2.3.10 validating form data */
$errors = []; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $qty  = $_POST['qty'] ?? '';
  if ($name === '')                                   $errors[] = 'Name is required.';
  if (!ctype_digit($qty) || $qty < 1 || $qty > 10)    $errors[] = 'Quantity must be a whole number from 1 to 10.';
  if (!$errors) $msg = 'Thanks ' . htmlspecialchars($name) . ', ' . (int) $qty . ' x ' . $package . ' = K' . $total * (int) $qty;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Unit 2 – PHP basics</title>
<style>body{font:16px/1.5 system-ui;max-width:720px;margin:24px auto;padding:0 16px}code{background:#eee;padding:1px 5px}.err{color:#b3261e}.ok{color:#1f7a3d}</style></head><body>
<h1>Unit 2: PHP basics at <?= STUDIO ?></h1>    <!-- 2.2 sending data to the browser: echo or the short echo tag -->
<p>Double quotes: <code><?= $double ?></code><br>Single quotes: <code><?= $single ?></code></p>
<p>Studio is <?= $isOpen ? 'open' : 'closed' ?> (ternary operator). 10% discount on <?= $package ?>: K<?= number_format($total, 2) ?></p>

<h2>Packages (foreach over an array)</h2>
<ul><?php foreach ($packages as $code => $p): ?><li><?= ucfirst($code) ?> – K<?= number_format($p) ?></li><?php endforeach; ?></ul>
<h2>Counting with loops</h2>
<p><?php for ($i = 1; $i <= 5; $i++) echo $i . ' '; ?>(for) &nbsp; <?php $n = 3; while ($n > 0) { echo $n-- . ' '; } ?>(while)</p>
<p>Strings: <?= strtoupper(STUDIO) ?> · length <?= strlen(STUDIO) ?> · "<?= substr(STUDIO, 0, 3) ?>" · <?= str_replace('media', 'photo', STUDIO) ?></p>

<h2>Order form (validated, sticky)</h2>
<?php foreach ($errors as $e): ?><p class="err"><?= $e ?></p><?php endforeach; ?>
<?php if ($msg): ?><p class="ok"><?= $msg ?></p><?php endif; ?>
<form method="post">
  <p><label>Name <input name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></label></p>
  <p><label>Quantity (1–10) <input name="qty" value="<?= htmlspecialchars($_POST['qty'] ?? '1') ?>"></label></p>
  <button>Calculate</button>
</form>
</body></html>
