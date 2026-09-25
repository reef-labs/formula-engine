<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

/**
 * If (condition)|then|else
 */
final class IfNode implements Node
{
    public function __construct(
        public readonly Node $condition,
        public readonly Node $then,
        public readonly Node $else,
    ) {
    }

    public function accept(NodeVisitor $visitor): mixed
    {
        return $visitor->visitIf($this);
    }
}
