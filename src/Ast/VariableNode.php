<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

/**
 * A reference to a named variable, e.g. [[Income]].
 */
final class VariableNode implements Node
{
    public function __construct(public readonly string $name)
    {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitVariable($this);
    }
}
