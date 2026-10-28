/* أزرار النظام: أيقونة + تلميح يوضح الوظيفة */
(function () {
  var PROCESSED = 'dms-iconified';

  function isRtl() {
    return (document.documentElement.getAttribute('dir') || 'rtl') === 'rtl';
  }

  function visibleLabel(btn) {
    var clone = btn.cloneNode(true);
    clone.querySelectorAll('i, svg, .visually-hidden, .badge, .topbar-notify-badge').forEach(function (n) {
      n.remove();
    });
    return String(clone.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function existingIconName(btn) {
    var icon = btn.querySelector('i.bi, i[class*="bi-"], i.fa, i[class*="fa-"]');
    if (!icon) {
      return '';
    }
    var cls = icon.className || '';
    var m = cls.match(/\bbi-([a-z0-9-]+)\b/i);
    if (m) {
      return 'bi-' + m[1];
    }
    m = cls.match(/\b(fa-[a-z0-9-]+)\b/i);
    return m ? m[1] : '';
  }

  function guessIcon(label, rtl) {
    var t = String(label || '').replace(/\s+/g, ' ').trim();
    if (!t) {
      return '';
    }
    var back = rtl ? 'bi-arrow-right' : 'bi-arrow-left';
    var rules = [
      [/ورقة\s*عرض|briefing/i, 'bi-file-earmark-ruled'],
      [/تصدير\s*و[وo]rd|export\s*word|\bword\b|ورد/i, 'bi-file-earmark-word'],
      [/تصدير\s*excel|export\s*excel|excel/i, 'bi-file-earmark-excel'],
      [/تصدير\s*csv|export\s*csv|\bcsv\b/i, 'bi-filetype-csv'],
      [/تصدير|export|تنزيل|تحميل/i, 'bi-download'],
      [/ملصق حراري|thermal/i, 'bi-upc'],
      [/ملصق|طابع|ظرف|label|envelope|postage/i, 'bi-sticky'],
      [/طباعة|print/i, 'bi-printer'],
      [/واتساب|whatsapp/i, 'bi-whatsapp'],
      [/بريد|email/i, 'bi-envelope'],
      [/تسليم|delivery/i, 'bi-clipboard-check'],
      [/حفظ بيانات الاستلام|تسجيل الاستلام/i, 'bi-clipboard-check'],
      [/حفظ التعديل|حفظ ورقة|حفظ الوارد|حفظ الصادر|\bsave\b/i, 'bi-check2'],
      [/^حفظ$|^save$/i, 'bi-check2'],
      [/تسجيل التوجيه|decide/i, 'bi-pen'],
      [/تأكيد الإلغاء|إلغاء\s*\/\s*سحب|سحب الصادر/i, 'bi-x-octagon'],
      [/^إلغاء$|^cancel$/i, 'bi-x-lg'],
      [/رجوع|عودة|\bback\b/i, back],
      [/تعديل|\bedit\b/i, 'bi-pencil'],
      [/حذف|delete|remove/i, 'bi-trash'],
      [/بحث|\bsearch\b/i, 'bi-search'],
      [/تصفية|\bfilter\b/i, 'bi-funnel'],
      [/إضافة بند|إضافة خيار|إضافة|\badd\b/i, 'bi-plus-lg'],
      [/إنشاء رد|رد صادر|create reply/i, 'bi-send'],
      [/عرض الرد|view reply/i, 'bi-link-45deg'],
      [/المعاملات المرتبطة|linked correspondence/i, 'bi-link-45deg'],
      [/مراجعة الارتباط|review link/i, 'bi-link-45deg'],
      [/رفع مرفق|رفع|\bupload\b/i, 'bi-upload'],
      [/إرسال|\bsend\b/i, 'bi-send'],
      [/إحالة|\brefer\b/i, 'bi-arrow-left-right'],
      [/استلام|\breceive\b/i, 'bi-inbox'],
      [/إكمال|\bcomplete\b/i, 'bi-check2-all'],
      [/فتح|عرض|\bopen\b|\bview\b/i, 'bi-eye'],
      [/نسخ.*رمز|رمز qr|copy qr/i, 'bi-qr-code'],
      [/نسخ|\bcopy\b/i, 'bi-clipboard'],
      [/خروج|\blogout\b/i, 'fa-sign-out-alt'],
      [/دخول|\blogin\b/i, 'bi-box-arrow-in-right'],
      [/دفعة|\bbatch\b/i, 'bi-collection'],
      [/مستلم|recipients|people/i, 'bi-people'],
      [/ربط|\bconnect\b/i, 'bi-cloud-plus'],
      [/استرجاع|\brestore\b/i, 'bi-upload'],
      [/تحقق|\bverify\b/i, 'bi-shield-check']
    ];
    var i;
    for (i = 0; i < rules.length; i++) {
      if (rules[i][0].test(t)) {
        return rules[i][1];
      }
    }
    return '';
  }

  function shouldSkip(btn) {
    if (!btn || btn.nodeType !== 1) {
      return true;
    }
    if (btn.classList.contains(PROCESSED) || btn.classList.contains('btn-close')) {
      return true;
    }
    if (btn.hasAttribute('data-keep-label')) {
      return true;
    }
    if (btn.classList.contains('dropdown-toggle') || btn.classList.contains('page-link')) {
      return true;
    }
    if (btn.classList.contains('letter-token-btn') || btn.classList.contains('comment-quick')) {
      return true;
    }
    if (btn.classList.contains('sidebar-rail-btn') || btn.classList.contains('gregorian-date-btn')) {
      return true;
    }
    if (btn.getAttribute('name') === 'rsvp_response') {
      return true;
    }
    if (btn.closest('.login-card, .login-form, .pagination, .hijri-picker, .dropdown-menu')) {
      return true;
    }
    if (btn.closest('.print-toolbar, .print-label-toolbar, .no-print, .corr-decide-actions')) {
      return true;
    }
    if (btn.closest('.btn-group') && !btn.querySelector('i')) {
      return true;
    }
    return false;
  }

  function keepInner(btn) {
    if (btn.querySelector('.badge, .topbar-notify-badge, select, input, img')) {
      return true;
    }
    var toggle = btn.getAttribute('data-bs-toggle') || '';
    if (toggle === 'dropdown' || toggle === 'tab' || toggle === 'collapse' || toggle === 'pill') {
      return true;
    }
    if (visibleLabel(btn) === '' && btn.querySelector('i, svg')) {
      return true;
    }
    return false;
  }

  function attachTip(btn, title) {
    if (!title) {
      return;
    }
    btn.setAttribute('title', title);
    btn.setAttribute('aria-label', title);
    if (!window.bootstrap || !bootstrap.Tooltip) {
      return;
    }
    var toggle = btn.getAttribute('data-bs-toggle');
    var opts = { container: 'body', placement: 'top', fallbackPlacements: ['top'], trigger: 'hover focus', title: title };
    btn.setAttribute('data-bs-placement', 'top');
    if (toggle && toggle !== 'tooltip') {
      bootstrap.Tooltip.getOrCreateInstance(btn, opts);
      return;
    }
    if (!toggle) {
      btn.setAttribute('data-bs-toggle', 'tooltip');
    }
    var inst = bootstrap.Tooltip.getInstance(btn);
    if (inst) {
      btn.setAttribute('data-bs-original-title', title);
      if (typeof inst.setContent === 'function') {
        inst.setContent({ '.tooltip-inner': title });
      }
      return;
    }
    bootstrap.Tooltip.getOrCreateInstance(btn, opts);
  }

  function iconifyOne(btn) {
    if (shouldSkip(btn)) {
      return;
    }
    if (btn.classList.contains('btn-icon-add')) {
      btn.classList.add(PROCESSED);
      attachTip(btn, btn.getAttribute('title') || btn.getAttribute('aria-label') || visibleLabel(btn));
      return;
    }

    var label = visibleLabel(btn) || btn.getAttribute('aria-label') || btn.getAttribute('title') || '';
    var iconName = existingIconName(btn);
    if (!iconName) {
      iconName = guessIcon(label, isRtl());
    }
    if (!iconName) {
      return;
    }

    btn.classList.add('btn-icon', PROCESSED);
    btn.classList.remove('w-100');

    if (!keepInner(btn)) {
      btn.innerHTML = '';
      var i = document.createElement('i');
      i.className = (iconName.indexOf('fa-') === 0 ? 'fas ' : 'bi ') + iconName;
      i.setAttribute('aria-hidden', 'true');
      btn.appendChild(i);
    }

    attachTip(btn, label || btn.getAttribute('title') || iconName);
  }

  function iconify(root) {
    root = root || document;
    var scope = root.querySelectorAll ? root : document;
    if (scope !== document && scope.classList && scope.classList.contains('btn')) {
      iconifyOne(scope);
    }
    if (scope.querySelectorAll) {
      scope.querySelectorAll('a.btn, button.btn').forEach(iconifyOne);
    }
  }

  window.dmsIconifyButtons = iconify;
  window.dmsSetBtnTip = function (el, text) {
    if (!el || !text) {
      return;
    }
    attachTip(el, text);
  };

  function startObserver() {
    if (!document.body || document.body.getAttribute('data-dms-icon-obs') === '1') {
      return;
    }
    document.body.setAttribute('data-dms-icon-obs', '1');
    var timer = null;
    var pending = [];
    var obs = new MutationObserver(function (mutations) {
      var i, j, node;
      for (i = 0; i < mutations.length; i++) {
        for (j = 0; j < mutations[i].addedNodes.length; j++) {
          node = mutations[i].addedNodes[j];
          if (node.nodeType !== 1) {
            continue;
          }
          if (node.classList && node.classList.contains('btn')) {
            pending.push(node);
          } else if (node.querySelectorAll) {
            node.querySelectorAll('a.btn, button.btn').forEach(function (b) {
              pending.push(b);
            });
          }
        }
      }
      if (!pending.length) {
        return;
      }
      clearTimeout(timer);
      timer = setTimeout(function () {
        var list = pending;
        pending = [];
        list.forEach(iconifyOne);
      }, 40);
    });
    obs.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startObserver);
  } else {
    startObserver();
  }
})();

document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.getElementById('sidebarToggle');
  var closeBtn = document.getElementById('sidebarClose');
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  var railBtn = document.getElementById('sidebarRailToggle');
  var storageKey = 'dms_sidebar_collapsed';
  var desktopMq = window.matchMedia('(min-width: 993px)');

  function isMobileNav() {
    return !desktopMq.matches;
  }

  function isRtl() {
    return (document.documentElement.getAttribute('dir') || 'rtl') === 'rtl';
  }

  function isCollapsed() {
    return document.body.classList.contains('sidebar-collapsed');
  }

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('open');
    document.body.classList.add('sidebar-open');
    if (backdrop) backdrop.classList.add('show');
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('open');
    document.body.classList.remove('sidebar-open');
    if (backdrop) backdrop.classList.remove('show');
  }

  function toggleSidebar() {
    if (sidebar && sidebar.classList.contains('open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  }

  function updateRailButton() {
    if (!railBtn) return;
    var icon = railBtn.querySelector('i');
    var hideLabel = railBtn.getAttribute('data-hide-label') || '';
    var showLabel = railBtn.getAttribute('data-show-label') || '';
    var collapsed = isCollapsed();
    if (icon) {
      if (collapsed) {
        icon.className = isRtl() ? 'bi bi-chevron-left' : 'bi bi-chevron-right';
      } else {
        icon.className = isRtl() ? 'bi bi-chevron-right' : 'bi bi-chevron-left';
      }
    }
    var label = collapsed ? showLabel : hideLabel;
    railBtn.setAttribute('aria-label', label);
    railBtn.setAttribute('title', label);
    railBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  }

  function setDesktopCollapsed(collapsed, persist) {
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    if (persist !== false) {
      try {
        localStorage.setItem(storageKey, collapsed ? '1' : '0');
      } catch (e) {}
    }
    updateRailButton();
  }

  function syncSidebarLayout() {
    if (isMobileNav()) {
      document.body.classList.remove('sidebar-collapsed');
      closeSidebar();
      updateRailButton();
      return;
    }
    closeSidebar();
    var stored = false;
    try {
      stored = localStorage.getItem(storageKey) === '1';
    } catch (e) {}
    setDesktopCollapsed(stored, false);
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      toggleSidebar();
    });
  }
  if (railBtn) {
    railBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (isMobileNav()) {
        toggleSidebar();
        return;
      }
      setDesktopCollapsed(!isCollapsed());
    });
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', function (e) {
      e.preventDefault();
      closeSidebar();
    });
  }
  if (backdrop) {
    backdrop.addEventListener('click', closeSidebar);
  }
  if (typeof desktopMq.addEventListener === 'function') {
    desktopMq.addEventListener('change', syncSidebarLayout);
  } else if (typeof desktopMq.addListener === 'function') {
    desktopMq.addListener(syncSidebarLayout);
  }
  updateRailButton();

  // إغلاق القائمة بعد اختيار رابط على الجوال (وليس زر القائمة الفرعية)
  if (sidebar) {
    sidebar.querySelectorAll('a.nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        if (isMobileNav()) closeSidebar();
      });
    });

    sidebar.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var group = btn.closest('[data-nav-group]');
        if (!group) return;
        var submenu = group.querySelector('.nav-submenu');
        var open = group.classList.toggle('open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (submenu) {
          if (open) submenu.removeAttribute('hidden');
          else submenu.setAttribute('hidden', '');
        }
      });
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  window.addEventListener('resize', function () {
    if (!isMobileNav()) closeSidebar();
  });

  // خروج تلقائي بعد مدة الخمول المحددة في الإعدادات
  var idleMs = window.__DMS_IDLE_MS__;
  var logoutUrl = window.__DMS_LOGOUT_URL__;
  if (idleMs && logoutUrl) {
    var idleTimer = null;
    function resetIdleTimer() {
      if (idleTimer) clearTimeout(idleTimer);
      idleTimer = setTimeout(function () {
        window.location.href = logoutUrl;
      }, idleMs);
    }
    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (ev) {
      document.addEventListener(ev, resetIdleTimer, { passive: true });
    });
    resetIdleTimer();
  }

  if (typeof window.dmsIconifyButtons === 'function') {
    window.dmsIconifyButtons(document);
  }

  if (window.bootstrap && bootstrap.Tooltip) {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      var isBtn = el.matches('button, a.btn, .btn, label.btn');
      var place = el.getAttribute('data-bs-placement') || '';
      var opts = {};
      if (isBtn || place === 'bottom' || place === 'left' || place === 'right') {
        opts.placement = 'top';
        opts.fallbackPlacements = ['top'];
        el.setAttribute('data-bs-placement', 'top');
      }
      if (el.getAttribute('data-tip-edge') === 'start') {
        opts.customClass = 'corr-tip-start';
        opts.placement = 'top';
        opts.fallbackPlacements = ['top'];
        opts.popperConfig = function (defaults) {
          return Object.assign({}, defaults, { placement: 'top' });
        };
      }
      bootstrap.Tooltip.getOrCreateInstance(el, opts);
    });
  }

  bindCopyActions(document);
  bindLetterTokenToolbar(document);
  bindLetterPreview(document);
  bindOfficerPicks(document);
});

function bindOfficerPicks(root) {
  (root || document).querySelectorAll('select.corr-officer-pick').forEach(function (select) {
    if (select.dataset.officerBound === '1') return;
    select.dataset.officerBound = '1';
    var wrap = document.createElement('div');
    wrap.className = 'corr-officer-pick';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('corr-officer-pick-native');

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'form-select corr-officer-pick-btn' + (select.classList.contains('form-select-sm') ? ' form-select-sm' : '');
    btn.disabled = select.disabled;
    btn.setAttribute('aria-haspopup', 'listbox');
    var nameEl = document.createElement('span');
    nameEl.className = 'corr-officer-pick-name';
    var officeEl = document.createElement('span');
    officeEl.className = 'corr-officer-pick-office';
    btn.appendChild(nameEl);
    btn.appendChild(officeEl);

    var menu = document.createElement('div');
    menu.className = 'corr-officer-pick-menu';
    menu.setAttribute('role', 'listbox');
    menu.hidden = true;
    wrap.appendChild(btn);
    wrap.appendChild(menu);

    function paint(option) {
      var text = option ? option.textContent.trim() : '';
      var office = option ? (option.getAttribute('data-office') || '') : '';
      nameEl.textContent = text;
      officeEl.textContent = office;
      officeEl.hidden = office === '';
      menu.querySelectorAll('.corr-officer-pick-opt').forEach(function (item) {
        item.classList.toggle('is-active', item.getAttribute('data-value') === select.value);
      });
    }

    function closeMenu() {
      menu.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
    }

    function placeMenu() {
      var rect = btn.getBoundingClientRect();
      var gap = 4;
      var spaceBelow = window.innerHeight - rect.bottom - gap;
      var spaceAbove = rect.top - gap;
      var openUp = spaceBelow < 220 && spaceAbove > spaceBelow;
      var room = Math.max(140, Math.min(320, openUp ? spaceAbove : spaceBelow));
      menu.style.width = rect.width + 'px';
      menu.style.left = rect.left + 'px';
      menu.style.maxHeight = room + 'px';
      if (openUp) {
        menu.style.top = 'auto';
        menu.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
      } else {
        menu.style.bottom = 'auto';
        menu.style.top = (rect.bottom + gap) + 'px';
      }
    }

    function openMenu() {
      if (btn.disabled) return;
      if (menu.parentNode !== document.body) {
        document.body.appendChild(menu);
      }
      menu.hidden = false;
      placeMenu();
      btn.setAttribute('aria-expanded', 'true');
    }

    Array.prototype.forEach.call(select.options, function (option) {
      var item = document.createElement('button');
      item.type = 'button';
      item.className = 'corr-officer-pick-opt';
      item.setAttribute('data-value', option.value);
      var itemName = document.createElement('span');
      itemName.className = 'corr-officer-pick-name';
      itemName.textContent = option.textContent.trim();
      item.appendChild(itemName);
      var office = option.getAttribute('data-office') || '';
      if (office !== '') {
        var itemOffice = document.createElement('span');
        itemOffice.className = 'corr-officer-pick-office';
        itemOffice.textContent = office;
        item.appendChild(itemOffice);
      }
      item.addEventListener('click', function () {
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        paint(option);
        btn.classList.remove('is-invalid');
        closeMenu();
      });
      menu.appendChild(item);
    });

    paint(select.options[select.selectedIndex] || select.options[0]);
    btn.addEventListener('click', function () {
      if (menu.hidden) openMenu();
      else closeMenu();
    });
    document.addEventListener('click', function (e) {
      if (!wrap.contains(e.target) && !menu.contains(e.target)) closeMenu();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });
    window.addEventListener('resize', function () {
      if (!menu.hidden) placeMenu();
    });
    window.addEventListener('scroll', function () {
      if (!menu.hidden) placeMenu();
    }, true);
    select.addEventListener('invalid', function (e) {
      e.preventDefault();
      btn.classList.add('is-invalid');
      btn.focus();
    });
  });
}

function bindLetterTokenToolbar(root) {
  root = root || document;
  root.querySelectorAll('[data-insert-target]').forEach(function (bar) {
    if (bar.getAttribute('data-bound') === '1') {
      return;
    }
    bar.setAttribute('data-bound', '1');
    var undoStack = [];
    var undoBtn = bar.querySelector('[data-letter-undo]');
    function setUndoEnabled() {
      if (!undoBtn) {
        return;
      }
      undoBtn.disabled = undoStack.length === 0;
    }
    function hideTip(btn) {
      if (window.bootstrap && bootstrap.Tooltip) {
        var tip = bootstrap.Tooltip.getInstance(btn);
        if (tip) {
          tip.hide();
        }
      }
    }
    function stripLetterFormatting(text) {
      return String(text || '')
        .replace(/\{font:[a-z0-9_]+\}/gi, '')
        .replace(/\{\/font\}/gi, '')
        .replace(/\{ls:[0-9]+\}/gi, '')
        .replace(/\{\/ls\}/gi, '')
        .replace(/\{\/?(?:b|u|big|center|right|left|justify|ul)\}/gi, '');
    }
    function pushUndo(ta) {
      undoStack.push({
        value: ta.value,
        start: typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length,
        end: typeof ta.selectionEnd === 'number' ? ta.selectionEnd : ta.value.length
      });
      if (undoStack.length > 40) {
        undoStack.shift();
      }
      setUndoEnabled();
    }
    function applyWrap(ta, wrapOpen, wrapClose, asParagraph) {
      ta.focus();
      var start = typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length;
      var end = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : ta.value.length;
      var value = ta.value;
      var origStart = start;
      var origEnd = end;
      if (start === end) {
        if (asParagraph) {
          var paraStart = value.lastIndexOf('\n\n', start - 1);
          start = paraStart < 0 ? 0 : paraStart + 2;
          var paraEnd = value.indexOf('\n\n', end);
          end = paraEnd < 0 ? value.length : paraEnd;
        } else {
          var lineStart = value.lastIndexOf('\n', start - 1) + 1;
          var lineEnd = value.indexOf('\n', end);
          if (lineEnd < 0) {
            lineEnd = value.length;
          }
          start = lineStart;
          end = lineEnd;
        }
      }
      var insert = wrapOpen + value.slice(start, end) + wrapClose;
      undoStack.push({ value: value, start: origStart, end: origEnd });
      if (undoStack.length > 40) {
        undoStack.shift();
      }
      ta.value = value.slice(0, start) + insert + value.slice(end);
      try {
        ta.setSelectionRange(start + wrapOpen.length, start + wrapOpen.length + (end - start));
      } catch (err) { /* تجاهل */ }
      ta.dispatchEvent(new Event('input', { bubbles: true }));
      setUndoEnabled();
    }
    var targetId = bar.getAttribute('data-insert-target') || '';
    var targetTa = targetId ? document.getElementById(targetId) : null;
    var lsSel = bar.querySelector('[data-letter-ls]');
    if (lsSel) {
      lsSel.addEventListener('change', function () {
        var code = (lsSel.value || '').replace(/[^0-9]/g, '');
        lsSel.value = '';
        if (!code) {
          return;
        }
        var taLs = document.getElementById(bar.getAttribute('data-insert-target') || '');
        if (!taLs) {
          return;
        }
        applyWrap(taLs, '{ls:' + code + '}', '{/ls}', true);
        hideTip(lsSel.closest('.letter-token-btn') || lsSel);
      });
    }
    setUndoEnabled();
    bar.addEventListener('click', function (e) {
      var undoHit = e.target.closest('[data-letter-undo]');
      if (undoHit && bar.contains(undoHit)) {
        var taUndo = document.getElementById(bar.getAttribute('data-insert-target') || '');
        var prev = undoStack.pop();
        if (taUndo && prev) {
          taUndo.focus();
          taUndo.value = prev.value;
          try {
            taUndo.setSelectionRange(prev.start, prev.end);
          } catch (err) { /* تجاهل */ }
          taUndo.dispatchEvent(new Event('input', { bubbles: true }));
        }
        setUndoEnabled();
        hideTip(undoHit);
        return;
      }
      var clearHit = e.target.closest('[data-letter-clear-format]');
      if (clearHit && bar.contains(clearHit)) {
        var taClear = document.getElementById(bar.getAttribute('data-insert-target') || '');
        if (taClear) {
          var src = taClear.value;
          var cStart = typeof taClear.selectionStart === 'number' ? taClear.selectionStart : 0;
          var cEnd = typeof taClear.selectionEnd === 'number' ? taClear.selectionEnd : src.length;
          if (cStart === cEnd) {
            cStart = 0;
            cEnd = src.length;
          }
          var cleaned = stripLetterFormatting(src.slice(cStart, cEnd));
          if (cleaned !== src.slice(cStart, cEnd)) {
            pushUndo(taClear);
            taClear.value = src.slice(0, cStart) + cleaned + src.slice(cEnd);
            try {
              taClear.setSelectionRange(cStart, cStart + cleaned.length);
            } catch (err) { /* تجاهل */ }
            taClear.dispatchEvent(new Event('input', { bubbles: true }));
          }
          taClear.focus();
        }
        hideTip(clearHit);
        return;
      }
      var btn = e.target.closest('[data-insert-token], [data-insert-nl], [data-wrap-open]');
      if (!btn || !bar.contains(btn)) {
        return;
      }
      var ta = document.getElementById(bar.getAttribute('data-insert-target') || '');
      if (!ta) {
        return;
      }
      ta.focus();
      var start = typeof ta.selectionStart === 'number' ? ta.selectionStart : ta.value.length;
      var end = typeof ta.selectionEnd === 'number' ? ta.selectionEnd : ta.value.length;
      var value = ta.value;
      var wrapOpen = btn.getAttribute('data-wrap-open') || '';
      var wrapClose = btn.getAttribute('data-wrap-close') || '';
      var insert = '';
      var selStart = start;
      var selEnd = end;
      if (wrapOpen) {
        applyWrap(ta, wrapOpen, wrapClose, btn.getAttribute('data-wrap-para') === '1');
        hideTip(btn);
        return;
      } else {
        insert = btn.getAttribute('data-insert-nl') === '1'
          ? '\n'
          : (btn.getAttribute('data-insert-token') || '');
        if (insert === '') {
          return;
        }
        selStart = start + insert.length;
        selEnd = selStart;
      }
      undoStack.push({
        value: value,
        start: typeof ta.selectionStart === 'number' ? ta.selectionStart : start,
        end: typeof ta.selectionEnd === 'number' ? ta.selectionEnd : end
      });
      if (undoStack.length > 40) {
        undoStack.shift();
      }
      ta.value = value.slice(0, start) + insert + value.slice(end);
      try {
        ta.setSelectionRange(selStart, selEnd);
      } catch (err) { /* تجاهل */ }
      ta.dispatchEvent(new Event('input', { bubbles: true }));
      setUndoEnabled();
      hideTip(btn);
    });
  });
}

function bindLetterPreview(root) {
  root = root || document;
  var modalEl = root.querySelector('#letterPreviewModal') || document.getElementById('letterPreviewModal');
  if (!modalEl || modalEl.getAttribute('data-bound') === '1') {
    return;
  }
  modalEl.setAttribute('data-bound', '1');
  var bodyEl = modalEl.querySelector('[data-letter-preview-body]');
  var url = modalEl.getAttribute('data-preview-url') || '';
  var fail = modalEl.getAttribute('data-preview-fail') || '';
  document.querySelectorAll('[data-letter-preview]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var ta = document.getElementById(btn.getAttribute('data-letter-preview') || '');
      if (!ta || !bodyEl || !url) {
        return;
      }
      bodyEl.innerHTML = '<p class="text-muted mb-0">…</p>';
      if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }
      var data = new URLSearchParams();
      data.set('body', ta.value || '');
      data.set('lang', btn.getAttribute('data-letter-preview-lang') || 'ar');
      var form = ta.closest('form') || document.querySelector('form.admin-form');
      function fieldVal(name) {
        var el = form ? form.querySelector('[name="' + name + '"]') : document.getElementById(name);
        if (!el) {
          return '';
        }
        if (el.type === 'checkbox') {
          return el.checked ? '1' : '';
        }
        return el.value || '';
      }
      var csrf = form ? form.querySelector('input[name="csrf_token"]') : null;
      if (csrf) {
        data.set('csrf_token', csrf.value);
      }
      data.set('entity_id', fieldVal('entity_id'));
      data.set('subject', fieldVal('subject'));
      data.set('corr_date', fieldVal('corr_date'));
      data.set('issuing_department_id', fieldVal('issuing_department_id'));
      data.set('department_id', fieldVal('department_id'));
      data.set('rsvp_required', fieldVal('rsvp_required') || fieldVal('requires_rsvp'));
      data.set('intend_electronic', fieldVal('intend_electronic') || '1');
      if (form && form.getAttribute('data-corr-id')) {
        data.set('id', form.getAttribute('data-corr-id'));
      }
      if (form && form.getAttribute('data-corr-ref')) {
        data.set('ref_number', form.getAttribute('data-corr-ref'));
      }
      if ((btn.getAttribute('data-letter-preview-lang') || 'ar') === 'en') {
        data.set('letter_body_en', ta.value || '');
        data.set('letter_body', fieldVal('letter_body'));
      } else {
        data.set('letter_body', ta.value || '');
        data.set('letter_body_en', fieldVal('letter_body_en'));
      }
      fetch(url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: data.toString()
      }).then(function (res) {
        return res.json();
      }).then(function (payload) {
        if (payload && payload.ok && typeof payload.html === 'string') {
          bodyEl.innerHTML = payload.html;
          return;
        }
        bodyEl.innerHTML = '<p class="text-danger mb-0">' + (fail || 'error') + '</p>';
      }).catch(function () {
        bodyEl.innerHTML = '<p class="text-danger mb-0">' + (fail || 'error') + '</p>';
      });
    });
  });
}

function renderQr(elId, text, size, correctLevel) {
  size = size || 160;
  var el = document.getElementById(elId);
  if (!el || typeof QRCode === 'undefined') return;
  var src = String(text || '').trim();
  if (!src) return;
  var level = correctLevel;
  if (level == null) {
    level = (QRCode.CorrectLevel && QRCode.CorrectLevel.M != null)
      ? QRCode.CorrectLevel.M
      : 0;
  }
  el.innerHTML = '';
  try {
    new QRCode(el, {
      text: src,
      width: size,
      height: size,
      colorDark: '#000000',
      colorLight: '#ffffff',
      correctLevel: level
    });
  } catch (err) {
    try {
      el.innerHTML = '';
      new QRCode(el, {
        text: src,
        width: size,
        height: size,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.L
      });
    } catch (err2) {}
  }
}

/** QR حاد للطابعة الحرارية: أبيض/أسود صلب بدون تمويه عند التصغير */
function renderThermalQr(elId, text, size) {
  size = Math.max(96, Math.round(Number(size) || 160));
  var level = (typeof QRCode !== 'undefined' && QRCode.CorrectLevel)
    ? QRCode.CorrectLevel.L
    : 1;
  renderQr(elId, text, size, level);
  var el = document.getElementById(elId);
  if (!el) return;
  var canvas = el.querySelector('canvas');
  if (!canvas) return;
  try {
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    var w = canvas.width;
    var h = canvas.height;
    var imgData = ctx.getImageData(0, 0, w, h);
    var px = imgData.data;
    for (var i = 0; i < px.length; i += 4) {
      var on = (0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2]) < 180;
      var v = on ? 0 : 255;
      px[i] = px[i + 1] = px[i + 2] = v;
      px[i + 3] = 255;
    }
    ctx.putImageData(imgData, 0, 0);
    var url = canvas.toDataURL('image/png');
    el.innerHTML = '';
    var out = document.createElement('img');
    out.alt = '';
    out.src = url;
    out.width = size;
    out.height = size;
    out.decoding = 'sync';
    out.style.cssText = 'display:block;width:100%;height:100%;image-rendering:pixelated;image-rendering:crisp-edges;-ms-interpolation-mode:nearest-neighbor;';
    el.appendChild(out);
  } catch (e) {}
}

/** شعار حراري حاد: أسود صلب مع شفافية PNG (بدون تمويه الفلتر) */
function renderThermalLogo(img) {
  if (!img || img.dataset.thermalBlack === '1') return;
  function paint() {
    try {
      var w = img.naturalWidth || img.width;
      var h = img.naturalHeight || img.height;
      if (!w || !h) return;

      /* بدون تكبير — على الشبكة التكبير ×2/×3 يجمد معاينة الطباعة */
      var c = document.createElement('canvas');
      c.width = Math.max(1, Math.min(w, 800));
      c.height = Math.max(1, Math.round(h * (c.width / w)));
      var ctx = c.getContext('2d', { willReadFrequently: true });
      if (!ctx) return;
      ctx.imageSmoothingEnabled = true;
      ctx.clearRect(0, 0, c.width, c.height);
      ctx.drawImage(img, 0, 0, c.width, c.height);

      var data = ctx.getImageData(0, 0, c.width, c.height);
      var px = data.data;
      for (var i = 0; i < px.length; i += 4) {
        var a = px[i + 3];
        if (a < 12) {
          px[i] = px[i + 1] = px[i + 2] = 255;
          px[i + 3] = 0;
          continue;
        }
        var lum = 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
        var ink = (255 - lum) * (a / 255);
        if (ink >= 28) {
          px[i] = px[i + 1] = px[i + 2] = 0;
          px[i + 3] = 255;
        } else {
          px[i] = px[i + 1] = px[i + 2] = 255;
          px[i + 3] = 0;
        }
      }
      ctx.putImageData(data, 0, 0);
      img.src = c.toDataURL('image/png');
      img.dataset.thermalBlack = '1';
      img.classList.add('is-thermal-ready');
      img.style.filter = 'none';
      img.style.webkitFilter = 'none';
    } catch (e) {
      img.dataset.thermalBlack = '1';
      img.classList.add('is-thermal-ready');
    }
  }
  if (img.complete && img.naturalWidth) paint();
  else img.addEventListener('load', paint, { once: true });
}

function dmsNotify(type, message) {
  if (!message) return;
  if (window.DmsSwal && typeof window.DmsSwal.flash === 'function') {
    window.DmsSwal.flash(type || 'success', message);
    return;
  }
  if (typeof window.alert === 'function') window.alert(message);
}

function copyTextToClipboard(text) {
  if (!text) return Promise.reject(new Error('empty'));
  if (navigator.clipboard && navigator.clipboard.writeText) {
    return navigator.clipboard.writeText(text);
  }
  var ta = document.createElement('textarea');
  ta.value = text;
  ta.setAttribute('readonly', '');
  ta.style.position = 'fixed';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.select();
  try {
    document.execCommand('copy');
    return Promise.resolve();
  } catch (err) {
    return Promise.reject(err);
  } finally {
    document.body.removeChild(ta);
  }
}

var QR_WORD_CM = 2.5;
var QR_WORD_PASTE_PX = Math.round(QR_WORD_CM / 2.54 * 96);
var QR_WORD_FILE_PX = 296;

function qrSourceCanvas(el) {
  if (!el) return null;
  var canvas = el.querySelector('canvas');
  if (canvas && canvas.width) return canvas;
  var img = el.querySelector('img');
  if (!img) return null;
  var out = document.createElement('canvas');
  out.width = img.naturalWidth || img.width || QR_WORD_FILE_PX;
  out.height = img.naturalHeight || img.height || QR_WORD_FILE_PX;
  var ctx = out.getContext('2d');
  if (!ctx) return null;
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, out.width, out.height);
  ctx.drawImage(img, 0, 0, out.width, out.height);
  return out;
}

function scaleQrCanvas(source, size) {
  var out = document.createElement('canvas');
  out.width = size;
  out.height = size;
  var ctx = out.getContext('2d');
  if (!ctx) return null;
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, size, size);
  ctx.imageSmoothingEnabled = false;
  ctx.drawImage(source, 0, 0, size, size);
  return out;
}

function canvasToPngBlob(canvas) {
  if (!canvas || typeof canvas.toBlob !== 'function') {
    return Promise.reject(new Error('canvas'));
  }
  return new Promise(function (resolve, reject) {
    canvas.toBlob(function (blob) {
      if (blob) resolve(blob);
      else reject(new Error('blob'));
    }, 'image/png');
  });
}

function u32bytes(n) {
  return [(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255];
}

function crc32bytes(bytes) {
  var table = crc32bytes.table;
  if (!table) {
    table = crc32bytes.table = new Uint32Array(256);
    for (var n = 0; n < 256; n++) {
      var c = n;
      for (var k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
      table[n] = c >>> 0;
    }
  }
  var crc = 0xFFFFFFFF;
  for (var i = 0; i < bytes.length; i++) crc = table[(crc ^ bytes[i]) & 255] ^ (crc >>> 8);
  return (crc ^ 0xFFFFFFFF) >>> 0;
}

function pngWithPhysicalSizeCm(pngBlob, widthCm, heightCm) {
  if (heightCm == null) heightCm = widthCm;
  return pngBlob.arrayBuffer().then(function (buf) {
    var data = new Uint8Array(buf);
    if (data.length < 33 || data[0] !== 0x89 || data[1] !== 0x50) return pngBlob;
    var width = ((data[16] << 24) | (data[17] << 16) | (data[18] << 8) | data[19]) >>> 0;
    var height = ((data[20] << 24) | (data[21] << 16) | (data[22] << 8) | data[23]) >>> 0;
    if (!width || !height || !widthCm || !heightCm) return pngBlob;
    var ppmX = Math.round(width / (widthCm / 100));
    var ppmY = Math.round(height / (heightCm / 100));
    if (!ppmX || !ppmY) return pngBlob;
    var physPayload = u32bytes(ppmX).concat(u32bytes(ppmY), [1]);
    var typeAndData = [0x70, 0x48, 0x59, 0x73].concat(physPayload);
    var chunk = u32bytes(physPayload.length).concat(typeAndData, u32bytes(crc32bytes(typeAndData)));
    var outParts = [data.subarray(0, 8)];
    var offset = 8;
    var inserted = false;
    while (offset + 12 <= data.length) {
      var len = ((data[offset] << 24) | (data[offset + 1] << 16) | (data[offset + 2] << 8) | data[offset + 3]) >>> 0;
      var type = String.fromCharCode(data[offset + 4], data[offset + 5], data[offset + 6], data[offset + 7]);
      var next = offset + 12 + len;
      if (next > data.length) break;
      if (type === 'pHYs') {
        offset = next;
        continue;
      }
      outParts.push(data.subarray(offset, next));
      if (type === 'IHDR' && !inserted) {
        outParts.push(new Uint8Array(chunk));
        inserted = true;
      }
      offset = next;
      if (type === 'IEND') break;
    }
    if (offset < data.length) outParts.push(data.subarray(offset));
    var total = 0;
    for (var i = 0; i < outParts.length; i++) total += outParts[i].length;
    var out = new Uint8Array(total);
    var pos = 0;
    for (var j = 0; j < outParts.length; j++) {
      out.set(outParts[j], pos);
      pos += outParts[j].length;
    }
    return new Blob([out], { type: 'image/png' });
  });
}

function qrPngForWord(el, px) {
  var source = qrSourceCanvas(el);
  if (!source) return Promise.reject(new Error('qr'));
  var scaled = scaleQrCanvas(source, px || QR_WORD_FILE_PX);
  if (!scaled) return Promise.reject(new Error('scale'));
  return canvasToPngBlob(scaled).then(function (blob) {
    return pngWithPhysicalSizeCm(blob, QR_WORD_CM);
  });
}

function qrElementToBlob(el) {
  return qrPngForWord(el, QR_WORD_FILE_PX);
}

function downloadBlob(blob, filename) {
  var url = URL.createObjectURL(blob);
  var a = document.createElement('a');
  a.href = url;
  a.download = filename || 'qr.png';
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
}

function blobToDataUrl(blob) {
  return new Promise(function (resolve, reject) {
    var reader = new FileReader();
    reader.onload = function () { resolve(reader.result); };
    reader.onerror = reject;
    reader.readAsDataURL(blob);
  });
}

function whenStickerImg(img) {
  return new Promise(function (resolve) {
    var timer = setTimeout(resolve, 1000);
    function ready() {
      clearTimeout(timer);
      resolve();
    }
    if (img.classList.contains('print-thermal-logo-img') && typeof renderThermalLogo === 'function') {
      renderThermalLogo(img);
    }
    function arm() {
      var logoOk = !img.classList.contains('print-thermal-logo-img') || img.dataset.thermalBlack === '1';
      if (img.complete && img.naturalWidth && logoOk) {
        ready();
        return;
      }
      img.addEventListener('load', function check() {
        var ok = !img.classList.contains('print-thermal-logo-img') || img.dataset.thermalBlack === '1';
        if (ok && img.naturalWidth) ready();
        else img.addEventListener('load', check, { once: true });
      }, { once: true });
      img.addEventListener('error', ready, { once: true });
    }
    arm();
  });
}

function whenStickerAssets(el) {
  var imgs = Array.prototype.slice.call(el.querySelectorAll('img'));
  return Promise.all(imgs.map(whenStickerImg));
}

function stickerElementToBlob(el) {
  var wMm = parseFloat(el.getAttribute('data-sticker-w')) || 50;
  var hMm = parseFloat(el.getAttribute('data-sticker-h')) || 25;
  return document.fonts.ready.then(function () {
    return whenStickerAssets(el);
  }).then(function () {
    var prevBorder = el.style.border;
    var prevShadow = el.style.boxShadow;
    el.style.border = 'none';
    el.style.boxShadow = 'none';
    var canvas;
    try {
      var rect = el.getBoundingClientRect();
      if (rect.width < 2 || rect.height < 2) {
        throw new Error('size');
      }
      var outW = Math.max(1, Math.round(wMm / 25.4 * 300));
      var outH = Math.max(1, Math.round(hMm / 25.4 * 300));
      var scaleX = outW / rect.width;
      var scaleY = outH / rect.height;
      canvas = document.createElement('canvas');
      canvas.width = outW;
      canvas.height = outH;
      var ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('canvas');
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, outW, outH);

      function drawTree(node) {
        if (!node) return;
        if (node.nodeType === 3) {
          var text = String(node.nodeValue || '').replace(/\s+/g, ' ').trim();
          if (!text) return;
          var parent = node.parentElement;
          if (!parent) return;
          var range = document.createRange();
          range.selectNodeContents(node);
          var r = range.getBoundingClientRect();
          if (r.width < 0.4 || r.height < 0.4) r = parent.getBoundingClientRect();
          if (r.width < 0.4 || r.height < 0.4) return;
          var cs = getComputedStyle(parent);
          var size = (parseFloat(cs.fontSize) || 12) * scaleY;
          ctx.font = (cs.fontStyle || 'normal') + ' ' + (cs.fontWeight || '700') + ' ' + size + 'px ' + (cs.fontFamily || 'GretaArabic, sans-serif');
          ctx.fillStyle = cs.color || '#000';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.direction = parent.getAttribute('dir') || cs.direction || 'rtl';
          ctx.fillText(text, ((r.left + r.right) / 2 - rect.left) * scaleX, ((r.top + r.bottom) / 2 - rect.top) * scaleY);
          return;
        }
        if (node.nodeType !== 1 || node.tagName === 'SCRIPT') return;
        if (node.tagName === 'IMG') {
          var ir = node.getBoundingClientRect();
          if (!node.complete || !node.naturalWidth || ir.width < 1 || ir.height < 1) return;
          var crisp = node.closest('.print-thermal-qr');
          ctx.imageSmoothingEnabled = !crisp;
          try {
            ctx.drawImage(node, (ir.left - rect.left) * scaleX, (ir.top - rect.top) * scaleY, ir.width * scaleX, ir.height * scaleY);
          } catch (err) {}
          ctx.imageSmoothingEnabled = true;
          return;
        }
        var kids = node.childNodes;
        for (var i = 0; i < kids.length; i++) drawTree(kids[i]);
      }
      drawTree(el);
    } finally {
      el.style.border = prevBorder;
      el.style.boxShadow = prevShadow;
    }
    return canvasToPngBlob(canvas).then(function (blob) {
      return pngWithPhysicalSizeCm(blob, wMm / 10, hMm / 10);
    });
  });
}

function copyStickerImage(el, filename) {
  return stickerElementToBlob(el).then(function (blob) {
    var wMm = parseFloat(el.getAttribute('data-sticker-w')) || 50;
    var hMm = parseFloat(el.getAttribute('data-sticker-h')) || 25;
    var pasteW = Math.max(1, Math.round(wMm / 25.4 * 96));
    var pasteH = Math.max(1, Math.round(hMm / 25.4 * 96));
    if (!(navigator.clipboard && window.ClipboardItem)) {
      downloadBlob(blob, filename || 'sticker.png');
      return 'downloaded';
    }
    return blobToDataUrl(blob).then(function (dataUrl) {
      var html = '<img src="' + dataUrl + '" width="' + pasteW + '" height="' + pasteH + '" style="width:' + wMm + 'mm;height:' + hMm + 'mm;border:0" alt="">';
      var pngOnly = { 'image/png': blob };
      var withHtml = {
        'text/html': new Blob([html], { type: 'text/html' }),
        'image/png': blob
      };
      return navigator.clipboard.write([new ClipboardItem(withHtml)]).catch(function () {
        return navigator.clipboard.write([new ClipboardItem(pngOnly)]);
      });
    });
  });
}

function copyQrImage(el) {
  return qrPngForWord(el, QR_WORD_PASTE_PX).then(function (blob) {
    if (!(navigator.clipboard && window.ClipboardItem)) {
      return qrPngForWord(el, QR_WORD_FILE_PX).then(function (fileBlob) {
        downloadBlob(fileBlob, 'qr.png');
        return 'downloaded';
      });
    }
    return blobToDataUrl(blob).then(function (dataUrl) {
      var html = '<img src="' + dataUrl + '" width="' + QR_WORD_PASTE_PX + '" height="' + QR_WORD_PASTE_PX + '" style="width:' + QR_WORD_CM + 'cm;height:' + QR_WORD_CM + 'cm;border:0" alt="QR">';
      var pngOnly = { 'image/png': blob };
      var withHtml = {
        'text/html': new Blob([html], { type: 'text/html' }),
        'image/png': blob
      };
      return navigator.clipboard.write([new ClipboardItem(withHtml)]).catch(function () {
        return navigator.clipboard.write([new ClipboardItem(pngOnly)]);
      });
    });
  });
}

function bindCopyActions(root) {
  root = root || document;
  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy-text], [data-copy-qr], [data-download-qr], [data-copy-sticker]');
    if (!btn) return;
    e.preventDefault();
    var ok = btn.getAttribute('data-copy-ok') || '';
    var fail = btn.getAttribute('data-copy-fail') || '';

    if (btn.hasAttribute('data-copy-sticker')) {
      var sticker = document.getElementById(btn.getAttribute('data-copy-sticker') || '');
      var stickerName = btn.getAttribute('data-sticker-filename') || 'sticker.png';
      if (!sticker) {
        dmsNotify('error', fail);
        return;
      }
      copyStickerImage(sticker, stickerName).then(function (result) {
        dmsNotify('success', result === 'downloaded' ? (btn.getAttribute('data-download-ok') || ok) : ok);
      }).catch(function () {
        stickerElementToBlob(sticker).then(function (blob) {
          downloadBlob(blob, stickerName);
          dmsNotify('success', btn.getAttribute('data-download-ok') || ok);
        }).catch(function () {
          dmsNotify('error', fail);
        });
      });
      return;
    }

    if (btn.hasAttribute('data-copy-text')) {
      copyTextToClipboard(btn.getAttribute('data-copy-text') || '').then(function () {
        dmsNotify('success', ok);
      }).catch(function () {
        dmsNotify('error', fail || ok);
      });
      return;
    }

    var qrId = btn.getAttribute('data-copy-qr') || btn.getAttribute('data-download-qr');
    var qrEl = qrId ? document.getElementById(qrId) : null;
    if (!qrEl) {
      dmsNotify('error', fail);
      return;
    }

    if (btn.hasAttribute('data-download-qr')) {
      qrElementToBlob(qrEl).then(function (blob) {
        downloadBlob(blob, btn.getAttribute('data-qr-filename') || 'qr.png');
        dmsNotify('success', ok);
      }).catch(function () {
        dmsNotify('error', fail || ok);
      });
      return;
    }

    copyQrImage(qrEl).then(function (result) {
      dmsNotify('success', result === 'downloaded' ? (btn.getAttribute('data-download-ok') || ok) : ok);
    }).catch(function () {
      qrElementToBlob(qrEl).then(function (blob) {
        downloadBlob(blob, btn.getAttribute('data-qr-filename') || 'qr.png');
        dmsNotify('success', btn.getAttribute('data-download-ok') || ok);
      }).catch(function () {
        dmsNotify('error', fail);
      });
    });
  });
}

(function () {
  function pad(n) {
    return String(n).padStart(2, '0');
  }
  function printClockText() {
    var d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
      + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }
  function dmsUpdatePrintClock() {
    document.querySelectorAll('.print-meta-datetime[data-print-clock]').forEach(function (el) {
      el.textContent = printClockText();
    });
  }
  window.dmsUpdatePrintClock = dmsUpdatePrintClock;
  document.addEventListener('DOMContentLoaded', dmsUpdatePrintClock);
  function hidePrintTooltips() {
    if (window.bootstrap && bootstrap.Tooltip) {
      document.querySelectorAll('[data-bs-toggle="tooltip"], [data-bs-original-title]').forEach(function (el) {
        var tip = bootstrap.Tooltip.getInstance(el);
        if (tip) {
          tip.hide();
        }
      });
    }
    document.querySelectorAll('body > .tooltip, body > .bs-tooltip-auto').forEach(function (el) {
      el.remove();
    });
  }
  window.addEventListener('beforeprint', function () {
    dmsUpdatePrintClock();
    hidePrintTooltips();
  });
  if (typeof window.print === 'function') {
    var nativePrint = window.print.bind(window);
    window.print = function () {
      dmsUpdatePrintClock();
      hidePrintTooltips();
      return nativePrint();
    };
  }
})();

(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var saved = null;
    try { saved = localStorage.getItem('dms.print.dirOrder'); } catch (e) { return; }
    if (saved !== 'from' && saved !== 'to') { return; }
    document.querySelectorAll('a[href*="brief_word.php"]').forEach(function (a) {
      try {
        var u = new URL(a.href, window.location.href);
        if (!u.searchParams.has('dir')) {
          u.searchParams.set('dir', saved);
          a.href = u.toString();
        }
      } catch (e) { /* تجاهل رابط غير صالح */ }
    });
  });
})();

/** حقول اختيار الملفات الموحّدة — data-dms-file-field */
(function () {
  function fileKey(file) {
    return [file.name || '', file.size || 0, file.lastModified || 0].join('|');
  }
  function formatSize(bytes) {
    bytes = Number(bytes) || 0;
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }
  function fileTypeLabel(file, wrap) {
    var name = String((file && file.name) || '').toLowerCase();
    var mime = String((file && file.type) || '').toLowerCase();
    var pdfLabel = wrap.getAttribute('data-dms-file-type-pdf') || 'PDF';
    var imageLabel = wrap.getAttribute('data-dms-file-type-image') || 'Image';
    var otherLabel = wrap.getAttribute('data-dms-file-type-other') || 'File';
    if (mime.indexOf('pdf') !== -1 || /\.pdf$/.test(name)) return pdfLabel;
    if (mime.indexOf('image/') === 0 || /\.(jpe?g|png|gif|webp|bmp)$/.test(name)) return imageLabel;
    return otherLabel;
  }
  function initDmsFileField(wrap) {
    if (!wrap || wrap.getAttribute('data-dms-file-ready') === '1') return;
    var input = wrap.querySelector('[data-dms-file-input]');
    var label = wrap.querySelector('[data-dms-file-label]');
    var main = wrap.querySelector('.dms-file-field-main');
    var list = wrap.parentElement
      ? wrap.parentElement.querySelector('[data-dms-file-list]')
      : null;
    if (!input || typeof DataTransfer === 'undefined') return;
    wrap.setAttribute('data-dms-file-ready', '1');

    var multiple = wrap.getAttribute('data-dms-file-multiple') === '1';
    var placeholder = wrap.getAttribute('data-dms-file-placeholder') || '';
    var selectedTpl = wrap.getAttribute('data-dms-file-selected') || ':count';
    var deleteLabel = wrap.getAttribute('data-dms-file-delete') || 'Delete';
    var autosubmit = wrap.getAttribute('data-dms-file-autosubmit') === '1';
    var bag = [];
    var bagMeta = [];

    function findEnclosurePicker() {
      var card = wrap.closest('.corr-field-card') || wrap.closest('form') || document;
      return card.querySelector('[data-enclosure-picker]');
    }

    function applyBag() {
      var dt = new DataTransfer();
      bag.forEach(function (file) { dt.items.add(file); });
      input.files = dt.files;
      if (label) {
        // مع قائمة الملفات: أفرغ المربع بعد الإضافة ليبدو جاهزاً لملف آخر
        if (!bag.length || (list && !autosubmit)) {
          label.textContent = placeholder;
          if (main) main.classList.remove('is-filled');
        } else if (bag.length === 1) {
          label.textContent = bag[0].name || selectedTpl.replace(':count', '1');
          if (main) main.classList.add('is-filled');
        } else {
          label.textContent = selectedTpl.replace(':count', String(bag.length));
          if (main) main.classList.add('is-filled');
        }
      }
      if (list) {
      list.textContent = '';
      if (!bag.length) {
        list.hidden = true;
      } else {
      bag.forEach(function (file, index) {
        var li = document.createElement('li');
        li.className = 'dms-file-list-item';
        var name = document.createElement('span');
        name.className = 'dms-file-list-name';
        name.textContent = file.name;
        var typeBadge = document.createElement('span');
        typeBadge.className = 'dms-file-list-type';
        typeBadge.textContent = fileTypeLabel(file, wrap);
        var meta = document.createElement('span');
        meta.className = 'dms-file-list-meta';
        meta.textContent = formatSize(file.size);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-danger btn-icon';
        btn.setAttribute('data-dms-file-remove', String(index));
        btn.setAttribute('title', deleteLabel);
        btn.setAttribute('aria-label', deleteLabel);
        btn.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
        li.appendChild(name);
        li.appendChild(typeBadge);
        li.appendChild(meta);
        li.appendChild(btn);
        list.appendChild(li);
      });
      list.hidden = false;
      }
      }
      wrap.dispatchEvent(new CustomEvent('dms-file-change', { bubbles: true }));
    }

    input.addEventListener('change', function () {
      var added = 0;
      var picker = findEnclosurePicker();
      var encInfo = null;
      if (picker && typeof picker._dmsAddEnclosure === 'function') {
        /* يُسجَّل النوع بعد معرفة عدد الملفات الجديدة */
      }
      var enclosureLabel = '';
      if (picker && typeof picker._dmsEnclosureLabel === 'function') {
        enclosureLabel = picker._dmsEnclosureLabel() || '';
      }

      var enclosureId = '';
      if (picker && typeof picker._dmsEnclosureId === 'function') {
        enclosureId = picker._dmsEnclosureId() || '';
      }
      if (!multiple) {
        bag = Array.prototype.slice.call(input.files || []);
        bagMeta = bag.map(function () {
          return { enclosureLabel: enclosureLabel, enclosureId: enclosureId };
        });
        added = bag.length;
      } else {
        var seen = {};
        bag.forEach(function (file) { seen[fileKey(file)] = true; });
        Array.prototype.forEach.call(input.files || [], function (file) {
          var key = fileKey(file);
          if (seen[key]) return;
          seen[key] = true;
          bag.push(file);
          bagMeta.push({ enclosureLabel: enclosureLabel, enclosureId: enclosureId });
          added += 1;
        });
      }
      if (added > 0 && picker && typeof picker._dmsAddEnclosure === 'function') {
        encInfo = picker._dmsAddEnclosure(added);
        if (encInfo && encInfo.label) {
          var i;
          for (i = bagMeta.length - added; i < bagMeta.length; i++) {
            if (bagMeta[i]) {
              bagMeta[i].enclosureLabel = encInfo.label;
              bagMeta[i].enclosureId = encInfo.id || enclosureId;
            }
          }
        }
      }
      applyBag();
      if (wrap.getAttribute('data-dms-file-autosubmit') === '1' && bag.length) {
        var form = wrap.closest('form');
        if (form) {
          if (typeof form.requestSubmit === 'function') form.requestSubmit();
          else form.submit();
        }
      }
    });

    if (list) {
      list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dms-file-remove]');
        if (!btn) return;
        var index = parseInt(btn.getAttribute('data-dms-file-remove') || '-1', 10);
        if (index < 0 || index >= bag.length) return;
        var meta = bagMeta[index] || {};
        var picker = findEnclosurePicker();
        if (picker && typeof picker._dmsRemoveEnclosure === 'function' && meta.enclosureId) {
          picker._dmsRemoveEnclosure(meta.enclosureId, 1);
        }
        bag.splice(index, 1);
        bagMeta.splice(index, 1);
        applyBag();
      });
    }
  }

  function initDmsFileFields(root) {
    (root || document).querySelectorAll('[data-dms-file-field]').forEach(initDmsFileField);
  }

  window.initDmsFileFields = initDmsFileFields;
  document.addEventListener('DOMContentLoaded', function () {
    initDmsFileFields(document);
  });
})();

/* تمييز الحقل الإلزامي: «*» حمراء مع تلميح، وإضافتها لعناوين الحقول المطلوبة التي تخلو منها */
(function () {
  var SELECTOR = 'label, .form-label, .col-form-label, .corr-field-card-title, legend, .admin-form-section-title, th';
  var FIELDS = 'input[required]:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]), select[required], textarea[required]';
  var CONTROLS = 'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea';

  function makeStar() {
    var star = document.createElement('span');
    star.className = 'req-star';
    star.setAttribute('aria-hidden', 'true');
    star.textContent = '*';
    var tip = window.__DMS_REQ_TIP__ || '';
    if (tip) {
      star.title = tip;
      star.setAttribute('data-bs-toggle', 'tooltip');
      if (window.bootstrap && bootstrap.Tooltip) bootstrap.Tooltip.getOrCreateInstance(star);
    }
    return star;
  }

  function hasStar(el) {
    return !!el.querySelector('.req-star') || /\*\s*$/.test(el.textContent || '');
  }

  function markOne(el) {
    if (el.querySelector('.req-star')) return;
    var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
    var last = null;
    var n;
    while ((n = walker.nextNode())) {
      if (n.nodeValue.trim() !== '') last = n;
    }
    if (!last || last.parentNode.closest('select, option, textarea, script, style')) return;
    var m = last.nodeValue.match(/^([\s\S]*?)\s*\*\s*$/);
    if (!m) return;
    last.nodeValue = m[1];
    last.parentNode.insertBefore(makeStar(), last.nextSibling);
  }

  function titleForField(field) {
    var lbl = null;
    if (field.id && window.CSS && CSS.escape) {
      lbl = document.querySelector('label[for="' + CSS.escape(field.id) + '"]');
    }
    if (lbl && lbl.contains(field)) lbl = null;
    if (lbl && !lbl.classList.contains('visually-hidden')) return lbl;
    var card = field.closest('.corr-field-card');
    if (card) return card.querySelector('.corr-field-card-title');
    if (lbl) return null;
    var box = field.closest('.mb-3, .mb-2, [class^="col"], [class*=" col-"]');
    if (!box || box.querySelectorAll(CONTROLS).length !== 1) return null;
    var cand = box.querySelector('.form-label, label');
    return cand && !cand.contains(field) && !cand.classList.contains('visually-hidden') ? cand : null;
  }

  function addMissing(root) {
    var list = [];
    if (root.nodeType === 1 && root.matches(FIELDS)) list.push(root);
    if (root.querySelectorAll) list = list.concat(Array.prototype.slice.call(root.querySelectorAll(FIELDS)));
    list.forEach(function (field) {
      if (field.closest('[data-no-req-star]')) return;
      var title = titleForField(field);
      if (!title || hasStar(title)) return;
      title.appendChild(makeStar());
    });
  }

  function markRequired(root) {
    root = root || document;
    if (root.nodeType === 1 && root.matches(SELECTOR)) markOne(root);
    if (root.querySelectorAll) root.querySelectorAll(SELECTOR).forEach(markOne);
    addMissing(root);
  }
  window.dmsMarkRequired = markRequired;

  function start() {
    markRequired(document);
    if (!document.body) return;
    var pending = [];
    var timer = null;
    new MutationObserver(function (mutations) {
      mutations.forEach(function (mu) {
        mu.addedNodes.forEach(function (node) {
          if (node.nodeType === 1 && !(node.classList && node.classList.contains('req-star'))) pending.push(node);
        });
      });
      if (!pending.length) return;
      clearTimeout(timer);
      timer = setTimeout(function () {
        var list = pending;
        pending = [];
        list.forEach(function (node) { if (node.isConnected) markRequired(node); });
      }, 60);
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
