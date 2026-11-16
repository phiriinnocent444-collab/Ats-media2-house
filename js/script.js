// ATS media house — client-side validation, package selection, Airtel Money polling, and WhatsApp link
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.mobile-nav-drawer a').forEach(function (link) {
    link.addEventListener('click', function () {
      const details = link.closest('details.mobile-nav');
      if (details) details.removeAttribute('open');
    });
  });

  const WHATSAPP_NUMBER = '260772300053';
  const form = document.getElementById('booking-form');
  if (!form) return;

  const submitBtn = document.getElementById('submit-btn');
  const statusBox = document.getElementById('payment-status');
  const waLink = document.getElementById('wa-confirm');
  const serviceSelect = document.getElementById('service');
  const dateInput = document.getElementById('eventDate');

  const today = new Date().toISOString().split('T')[0];
  if (dateInput) dateInput.setAttribute('min', today);

  // Clicking a Contact Sheet package card selects the package and scrolls smoothly to #book
  document.querySelectorAll('[data-package-code]').forEach(function (card) {
    card.addEventListener('click', function (e) {
      e.preventDefault();
      const code = card.getAttribute('data-package-code');
      if (serviceSelect && code) {
        serviceSelect.value = code;
      }
      const bookEl = document.getElementById('book');
      if (bookEl) bookEl.scrollIntoView({ behavior: 'smooth' });
    });
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (validateForm()) {
      handleBookingAndPayment();
    }
  });

  function validateForm() {
    let isValid = true;
    isValid = validateRequired('fullName', 'Please enter your full name.') && isValid;
    isValid = validatePhone() && isValid;
    isValid = validateEmailIfProvided() && isValid;
    isValid = validateRequired('service', 'Please choose a package.') && isValid;
    isValid = validateRequired('eventDate', 'Please pick a preferred date.') && isValid;
    return isValid;
  }

  function validateRequired(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorEl = document.getElementById('err-' + fieldId);
    if (!field || !field.value || field.value.trim() === '') {
      if (errorEl) errorEl.textContent = message;
      return false;
    }
    if (errorEl) errorEl.textContent = '';
    return true;
  }

  function validatePhone() {
    const field = document.getElementById('phone');
    const errorEl = document.getElementById('err-phone');
    const digits = field.value.replace(/[^\d]/g, '');
    const isValidLength = digits.length === 9 || digits.length === 10 || digits.length === 12;
    if (!field.value.trim()) {
      errorEl.textContent = 'Please enter your Airtel Money number.';
      return false;
    }
    if (!isValidLength) {
      errorEl.textContent = 'Enter a valid Zambian number, e.g. 0972971151.';
      return false;
    }
    errorEl.textContent = '';
    return true;
  }

  function validateEmailIfProvided() {
    const field = document.getElementById('email');
    const errorEl = document.getElementById('err-email');
    if (!field.value.trim()) {
      errorEl.textContent = '';
      return true;
    }
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(field.value)) {
      errorEl.textContent = 'That email address does not look right.';
      return false;
    }
    errorEl.textContent = '';
    return true;
  }

  function showWhatsAppLink(booking, paid) {
    const text =
      'Hello ATS media house, ' + (paid ? 'I have paid for' : 'I have made') + ' a booking.\n' +
      'Reference: ' + booking.id + '\n' +
      'Name: ' + booking.fullName + '\n' +
      'Package: ' + booking.serviceLabel + ' (K' + booking.amount + ')\n' +
      'Date: ' + booking.eventDate;
    waLink.href = 'https://wa.me/' + WHATSAPP_NUMBER + '?text=' + encodeURIComponent(text);
    waLink.style.display = 'block';
  }

  async function handleBookingAndPayment() {
    setStatus('pending', 'Creating your booking\u2026');
    toggleSubmitting(true);
    waLink.style.display = 'none';

    const payload = {
      fullName: document.getElementById('fullName').value.trim(),
      phone: document.getElementById('phone').value.trim(),
      email: document.getElementById('email').value.trim(),
      service: document.getElementById('service').value,
      eventDate: document.getElementById('eventDate').value,
      notes: document.getElementById('notes').value.trim(),
    };

    try {
      const bookingRes = await fetch('api/bookings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const booking = await bookingRes.json();
      if (!bookingRes.ok) throw new Error(booking.error || 'Booking failed.');

      showWhatsAppLink(booking, false);
      setStatus('pending', 'Booking ' + booking.id + ' created. Sending Airtel Money prompt for K' + booking.amount + '\u2026');

      const paymentRes = await fetch('api/payment_initiate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bookingId: booking.id, phone: payload.phone }),
      });
      const payment = await paymentRes.json();
      if (!paymentRes.ok) {
        setStatus('error', payment.error || 'Airtel Money could not be reached. Please try again.');
        toggleSubmitting(false);
        return;
      }

      setStatus('pending', 'Prompt sent! Confirm your Airtel Money PIN on your phone\u2026');
      pollPaymentStatus(payment.transactionId, 0, booking);
    } catch (err) {
      setStatus('error', err.message || 'Something went wrong. Please try again.');
      toggleSubmitting(false);
    }
  }

  function pollPaymentStatus(transactionId, attempt, booking) {
    attempt = attempt || 0;
    const maxAttempts = 15;
    if (attempt >= maxAttempts) {
      setStatus('error', 'Payment confirmation timed out. Check the Airtel Money app and try again if needed.');
      toggleSubmitting(false);
      return;
    }

    setTimeout(async function () {
      try {
        const res = await fetch('api/payment_status.php?id=' + encodeURIComponent(transactionId));
        const data = await res.json();
        if (data.status === 'TS' || data.status === 'SUCCESS') {
          setStatus('success', 'Payment confirmed \u2014 your booking is secured! Tap the WhatsApp button to send your booking details to 0772 300 053.');
          showWhatsAppLink(booking, true);
          toggleSubmitting(false);
          form.reset();
        } else if (data.status === 'FAILED' || data.status === 'TF') {
          setStatus('error', 'Payment was not completed. Please try again.');
          toggleSubmitting(false);
        } else {
          setStatus('pending', 'Waiting for Airtel Money confirmation on the customer\u2019s phone\u2026');
          pollPaymentStatus(transactionId, attempt + 1, booking);
        }
      } catch (err) {
        pollPaymentStatus(transactionId, attempt + 1, booking);
      }
    }, 1500);
  }

  function setStatus(type, message) {
    statusBox.className = 'payment-status show ' + type;
    statusBox.textContent = message;
  }

  function toggleSubmitting(isSubmitting) {
    submitBtn.disabled = isSubmitting;
    submitBtn.textContent = isSubmitting ? 'Processing\u2026' : 'Book & pay with Airtel Money';
  }
});
