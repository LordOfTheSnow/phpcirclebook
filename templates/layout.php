<!DOCTYPE html>
<html lang="<?= htmlspecialchars(App\Translator::getInstance()->getLanguage()) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
</head>
<body>
    <main class="container">
        <header>
            <h1><?= htmlspecialchars($appName) ?></h1>
        </header>
        <?= $content ?>
    </main>
</body>
</html>
