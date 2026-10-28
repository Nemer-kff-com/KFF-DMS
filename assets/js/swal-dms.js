/**
 * DMS SweetAlert2 — نفس أسلوب نظام الدعوات (نافذة مركزية، بدون toast جانبي)
 */
(function (window, document) {
  'use strict';

  var ICON_HTML = {
    success: '<i class="fas fa-check" aria-hidden="true"></i>',
    error: '<i class="fas fa-times" aria-hidden="true"></i>',
    warning: '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i>',
    info: '<i class="fas fa-info" aria-hidden="true"></i>',
    question: '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i>'
  };
  var ICON_CLASS = {
    success: 'dms-swal-success-icon',
    error: 'dms-swal-error-icon',
    warning: 'dms-swal-warning-icon',
    info: 'dms-swal-info-icon',
    question: 'dms-swal-warning-icon'
  };

  function cfg() {
    return window.__DMS_SWAL__ || {};
  }

  function i18n(key, fallback) {
    var t = cfg().i18n || {};
    return t[key] || fallback;
  }

  function isPlainOptions(value) {
    return !!value
      && typeof value === 'object'
      && !Array.isArray(value)
      && typeof value.then !== 'function'
      && !(typeof Element !== 'undefined' && value instanceof Element);
  }

  function mergeCustomClass(base, extra) {
    var out = Object.assign({}, base || {});
    if (!extra) return out;
    if (typeof extra === 'string') {
      out.popup = ((out.popup || '') + ' ' + extra).trim();
      return out;
    }
    Object.keys(extra).forEach(function (key) {
      var a = out[key] ? String(out[key]) : '';
      var b = extra[key] ? String(extra[key]) : '';
      out[key] = (a + ' ' + b).replace(/\s+/g, ' ').trim();
    });
    return out;
  }

  function normalizeOptions(args) {
    if (!args.length) return {};
    if (isPlainOptions(args[0])) return Object.assign({}, args[0]);
    return { title: args[0], text: args[1], icon: args[2] };
  }

  function isDangerColor(color) {
    var c = String(color || '').toLowerCase();
    return c === '#dc3545' || c === '#d33' || c === '#c82333' || c === 'rgb(220, 53, 69)';
  }

  function brandOptions(opts) {
    opts = opts || {};
    var iconName = opts.icon;
    var custom = mergeCustomClass({
      popup: 'dms-swal-popup',
      title: 'dms-swal-title',
      htmlContainer: 'dms-swal-text',
      confirmButton: 'dms-swal-confirm',
      cancelButton: 'dms-swal-cancel',
      denyButton: 'dms-swal-cancel',
      timerProgressBar: 'dms-swal-timer'
    }, opts.customClass);

    var iconClass = custom.icon || '';
    var keepCustomIcon = !!opts.iconHtml
      || /dms-swal-success-icon|dms-swal-error-icon|dms-swal-warning-icon|dms-swal-info-icon/.test(iconClass);

    if (!keepCustomIcon && iconName && ICON_HTML[iconName]) {
      opts.iconHtml = ICON_HTML[iconName];
      custom.icon = (iconClass + ' ' + ICON_CLASS[iconName]).replace(/\s+/g, ' ').trim();
      delete opts.icon;
    }

    if (isDangerColor(opts.confirmButtonColor) || iconName === 'error' || /dms-swal-error-icon/.test(custom.icon || '')) {
      custom.confirmButton = ((custom.confirmButton || '') + ' dms-swal-confirm--danger').replace(/\s+/g, ' ').trim();
    }

    opts.customClass = custom;
    if (opts.buttonsStyling !== true) {
      opts.buttonsStyling = false;
    }
    if (opts.confirmButtonColor == null && !isDangerColor(opts.confirmButtonColor)) {
      // brand default via CSS; keep confirmButtonColor for Swal when set by caller
    }
    return opts;
  }

  function applyBrand() {
    if (typeof window.Swal === 'undefined' || typeof window.Swal.fire !== 'function') {
      return false;
    }
    if (window.Swal.fire.__dmsBranded) {
      return true;
    }
    var originalFire = window.Swal.fire.bind(window.Swal);
    var brandedFire = function () {
      var args = Array.prototype.slice.call(arguments);
      if (args.length === 1 && typeof args[0] !== 'undefined' && typeof args[0].then === 'function') {
        return originalFire(args[0]);
      }
      return originalFire(brandOptions(normalizeOptions(args)));
    };
    brandedFire.__dmsBranded = true;
    brandedFire.__dmsOriginal = originalFire;
    window.Swal.fire = brandedFire;
    window.dmsSwalFire = brandedFire;
    return true;
  }

  function mapType(type) {
    var t = String(type || 'info').toLowerCase();
    if (t === 'danger' || t === 'error' || t === 'fail') return 'error';
    if (t === 'success' || t === 'ok') return 'success';
    if (t === 'warning' || t === 'warn') return 'warning';
    return 'info';
  }

  /** رسالة نتيجة (مثل inv_swal_flash) — نافذة مركزية */
  function flash(type, message) {
    if (!message || typeof Swal === 'undefined') return Promise.resolve();
    var icon = mapType(type);
    var title = icon === 'success'
      ? i18n('successTitle', 'نجح')
      : (icon === 'warning' ? i18n('warningTitle', 'تنبيه') : (icon === 'error' ? i18n('errorTitle', 'خطأ') : i18n('infoTitle', 'تنبيه')));

    var opts = {
      icon: icon,
      title: title
    };
    if (String(message).indexOf('\n') !== -1) {
      opts.html = String(message)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>');
    } else {
      opts.text = message;
    }

    if (icon === 'success') {
      opts.timer = 2200;
      opts.timerProgressBar = true;
      opts.showConfirmButton = false;
    } else {
      opts.confirmButtonText = i18n('ok', 'حسناً');
      opts.confirmButtonColor = icon === 'error' ? '#dc3545' : '#3B4F6E';
    }

    return Swal.fire(opts);
  }

  function alert(type, message, title) {
    if (!message || typeof Swal === 'undefined') return Promise.resolve();
    var icon = mapType(type);
    return Swal.fire({
      icon: icon,
      title: title || (icon === 'success' ? i18n('successTitle', 'نجح') : (icon === 'error' ? i18n('errorTitle', 'خطأ') : i18n('warningTitle', 'تنبيه'))),
      text: message,
      confirmButtonText: i18n('ok', 'حسناً'),
      confirmButtonColor: icon === 'error' ? '#dc3545' : '#3B4F6E'
    });
  }

  function formHasSelectedFiles(form) {
    if (!form || !form.querySelectorAll) return false;
    var inputs = form.querySelectorAll('input[type="file"]');
    for (var i = 0; i < inputs.length; i++) {
      if (inputs[i].files && inputs[i].files.length) return true;
    }
    return false;
  }

  function uploading() {
    if (typeof Swal === 'undefined') return;
    if (Swal.isVisible && Swal.isVisible()) return;
    Swal.fire({
      title: i18n('uploadingFiles', 'جاري تحميل المرفقات'),
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: function () {
        if (Swal.showLoading) Swal.showLoading();
      }
    });
  }

  function swalReady(waitMs) {
    if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
      return Promise.resolve(true);
    }
    return new Promise(function (resolve) {
      var started = Date.now();
      var limit = waitMs || 2500;
      var timer = window.setInterval(function () {
        if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
          window.clearInterval(timer);
          resolve(true);
          return;
        }
        if (Date.now() - started >= limit) {
          window.clearInterval(timer);
          resolve(false);
        }
      }, 50);
    });
  }

  function confirm(message, title) {
    return swalReady().then(function (ready) {
      if (!ready) {
        return false;
      }
      return Swal.fire({
      icon: 'warning',
      title: title || i18n('confirmTitle', 'تأكيد'),
      text: message,
      showCancelButton: true,
      focusCancel: true,
      confirmButtonText: i18n('yes', 'نعم'),
      cancelButtonText: i18n('cancel', 'إلغاء'),
      confirmButtonColor: '#3B4F6E',
      reverseButtons: (document.documentElement.getAttribute('dir') || 'rtl') === 'rtl'
    }).then(function (result) {
      return !!result.isConfirmed;
    });
    });
  }

  var DmsSwal = {
    flash: flash,
    alert: alert,
    success: function (message, title) { return alert('success', message, title); },
    error: function (message, title) { return alert('error', message, title); },
    warning: function (message, title) { return alert('warning', message, title); },
    info: function (message, title) { return alert('info', message, title); },
    confirm: confirm,
    uploading: uploading
  };
  window.DmsSwal = DmsSwal;

  function bootFlash() {
    var f = cfg().flash;
    if (!f || !f.message) return;
    flash(f.type || 'info', f.message);
  }

  function bindAutoStatusForms() {
    document.querySelectorAll('form[data-auto-status]').forEach(function (form) {
      if (form.dataset.autoStatusBound === '1') return;
      var sel = form.querySelector('select[name="status"]');
      if (!sel) return;
      form.dataset.autoStatusBound = '1';
      var current = form.getAttribute('data-current-status') || sel.value;
      var template = form.getAttribute('data-status-confirm') || '';
      sel.addEventListener('change', function () {
        var next = sel.value;
        if (next === current) return;
        var label = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : next;
        var msg = template.replace(':status', label);
        var revert = function () {
          sel.value = current;
        };
        var submit = function () {
          form.dataset.dmsConfirmed = '1';
          HTMLFormElement.prototype.submit.call(form);
        };
        confirm(msg).then(function (ok) {
          if (ok) {
            current = next;
            form.setAttribute('data-current-status', current);
            submit();
          } else {
            revert();
          }
        });
      });
    });
  }

  function bindNoteRequired() {
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('[data-note-required]') : null;
      if (!btn) return;
      var fieldId = btn.getAttribute('data-note-required') || '';
      var note = fieldId ? document.getElementById(fieldId) : null;
      var value = note ? String(note.value || '') : '';
      if (value.replace(/\s+/g, ' ').trim() !== '') return;
      e.preventDefault();
      e.stopPropagation();
      var msg = btn.getAttribute('data-note-required-msg') || '';
      if (msg && window.DmsSwal) window.DmsSwal.warning(msg);
    }, true);
  }

  function bindConfirms() {
    document.addEventListener('click', function (e) {
      var link = e.target && e.target.closest ? e.target.closest('a[data-confirm]') : null;
      if (!link || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if (link.dataset.dmsConfirmed === '1') {
        delete link.dataset.dmsConfirmed;
        return;
      }
      var linkMsg = link.getAttribute('data-confirm') || '';
      if (!linkMsg) return;
      e.preventDefault();
      e.stopPropagation();
      confirm(linkMsg).then(function (ok) {
        if (!ok) return;
        link.dataset.dmsConfirmed = '1';
        link.click();
      });
    }, true);
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || form.tagName !== 'FORM') return;
      if (form.dataset.dmsConfirmed === '1') {
        delete form.dataset.dmsConfirmed;
        return;
      }
      var msg = form.getAttribute('data-confirm');
      var submitter = e.submitter;
      if (!msg && submitter) {
        msg = submitter.getAttribute('data-confirm');
      }
      if (!msg) return;
      e.preventDefault();
      e.stopPropagation();
      confirm(msg).then(function (ok) {
        if (!ok) return;
        form.dataset.dmsConfirmed = '1';
        try {
          if (typeof form.requestSubmit === 'function') {
            var btn = submitter && form.contains(submitter) ? submitter : undefined;
            form.requestSubmit(btn);
            return;
          }
        } catch (err) {}
        if (formHasSelectedFiles(form)) uploading();
        HTMLFormElement.prototype.submit.call(form);
      });
    }, true);
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || form.tagName !== 'FORM' || e.defaultPrevented) return;
      if (!formHasSelectedFiles(form)) return;
      uploading();
    }, false);
  }

  applyBrand();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      applyBrand();
      bootFlash();
      bindNoteRequired();
      bindConfirms();
      bindAutoStatusForms();
    });
  } else {
    bootFlash();
    bindNoteRequired();
    bindConfirms();
    bindAutoStatusForms();
  }
  window.addEventListener('load', applyBrand);
  var tries = 0;
  var timer = window.setInterval(function () {
    applyBrand();
    tries += 1;
    if (tries > 40) window.clearInterval(timer);
  }, 250);
})(window, document);

/** رسائل HTML5 required/type حسب لغة الواجهة (ع/EN) لكل نماذج النظام */
(function () {
  function langCode() {
    var cfg = window.__DMS_SWAL__ || {};
    if (cfg.lang) return String(cfg.lang).toLowerCase();
    return String(document.documentElement.lang || 'ar').toLowerCase();
  }

  function messages() {
    var cfg = window.__DMS_SWAL__ || {};
    var i18n = (cfg.i18n && cfg.i18n.validation) ? cfg.i18n.validation : null;
    if (i18n) return i18n;
    if (langCode().indexOf('ar') === 0) {
      return {
        valueMissing: 'يرجى تعبئة هذا الحقل.',
        valueMissingNamed: 'يرجى تعبئة حقل «:field».',
        valueMissingSelect: 'يرجى اختيار عنصر من القائمة.',
        valueMissingSelectNamed: 'يرجى الاختيار في حقل «:field».',
        valueMissingFile: 'يرجى اختيار ملف.',
        valueMissingFileNamed: 'يرجى اختيار ملف في حقل «:field».',
        typeMismatchEmail: 'يرجى إدخال بريد إلكتروني صالح.',
        typeMismatchUrl: 'يرجى إدخال رابط صالح.',
        typeMismatch: 'يرجى إدخال قيمة بالصيغة الصحيحة.',
        patternMismatch: 'يرجى مطابقة الصيغة المطلوبة.',
        tooShort: 'النص أقصر من الحد الأدنى المسموح.',
        tooLong: 'النص أطول من الحد الأقصى المسموح.',
        rangeUnderflow: 'القيمة أصغر من الحد الأدنى المسموح.',
        rangeOverflow: 'القيمة أكبر من الحد الأقصى المسموح.',
        stepMismatch: 'يرجى إدخال قيمة صحيحة وفق الخطوة المحددة.',
        badInput: 'يرجى إدخال قيمة صحيحة.'
      };
    }
    return null;
  }

  function cleanLabel(text) {
    return String(text || '').replace(/\*/g, '').replace(/\s+/g, ' ').trim();
  }

  function fieldLabel(el) {
    if (!el) return '';
    var explicit = el.getAttribute('data-field-label') || el.getAttribute('aria-label') || '';
    if (explicit) return cleanLabel(explicit);
    if (el.id && window.CSS && CSS.escape) {
      var lbl = document.querySelector('label[for="' + CSS.escape(el.id) + '"]');
      if (lbl && !lbl.classList.contains('visually-hidden') && !lbl.contains(el)) {
        return cleanLabel(lbl.textContent);
      }
    }
    var card = el.closest ? el.closest('.corr-field-card, .mb-3, .mb-2') : null;
    if (card) {
      var title = card.querySelector('.corr-field-card-title, .form-label, label');
      if (title && !title.classList.contains('visually-hidden') && !title.contains(el)) {
        return cleanLabel(title.textContent);
      }
    }
    return cleanLabel(el.getAttribute('placeholder') || '');
  }

  function named(template, fallback, name) {
    if (name && template && template.indexOf(':field') !== -1) {
      return template.replace(':field', name);
    }
    return fallback || '';
  }

  function messageFor(el) {
    var m = messages();
    if (!m || !el || !el.validity) return '';
    var v = el.validity;
    if (v.valueMissing) {
      var custom = el.getAttribute('data-value-missing') || '';
      if (custom) return custom;
      var name = fieldLabel(el);
      if (el.tagName === 'SELECT') return named(m.valueMissingSelectNamed, m.valueMissingSelect || m.valueMissing, name);
      if (el.type === 'file') return named(m.valueMissingFileNamed, m.valueMissingFile || m.valueMissing, name);
      return named(m.valueMissingNamed, m.valueMissing, name);
    }
    if (v.typeMismatch) {
      if (el.type === 'email') return m.typeMismatchEmail || m.typeMismatch || '';
      if (el.type === 'url') return m.typeMismatchUrl || m.typeMismatch || '';
      return m.typeMismatch || '';
    }
    if (v.patternMismatch) return m.patternMismatch || '';
    if (v.tooShort) return m.tooShort || '';
    if (v.tooLong) return m.tooLong || '';
    if (v.rangeUnderflow) return m.rangeUnderflow || '';
    if (v.rangeOverflow) return m.rangeOverflow || '';
    if (v.stepMismatch) return m.stepMismatch || '';
    if (v.badInput) return m.badInput || '';
    return '';
  }

  document.addEventListener('invalid', function (e) {
    var el = e.target;
    if (!el || typeof el.setCustomValidity !== 'function') return;
    if (el.validity && el.validity.customError) {
      return;
    }
    if (langCode().indexOf('ar') !== 0) {
      el.setCustomValidity('');
      return;
    }
    var msg = messageFor(el);
    el.setCustomValidity(msg || 'يرجى تعبئة هذا الحقل.');
  }, true);

  function clearCustom(e) {
    var el = e.target;
    if (!el || typeof el.setCustomValidity !== 'function') return;
    el.setCustomValidity('');
    if (el.classList) el.classList.remove('is-invalid');
  }
  document.addEventListener('input', clearCustom, true);
  document.addEventListener('change', clearCustom, true);
})();
