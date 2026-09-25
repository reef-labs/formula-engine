<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Ast;

interface NodeVisitor
{
    public function visitVariable(VariableNode $node): mixed;

    public function visitLiteral(LiteralNode $node): mixed;

    public function visitBinaryExpression(BinaryExpressionNode $node): mixed;

    public function visitUnaryExpression(UnaryExpressionNode $node): mixed;

    public function visitFunctionCall(FunctionCallNode $node): mixed;

    public function visitIf(IfNode $node): mixed;

    public function visitCase(CaseNode $node): mixed;
}
