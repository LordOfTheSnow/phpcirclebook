<article>
    <header><strong><?= __('info.title') ?></strong></header>
    <p><?= __('info.intro') ?></p>
    <?php if (!empty($appDescription)): ?>
        <p><?= htmlspecialchars($appDescription) ?></p>
    <?php endif; ?>
    <p><?= __('info.how_it_works') ?></p>
    <footer><small><?= __('info.contact', ['email' => obfuscateEmail($adminEmail)]) ?></small></footer>
</article>

<form method="post" action="?action=submit">
    <fieldset>
        <label for="email">
            <?= __('form.email_label') ?>
            <input type="email" id="email" name="email" placeholder="<?= htmlspecialchars(__('form.email_placeholder')) ?>" required>
        </label>

        <label for="name">
            <?= __('form.name_label') ?>
            <input type="text" id="name" name="name" placeholder="<?= htmlspecialchars(__('form.name_placeholder')) ?>">
        </label>

        <!-- Honeypot field — hidden from humans -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label for="website"><?= __('form.honeypot_label') ?></label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit"><?= __('form.submit') ?></button>
    </fieldset>
</form>
<p><small><?= __('form.explanation') ?></small></p>
