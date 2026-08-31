<article>
    <header class="card-title"><?= __('info.title') ?></header>
    <p><?= __('info.intro') ?></p>
    <?php if (!empty($appDescription)): ?>
        <p><?= htmlspecialchars($appDescription) ?></p>
    <?php endif; ?>
    <?php if (!empty($listStats) && $listStats['count'] > 0): ?>
        <?php
            // updated_at is stored as a UTC 'YYYY-MM-DD HH:MM:SS' string by SQLite;
            // convert to the configured app timezone (APP_TIMEZONE) for display.
            $lastUpdate = (new \DateTimeImmutable($listStats['last_update'], new \DateTimeZone('UTC')))
                ->setTimezone(appTimezone());
        ?>
        <p><small><?= htmlspecialchars(__('info.stats', [
            'count' => formatNumber($listStats['count']),
            'date' => formatDate($lastUpdate),
        ])) ?></small></p>
    <?php endif; ?>
    <p><?= __('info.how_it_works') ?></p>
    <p class="disclaimer"><small><strong><span class="disclaimer-icon" aria-hidden="true">&#9888;&#65039;</span><?= __('form.disclaimer') ?></strong></small></p>
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

        <label for="comment">
            <?= __('form.comment_label', ['max' => COMMENT_MAX_LENGTH]) ?>
            <textarea id="comment" name="comment" rows="3" maxlength="<?= COMMENT_MAX_LENGTH ?>" placeholder="<?= htmlspecialchars(__('form.comment_placeholder')) ?>"></textarea>
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

<?php if (!empty($appFooter)): ?>
    <hr class="app-footer-divider">
    <footer class="app-footer"><small><?= htmlspecialchars($appFooter) ?></small></footer>
<?php endif; ?>
