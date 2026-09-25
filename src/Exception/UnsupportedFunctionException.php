<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Exception;

/**
 * Raised when compiling a formula that calls a function into a target that
 * cannot represent function calls (currently, SQL — a function call may
 * rely on a caller-supplied PHP closure, which has no SQL equivalent).
 */
class UnsupportedFunctionException extends FormulaEngineException
{
    public function __construct(public readonly string $functionName, string $target)
    {
        parent::__construct(sprintf('Function "%s" cannot be compiled to %s', $functionName, $target));
    }
}
