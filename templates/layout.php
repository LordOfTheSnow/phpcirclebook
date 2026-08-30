<!DOCTYPE html>
<html lang="<?= htmlspecialchars(App\Translator::getInstance()->getLanguage()) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $appName) ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
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
            /*
             * Baseline header height, driven by the app title. The optional logo
             * is capped at this height + 40px; when the logo is taller than the
             * title the flex row grows and everything stays vertically centered
             * (align-items: center).
             */
            --app-header-height: 2.75rem;
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

        /* Logo + title grouped on the left so the version stays on the right.
           flex: 1 lets the group claim the width left over by the (nowrap) version
           block, so the title only wraps when it genuinely runs out of room. */
        .app-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1 1 auto;
            min-width: 0;
        }

        .app-logo {
            /* Fit the header by default; may exceed it by at most 40px. Aspect
               ratio preserved; small logos are shown at natural size (not upscaled). */
            height: auto;
            width: auto;
            max-height: calc(var(--app-header-height) + 40px);
            max-width: 100%;
            flex-shrink: 0;
        }

        .app-version {
            font-size: 0.75rem;
            color: #6b7680;
            white-space: nowrap;
        }

        .app-version a {
            color: #0159a3;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .app-version .gh-icon {
            flex-shrink: 0;
        }

        /* Privacy disclaimer under the form. */
        .disclaimer {
            color: #c0392b;
        }

        .disclaimer .disclaimer-icon {
            margin-right: 0.35rem;
        }

        /* Optional footer line under the form (APP_FOOTER). */
        .app-footer-divider {
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .app-footer {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 0.9rem;
            color: #6b7680;
        }

        /* On narrow screens, stack the version under the title instead of crowding it. */
        @media (max-width: 480px) {
            .app-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            /* Let the "powered by ..." line wrap instead of overflowing, and keep
               the icon aligned with the first line of wrapped text. */
            .app-version {
                white-space: normal;
            }

            .app-version a {
                flex-wrap: wrap;
            }
        }

        /*
         * Sidebar layout. Two columns on wide screens (form + sidebar), stacking
         * to a single column below 768px. The main content is always first in
         * the DOM; `order` on the columns places the sidebar left or right
         * visually, and when stacked the sidebar always drops below the form.
         */
        .layout-with-sidebar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 20rem);
            gap: 2.5rem;
            align-items: start;
        }

        .layout-with-sidebar .layout-main {
            order: 1;
            min-width: 0;
        }

        .layout-with-sidebar .layout-sidebar {
            order: 2;
        }

        /* Sidebar on the left: swap the visual order of the two columns. */
        .layout-with-sidebar.sidebar-left {
            grid-template-columns: minmax(0, 20rem) minmax(0, 1fr);
        }

        .layout-with-sidebar.sidebar-left .layout-main {
            order: 2;
        }

        .layout-with-sidebar.sidebar-left .layout-sidebar {
            order: 1;
        }

        .sidebar-card {
            margin-bottom: 1.5rem;
            border: 1px solid #dfe4ea;
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(15, 40, 70, 0.12), 0 2px 4px rgba(15, 40, 70, 0.08);
        }

        /* Uniform card heading, shared by the info card and the sidebar cards. */
        .card-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: #0159a3;
            border-bottom: 2px solid #e1e8ee;
            padding-bottom: 0.5rem;
            margin-bottom: 0.75rem;
        }

        /*
         * Pico sets `aside li a { display: block }` for nav menus. Our sidebar
         * lives in an <aside>, so that rule would force inline links onto their
         * own line (breaking text like "dinner (reserve a seat)" across lines).
         * Keep links inline within card content.
         */
        .sidebar-card li a {
            display: inline;
            margin: 0;
            padding: 0;
        }

        @media (max-width: 768px) {
            .layout-with-sidebar,
            .layout-with-sidebar.sidebar-left {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            /* Stacked: form first, sidebar below, regardless of desktop side. */
            .layout-with-sidebar .layout-main,
            .layout-with-sidebar.sidebar-left .layout-main {
                order: 1;
            }

            .layout-with-sidebar .layout-sidebar,
            .layout-with-sidebar.sidebar-left .layout-sidebar {
                order: 2;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <header class="app-header">
            <div class="app-brand">
                <?php $logoUrl = logoSrc($appLogo ?? ''); ?>
                <?php if ($logoUrl !== ''): ?>
                    <img class="app-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($appName) ?>">
                <?php endif; ?>
                <h1><?= htmlspecialchars($appName) ?></h1>
            </div>
            <small class="app-version">
                powered by
                <a href="https://github.com/LordOfTheSnow/phpcirclebook" target="_blank" rel="noopener">
                    PHPCircleBook v<?= htmlspecialchars(app_version()) ?>
                    <svg class="gh-icon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
                </a>
            </small>
        </header>
        <?php if (!empty($sidebarCards)): ?>
            <div class="layout-with-sidebar <?= $sidebarSide === 'left' ? 'sidebar-left' : 'sidebar-right' ?>">
                <div class="layout-main"><?= $content ?></div>
                <aside class="layout-sidebar">
                    <?php foreach ($sidebarCards as $card): ?>
                        <article class="sidebar-card">
                            <header class="card-title"><?= htmlspecialchars($card['title']) ?></header>
                            <?= $card['body'] ?>
                        </article>
                    <?php endforeach; ?>
                </aside>
            </div>
        <?php else: ?>
            <?= $content ?>
        <?php endif; ?>
    </main>
</body>
</html>
