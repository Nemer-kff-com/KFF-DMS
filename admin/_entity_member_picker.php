<?php
/**
 * قائمة اختيار أعضاء (checkboxes مع بحث)
 * يتطلب: $entities, $selectedIds (int[]), $inputName, $pickerId
 */
$selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds ?? []))));
$selectedMap = array_fill_keys($selectedIds, true);
$entities = is_array($entities ?? null) ? $entities : [];
$inputName = $inputName ?? 'entity_ids[]';
$pickerId = $pickerId ?? 'member-picker';

$memberRows = [];
foreach ($entities as $entity) {
    $eid = (int) ($entity['id'] ?? 0);
    if ($eid <= 0) {
        continue;
    }
    $person = trim((string) ($entity['directed_to'] ?? ''));
    $org = trim((string) (bilingual_label($entity, 'name_ar', 'name_en') ?: ''));
    $job = trim((string) ($entity['job_title'] ?? ''));
    $prefix = trim((string) ($entity['title_prefix'] ?? ''));
    $primary = $person !== '' ? $person : $org;
    if ($primary === '') {
        $primary = '#' . $eid;
    }
    $memberRows[] = [
        'id' => $eid,
        'primary' => $primary,
        'prefix' => $prefix,
        'job' => $job,
        'org' => ($org !== '' && $org !== $primary) ? $org : '',
        'email' => trim((string) ($entity['email'] ?? '')),
        'checked' => isset($selectedMap[$eid]),
        'search' => mb_strtolower(trim($prefix . ' ' . $primary . ' ' . $job . ' ' . $org . ' ' . ($entity['email'] ?? '')), 'UTF-8'),
    ];
}
?>
<div class="member-picker" id="<?= e($pickerId) ?>" data-selected-label="<?= e(__('groups.selected_count')) ?>">
    <div class="member-picker-selected" data-selected-panel <?= $selectedIds ? '' : 'hidden' ?>>
        <div class="member-picker-selected-head">
            <span class="member-picker-selected-title"><?= e(__('groups.selected_members')) ?></span>
            <span class="badge text-bg-light text-dark border member-picker-count"><?= count($selectedIds) ?></span>
        </div>
        <div class="member-picker-chips" data-selected-chips></div>
    </div>

    <div class="member-picker-toolbar">
        <div class="member-picker-search-wrap">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control member-picker-search" placeholder="<?= e(__('groups.search')) ?>" autocomplete="off">
        </div>
        <div class="member-picker-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-pick-all><?= e(__('groups.select_all')) ?></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-pick-none><?= e(__('groups.clear')) ?></button>
        </div>
    </div>

    <div class="member-picker-list">
        <?php if (!$memberRows): ?>
            <p class="text-muted small mb-0 p-3"><?= e(__('common.no_data')) ?></p>
        <?php else: ?>
            <?php foreach ($memberRows as $row): ?>
                <label class="member-picker-item<?= $row['checked'] ? ' is-checked' : '' ?>" data-search="<?= e($row['search']) ?>">
                    <input type="checkbox" name="<?= e($inputName) ?>" value="<?= (int) $row['id'] ?>"
                           data-label="<?= e($row['primary']) ?>"
                           data-email="<?= e($row['email']) ?>"
                           <?= $row['checked'] ? 'checked' : '' ?>>
                    <span class="member-picker-body">
                        <?php if ($row['prefix'] !== ''): ?>
                            <span class="member-picker-prefix"><?= e($row['prefix']) ?></span>
                        <?php endif; ?>
                        <span class="member-picker-name"><?= e($row['primary']) ?></span>
                        <?php if ($row['job'] !== ''): ?>
                            <span class="member-picker-sub"><?= e($row['job']) ?></span>
                        <?php endif; ?>
                        <?php if ($row['org'] !== ''): ?>
                            <span class="member-picker-org"><?= e($row['org']) ?></span>
                        <?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
  var root = document.getElementById(<?= json_encode($pickerId) ?>);
  if (!root) return;
  var search = root.querySelector('.member-picker-search');
  var countEl = root.querySelector('.member-picker-count');
  var panel = root.querySelector('[data-selected-panel]');
  var chips = root.querySelector('[data-selected-chips]');
  var boxes = function () { return Array.prototype.slice.call(root.querySelectorAll('.member-picker-item input[type="checkbox"]')); };

  function refresh() {
    var checked = boxes().filter(function (b) { return b.checked; });
    if (countEl) countEl.textContent = String(checked.length);
    boxes().forEach(function (b) {
      var item = b.closest('.member-picker-item');
      if (item) item.classList.toggle('is-checked', b.checked);
    });
    if (panel) panel.hidden = checked.length === 0;
    if (chips) {
      chips.innerHTML = '';
      checked.forEach(function (b) {
        var chip = document.createElement('span');
        chip.className = 'member-picker-chip';
        chip.textContent = b.getAttribute('data-label') || b.value;
        chips.appendChild(chip);
      });
    }
  }

  if (search) {
    search.addEventListener('input', function () {
      var q = (search.value || '').trim().toLowerCase();
      root.querySelectorAll('.member-picker-item').forEach(function (item) {
        var text = (item.getAttribute('data-search') || item.textContent || '').toLowerCase();
        item.style.display = !q || text.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }
  root.addEventListener('change', refresh);
  var allBtn = root.querySelector('[data-pick-all]');
  var noneBtn = root.querySelector('[data-pick-none]');
  if (allBtn) allBtn.addEventListener('click', function () {
    boxes().forEach(function (b) {
      if (b.closest('.member-picker-item').style.display !== 'none') b.checked = true;
    });
    refresh();
  });
  if (noneBtn) noneBtn.addEventListener('click', function () {
    boxes().forEach(function (b) { b.checked = false; });
    refresh();
  });
  refresh();
})();
</script>
