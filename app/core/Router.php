<?php
// Unit 8 MVC: the front controller sends  ?r=controller/action  to Controller::action()
class Router {
  public static function dispatch(string $route): void {
    if (!preg_match('~^([a-z]+)(?:/([a-z_]+))?$~', $route, $m)) { self::notFound(); return; }   // Unit 8.4 regex whitelist
    $class = ucfirst($m[1]) . 'Controller';
    $action = $m[2] ?? 'index';
    if (!class_exists($class) || !is_subclass_of($class, 'Controller') || !method_exists($class, $action)
        || !(new ReflectionMethod($class, $action))->isPublic() || $action === '__construct') { self::notFound(); return; }
    (new $class())->$action();
  }
  private static function notFound(): void { http_response_code(404); echo '<h1>404 Not found</h1>'; }
}
