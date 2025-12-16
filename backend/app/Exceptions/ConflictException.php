<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ConflictException extends Exception
{
    protected $code = 409;

    public function __construct(
        string $message = 'Resource conflict detected',
        protected ?array $conflictDetails = null
    ) {
        parent::__construct($message, $this->code);
    }

    public function getConflictDetails(): ?array
    {
        return $this->conflictDetails;
    }

    public function getDetails(): ?array
    {
        return $this->conflictDetails;
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => [
                'conflict' => $this->conflictDetails ?? []
            ],
        ], 409);
    }
}
