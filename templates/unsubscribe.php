<article>
    <h2><?= __('unsubscribe.heading') ?></h2>
    <p><?= __('unsubscribe.confirm', ['email' => '<strong>' . htmlspecialchars($email) . '</strong>', 'appName' => htmlspecialchars($appName)]) ?></p>
    <p><?= __('unsubscribe.warning') ?></p>

    <form method="post" action="?action=confirm-unsubscribe">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <button type="submit"><?= __('unsubscribe.submit') ?></button>
    </form>

    <p><a href="<?= htmlspecialchars($appUrl) ?>">&larr; <?= __('unsubscribe.cancel') ?></a></p>
</article>
