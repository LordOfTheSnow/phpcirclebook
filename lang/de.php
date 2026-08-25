<?php

declare(strict_types=1);

return [
    // --- Formular (templates/form.php) ---
    'form.email_label'       => 'E-Mail-Adresse',
    'form.email_placeholder' => 'du@beispiel.de',
    'form.name_label'        => 'Name (optional)',
    'form.name_placeholder'  => 'Dein Name',
    'form.honeypot_label'    => 'Dieses Feld leer lassen',
    'form.submit'            => 'Liste anfordern',
    'form.explanation'       => 'Gib deine E-Mail-Adresse ein, um die Empfängerliste zu erhalten. Falls du noch nicht registriert bist, wird deine Anfrage zur Genehmigung an den Administrator weitergeleitet.',

    // --- Abmeldung (templates/unsubscribe.php) ---
    'unsubscribe.heading'    => 'Abmelden',
    'unsubscribe.confirm'    => 'Möchtest du {email} wirklich von {appName} abmelden?',
    'unsubscribe.warning'    => 'Du wirst nicht mehr in der Empfängerliste erscheinen und sie auch nicht mehr anfordern können.',
    'unsubscribe.submit'     => 'Ja, abmelden',
    'unsubscribe.cancel'     => 'Nein, zurück',

    // --- Nachricht (templates/message.php) ---
    'message.back'           => 'Zurück zum Formular',

    // --- Controller-Meldungen (public/index.php) ---
    'message.generic_thanks'    => 'Vielen Dank. Falls deine Adresse berechtigt ist, erhältst du in Kürze eine E-Mail.',
    'message.invalid_email'     => 'Bitte gib eine gültige E-Mail-Adresse ein.',
    'message.rate_limited'      => 'Zu viele Anfragen. Bitte versuche es später erneut.',
    'message.list_sent'         => 'Die Liste wurde an deine E-Mail-Adresse gesendet.',
    'message.invalid_link'      => 'Ungültiger oder abgelaufener Link.',
    'message.approved'          => 'Genehmigt! {email} wurde zur Liste hinzugefügt und benachrichtigt.',
    'message.rejected'          => 'Abgelehnt. {email} wurde stillschweigend abgewiesen.',
    'message.invalid_unsub'     => 'Ungültiger Abmelde-Link.',
    'message.unsubscribed'      => 'Du wurdest abgemeldet. Du kannst dich jederzeit erneut registrieren.',

    // --- E-Mail: Empfängerliste (src/Mailer.php) ---
    'mail.list_subject'      => '[{appName}] Empfängerliste',
    'mail.list_intro'        => 'Hier ist die aktuelle Empfängerliste für {appName}:',
    'mail.list_total'        => 'Insgesamt: {count} Empfänger',
    'mail.list_unsubscribe'  => 'Um dich von dieser Liste abzumelden:',

    // --- E-Mail: Genehmigungsanfrage an Admin ---
    'mail.approval_subject'  => '[{appName}] Genehmigung erforderlich: {displayName}',
    'mail.approval_intro'    => 'Neue Registrierungsanfrage für {appName}:',
    'mail.approval_email'    => 'E-Mail: {email}',
    'mail.approval_name'     => 'Name: {name}',
    'mail.approval_approve'  => 'Genehmigen:',
    'mail.approval_reject'   => 'Ablehnen:',

    // --- E-Mail: Bestätigung an Empfänger ---
    'mail.confirm_subject'   => '[{appName}] Registrierung genehmigt',
    'mail.confirm_greeting'  => 'Hallo {name}',
    'mail.confirm_greeting_anon' => 'Hallo',
    'mail.confirm_body'      => 'Deine Registrierung für {appName} wurde genehmigt.',
    'mail.confirm_instructions' => 'Du kannst ab sofort die folgende Seite besuchen und jederzeit die Empfängerliste anfordern:',
    'mail.confirm_unsub'     => 'Zum Abmelden:',
];
