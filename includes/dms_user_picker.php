<?php
/**
 * نافذة منبثقة لاختيار مستخدم (صورة + بحث) — نفس أسلوب المعتمدين في الإعدادات.
 * يُستدعى مرة في الصفحة. الحقول: select[data-approver-select] + زر [data-approver-pick].
 */
if (!empty($GLOBALS['_dms_user_picker_printed'])) {
    return;
}
$GLOBALS['_dms_user_picker_printed'] = true;
$pickerTitle = __('departments.principal_picker_title');
$pickerNone = __('settings.outgoing_approver_none');
$pickerSearch = __('settings.outgoing_approver_picker_search');
$pickerEmpty = __('settings.outgoing_approver_picker_empty');
$pickerCurrent = __('settings.outgoing_approver_picker_current');
$pickText = __('settings.outgoing_approver_pick');
$changeText = __('settings.outgoing_approver_change');
?>
<div class="modal fade" id="dmsUserPickerModal" tabindex="-1" aria-labelledby="dmsUserPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content approver-picker">
            <div class="modal-header">
                <h5 class="modal-title" id="dmsUserPickerTitle">
                    <i class="bi bi-person-check me-1"></i><span data-user-picker-title><?= e($pickerTitle) ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.close')) ?>"></button>
            </div>
            <div class="approver-picker-search">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="dmsUserPickerSearch" autocomplete="off"
                           placeholder="<?= e($pickerSearch) ?>">
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush" id="dmsUserPickerList"></div>
                <div class="approver-picker-empty text-muted d-none" id="dmsUserPickerEmpty">
                    <i class="bi bi-person-x d-block mb-1"></i><?= e($pickerEmpty) ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modalEl = document.getElementById('dmsUserPickerModal');
    if (!modalEl || modalEl.dataset.bound === '1') return;
    modalEl.dataset.bound = '1';
    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    var listEl = document.getElementById('dmsUserPickerList');
    var searchEl = document.getElementById('dmsUserPickerSearch');
    var emptyEl = document.getElementById('dmsUserPickerEmpty');
    var titleEl = modalEl.querySelector('[data-user-picker-title]');
    var noneText = <?= json_encode($pickerNone, JSON_UNESCAPED_UNICODE) ?>;
    var pickText = <?= json_encode($pickText, JSON_UNESCAPED_UNICODE) ?>;
    var changeText = <?= json_encode($changeText, JSON_UNESCAPED_UNICODE) ?>;
    var pickerTitle = <?= json_encode($pickerTitle, JSON_UNESCAPED_UNICODE) ?>;
    var currentText = <?= json_encode($pickerCurrent, JSON_UNESCAPED_UNICODE) ?>;
    var activeSelect = null;

    function renderPerson(person, opt, selected) {
        if (!person) return;
        var emptyName = person.getAttribute('data-empty-label') || noneText;
        var name = selected ? (opt.getAttribute('data-name') || '') : emptyName;
        var prefix = selected ? (opt.getAttribute('data-title-prefix') || '') : '';
        var job = selected ? (opt.getAttribute('data-job-title') || opt.getAttribute('data-job') || '') : '';
        var photo = selected ? (opt.getAttribute('data-photo') || '') : '';
        person.classList.toggle('is-empty', !selected);
        var wrap = person.querySelector('.settings-approver-avatar-wrap');
        if (wrap) {
            wrap.textContent = '';
            if (photo) {
                var img = document.createElement('img');
                img.src = photo;
                img.alt = '';
                img.className = 'settings-approver-avatar';
                wrap.appendChild(img);
            } else {
                var span = document.createElement('span');
                span.className = 'settings-approver-avatar is-fallback';
                var ic = document.createElement('i');
                ic.className = 'bi bi-person';
                ic.setAttribute('aria-hidden', 'true');
                span.appendChild(ic);
                wrap.appendChild(span);
            }
        }
        var nameEl = person.querySelector('.settings-approver-person-name');
        if (nameEl) nameEl.textContent = name;
        var prefixEl = person.querySelector('.settings-approver-person-prefix');
        if (prefixEl) {
            prefixEl.textContent = prefix;
            prefixEl.classList.toggle('d-none', prefix === '');
        }
        var jobEl = person.querySelector('.settings-approver-person-job');
        if (jobEl) {
            jobEl.textContent = job;
            jobEl.classList.toggle('d-none', job === '');
        }
    }

    function bindSelect(sel) {
        if (!sel || sel.dataset.pickerBound === '1') return;
        sel.dataset.pickerBound = '1';
        var person = document.querySelector('[data-approver-person-for="' + sel.id + '"]');
        var pickBtn = document.querySelector('button[data-approver-pick="' + sel.id + '"]');
        var pickLabel = pickBtn ? pickBtn.querySelector('[data-approver-pick-label]') : null;
        function refresh() {
            var opt = sel.options[sel.selectedIndex];
            var selected = !!opt && sel.value !== '0';
            renderPerson(person, opt, selected);
            if (pickLabel && pickBtn) {
                pickLabel.textContent = selected
                    ? (pickBtn.getAttribute('data-change-label') || changeText)
                    : (pickBtn.getAttribute('data-pick-label') || pickText);
            }
        }
        sel.addEventListener('change', refresh);
        refresh();
    }

    document.querySelectorAll('select[data-approver-select]').forEach(bindSelect);

    function avatarNode(photo) {
        if (photo) {
            var img = document.createElement('img');
            img.src = photo;
            img.alt = '';
            img.className = 'settings-approver-avatar';
            return img;
        }
        var span = document.createElement('span');
        span.className = 'settings-approver-avatar is-fallback';
        var ic = document.createElement('i');
        ic.className = 'bi bi-person';
        ic.setAttribute('aria-hidden', 'true');
        span.appendChild(ic);
        return span;
    }

    function choose(value) {
        if (!activeSelect) return;
        activeSelect.value = value;
        activeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.bootstrap && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        }
    }

    function filterList() {
        var q = (searchEl.value || '').trim().toLowerCase();
        var shown = 0;
        Array.prototype.forEach.call(listEl.children, function (item) {
            var match = q === '' || (item.getAttribute('data-search') || '').indexOf(q) !== -1;
            if (item.classList.contains('is-none') && q !== '') match = false;
            item.classList.toggle('d-none', !match);
            if (match) shown++;
        });
        emptyEl.classList.toggle('d-none', shown > 0);
    }

    function buildList() {
        listEl.textContent = '';
        if (!activeSelect) return;
        var addMode = activeSelect.getAttribute('data-picker-mode') === 'add';
        var exclude = {};
        (activeSelect.getAttribute('data-exclude-ids') || '').split(',').forEach(function (id) {
            id = String(id || '').trim();
            if (id) exclude[id] = true;
        });
        Array.prototype.forEach.call(activeSelect.options, function (opt) {
            var isNone = opt.value === '0';
            if (isNone && addMode) return;
            if (!isNone && exclude[String(opt.value)]) return;
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action approver-picker-item';
            item.setAttribute('data-keep-label', '');
            item.setAttribute('data-value', opt.value);
            var name = isNone ? opt.textContent.trim() : (opt.getAttribute('data-name') || '');
            var job = isNone ? '' : (opt.getAttribute('data-job-title') || opt.getAttribute('data-job') || '');
            var email = isNone ? '' : (opt.textContent.split('—').slice(1).join('—').trim());
            item.setAttribute('data-search', (name + ' ' + job + ' ' + email).toLowerCase());
            if (isNone) item.classList.add('is-none');
            item.appendChild(avatarNode(isNone ? '' : (opt.getAttribute('data-photo') || '')));
            var text = document.createElement('div');
            text.className = 'approver-picker-text';
            var n = document.createElement('div');
            n.className = 'approver-picker-name';
            n.textContent = name;
            text.appendChild(n);
            if (job || email) {
                var meta = document.createElement('div');
                meta.className = 'approver-picker-meta';
                meta.textContent = job && email ? job + ' · ' + email : (job || email);
                text.appendChild(meta);
            }
            item.appendChild(text);
            if (opt.value === activeSelect.value) {
                item.classList.add('active');
                item.setAttribute('aria-current', 'true');
                var cur = document.createElement('span');
                cur.className = 'badge approver-picker-badge is-current';
                cur.textContent = currentText;
                item.appendChild(cur);
            }
            item.addEventListener('click', function () { choose(opt.value); });
            listEl.appendChild(item);
        });
        filterList();
    }

    document.querySelectorAll('[data-approver-pick]').forEach(function (btn) {
        btn.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter' && ev.key !== ' ') return;
            ev.preventDefault();
            btn.click();
        });
        btn.addEventListener('click', function () {
            if (btn.disabled) return;
            activeSelect = document.getElementById(btn.getAttribute('data-approver-pick'));
            if (!activeSelect || !window.bootstrap || !bootstrap.Modal) return;
            if (bootstrap.Tooltip) {
                var tip = bootstrap.Tooltip.getInstance(btn);
                if (tip) tip.hide();
            }
            if (titleEl) {
                var custom = btn.getAttribute('data-picker-title') || '';
                var role = btn.getAttribute('data-role-label') || '';
                titleEl.textContent = custom || (role ? pickerTitle + ' — ' + role : pickerTitle);
            }
            searchEl.value = '';
            buildList();
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });
    searchEl.addEventListener('input', filterList);
    searchEl.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        ev.preventDefault();
        var first = listEl.querySelector('.approver-picker-item:not(.d-none):not(:disabled)');
        if (first) first.click();
    });
    modalEl.addEventListener('shown.bs.modal', function () {
        modalEl.style.zIndex = '1100';
        var backs = document.querySelectorAll('.modal-backdrop');
        if (backs.length) backs[backs.length - 1].style.zIndex = '1090';
        searchEl.focus();
    });
    modalEl.addEventListener('hidden.bs.modal', function () {
        var parent = document.querySelector('.modal.show');
        if (parent) document.body.classList.add('modal-open');
    });
})();
</script>
