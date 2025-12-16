<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class MissingPricingException extends Exception
{
    protected $code = 422;

    public function __construct(
        string $message = 'No pricing configuration found for this class',
        protected ?array $details = null
    ) {
        parent::__construct($message, $this->code);
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => [
                'pricing' => $this->details ?? []
            ],
        ], 422);
    }
}
