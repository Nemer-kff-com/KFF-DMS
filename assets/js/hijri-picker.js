(function () {
  var cfg = window.__DMS_HIJRI__ || {};
  var lengths = cfg.lengths || [];
  var monthNames = cfg.months || [];
  var weekdays = cfg.weekdays || [];
  var minYear = cfg.minYear || 1356;
  var maxYear = cfg.maxYear || 1448;
  var table = null;
  var popup = null;
  var activeInput = null;
  var viewHy = maxYear;
  var viewHm = 1;
  var syncing = false;

  function idiv(a, b) {
    return Math.trunc(a / b);
  }

  function mjdnTable() {
    if (table) return table;
    var sum = 28607;
    table = [sum];
    for (var i = 0; i < lengths.length; i++) {
      sum += lengths[i];
      table.push(sum);
    }
    return table;
  }

  function g2d(gy, gm, gd) {
    var d = idiv((gy + idiv(gm - 8, 6) + 100100) * 1461, 4)
      + idiv(153 * ((gm + 9) % 12) + 2, 5)
      + gd - 34840408;
    return d - idiv(idiv(gy + 100100 + idiv(gm - 8, 6), 100) * 3, 4) + 752;
  }

  function fromGregorian(gy, gm, gd) {
    var t = mjdnTable();
    var mjdn = g2d(gy, gm, gd) - 2400000;
    var i = null;
    for (var idx = 0; idx < t.length; idx++) {
      if (t[idx] > mjdn) {
        i = idx;
        break;
      }
    }
    if (i === null || i < 1) return null;
    var totalMonths = i + 16260;
    var cYears = idiv(totalMonths - 1, 12);
    var hy = cYears + 1;
    var hm = totalMonths - 12 * cYears;
    var hd = mjdn - t[i - 1] + 1;
    if (hy < minYear || hy > 1500 || hm < 1 || hm > 12 || hd < 1) return null;
    return [hy, hm, hd];
  }

  function h2d(hy, hm, hd) {
    var t = mjdnTable();
    var i = ((hy - 1) * 12) + hm - 16260;
    if (i < 1 || i >= t.length) return null;
    return hd + t[i - 1] - 1 + 2400000;
  }

  function d2g(jdn) {
    var j = 4 * jdn + 139361631;
    j = j + idiv(idiv(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
    var i = idiv(j % 1461, 4) * 5 + 308;
    var gd = idiv(i % 153, 5) + 1;
    var gm = (idiv(i, 153) % 12) + 1;
    var gy = idiv(j, 1461) - 100100 + idiv(8 - gm, 6);
    return [gy, gm, gd];
  }

  function toGregorian(hy, hm, hd) {
    var jdn = h2d(hy, hm, hd);
    if (jdn == null) return null;
    return d2g(jdn);
  }

  function monthLen(hy, hm) {
    var i = ((hy - 1) * 12) + hm - 16260;
    return lengths[i - 1] || 0;
  }

  function pad(n) {
    return (n < 10 ? '0' : '') + n;
  }

  function fmtH(hy, hm, hd) {
    return hy + '/' + pad(hm) + '/' + pad(hd);
  }

  function fmtG(gy, gm, gd) {
    return gy + '-' + pad(gm) + '-' + pad(gd);
  }

  function fmtGSlash(gy, gm, gd) {
    /* نفس عرض قائمة الصادر والفلاتر: سنة/شهر/يوم */
    return gy + '/' + pad(gm) + '/' + pad(gd);
  }

  function gregorianDisplayOf(gInput) {
    return document.querySelector('[data-date-input="' + gInput.id + '"]');
  }

  function setGregorianParts(gInput, gy, gm, gd) {
    gInput.value = fmtG(gy, gm, gd);
    var disp = gregorianDisplayOf(gInput);
    if (disp) disp.value = fmtGSlash(gy, gm, gd);
  }

  function clearGregorian(gInput) {
    gInput.value = '';
    var disp = gregorianDisplayOf(gInput);
    if (disp) disp.value = '';
  }

  function parseH(raw) {
    raw = String(raw || '').replace(/[هـhH\s]/g, '');
    var m = raw.match(/^(\d{1,4})[\/\-.](\d{1,2})[\/\-.](\d{1,4})$/);
    if (!m) return null;
    var a = +m[1], b = +m[2], c = +m[3];
    var hy, hm, hd;
    if (a >= minYear && a <= maxYear) {
      hy = a; hm = b; hd = c;
    } else if (c >= minYear && c <= maxYear) {
      hd = a; hm = b; hy = c;
    } else {
      return null;
    }
    if (hm < 1 || hm > 12 || hd < 1 || hd > 30) return null;
    return [hy, hm, hd];
  }

  function parseG(raw) {
    var m = String(raw || '').trim().match(/^(\d{1,4})[\/\-.](\d{1,2})[\/\-.](\d{1,4})$/);
    if (!m) return null;
    var a = +m[1], b = +m[2], c = +m[3];
    var gy, gm, gd;
    if (a >= 1000) {
      gy = a; gm = b; gd = c;
    } else if (c >= 1000) {
      gd = a; gm = b; gy = c;
    } else {
      return null;
    }
    if (gm < 1 || gm > 12 || gd < 1 || gd > 31) return null;
    return [gy, gm, gd];
  }

  function openGregorianPicker(gInput) {
    if (!gInput) return;
    if (typeof gInput.showPicker === 'function') {
      try {
        gInput.showPicker();
        return;
      } catch (err) {}
    }
    try { gInput.focus(); gInput.click(); } catch (err2) {}
  }

  function bindGregorianField(gInput) {
    if (!gInput || gInput.dataset.hijriBound === '1') return;
    gInput.dataset.hijriBound = '1';
    gInput.addEventListener('change', function () {
      var parts = parseG(gInput.value);
      var disp = gregorianDisplayOf(gInput);
      if (disp) {
        disp.value = parts ? fmtGSlash(parts[0], parts[1], parts[2]) : '';
      }
      syncFromGregorian(gInput);
    });
    var disp = gregorianDisplayOf(gInput);
    if (disp && disp.dataset.hijriBound !== '1') {
      disp.dataset.hijriBound = '1';
      disp.addEventListener('change', function () {
        var g = parseG(disp.value);
        syncing = true;
        if (g) setGregorianParts(gInput, g[0], g[1], g[2]);
        else if (disp.value === '') gInput.value = '';
        syncing = false;
        syncFromGregorian(gInput);
      });
      disp.addEventListener('click', function () {
        openGregorianPicker(gInput);
      });
      disp.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openGregorianPicker(gInput);
        }
      });
    }
    if (gInput.value) {
      var cur = parseG(gInput.value);
      if (cur && disp) disp.value = fmtGSlash(cur[0], cur[1], cur[2]);
    }
  }

  function todayH() {
    var n = new Date();
    return fromGregorian(n.getFullYear(), n.getMonth() + 1, n.getDate());
  }

  function setValue(el, value) {
    if (!el) return;
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function gregorianOf(input) {
    var id = input.getAttribute('data-gregorian-target');
    return id ? document.getElementById(id) : null;
  }

  function hijriOf(input) {
    var id = input.getAttribute('data-hijri-target');
    return id ? document.getElementById(id) : null;
  }

  function syncFromGregorian(gInput) {
    if (syncing) return;
    var hInput = hijriOf(gInput);
    if (!hInput) return;
    var g = parseG(gInput.value);
    syncing = true;
    if (!g) {
      if (gInput.value === '') hInput.value = '';
    } else {
      var h = fromGregorian(g[0], g[1], g[2]);
      hInput.value = h ? fmtH(h[0], h[1], h[2]) : '';
      var disp = gregorianDisplayOf(gInput);
      if (disp) disp.value = fmtGSlash(g[0], g[1], g[2]);
    }
    syncing = false;
  }

  function syncFromHijri(hInput) {
    if (syncing) return;
    var gInput = gregorianOf(hInput);
    if (!gInput) return;
    var h = parseH(hInput.value);
    syncing = true;
    if (!h) {
      if (hInput.value === '') clearGregorian(gInput);
    } else {
      var g = toGregorian(h[0], h[1], h[2]);
      if (g) setGregorianParts(gInput, g[0], g[1], g[2]);
    }
    syncing = false;
  }

  function ensurePopup() {
    if (popup) return popup;
    popup = document.createElement('div');
    popup.className = 'hijri-picker';
    popup.hidden = true;
    popup.setAttribute('role', 'dialog');
    popup.innerHTML =
      '<div class="hijri-picker-nav">' +
        '<button type="button" data-nav="prev-y" aria-label="-1">&laquo;</button>' +
        '<button type="button" data-nav="prev-m" aria-label="-">&lsaquo;</button>' +
        '<select data-nav="month"></select>' +
        '<select data-nav="year"></select>' +
        '<button type="button" data-nav="next-m" aria-label="+">&rsaquo;</button>' +
        '<button type="button" data-nav="next-y" aria-label="+1">&raquo;</button>' +
      '</div>' +
      '<div class="hijri-picker-week"></div>' +
      '<div class="hijri-picker-days"></div>' +
      '<div class="hijri-picker-foot">' +
        '<button type="button" data-nav="today">' + (cfg.today || '') + '</button>' +
        '<button type="button" data-nav="clear">' + (cfg.clear || '') + '</button>' +
      '</div>';
    var week = popup.querySelector('.hijri-picker-week');
    for (var i = 0; i < 7; i++) {
      var s = document.createElement('span');
      s.textContent = weekdays[i] || '';
      week.appendChild(s);
    }
    var monthSel = popup.querySelector('[data-nav="month"]');
    for (var m = 1; m <= 12; m++) {
      var opt = document.createElement('option');
      opt.value = String(m);
      opt.textContent = monthNames[m - 1] || String(m);
      monthSel.appendChild(opt);
    }
    var yearSel = popup.querySelector('[data-nav="year"]');
    for (var y = minYear; y <= maxYear; y++) {
      var yo = document.createElement('option');
      yo.value = String(y);
      yo.textContent = String(y);
      yearSel.appendChild(yo);
    }
    popup.addEventListener('click', onPopupClick);
    popup.addEventListener('change', onPopupChange);
    document.body.appendChild(popup);
    return popup;
  }

  function selectedParts() {
    return activeInput ? parseH(activeInput.value) : null;
  }

  function renderDays() {
    if (!popup) return;
    popup.querySelector('[data-nav="month"]').value = String(viewHm);
    popup.querySelector('[data-nav="year"]').value = String(viewHy);
    var daysEl = popup.querySelector('.hijri-picker-days');
    daysEl.innerHTML = '';
    var dim = monthLen(viewHy, viewHm);
    var firstG = toGregorian(viewHy, viewHm, 1);
    var start = 0;
    if (firstG) {
      var wd = new Date(Date.UTC(firstG[0], firstG[1] - 1, firstG[2])).getUTCDay();
      start = (wd + 1) % 7;
    }
    var today = todayH();
    var sel = selectedParts();
    var i;
    for (i = 0; i < start; i++) {
      var empty = document.createElement('button');
      empty.type = 'button';
      empty.disabled = true;
      daysEl.appendChild(empty);
    }
    for (var d = 1; d <= dim; d++) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = String(d);
      btn.setAttribute('data-day', String(d));
      if (today && today[0] === viewHy && today[1] === viewHm && today[2] === d) {
        btn.classList.add('is-today');
      }
      if (sel && sel[0] === viewHy && sel[1] === viewHm && sel[2] === d) {
        btn.classList.add('is-selected');
      }
      daysEl.appendChild(btn);
    }
  }

  function placePopup(anchor) {
    var box = ensurePopup();
    box.hidden = false;
    var r = anchor.getBoundingClientRect();
    var w = box.offsetWidth || 284;
    var h = box.offsetHeight || 280;
    var rtl = (document.documentElement.getAttribute('dir') || 'rtl') === 'rtl';
    var left = rtl ? r.right - w : r.left;
    var top = r.bottom + 6;
    if (left < 8) left = 8;
    if (left + w > window.innerWidth - 8) left = window.innerWidth - w - 8;
    if (top + h > window.innerHeight - 8 && r.top - 6 - h > 8) {
      top = r.top - 6 - h;
    }
    box.style.left = left + 'px';
    box.style.top = top + 'px';
  }

  function openPicker(input) {
    activeInput = input;
    var h = parseH(input.value) || (function () {
      var gEl = gregorianOf(input);
      var g = gEl ? parseG(gEl.value) : null;
      return g ? fromGregorian(g[0], g[1], g[2]) : todayH();
    })();
    if (h) {
      viewHy = Math.min(maxYear, Math.max(minYear, h[0]));
      viewHm = h[1];
    }
    ensurePopup();
    renderDays();
    placePopup(input.closest('.hijri-date-field') || input);
  }

  function closePicker() {
    if (popup) popup.hidden = true;
    activeInput = null;
  }

  function applyHijri(hy, hm, hd) {
    if (!activeInput) return;
    var input = activeInput;
    syncing = true;
    input.value = fmtH(hy, hm, hd);
    var g = toGregorian(hy, hm, hd);
    var gInput = gregorianOf(input);
    if (gInput && g) setGregorianParts(gInput, g[0], g[1], g[2]);
    syncing = false;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    closePicker();
  }

  function shiftMonth(delta) {
    viewHm += delta;
    while (viewHm < 1) {
      viewHm += 12;
      viewHy -= 1;
    }
    while (viewHm > 12) {
      viewHm -= 12;
      viewHy += 1;
    }
    if (viewHy < minYear) { viewHy = minYear; viewHm = 1; }
    if (viewHy > maxYear) { viewHy = maxYear; viewHm = 12; }
    renderDays();
    if (activeInput) placePopup(activeInput.closest('.hijri-date-field') || activeInput);
  }

  function onPopupClick(e) {
    var nav = e.target.closest('[data-nav]');
    var dayBtn = e.target.closest('[data-day]');
    if (dayBtn) {
      applyHijri(viewHy, viewHm, +dayBtn.getAttribute('data-day'));
      return;
    }
    if (!nav) return;
    var act = nav.getAttribute('data-nav');
    if (act === 'prev-m') shiftMonth(-1);
    else if (act === 'next-m') shiftMonth(1);
    else if (act === 'prev-y') { viewHy = Math.max(minYear, viewHy - 1); renderDays(); }
    else if (act === 'next-y') { viewHy = Math.min(maxYear, viewHy + 1); renderDays(); }
    else if (act === 'today') {
      var t = todayH();
      if (t) applyHijri(t[0], t[1], t[2]);
    } else if (act === 'clear' && activeInput) {
      var input = activeInput;
      syncing = true;
      input.value = '';
      var gInput = gregorianOf(input);
      if (gInput) clearGregorian(gInput);
      syncing = false;
      input.dispatchEvent(new Event('change', { bubbles: true }));
      closePicker();
    }
  }

  function onPopupChange(e) {
    var nav = e.target.getAttribute('data-nav');
    if (nav === 'month') {
      viewHm = +e.target.value;
      renderDays();
    } else if (nav === 'year') {
      viewHy = +e.target.value;
      renderDays();
    }
  }

  function bind() {
    document.querySelectorAll('.gregorian-date-native').forEach(bindGregorianField);
    if (lengths.length) {
      document.querySelectorAll('.hijri-date-input').forEach(function (input) {
        if (input.dataset.hijriBound) return;
        input.dataset.hijriBound = '1';
        var wrap = input.closest('.hijri-date-field');
        var btn = wrap ? wrap.querySelector('.hijri-date-btn') : null;
        input.addEventListener('focus', function () { openPicker(input); });
        input.addEventListener('click', function () { openPicker(input); });
        input.addEventListener('change', function () { syncFromHijri(input); });
        if (btn) {
          btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (popup && !popup.hidden && activeInput === input) closePicker();
            else openPicker(input);
          });
        }
        var gInput = gregorianOf(input);
        if (gInput) {
          bindGregorianField(gInput);
          if (gInput.value && !input.value) syncFromGregorian(gInput);
          else if (input.value && !gInput.value) syncFromHijri(input);
        }
      });
    }
    document.querySelectorAll('form').forEach(function (form) {
      if (form.dataset.dateSync || !form.querySelector('.gregorian-date-display')) return;
      form.dataset.dateSync = '1';
      form.addEventListener('submit', function () {
        form.querySelectorAll('.gregorian-date-display').forEach(function (disp) {
          var native = document.getElementById(disp.getAttribute('data-date-input') || '');
          var g = parseG(disp.value);
          if (native && g) native.value = fmtG(g[0], g[1], g[2]);
        });
      });
    });
  }

  document.addEventListener('mousedown', function (e) {
    if (!popup || popup.hidden) return;
    if (popup.contains(e.target)) return;
    if (e.target.closest('.hijri-date-field')) return;
    closePicker();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closePicker();
  });
  window.addEventListener('resize', closePicker);
  document.addEventListener('scroll', function (e) {
    if (!popup || popup.hidden) return;
    if (popup.contains(e.target)) return;
    closePicker();
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
