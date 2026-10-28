<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/briefing.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['correspondence_id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('index.php'));
}
briefing_require_incoming($corr);

$folder = briefing_corr_folder($corr);
$viewUrl = $folder . '/view.php?id=' . $id;
$isCancelled = correspondence_is_cancelled($corr);
$canEdit = can_edit_correspondence($user) && !$isCancelled;
$brief = get_correspondence_brief($id);
$locked = briefing_is_decided($brief);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['brief_action'] ?? 'save');
    $decide = $action === 'decide';
    if (!$canEdit) {
        flash('danger', $isCancelled ? __('cancel.locked') : __('auth.forbidden'));
        redirect(base_url($viewUrl));
    }
    try {
        save_correspondence_brief($corr, $_POST, $user, $decide);
        flash('success', $decide ? __('brief.decided') : __('brief.saved'));
        redirect(base_url('correspondence/brief.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $brief = array_merge($brief ?? [], $_POST);
        $brief['options'] = briefing_options_from_request($_POST, false);
        $brief['decided'] = $locked;
    }
}

$defaults = briefing_defaults_from_correspondence($corr);
$v = static function (string $key, $fallback = '') use ($brief, $defaults) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (is_array($brief) && array_key_exists($key, $brief) && $brief[$key] !== null && $brief[$key] !== '') {
        return $brief[$key];
    }
    return $defaults[$key] ?? $fallback;
};
$options = is_array($brief['options'] ?? null) && $brief['options'] !== []
    ? $brief['options']
    : $defaults['options'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $options = briefing_options_from_request($_POST, false);
}
if ($options === []) {
    $options = briefing_default_options();
}

$recParts = ['intro' => '', 'items' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recParts = briefing_parse_recommendation([
        'intro' => $_POST['recommendation_intro'] ?? '',
        'items' => $_POST['rec_item'] ?? [],
    ]);
} elseif (is_array($brief) && is_array($brief['recommendation'] ?? null)) {
    $recParts = $brief['recommendation'];
} elseif (is_array($brief)) {
    $recParts = briefing_parse_recommendation($brief['recommendation_text'] ?? '');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && trim((string) ($recParts['intro'] ?? '')) === '') {
    $recParts['intro'] = (string) ($defaults['recommendation_intro'] ?? '');
}
$recItems = $recParts['items'] ?? [];
if ($recItems === []) {
    $recItems = ['', ''];
}

$pageTitle = __('brief.page') . ' — ' . $corr['ref_number'];
$dirState = briefing_dir_order_from_request();
$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
$ro = $locked || !$canEdit;
$isOutgoing = ($corr['type'] ?? '') === 'outgoing';
$isInternal = ($corr['type'] ?? '') === 'internal';
$attrRo = $ro ? 'readonly' : '';
$dirParts = function_exists('print_direction_parts') ? print_direction_parts($corr) : ['to' => [], 'from' => []];
$partyField = static function (string $side, string $field) use ($v, $dirParts): string {
    $nameKey = 'directed_' . $side . '_' . $field;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$nameKey])) {
        return (string) $_POST[$nameKey];
    }
    $fields = briefing_party_fields($dirParts[$side] ?? [], (string) $v('directed_' . $side));
    return (string) ($fields[$field] ?? '');
};
?>
<div class="page-header corr-view-header">
    <div class="corr-view-title-wrap">
        <div class="corr-view-badges">
            <span class="badge text-bg-secondary"><?= e(type_label((string) ($corr['type'] ?? 'incoming'))) ?></span>
            <span class="badge <?= $locked ? 'text-bg-success' : 'text-bg-light' ?>">
                <?= e($locked ? __('brief.status_decided') : __('brief.status_draft')) ?>
            </span>
        </div>
        <h1 class="page-title"><?= e(__('brief.page')) ?> — <span dir="ltr"><?= e($corr['ref_number']) ?></span></h1>
        <p class="page-sub"><?= e($corr['subject']) ?></p>
    </div>
    <div class="page-header-actions">
        <?= briefing_swap_dir_button() ?>
        <?php if ($brief): ?>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('correspondence/brief_print.php?id=' . $id)) ?>" target="_blank">
                <i class="bi bi-printer me-1"></i><?= e(__('brief.print')) ?>
            </a>
            <a class="btn btn-outline-secondary" href="<?= e(briefing_word_url($id)) ?>">
                <i class="bi bi-file-earmark-word me-1"></i><?= e(__('brief.word')) ?>
            </a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(base_url($viewUrl)) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<?php if ($locked): ?>
<div class="alert alert-success">
    <i class="bi bi-check2-circle me-1"></i><?= e(__('brief.locked_notice')) ?>
    <?php if (!empty($brief['decided_at'])): ?>
        — <?= e(format_datetime($brief['decided_at'])) ?>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="brief-editor<?= $ro ? ' is-locked' : '' ?>" id="brief-form">
    <?= csrf_field() ?>
    <input type="hidden" name="correspondence_id" value="<?= (int) $id ?>">

    <div class="brief-editor-sheet">
        <div class="brief-editor-head">
            <div class="brief-editor-kicker"><?= e(__('brief.form_title')) ?></div>
            <h2 class="brief-editor-title"><?= e(briefing_title_for_type((string) $corr['type'])) ?></h2>
        </div>

        <div class="brief-editor-meta" id="briefDirection">
            <div class="brief-editor-cell">
                <span class="brief-editor-k"><?= e($isOutgoing ? __('brief.ref_outgoing') : ($isInternal ? __('brief.ref_internal') : __('brief.ref_incoming'))) ?></span>
                <span class="brief-editor-ref" dir="ltr"><?= e($corr['ref_number']) ?></span>
            </div>
            <div class="brief-editor-cell">
                <label class="brief-editor-k" for="receipt_date-display"><?= e(__('brief.receipt_date')) ?></label>
                <?php
                render_dms_gregorian_date_field([
                    'name' => 'receipt_date',
                    'id' => 'receipt_date',
                    'value' => (string) $v('receipt_date'),
                    'readonly' => $ro,
                ]);
                ?>
            </div>
            <div class="brief-editor-cell">
                <label class="brief-editor-k" for="basis_number"><?= e(__('brief.basis_number')) ?></label>
                <input type="text" name="basis_number" id="basis_number" class="form-control" value="<?= e((string) $v('basis_number')) ?>" <?= $attrRo ?>>
            </div>
            <div class="brief-editor-cell">
                <label class="brief-editor-k" for="basis_date-display"><?= e(__('brief.basis_date')) ?></label>
                <?php
                render_dms_gregorian_date_field([
                    'name' => 'basis_date',
                    'id' => 'basis_date',
                    'value' => (string) $v('basis_date'),
                    'readonly' => $ro,
                ]);
                ?>
            </div>
            <div class="brief-editor-cell brief-editor-span" data-dir-row="to">
                <span class="brief-editor-k"><?= e(__('brief.to')) ?></span>
                <div class="brief-party-lines">
                    <label class="brief-party-line">
                        <span class="brief-party-k"><?= e(__('brief.party_name')) ?></span>
                        <input type="text" name="directed_to_name" id="directed_to_name" class="form-control" value="<?= e($partyField('to', 'name')) ?>" <?= $attrRo ?>>
                    </label>
                    <label class="brief-party-line">
                        <span class="brief-party-k"><?= e(__('brief.party_job')) ?></span>
                        <input type="text" name="directed_to_job" id="directed_to_job" class="form-control" value="<?= e($partyField('to', 'job')) ?>" <?= $attrRo ?>>
                    </label>
                    <label class="brief-party-line">
                        <span class="brief-party-k"><?= e(__('brief.party_org')) ?></span>
                        <input type="text" name="directed_to_org" id="directed_to_org" class="form-control" value="<?= e($partyField('to', 'org')) ?>" <?= $attrRo ?>>
                    </label>
                </div>
            </div>
            <div class="brief-editor-cell brief-editor-span" data-dir-row="from">
                <span class="brief-editor-k"><?= e(__('brief.from')) ?></span>
                <div class="brief-party-lines">
                    <label class="brief-party-line">
                        <span class="brief-party-k"><?= e(__('brief.party_name')) ?></span>
                        <input type="text" name="directed_from_name" id="directed_from_name" class="form-control" value="<?= e($partyField('from', 'name')) ?>" <?= $attrRo ?>>
                    </label>
                    <label class="brief-party-line">
                        <span class="brief-party-k"><?= e(__('brief.party_job')) ?></span>
                        <input type="text" name="directed_from_job" id="directed_from_job" class="form-control" value="<?= e($partyField('from', 'job')) ?>" <?= $attrRo ?>>
                    </label>
                    <label class="brief-party-line">
                        <span class="brief-party-k"><?= e(__('brief.party_org')) ?></span>
                        <input type="text" name="directed_from_org" id="directed_from_org" class="form-control" value="<?= e($partyField('from', 'org')) ?>" <?= $attrRo ?>>
                    </label>
                </div>
            </div>
            <div class="brief-editor-cell brief-editor-span">
                <span class="brief-editor-k"><?= e(__('corr.subject')) ?></span>
                <span class="brief-editor-subject"><?= e($corr['subject']) ?></span>
            </div>
            <div class="brief-editor-cell brief-editor-span">
                <label class="brief-editor-k" for="attachments_desc"><?= e(__('brief.attachments')) ?></label>
                <textarea name="attachments_desc" id="attachments_desc" class="form-control" rows="2" <?= $attrRo ?>><?= e((string) $v('attachments_desc')) ?></textarea>
            </div>
            <div class="brief-editor-cell brief-editor-span">
                <label class="brief-editor-k" for="request_text"><?= e(__('brief.request')) ?></label>
                <textarea name="request_text" id="request_text" class="form-control brief-request-input" rows="2" <?= $attrRo ?>><?= e((string) $v('request_text')) ?></textarea>
            </div>
        </div>

        <div class="brief-editor-block">
            <label class="brief-editor-block-title" for="basis_text"><?= e(__('brief.basis')) ?></label>
            <textarea name="basis_text" id="basis_text" class="form-control" rows="3" style="text-align:justify" <?= $attrRo ?>><?= e((string) $v('basis_text')) ?></textarea>
        </div>
        <div class="brief-editor-block">
            <label class="brief-editor-block-title" for="study_text"><?= e(__('brief.study')) ?></label>
            <textarea name="study_text" id="study_text" class="form-control" rows="3" <?= $attrRo ?>><?= e((string) $v('study_text')) ?></textarea>
        </div>
        <div class="brief-editor-block">
            <label class="brief-editor-block-title" for="recommendation_intro"><?= e(__('brief.recommendation')) ?></label>
            <p class="admin-form-hint"><?= e(__('brief.recommendation_hint')) ?></p>
            <label class="visually-hidden" for="recommendation_intro"><?= e(__('brief.recommendation_intro')) ?></label>
            <input type="text" name="recommendation_intro" id="recommendation_intro" class="form-control mb-2"
                   value="<?= e((string) ($recParts['intro'] ?? '')) ?>"
                   placeholder="<?= e(__('brief.recommendation_intro_ph')) ?>" <?= $attrRo ?>>
            <div id="brief-rec-items" class="brief-option-list brief-rec-list">
                <?php foreach ($recItems as $item): ?>
                    <div class="brief-option-row brief-rec-row">
                        <span class="brief-rec-num" aria-hidden="true"></span>
                        <input type="text" name="rec_item[]" class="form-control" value="<?= e($item) ?>" placeholder="<?= e(__('brief.recommendation_item_ph')) ?>" <?= $attrRo ?>>
                        <?php if (!$ro): ?>
                            <button type="button" class="btn btn-outline-secondary brief-rec-remove" title="<?= e(__('action.delete')) ?>"><i class="bi bi-x-lg"></i></button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$ro): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="brief-rec-add">
                    <i class="bi bi-plus-lg me-1"></i><?= e(__('brief.add_rec_item')) ?>
                </button>
            <?php endif; ?>
        </div>

        <div class="brief-editor-block brief-editor-direction">
            <div class="brief-editor-block-title"><?= e(__('brief.direction')) ?></div>
            <label class="visually-hidden" for="direction_intro"><?= e(__('brief.direction_intro')) ?></label>
            <textarea name="direction_intro" id="direction_intro" class="form-control mb-3" rows="2" <?= $attrRo ?>><?= e((string) $v('direction_intro')) ?></textarea>
            <p class="admin-form-hint"><?= e(__('brief.options_hint')) ?></p>
            <div id="brief-options" class="brief-option-list">
                <?php foreach ($options as $i => $opt): ?>
                    <div class="brief-option-row">
                        <?php if ($canEdit && !$locked): ?>
                            <input type="hidden" name="option_on[<?= (int) $i ?>]" value="0">
                            <input type="checkbox" class="form-check-input" name="option_on[<?= (int) $i ?>]" value="1" <?= !empty($opt['selected']) ? 'checked' : '' ?> title="<?= e(__('brief.mark_selected')) ?>">
                        <?php else: ?>
                            <span class="brief-check<?= !empty($opt['selected']) ? ' is-on' : '' ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <input type="text" name="option_label[]" class="form-control" value="<?= e((string) ($opt['label'] ?? '')) ?>" <?= $attrRo ?>>
                        <?php if (!$ro): ?>
                            <button type="button" class="btn btn-outline-secondary brief-opt-remove" title="<?= e(__('action.delete')) ?>"><i class="bi bi-x-lg"></i></button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$ro): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="brief-opt-add">
                    <i class="bi bi-plus-lg me-1"></i><?= e(__('brief.add_option')) ?>
                </button>
            <?php endif; ?>
            <div class="brief-editor-note">
                <label class="brief-editor-k" for="decision_note"><?= e(__('brief.decision_note')) ?></label>
                <input type="text" name="decision_note" id="decision_note" class="form-control" value="<?= e((string) $v('decision_note')) ?>" <?= $attrRo ?>>
            </div>
        </div>

        <div class="brief-editor-sign">
            <div class="brief-editor-sign-field">
                <label class="brief-editor-k" for="approver_name"><?= e(__('brief.approver_name')) ?></label>
                <input type="text" name="approver_name" id="approver_name" class="form-control" value="<?= e((string) $v('approver_name')) ?>" <?= $attrRo ?>>
            </div>
            <div class="brief-editor-sign-field">
                <label class="brief-editor-k" for="approver_title"><?= e(__('brief.approver_title')) ?></label>
                <input type="text" name="approver_title" id="approver_title" class="form-control" value="<?= e((string) $v('approver_title')) ?>" <?= $attrRo ?>>
            </div>
        </div>
    </div>

    <?php if ($canEdit && !$locked): ?>
    <div class="brief-editor-actions">
        <button class="btn btn-kff" type="submit" name="brief_action" value="save">
            <i class="bi bi-check2 me-1"></i><?= e(__('brief.save')) ?>
        </button>
        <button class="btn btn-gold" type="submit" name="brief_action" value="decide" data-confirm="<?= e(__('brief.confirm_decide')) ?>">
            <i class="bi bi-pen me-1"></i><?= e(__('brief.decide')) ?>
        </button>
    </div>
    <?php endif; ?>
</form>
<template id="brief-opt-tpl">
    <div class="brief-option-row">
        <input type="hidden" name="option_on[__i__]" value="0">
        <input type="checkbox" class="form-check-input" name="option_on[__i__]" value="1">
        <input type="text" name="option_label[]" class="form-control" value="">
        <button type="button" class="btn btn-outline-secondary brief-opt-remove" title="<?= e(__('action.delete')) ?>"><i class="bi bi-x-lg"></i></button>
    </div>
</template>
<template id="brief-rec-tpl">
    <div class="brief-option-row brief-rec-row">
        <span class="brief-rec-num" aria-hidden="true"></span>
        <input type="text" name="rec_item[]" class="form-control" value="" placeholder="">
        <button type="button" class="btn btn-outline-secondary brief-rec-remove" title="<?= e(__('action.delete')) ?>"><i class="bi bi-x-lg"></i></button>
    </div>
</template>
<script>
(function () {
  var list = document.getElementById('brief-options');
  var addBtn = document.getElementById('brief-opt-add');
  var tpl = document.getElementById('brief-opt-tpl');
  var recList = document.getElementById('brief-rec-items');
  var recAdd = document.getElementById('brief-rec-add');
  var recTpl = document.getElementById('brief-rec-tpl');
  var form = document.getElementById('brief-form');
  function reindex() {
    if (!list) return;
    list.querySelectorAll('.brief-option-row').forEach(function (row, i) {
      row.querySelectorAll('[name^="option_on"]').forEach(function (el) {
        el.setAttribute('name', 'option_on[' + i + ']');
      });
    });
  }
  if (addBtn && tpl && list) {
    addBtn.addEventListener('click', function () {
      var html = tpl.innerHTML.replace(/__i__/g, String(list.children.length));
      list.insertAdjacentHTML('beforeend', html);
      reindex();
    });
  }
  if (list) {
    list.addEventListener('click', function (e) {
      var btn = e.target.closest('.brief-opt-remove');
      if (!btn) return;
      var row = btn.closest('.brief-option-row');
      if (row && list.children.length > 1) {
        row.remove();
        reindex();
      }
    });
  }
  if (recAdd && recTpl && recList) {
    recAdd.addEventListener('click', function () {
      recList.insertAdjacentHTML('beforeend', recTpl.innerHTML);
    });
    recList.addEventListener('click', function (e) {
      var btn = e.target.closest('.brief-rec-remove');
      if (!btn) return;
      var row = btn.closest('.brief-rec-row');
      if (row && recList.children.length > 1) {
        row.remove();
      }
    });
  }
  if (form) {
    form.addEventListener('submit', function (e) {
      var btn = e.submitter;
      if (!btn || btn.getAttribute('name') !== 'brief_action' || btn.value !== 'decide') return;
      var msg = btn.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) {
        e.preventDefault();
      }
    });
  }
})();
</script>
<?php
briefing_dir_order_script($dirState['order'], $dirState['forced']);
require __DIR__ . '/../includes/footer.php';
?>
