<?php

namespace App\Services\Whatsapp\Waha;

use RuntimeException;

/** Something WAHA refused or could not be reached for, in words a merchant can read. */
class WahaException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
