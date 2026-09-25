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
use ReefLabs\FormulaEngine\Runtime\FormulaRuntime;

/**
 * Compiles a formula AST into a PHP expression string, suitable for
 * embedding in a generated anonymous function.
 */
final class PhpCodeCompiler implements NodeVisitor
{
    public function compile(Node $node): string
    {
        return $node->accept($this);
    }

    public function visitVariable(VariableNode $node): mixed
    {
        return sprintf(
            '\%s::getVariable($variables, %s)',
            FormulaRuntime::class,
            var_export($node->name, true)
        );
    }

    public function visitLiteral(LiteralNode $node): mixed
    {
        return var_export($node->value, true);
    }

    public function visitBinaryExpression(BinaryExpressionNode $node): mixed
    {
        $operator = strtoupper($node->operator);

        $phpOperator = match ($operator) {
            'AND' => '&&',
            'OR' => '||',
            '=' => '==',
            '<>' => '!=',
            '==', '!=', '<', '<=', '>', '>=', '+', '-', '*', '/' => $node->operator,
            default => throw new \LogicException(sprintf('Unsupported operator "%s"', $node->operator)),
        };

        return sprintf('(%s %s %s)', $node->left->accept($this), $phpOperator, $node->right->accept($this));
    }

    public function visitUnaryExpression(UnaryExpressionNode $node): mixed
    {
        return match ($node->operator) {
            'NOT' => sprintf('(!(%s))', $node->operand->accept($this)),
            '-' => sprintf('(-(%s))', $node->operand->accept($this)),
            default => throw new \LogicException(sprintf('Unsupported unary operator "%s"', $node->operator)),
        };
    }

    public function visitFunctionCall(FunctionCallNode $node): mixed
    {
        $argumentsCode = array_map(
            fn (Node $argument): string => $argument->accept($this),
            $node->arguments
        );

        return sprintf(
            '\%s::callFunction($functions, %s, [%s])',
            FormulaRuntime::class,
            var_export($node->name, true),
            implode(', ', $argumentsCode)
        );
    }

    public function visitIf(IfNode $node): mixed
    {
        return sprintf(
            '(%s ? %s : %s)',
            $node->condition->accept($this),
            $node->then->accept($this),
            $node->else->accept($this)
        );
    }

    public function visitCase(CaseNode $node): mixed
    {
        $subjectCode = $node->subject->accept($this);
        $result = $node->default !== null ? $node->default->accept($this) : 'null';

        foreach (array_reverse($node->whenClauses) as $clause) {
            $whenCode = $clause->when->accept($this);
            $thenCode = $clause->then->accept($this);
            $result = sprintf('(%s == %s) ? %s : (%s)', $subjectCode, $whenCode, $thenCode, $result);
        }

        return sprintf('(%s)', $result);
    }
}
