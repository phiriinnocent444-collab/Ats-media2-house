<?php
// Public pages: PHP-rendered booking form (sticky), thank-you, booking lookup/status, gallery.
class PageController extends Controller {
  public function index(): void { redirect(url('page/book')); }

  public function book(): void {
    $errors = []; $defaults = ['fullName' => $_COOKIE['ats_name'] ?? '', 'service' => $_GET['service'] ?? ''];
    if ($this->isPost()) {
      if (!csrf_verify()) {
        http_response_code(400);
        $errors['form'] = 'Your form security token expired or was invalid. A fresh token has been issued — please submit again.';
      } else {
        [$errors, $clean] = Validator::booking($_POST);
        if (!$errors) {
          try {
            $b = Booking::create($_POST);
            app_set_cookie('ats_name', $clean['name'], time() + 30 * 86400);
            if ($clean['email'] !== '') {
              send_mail($clean['email'], 'ATS media house booking ' . $b['id'],
                "Hello {$clean['name']},\n\nYour booking {$b['id']} for {$b['serviceLabel']} on " . nice_date($b['eventDate']) .
                " (" . money($b['amount']) . ") is saved and awaiting payment.\n\nATS media house");
            }
            $_SESSION['last_booking'] = $b['id'];
            redirect(url('page/thanks', ['id' => $b['id']]));
          } catch (HttpError $e) { $errors['form'] = $e->getMessage(); }
        }
      }
    }
    $this->render('page/book', ['services' => Service::active(), 'errors' => $errors, 'defaults' => $defaults, 'action' => url('page/book'), 'submit' => 'Save booking'], 'Book a session');
  }

  public function thanks(): void {
    $id = trim((string) ($_SESSION['last_booking'] ?? $_GET['id'] ?? ''));
    if ($id === '') redirect(url('page/book'));
    try {
      $b = find_booking($id);
    } catch (HttpError $e) {
      redirect(url('page/book'));
    }
    $this->render('page/thanks', ['b' => $b], 'Booking saved');
  }

  public function status(): void {
    $q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
    if ($this->isPost()) {
      csrf_check();
      $id = trim((string) ($_POST['id'] ?? ''));
      $do = (string) ($_POST['do'] ?? '');
      try {
        if ($do === 'pay') {
          $txnId = Booking::payNow($id);
          flash("Payment {$txnId} confirmed — booking {$id} is now paid.");
        } elseif ($do === 'cancel') {
          $res = delete_booking($id);
          flash("Booking {$id} {$res}.");
        }
      } catch (HttpError $e) {
        flash($e->getMessage(), 'err');
      }
      redirect(url('page/status', $q !== '' ? ['q' => $q] : []));
    }
    $rows = $q !== '' ? Booking::byLookup($q) : [];
    $this->render('page/status', ['q' => $q, 'rows' => $rows], 'Booking lookup');
  }

  public function gallery(): void { $this->render('page/gallery', ['photos' => Gallery::all()], 'Gallery'); }
}
