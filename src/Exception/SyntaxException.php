<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Exception;

/**
 * Raised when a formula cannot be tokenized or parsed.
 */
class SyntaxException extends FormulaEngineException
{
    public function __construct(string $message, public readonly int $position)
    {
        parent::__construct(sprintf('%s at position %d', $message, $position));
    }
}
