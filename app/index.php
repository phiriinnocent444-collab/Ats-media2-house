<?php
// Front controller (Unit 8 MVC): every request enters here.
require __DIR__ . '/bootstrap.php';
Router::dispatch((string) ($_GET['r'] ?? 'page/book'));
