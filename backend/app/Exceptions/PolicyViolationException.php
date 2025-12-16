<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class PolicyViolationException extends Exception
{
    protected $code = 451;

    public function __construct(
        string $message = 'Business policy violation',
        protected ?array $details = null
    ) {
        parent::__construct($message, $this->code);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->details ?? [],
        ], 451);
    }
}
