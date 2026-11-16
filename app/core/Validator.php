<?php
// Unit 2.3.10 validating form data + Unit 8.4 regular expressions
class Validator {
  /** Returns [errors[], cleanValues[]]; errors are per field so the form can show them (sticky form). */
  public static function booking(array $in): array {
    $e = [];
    $name  = trim((string) ($in['fullName'] ?? ''));
    $phone = normalize_msisdn((string) ($in['phone'] ?? ''));
    $email = trim((string) ($in['email'] ?? ''));
    $svc   = trim((string) ($in['service'] ?? ''));
    $date  = trim((string) ($in['eventDate'] ?? ''));
    $notes = trim((string) ($in['notes'] ?? ''));

    if (!preg_match("/^\p{L}[\p{L} .'-]{1,118}$/u", $name)) $e['fullName'] = 'Enter your full name (letters only).';
    if (!preg_match('/^[79]\d{8}$/', $phone))               $e['phone']    = 'Enter a valid Zambian number, e.g. 0972971151.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $e['email'] = 'That email address does not look right.';
    if (!in_array($svc, array_column(Service::active(), 'code'), true)) $e['service'] = 'Please choose a package.';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date)  $e['eventDate'] = 'Pick a valid date.';
    elseif ($date < date('Y-m-d'))             $e['eventDate'] = 'The date cannot be in the past.';
    if (str_len($notes) > 1000)              $e['notes'] = 'Notes are too long (max 1000 characters).';
    return [$e, ['name' => $name, 'phone' => $phone, 'email' => $email, 'service' => $svc, 'date' => $date, 'notes' => $notes]];
  }
}
