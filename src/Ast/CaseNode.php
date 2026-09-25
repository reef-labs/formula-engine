<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

/**
 * Case (subject)|value1,result1|value2,result2|...|default
 */
final class CaseNode implements Node
{
    /**
     * @param CaseWhenClause[] $whenClauses
     */
    public function __construct(
        public readonly Node $subject,
        public readonly array $whenClauses,
        public readonly ?Node $default,
    ) {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitCase($this);
    }
}
