<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

/**
 * A comparison (<, <=, >, >=, ==, !=), logical (AND, OR), or arithmetic
 * (+, -, *, /) expression.
 */
final class BinaryExpressionNode implements Node
{
    public function __construct(
        public readonly Node $left,
        public readonly string $operator,
        public readonly Node $right,
    ) {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitBinaryExpression($this);
    }
}
