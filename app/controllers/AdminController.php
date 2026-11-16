<?php
// Password-protected admin area: CRUD for bookings, services, gallery + ACID transactions + reports.
class AdminController extends Controller {
  public function __construct() {
    if (empty($_SESSION['admin'])) { flash('Please log in.', 'err'); redirect(url('auth/login')); }
    header('Cache-Control: no-store');
  }

  public function dashboard(): void { $this->render('admin/dashboard', ['s' => Booking::stats(), 'up' => Booking::upcoming()], 'Dashboard'); }

  public function bookings(): void {
    $q = trim((string) ($_GET['q'] ?? ''));
    $status = (string) ($_GET['status'] ?? '');
    $timing = (string) ($_GET['timing'] ?? '');
    $this->render('admin/bookings', [
      'rows'     => Booking::all($q, $status, $timing),
      'q'        => $q,
      'status'   => $status,
      'timing'   => $timing,
      'services' => Service::active(),
    ], 'Bookings');
  }

  public function booking_create(): void {
    if (!$this->isPost()) redirect(url('admin/bookings'));
    csrf_check();
    [$errors, $clean] = Validator::booking($_POST);
    if ($errors) {
      flash(implode(' ', array_values($errors)), 'err');
      redirect(url('admin/bookings'));
    }
    try {
      $b = Booking::create($_POST);
      flash("Booking {$b['id']} created for {$b['fullName']}.");
    } catch (HttpError $e) {
      flash($e->getMessage(), 'err');
    }
    redirect(url('admin/bookings'));
  }

  public function booking_action(): void {
    if (!$this->isPost()) redirect(url('admin/bookings'));
    csrf_check(); $id = (string) ($_POST['id'] ?? '');
    try {
      switch ($_POST['do'] ?? '') {
        case 'paid':   Booking::markPaid($id);  flash('Booking marked as paid (transaction recorded).'); break;
        case 'cancel': Booking::cancel($id);    flash('Booking cancelled.'); break;
        case 'delete': flash('Booking ' . delete_booking($id) . '.'); break;
        default: flash('Unknown action.', 'err');
      }
    } catch (HttpError $e) { flash($e->getMessage(), 'err'); }
    redirect(url('admin/bookings'));
  }

  public function booking_edit(): void {
    $id = (string) ($_GET['id'] ?? ''); $errors = [];
    try { $b = find_booking($id); } catch (HttpError $e) { flash('Booking not found.', 'err'); redirect(url('admin/bookings')); }
    if ($this->isPost()) {
      if (!csrf_verify()) {
        http_response_code(400);
        $errors['form'] = 'Your form security token expired or was invalid. A fresh token has been issued — please submit again.';
      } else {
        [$errors, $clean] = Validator::booking($_POST);
        if (!$errors) {
          try { update_booking($id, booking_input($_POST)); flash('Booking updated.'); redirect(url('admin/bookings')); }
          catch (HttpError $e) { $errors['form'] = $e->getMessage(); }
        }
      }
    }
    $defaults = $this->isPost() ? [] : ['fullName' => $b['fullName'], 'phone' => $b['phone'], 'email' => $b['email'], 'service' => $b['service'], 'eventDate' => $b['eventDate'], 'notes' => $b['notes']];
    $this->render('page/book', ['services' => Service::active(), 'errors' => $errors, 'defaults' => $defaults, 'action' => url('admin/booking_edit', ['id' => $id]), 'submit' => 'Update booking'], 'Edit booking ' . $id);
  }

  public function services(): void { $this->render('admin/services', ['rows' => Service::all()], 'Services'); }

  public function service_save(): void {
    if (!$this->isPost()) redirect(url('admin/services'));
    csrf_check(); $id = (int) ($_POST['id'] ?? 0) ?: null;
    $err = Service::save($id, $_POST);
    $err ? flash($err, 'err') : flash($id ? 'Service updated.' : 'Service added.');
    redirect(url('admin/services'));
  }

  public function service_delete(): void {
    if (!$this->isPost()) redirect(url('admin/services'));
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    try {
      $res = Service::delete($id);
      flash("Service package {$res}.");
    } catch (HttpError $e) {
      flash($e->getMessage(), 'err');
    }
    redirect(url('admin/services'));
  }

  public function transactions(): void {
    $this->render('admin/transactions', ['rows' => Booking::transactions()], 'Transactions');
  }

  public function transaction_action(): void {
    if (!$this->isPost()) redirect(url('admin/transactions'));
    csrf_check();
    $txnId = trim((string) ($_POST['id'] ?? ''));
    $outcome = (string) ($_POST['outcome'] ?? '');
    try {
      $res = Booking::settlePayment($txnId, $outcome);
      flash("Transaction {$txnId} settled as {$res} inside ACID transaction.");
    } catch (HttpError $e) {
      flash($e->getMessage(), 'err');
    }
    redirect(url('admin/transactions'));
  }

  public function gallery(): void { $this->render('admin/gallery', ['photos' => Gallery::all()], 'Gallery admin'); }

  public function gallery_upload(): void {
    if (!$this->isPost()) redirect(url('admin/gallery'));
    csrf_check();
    try {
      $title = trim((string) ($_POST['title'] ?? '')); if ($title === '') throw new RuntimeException('Give the photo a title.');
      [$file, $thumb] = ImageTool::process($_FILES['photo'] ?? []);
      Gallery::add($title, $file, $thumb, $_SESSION['admin']['id']);
      flash('Photo uploaded.');
    } catch (RuntimeException $e) { flash($e->getMessage(), 'err'); }
    redirect(url('admin/gallery'));
  }

  public function gallery_delete(): void {
    if ($this->isPost()) { csrf_check(); Gallery::remove((int) ($_POST['id'] ?? 0)); flash('Photo deleted.'); }
    redirect(url('admin/gallery'));
  }

  public function reports(): void {
    $this->render('admin/reports', ['svc' => Booking::byService(), 'month' => Booking::byMonth(), 'top' => Booking::topCustomers()], 'Reports');
  }
}
