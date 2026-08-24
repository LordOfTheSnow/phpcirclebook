<form method="post" action="?action=submit">
    <fieldset>
        <label for="email">
            Email address
            <input type="email" id="email" name="email" placeholder="you@example.com" required>
        </label>

        <label for="name">
            Name (optional)
            <input type="text" id="name" name="name" placeholder="Your Name">
        </label>

        <!-- Honeypot field — hidden from humans -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label for="website">Leave this empty</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit">Send me the list</button>
    </fieldset>
</form>
<p><small>Enter your email to receive the recipient list. If you're not yet registered, your request will be forwarded to the administrator for approval.</small></p>
