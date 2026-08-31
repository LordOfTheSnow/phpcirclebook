<?php

declare(strict_types=1);

return [
    // --- Info card (templates/form.php) ---
    'info.title'             => 'What is this?',
    'info.intro'             => 'This app manages a shared mailing list.',
    'info.how_it_works'      => 'Enter your email address to request the current list of recipients. If you are not yet a member, an administrator will review your request. Once approved, you can request the list at any time or unsubscribe whenever you like.',
    'info.stats'             => 'No. of entries: {count}. Last update: {date}.',
    'info.contact'           => 'Something not working? <a href="mailto:{email}">Contact the administrator</a>.',

    // --- Form (templates/form.php) ---
    'form.email_label'       => 'Email address',
    'form.email_placeholder' => 'you@example.com',
    'form.name_label'        => 'Name (optional)',
    'form.name_placeholder'  => 'Your Name',
    'form.comment_label'     => 'Comment (optional, max {max} characters)',
    'form.comment_placeholder' => 'Anything you\'d like the administrator to know',
    'form.honeypot_label'    => 'Leave this empty',
    'form.submit'            => 'Send me the list',
    'form.explanation'       => 'Enter your email to receive the recipient list. If you\'re not yet registered, your request will be forwarded to the administrator for approval.',
    'form.disclaimer'        => 'By subscribing to the list, you allow the other members to see your email address. If you don\'t want that, please don\'t subscribe.',

    // --- Sidebar cards (templates/form.php) ---
    'sidebar.events_title'   => 'Upcoming events',
    'sidebar.links_title'    => 'Links',

    // --- Unsubscribe page (templates/unsubscribe.php) ---
    'unsubscribe.heading'    => 'Unsubscribe',
    'unsubscribe.confirm'    => 'Are you sure you want to unsubscribe {email} from {appName}?',
    'unsubscribe.warning'    => 'You will no longer appear in the recipient list and will not be able to request it.',
    'unsubscribe.submit'     => 'Yes, unsubscribe me',
    'unsubscribe.cancel'     => 'No, take me back',

    // --- Message template (templates/message.php) ---
    'message.back'           => 'Back to the form',

    // --- Controller messages (public/index.php) ---
    'message.generic_thanks'    => 'Thank you. If your address is eligible you\'ll receive an email shortly.',
    'message.invalid_email'     => 'Please enter a valid email address.',
    'message.rate_limited'      => 'Too many requests. Please try again later.',
    'message.list_sent'         => 'The list has been sent to your email address.',
    'message.invalid_link'      => 'Invalid or expired link.',
    'message.approved'          => 'Approved! {email} has been added to the list and notified.',
    'message.rejected'          => 'Rejected. {email} has been silently denied.',
    'message.invalid_unsub'     => 'Invalid unsubscribe link.',
    'message.unsubscribed'      => 'You have been unsubscribed. You can re-register at any time.',

    // --- Email: Recipient list (src/Mailer.php) ---
    'mail.list_subject'      => '[{appName}] Recipient List',
    'mail.list_intro'        => 'Here is the current recipient list for {appName}:',
    'mail.list_total'        => 'Total: {count} recipient(s)',
    'mail.list_notes_heading' => 'Notes:',
    'mail.list_unsubscribe'  => 'To unsubscribe from this list:',

    'mail.csv_header_public_note' => 'Public note',
    'mail.csv_header_tags'       => 'Tags',
    'mail.csv_header_registered' => 'Registered',

    // --- Email: Approval request to admin ---
    'mail.approval_subject'  => '[{appName}] Approval needed: {displayName}',
    'mail.approval_intro'    => 'A new registration request for {appName}:',
    'mail.approval_email'    => 'Email: {email}',
    'mail.approval_name'     => 'Name: {name}',
    'mail.approval_comment'  => 'Comment: {comment}',
    'mail.approval_approve'  => 'Approve:',
    'mail.approval_reject'   => 'Reject:',

    // --- Email: Approval confirmation to recipient ---
    'mail.confirm_subject'   => '[{appName}] Registration approved',
    'mail.confirm_greeting'  => 'Hi {name}',
    'mail.confirm_greeting_anon' => 'Hi',
    'mail.confirm_body'      => 'Your registration for {appName} has been approved.',
    'mail.confirm_instructions' => 'You can now visit the following page and request the recipient list at any time:',
    'mail.confirm_unsub'     => 'To unsubscribe:',
];
