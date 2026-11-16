<?php
// Unit 6.3 Session management and password protected pages
class AuthController extends Controller {
  public function login(): void {
    $error = '';
    if ($this->isPost()) {
      if (!csrf_verify()) {
        http_response_code(400);
        $error = 'Your login token expired or was invalid. A fresh token has been issued — please log in again.';
      } elseif (($_SESSION['lock_until'] ?? 0) > time()) {
        $error = 'Too many attempts. Wait a minute and try again.';
      } else {
        $u = AdminUser::find(trim((string) ($_POST['username'] ?? '')));
        if ($u && password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'])) {
          $hadSessionCookie = !empty($_COOKIE[session_name()]);
          session_regenerate_id(true);                                  // new session id after login (stops session fixation)
          $_SESSION['admin'] = ['id' => (int) $u['admin_id'], 'name' => $u['username']];
          $_SESSION['fails'] = 0;
          app_set_cookie('ats_user', $u['username'], time() + 30 * 86400);
          if (!$hadSessionCookie) {
            $GLOBALS['_ats_admin_token'] = admin_token_create((int) $u['admin_id'], $u['username']);
          }
          redirect(url('admin/dashboard'));
        }
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        if ($_SESSION['fails'] >= 5) { $_SESSION['lock_until'] = time() + 60; $_SESSION['fails'] = 0; }
        $error = 'Wrong username or password.';
      }
    }
    $this->render('auth/login', ['error' => $error, 'remembered' => $_COOKIE['ats_user'] ?? ''], 'Admin login');
  }

  public function logout(): void {
    if ($this->isPost()) {
      csrf_check();
      $tok = admin_active_token();
      if ($tok !== '') @unlink(flash_storage_file($tok));
      $GLOBALS['_ats_admin_token'] = null;
      $_SESSION = [];
      session_destroy();
    }
    redirect(url('auth/login'));
  }
}
