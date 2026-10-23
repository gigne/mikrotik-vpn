<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A user-actionable provisioning error (duplicate IP, no free port, etc.).
 *
 * Rendered as a 422 JSON response for API requests and a back-redirect with
 * an error flash message for web requests (see bootstrap/app.php).
 */
class ProvisioningException extends RuntimeException
{
}