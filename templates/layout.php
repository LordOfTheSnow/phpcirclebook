<!DOCTYPE html>
<html lang="<?= htmlspecialchars(App\Translator::getInstance()->getLanguage()) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        /*
         * Make form labels and inputs a little more prominent, especially on mobile.
         * Pico styles form controls with fairly high specificity and via its own
         * custom properties, so we retune those variables and use matching selectors
         * rather than plain `form input`, which Pico's shorthand would override.
         */
        :root {
            --pico-form-element-spacing-vertical: 0.85rem;
            --pico-form-element-spacing-horizontal: 1rem;
        }

        form label {
            font-weight: 600;
            font-size: 1.05rem;
            margin-bottom: 1.25rem;
        }

        form input:not([type="checkbox"]):not([type="radio"]),
        form textarea,
        form select {
            border: 2px solid #b9c2cc;
            border-radius: 8px;
            background-color: #f6f8fa;
        }

        form input:not([type="checkbox"]):not([type="radio"]):focus,
        form textarea:focus,
        form select:focus {
            border-color: var(--pico-primary, #0172ad);
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(1, 114, 173, 0.20);
        }

        /* Header: title on the left, version right-aligned and vertically centered. */
        .app-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .app-header h1 {
            margin-bottom: 0;
            color: #0159a3;
        }

        .app-version {
            white-space: nowrap;
        }

        .app-version a {
            color: #0159a3;
        }

        /* Privacy disclaimer under the form. */
        .disclaimer {
            color: #c0392b;
        }

        .disclaimer .disclaimer-icon {
            margin-right: 0.35rem;
        }

        /* On narrow screens, stack the version under the title instead of crowding it. */
        @media (max-width: 480px) {
            .app-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="app-header">
            <h1><?= htmlspecialchars($appName) ?></h1>
            <small class="app-version">
                <a href="https://github.com/LordOfTheSnow/phpcirclebook" target="_blank">PHPCircleBook v<?= htmlspecialchars(app_version()) ?></a>
            </small>
        </header>
        <?= $content ?>
    </main>
</body>
</html>
