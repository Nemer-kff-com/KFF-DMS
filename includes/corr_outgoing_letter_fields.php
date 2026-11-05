<?php
/**
 * قسم نص الخطاب + قالب + معاينة البريد — للصادر الفردي (إنشاء/تعديل).
 * يتوقع اختيارياً: $corr, $user
 */
if (!function_exists('letter_templates_list')) {
    require_once __DIR__ . '/../config/letter_templates.php';
}
$user = $user ?? (function_exists('current_user') ? current_user() : null);
$templates = letter_templates_list($user, true);
$letterAr = (string) (
    $_POST['letter_body']
    ?? ($corr['letter_body'] ?? '')
);
$letterEn = (string) (
    $_POST['letter_body_en']
    ?? ($corr['letter_body_en'] ?? '')
);
$letterSectionAlways = !empty($letterSectionAlways);
$letterSectionOpen = $letterSectionAlways
    || !empty($_POST['intend_electronic'])
    || (!empty($corr['intend_electronic']) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST');
?>
<div class="admin-form-section<?= $letterSectionOpen ? '' : ' d-none' ?>" id="outgoing-letter-section"
     <?= $letterSectionAlways ? 'data-letter-always="1"' : 'data-depends-on="intend_electronic"' ?>>
    <div class="admin-form-section-title">
        <span><?= e(__('bulk.letter_body')) ?></span>
    </div>
    <?php if ($templates): ?>
    <div class="mb-2">
        <label class="form-label" for="letter_template_id"><?= e(__('tpl.apply')) ?></label>
        <select name="letter_template_id" id="letter_template_id" class="form-select" data-letter-templates>
            <option value=""><?= e(__('common.none')) ?></option>
            <?php
            $letterTplSelected = (int) ($_POST['letter_template_id'] ?? ($letterTemplateId ?? (is_array($corr ?? null) ? ($corr['letter_template_id'] ?? 0) : 0)));
            foreach ($templates as $tpl): ?>
                <option value="<?= (int) $tpl['id'] ?>"
                        data-body="<?= e((string) ($tpl['body'] ?? '')) ?>"
                        data-body-en="<?= e((string) ($tpl['body_en'] ?? '')) ?>"
                        <?= $letterTplSelected === (int) $tpl['id'] ? 'selected' : '' ?>><?= e(letter_template_label($tpl)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php render_letter_lang_editor('letter_body', 'letter_body_en', $letterAr, $letterEn, 8, false, [], [], '', letter_signer_insert_tokens(), [
        'template_id' => (int) ($letterTemplateId ?? (is_array($corr ?? null) ? ($corr['letter_template_id'] ?? 0) : 0)),
        'ar' => 'letter_body',
        'en' => 'letter_body_en',
    ]); ?>
    <?php
    $batchLink = is_array($editBatch ?? null) ? $editBatch : [];
    $corrLink = is_array($corr ?? null) ? $corr : [];
    $linkUrlVal = (string) (
        $_POST['letter_link_url']
        ?? ($corrLink['letter_link_url'] ?? ($batchLink['letter_link_url'] ?? ''))
    );
    $linkLabelVal = (string) (
        $_POST['letter_link_label']
        ?? ($corrLink['letter_link_label'] ?? ($batchLink['letter_link_label'] ?? ''))
    );
    $linkEnabled = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? (!empty($_POST['letter_link_enable']) || $linkUrlVal !== '')
        : ($linkUrlVal !== '');
    ?>
    <div class="mt-3">
        <label class="corr-option-card d-inline-flex" title="<?= e(__('letter_link.enable_hint')) ?>">
            <input type="checkbox" class="form-check-input" name="letter_link_enable" id="letter_link_enable" value="1" <?= $linkEnabled ? 'checked' : '' ?>>
            <span class="corr-option-title"><?= e(__('letter_link.enable')) ?></span>
        </label>
        <div class="letter-link-fields<?= $linkEnabled ? '' : ' d-none' ?>" id="letter-link-fields">
            <p class="admin-form-hint mb-2"><?= e(__('letter_link.enable_hint')) ?></p>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label" for="letter_link_label"><?= e(__('letter_link.label')) ?></label>
                    <input type="text" class="form-control" name="letter_link_label" id="letter_link_label"
                           value="<?= e($linkLabelVal) ?>" maxlength="200" placeholder="<?= e(__('letter_link.label_ph')) ?>"
                           <?= $linkEnabled ? '' : 'disabled' ?>>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="letter_link_url"><?= e(__('letter_link.url')) ?></label>
                    <input type="url" class="form-control" name="letter_link_url" id="letter_link_url" dir="ltr"
                           value="<?= e($linkUrlVal) ?>" maxlength="500" placeholder="<?= e(__('letter_link.url_ph')) ?>"
                           <?= $linkEnabled ? '' : 'disabled' ?>>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  var section = document.getElementById('outgoing-letter-section');
  var box = document.getElementById('intend_electronic');
  if (section && box && section.getAttribute('data-letter-always') !== '1') {
    function syncLetterSection() {
      var on = !!box.checked;
      section.classList.toggle('d-none', !on);
      section.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.disabled = !on;
      });
    }
    box.addEventListener('change', syncLetterSection);
    syncLetterSection();
  }

  var tpl = document.querySelector('#outgoing-letter-section [data-letter-templates]');
  var ta = document.getElementById('letter_body');
  var taEn = document.getElementById('letter_body_en');
  if (tpl && ta) {
    tpl.addEventListener('change', function () {
      var opt = tpl.options[tpl.selectedIndex];
      var body = opt ? (opt.getAttribute('data-body') || '') : '';
      var bodyEn = opt ? (opt.getAttribute('data-body-en') || '') : '';
      if (body) ta.value = body;
      if (taEn && bodyEn) taEn.value = bodyEn;
    });
  }

  var linkBox = document.getElementById('letter_link_enable');
  var linkFields = document.getElementById('letter-link-fields');
  if (linkBox && linkFields) {
    function syncLetterLink() {
      var on = !!linkBox.checked;
      linkFields.classList.toggle('d-none', !on);
      linkFields.querySelectorAll('input').forEach(function (input) {
        input.disabled = !on;
      });
    }
    linkBox.addEventListener('change', syncLetterLink);
    syncLetterLink();
  }
})();
</script>
