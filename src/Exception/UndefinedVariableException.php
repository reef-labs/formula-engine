<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Exception;

/**
 * Raised at evaluation time when a formula references a variable that was
 * not supplied in the variables array.
 */
class UndefinedVariableException extends FormulaEngineException
{
    public function __construct(public readonly string $variableName)
    {
        parent::__construct(sprintf('Undefined variable "%s"', $variableName));
    }
}
