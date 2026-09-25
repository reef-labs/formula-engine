<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

/**
 * A literal string, number, boolean, or null value.
 */
final class LiteralNode implements Node
{
    public function __construct(public readonly bool|int|float|string|null $value)
    {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitLiteral($this);
    }
}
