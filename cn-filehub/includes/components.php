<?php
declare(strict_types=1);

/** Text input with label and an inline error. style: pill (auth pages) or box (forms). */
function text_field(string $id, string $label, string $value = '', string $error = '', array $o = []): void
{
    $style = $o['style'] ?? 'box';
    $cls   = $style === 'pill' ? 'field field-pill' : 'field';
    $attrs = '';
    foreach (['pattern', 'title', 'maxlength', 'autocomplete', 'placeholder'] as $a) {
        if (isset($o[$a])) $attrs .= ' ' . $a . '="' . e((string) $o[$a]) . '"';
    }
    ?>
    <div class="form-row">
      <div class="label-row">
        <label for="<?= e($id) ?>"><?= e($label) ?></label>
        <?php if ($style === 'box'): ?><span class="field-error" data-error-for="<?= e($id) ?>"><?= e($error) ?></span><?php endif; ?>
      </div>
      <input class="<?= e($cls) ?>" type="<?= e($o['type'] ?? 'text') ?>" id="<?= e($id) ?>" name="<?= e($id) ?>"
             value="<?= e($value) ?>" required<?= $attrs ?>>
      <?php if ($style === 'pill'): ?><span class="field-error" data-error-for="<?= e($id) ?>"><?= e($error) ?></span><?php endif; ?>
    </div>
    <?php
}

/** Password input with show/hide eye. role: new | confirm | '' */
function password_field(string $id, string $label, string $error = '', array $o = []): void
{
    $style = $o['style'] ?? 'pill';
    $role  = $o['role'] ?? '';
    $attr  = $role === 'new' ? ' data-password' : ($role === 'confirm' ? ' data-confirm' : '');
    $cls   = $style === 'pill' ? 'field field-pill' : 'field';
    ?>
    <div class="form-row">
      <div class="label-row">
        <label for="<?= e($id) ?>"><?= e($label) ?></label>
        <?php if ($style === 'box'): ?><span class="field-error" data-error-for="<?= e($id) ?>"><?= e($error) ?></span><?php endif; ?>
      </div>
      <div class="pw-wrap">
        <input class="<?= e($cls) ?>" type="password" id="<?= e($id) ?>" name="<?= e($id) ?>" required maxlength="200"
               autocomplete="<?= e($o['autocomplete'] ?? 'current-password') ?>"
               <?= isset($o['placeholder']) ? 'placeholder="' . e($o['placeholder']) . '"' : '' ?><?= $attr ?>>
        <button type="button" class="pw-toggle" data-toggle-password="<?= e($id) ?>" aria-label="Show password">
          <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5C6.5 5 2.7 9 1 12c1.7 3 5.5 7 11 7s9.3-4 11-7c-1.7-3-5.5-7-11-7zm0 11a4 4 0 110-8 4 4 0 010 8zm0-6a2 2 0 100 4 2 2 0 000-4z"/></svg>
          <svg class="eye-shut" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="M12 5C6.5 5 2.7 9 1 12c1.7 3 5.5 7 11 7s9.3-4 11-7c-1.7-3-5.5-7-11-7zm0 11a4 4 0 110-8 4 4 0 010 8z"/><path d="M3 3l18 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        </button>
      </div>
      <?php if ($style === 'pill'): ?><span class="field-error" data-error-for="<?= e($id) ?>"><?= e($error) ?></span><?php endif; ?>
    </div>
    <?php
}
