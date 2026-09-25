<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Compiler;

use ReefLabs\FormulaEngine\Ast\BinaryExpressionNode;
use ReefLabs\FormulaEngine\Ast\CaseNode;
use ReefLabs\FormulaEngine\Ast\FunctionCallNode;
use ReefLabs\FormulaEngine\Ast\IfNode;
use ReefLabs\FormulaEngine\Ast\LiteralNode;
use ReefLabs\FormulaEngine\Ast\Node;
use ReefLabs\FormulaEngine\Ast\NodeVisitor;
use ReefLabs\FormulaEngine\Ast\UnaryExpressionNode;
use ReefLabs\FormulaEngine\Ast\VariableNode;

/**
 * Walks a formula AST and collects the distinct names of every function it
 * calls, in order of first appearance.
 */
final class FunctionCollector implements NodeVisitor
{
    /**
     * @var array<string, true>
     */
    private array $names = [];

    /**
     * @return string[]
     */
    public function collect(Node $node): array
    {
        $node->accept($this);

        return array_keys($this->names);
    }

    public function visitVariable(VariableNode $node): mixed
    {
        return null;
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        return null;
    }

    public function visitBinaryExpression(BinaryExpressionNode $node): mixed
    {
        $node->left->accept($this);
        $node->right->accept($this);

        return null;
    }

    public function visitUnaryExpression(UnaryExpressionNode $node): mixed
    {
        $node->operand->accept($this);

        return null;
    }

    public function visitFunctionCall(FunctionCallNode $node): mixed
    {
        $this->names[$node->name] = true;

        foreach ($node->arguments as $argument) {
            $argument->accept($this);
        }

        return null;
    }

    public function visitIf(IfNode $node): mixed
    {
        $node->condition->accept($this);
        $node->then->accept($this);
        $node->else->accept($this);

        return null;
    }

    public function visitCase(CaseNode $node): mixed
    {
        $node->subject->accept($this);

        foreach ($node->whenClauses as $clause) {
            $clause->when->accept($this);
            $clause->then->accept($this);
        }

        $node->default?->accept($this);

        return null;
    }
}
