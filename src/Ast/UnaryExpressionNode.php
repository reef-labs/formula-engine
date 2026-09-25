<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

/**
 * A unary expression: logical negation (NOT) or arithmetic negation (-).
 */
final class UnaryExpressionNode implements Node
{
    public function __construct(
        public readonly string $operator,
        public readonly Node $operand,
    ) {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitUnaryExpression($this);
    }
}
