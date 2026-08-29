<?php

declare(strict_types=1);

namespace App;

/**
 * Thrown when an operation would create or update a recipient with an email
 * address that already exists (the recipients.email UNIQUE constraint). Lets
 * callers show a friendly message instead of surfacing a raw PDOException.
 */
final class DuplicateEmailException extends \RuntimeException
{
}
