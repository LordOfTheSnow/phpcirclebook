<article>
    <h2>Unsubscribe</h2>
    <p>Are you sure you want to unsubscribe <strong><?= htmlspecialchars($email) ?></strong> from <?= htmlspecialchars($appName) ?>?</p>
    <p>You will no longer appear in the recipient list and will not be able to request it.</p>

    <form method="post" action="?action=confirm-unsubscribe">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <button type="submit">Yes, unsubscribe me</button>
    </form>

    <p><a href="<?= htmlspecialchars($appUrl) ?>">&larr; No, take me back</a></p>
</article>
