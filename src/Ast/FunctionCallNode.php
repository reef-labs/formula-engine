<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

/**
 * A call to a named function, e.g. Today() or GetNameFromID([[ID]]).
 */
final class FunctionCallNode implements Node
{
    /**
     * @param Node[] $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitFunctionCall($this);
    }
}
