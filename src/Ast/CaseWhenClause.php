<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

/**
 * One `value,result` branch of a Case expression.
 */
final class CaseWhenClause
{
    public function __construct(
        public readonly Node $when,
        public readonly Node $then,
    ) {
    }
}
