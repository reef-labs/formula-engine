<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

interface Node
{
    public function accept(NodeVisitor $visitor): mixed;
}
