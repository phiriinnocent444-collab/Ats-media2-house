// Unit 9: JavaScript + DOM for the PHP pages (live price preview, character counter, table filter, inline confirmation)
document.addEventListener('DOMContentLoaded', function () {
  // 9.6 Interacting with HTML forms: live price preview when a package is chosen
  var select = document.querySelector('.js-price-source');
  var preview = document.getElementById('price-preview');
  function showPrice() {
    if (!select || !preview) return;
    var opt = select.options[select.selectedIndex];
    var price = opt && opt.dataset.price;
    preview.textContent = price
      ? 'Amount due: K' + Number(price).toLocaleString('en-ZM', { minimumFractionDigits: 2 })
      : '';
  }
  if (select) {
    select.addEventListener('change', showPrice);
    showPrice();
  }

  // 9.4 DOM: character counter for any textarea with data-counter
  document.querySelectorAll('textarea[data-counter]').forEach(function (ta) {
    var out = document.getElementById(ta.dataset.counter);
    if (!out) return;
    function update() {
      out.textContent = ta.value.length + ' / ' + ta.maxLength;
    }
    ta.addEventListener('input', update);
    update();
  });

  // 9.5 Functions: filter table rows as you type
  document.querySelectorAll('input[data-filter]').forEach(function (box) {
    box.addEventListener('input', function () {
      var term = box.value.toLowerCase();
      document.querySelectorAll('#' + box.dataset.filter + ' tr').forEach(function (row, i) {
        if (i === 0) return;
        row.style.display = row.textContent.toLowerCase().indexOf(term) === -1 ? 'none' : '';
      });
    });
  });

  // Accessible inline two-step confirmation for delete forms (avoids window.confirm inside iframe)
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    var btn = f.querySelector('button[type="submit"]');
    if (!btn) return;
    var armed = false;
    var originalLabel = btn.textContent;
    f.addEventListener('submit', function (e) {
      if (!armed) {
        e.preventDefault();
        armed = true;
        btn.textContent = 'Confirm delete';
        btn.classList.add('btn-danger');
        setTimeout(function () {
          armed = false;
          btn.textContent = originalLabel;
        }, 4000);
      }
    });
  });
});
