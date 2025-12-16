<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class LockedResourceException extends Exception
{
    protected $code = 423;

    public function __construct(
        string $message = 'Resource is locked and cannot be modified',
        protected ?string $reason = null
    ) {
        parent::__construct($message, $this->code);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => [
                'locked' => true,
                'reason' => $this->reason ?? 'Time window has passed or resource is locked'
            ],
        ], 423);
    }
}
