<?php

declare(strict_types=1);

/*
 * Fallback front controller for hosts where the document root can't be pointed
 * at public/ (a fixed public_html on FTP-only shared hosting, for example).
 * Paired with the root .htaccess, which denies access to the non-public files
 * and routes everything else here. See the README "Installing via FTP" section
 * for when this is needed.
 *
 * public/index.php resolves all its paths via dirname(__DIR__), i.e. relative to
 * the public/ directory, so requiring it from here works unchanged.
 */
require __DIR__ . '/public/index.php';
