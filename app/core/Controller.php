<?php
// Base controller: render() = the "V" in MVC (puts a view inside the shared layout).
abstract class Controller {
  protected function render(string $view, array $data = [], string $title = 'ATS media house'): void {
    extract($data);
    $viewFile = __DIR__ . "/../views/$view.php";
    require __DIR__ . '/../views/layout.php';
  }
  protected function isPost(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
}
