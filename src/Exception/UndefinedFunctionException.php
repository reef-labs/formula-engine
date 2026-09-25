<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Exception;

/**
 * Raised at evaluation time when a formula calls a function that is neither
 * a built-in nor supplied by the caller.
 */
class UndefinedFunctionException extends FormulaEngineException
{
    public function __construct(public readonly string $functionName)
    {
        parent::__construct(sprintf('Undefined function "%s"', $functionName));
    }
}
