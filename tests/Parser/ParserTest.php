<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Tests\Parser;

use ReefLabs\FormulaEngine\Ast\BinaryExpressionNode;
use ReefLabs\FormulaEngine\Ast\CaseNode;
use ReefLabs\FormulaEngine\Ast\FunctionCallNode;
use ReefLabs\FormulaEngine\Ast\IfNode;
use ReefLabs\FormulaEngine\Ast\LiteralNode;
use ReefLabs\FormulaEngine\Ast\Node;
use ReefLabs\FormulaEngine\Ast\UnaryExpressionNode;
use ReefLabs\FormulaEngine\Ast\VariableNode;
use ReefLabs\FormulaEngine\Exception\SyntaxException;
use ReefLabs\FormulaEngine\Lexer\Lexer;
use ReefLabs\FormulaEngine\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private function parse(string $formula): Node
    {
        $tokens = (new Lexer())->tokenize($formula);

        return (new Parser($tokens))->parse();
    }

    public function testParsesIfIntoIfNode(): void
    {
        $node = $this->parse('If ([[Income]] < 1000)|"poor"|"rich"');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertInstanceOf(BinaryExpressionNode::class, $node->condition);
        self::assertInstanceOf(VariableNode::class, $node->condition->left);
        self::assertSame('Income', $node->condition->left->name);
        self::assertSame('<', $node->condition->operator);
        self::assertInstanceOf(LiteralNode::class, $node->condition->right);
        self::assertSame(1000, $node->condition->right->value);
        self::assertSame('poor', $node->then->value);
        self::assertSame('rich', $node->else->value);
    }

    public function testParsesCaseIntoCaseNodeWithoutDefault(): void
    {
        $node = $this->parse('Case ([[Status]])|"Approved","green"|"Denied","red"');

        self::assertInstanceOf(CaseNode::class, $node);
        self::assertInstanceOf(VariableNode::class, $node->subject);
        self::assertSame('Status', $node->subject->name);
        self::assertCount(2, $node->whenClauses);
        self::assertSame('Approved', $node->whenClauses[0]->when->value);
        self::assertSame('green', $node->whenClauses[0]->then->value);
        self::assertSame('Denied', $node->whenClauses[1]->when->value);
        self::assertSame('red', $node->whenClauses[1]->then->value);
        self::assertNull($node->default);
    }

    public function testParsesCaseWithTrailingDefaultBranch(): void
    {
        $node = $this->parse('Case ([[Status]])|"Approved","green"|"Denied","red"|"gray"');

        self::assertInstanceOf(CaseNode::class, $node);
        self::assertCount(2, $node->whenClauses);
        self::assertInstanceOf(LiteralNode::class, $node->default);
        self::assertSame('gray', $node->default->value);
    }

    public function testParsesLogicalAndOrAndNot(): void
    {
        $node = $this->parse('If (NOT [[A]] AND [[B]] OR [[C]])|"yes"|"no"');

        self::assertInstanceOf(IfNode::class, $node);
        /** @var BinaryExpressionNode $or */
        $or = $node->condition;
        self::assertInstanceOf(BinaryExpressionNode::class, $or);
        self::assertSame('OR', $or->operator);

        /** @var BinaryExpressionNode $and */
        $and = $or->left;
        self::assertInstanceOf(BinaryExpressionNode::class, $and);
        self::assertSame('AND', $and->operator);
        self::assertInstanceOf(UnaryExpressionNode::class, $and->left);
        self::assertSame('NOT', $and->left->operator);
    }

    public function testParsesParenthesizedExpression(): void
    {
        $node = $this->parse('If (([[A]] AND [[B]]) OR [[C]])|1|2');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertInstanceOf(BinaryExpressionNode::class, $node->condition);
        self::assertSame('OR', $node->condition->operator);
        self::assertInstanceOf(BinaryExpressionNode::class, $node->condition->left);
        self::assertSame('AND', $node->condition->left->operator);
    }

    public function testParsesArithmeticWithMultiplicationBindingTighterThanAddition(): void
    {
        $node = $this->parse('If (2 + 3 * 4 == 14)|"yes"|"no"');

        /** @var BinaryExpressionNode $comparison */
        $comparison = $node->condition;
        self::assertSame('==', $comparison->operator);

        /** @var BinaryExpressionNode $addition */
        $addition = $comparison->left;
        self::assertInstanceOf(BinaryExpressionNode::class, $addition);
        self::assertSame('+', $addition->operator);
        self::assertSame(2, $addition->left->value);

        /** @var BinaryExpressionNode $multiplication */
        $multiplication = $addition->right;
        self::assertInstanceOf(BinaryExpressionNode::class, $multiplication);
        self::assertSame('*', $multiplication->operator);
        self::assertSame(3, $multiplication->left->value);
        self::assertSame(4, $multiplication->right->value);
    }

    public function testArithmeticBindsTighterThanComparison(): void
    {
        $node = $this->parse('If ([[Income]] + [[Raise]] <= 1000)|"poor"|"rich"');

        self::assertInstanceOf(BinaryExpressionNode::class, $node->condition);
        self::assertSame('<=', $node->condition->operator);
        self::assertInstanceOf(BinaryExpressionNode::class, $node->condition->left);
        self::assertSame('+', $node->condition->left->operator);
        self::assertSame(1000, $node->condition->right->value);
    }

    public function testParsesUnaryMinusOnExpression(): void
    {
        $node = $this->parse('If (-[[A]] == -5)|"yes"|"no"');

        self::assertInstanceOf(UnaryExpressionNode::class, $node->condition->left);
        self::assertSame('-', $node->condition->left->operator);
        self::assertInstanceOf(VariableNode::class, $node->condition->left->operand);

        self::assertInstanceOf(LiteralNode::class, $node->condition->right);
        self::assertSame(-5, $node->condition->right->value);
    }

    public function testParsesBooleanAndNullLiterals(): void
    {
        $node = $this->parse('If ([[Active]] == TRUE)|NULL|FALSE');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertTrue($node->condition->right->value);
        self::assertNull($node->then->value);
        self::assertFalse($node->else->value);
    }

    public function testParsesNestedFormulaAsResultValue(): void
    {
        $node = $this->parse('If ([[A]] < 1)|If ([[B]] < 1)|"both small"|"a small"|"neither"');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertInstanceOf(IfNode::class, $node->then);
        self::assertSame('both small', $node->then->then->value);
        self::assertSame('a small', $node->then->else->value);
        self::assertSame('neither', $node->else->value);
    }

    public function testParsesFunctionCallWithNoArguments(): void
    {
        $node = $this->parse('If (TRUE)|Today()|"no"');

        self::assertInstanceOf(IfNode::class, $node);
        self::assertInstanceOf(FunctionCallNode::class, $node->then);
        self::assertSame('Today', $node->then->name);
        self::assertSame([], $node->then->arguments);
    }

    public function testParsesFunctionCallWithVariableAndMultipleArguments(): void
    {
        $node = $this->parse('If (TRUE)|Lookup([[ID]], "type")|"no"');

        self::assertInstanceOf(FunctionCallNode::class, $node->then);
        self::assertSame('Lookup', $node->then->name);
        self::assertCount(2, $node->then->arguments);
        self::assertInstanceOf(VariableNode::class, $node->then->arguments[0]);
        self::assertSame('ID', $node->then->arguments[0]->name);
        self::assertSame('type', $node->then->arguments[1]->value);
    }

    public function testThrowsWhenFunctionCallMissingClosingParen(): void
    {
        $this->expectException(SyntaxException::class);

        $this->parse('If (TRUE)|Today(|"no"');
    }

    public function testParsesBareExpressionAsAWholeFormula(): void
    {
        self::assertInstanceOf(FunctionCallNode::class, $this->parse('Join([[A]], [[B]])'));
        self::assertInstanceOf(VariableNode::class, $this->parse('[[A]]'));
        self::assertInstanceOf(BinaryExpressionNode::class, $this->parse('[[A]] + [[B]]'));
    }

    public function testThrowsWhenBareFunctionCallFormulaHasTrailingPipes(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unexpected trailing input');

        $this->parse('Wat([[A]])|1|2');
    }

    public function testThrowsWhenIfMissingSecondPipe(): void
    {
        $this->expectException(SyntaxException::class);

        $this->parse('If ([[A]] < 1)|"yes"');
    }

    public function testThrowsWhenCaseHasNoBranches(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('requires at least one');

        $this->parse('Case ([[Status]])');
    }

    public function testThrowsWhenDefaultBranchIsNotLast(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('must be the last branch');

        $this->parse('Case ([[Status]])|"gray"|"Approved","green"');
    }

    public function testThrowsOnTrailingInput(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unexpected trailing input');

        $this->parse('If ([[A]] < 1)|1|2 extra');
    }

    public function testThrowsOnUnexpectedToken(): void
    {
        $this->expectException(SyntaxException::class);

        $this->parse('If (|)|1|2');
    }
}
