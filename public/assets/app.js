// Client-side checks for quick feedback. The server validates every POST again;
// this file only saves a round trip.
(function () {
  'use strict';

  function message(input) {
    var v = input.validity;
    if (v.valueMissing) return 'This field is required.';
    if (input.hasAttribute('data-grade') && (v.rangeUnderflow || v.rangeOverflow || v.badInput || v.stepMismatch)) {
      return 'Enter a grade from 0 to 100, up to two decimals.';
    }
    if (v.tooShort) return 'Use at least ' + input.minLength + ' characters.';
    if (v.patternMismatch) return 'Use lowercase letters, digits, dot, dash or underscore (3 to 40).';
    if (v.rangeUnderflow || v.rangeOverflow) return 'Enter a number from ' + input.min + ' to ' + input.max + '.';
    return input.validationMessage;
  }

  function showError(input, text) {
    var id = (input.id || input.name) + '-error';
    var el = document.getElementById(id);
    if (!text) {
      if (el) el.remove();
      input.removeAttribute('aria-invalid');
      return;
    }
    if (!el) {
      el = document.createElement('small');
      el.className = 'field-error';
      el.id = id;
      input.insertAdjacentElement('afterend', el);
    }
    el.textContent = text;
    input.setAttribute('aria-invalid', 'true');
    input.setAttribute('aria-describedby', id);
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var first = null;
      form.querySelectorAll('input, select, textarea').forEach(function (input) {
        if (input.type === 'hidden') return;
        var bad = !input.checkValidity();
        showError(input, bad ? message(input) : '');
        if (bad && !first) first = input;
      });
      if (first) {
        e.preventDefault();
        first.focus();
      }
    });
  });

  // Destructive actions ask first.
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () { select.form.submit(); });
  });
})();
