<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Tests\Lexer;

use ReefLabs\FormulaEngine\Exception\SyntaxException;
use ReefLabs\FormulaEngine\Lexer\Lexer;
use ReefLabs\FormulaEngine\Lexer\TokenType;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    public function testTokenizesIfFormula(): void
    {
        $tokens = (new Lexer())->tokenize('If ([[Income]] < 1000)|"poor"|"rich"');

        $types = array_map(static fn ($token) => $token->type, $tokens);

        self::assertSame([
            TokenType::Identifier,
            TokenType::LParen,
            TokenType::Variable,
            TokenType::Operator,
            TokenType::Number,
            TokenType::RParen,
            TokenType::Pipe,
            TokenType::String,
            TokenType::Pipe,
            TokenType::String,
            TokenType::Eof,
        ], $types);

        self::assertSame('Income', $tokens[2]->value);
        self::assertSame('<', $tokens[3]->value);
        self::assertSame('1000', $tokens[4]->value);
        self::assertSame('poor', $tokens[7]->value);
        self::assertSame('rich', $tokens[9]->value);
    }

    public function testTokenizesCaseFormula(): void
    {
        $tokens = (new Lexer())->tokenize('Case ([[Status]])|"Approved","green"|"Denied","red"');

        $types = array_map(static fn ($token) => $token->type, $tokens);

        self::assertSame([
            TokenType::Identifier,
            TokenType::LParen,
            TokenType::Variable,
            TokenType::RParen,
            TokenType::Pipe,
            TokenType::String,
            TokenType::Comma,
            TokenType::String,
            TokenType::Pipe,
            TokenType::String,
            TokenType::Comma,
            TokenType::String,
            TokenType::Eof,
        ], $types);
    }

    public function testTrimsWhitespaceInsideVariableBrackets(): void
    {
        $tokens = (new Lexer())->tokenize('[[  Income  ]]');

        self::assertSame('Income', $tokens[0]->value);
    }

    public function testTokenizesNegativeAndDecimalNumbers(): void
    {
        $tokens = (new Lexer())->tokenize('-12.5');

        self::assertSame(TokenType::Number, $tokens[0]->type);
        self::assertSame('-12.5', $tokens[0]->value);
    }

    public function testTokenizesEscapedStringLiteral(): void
    {
        $tokens = (new Lexer())->tokenize('"say \\"hi\\" \\\\ ok"');

        self::assertSame(TokenType::String, $tokens[0]->type);
        self::assertSame('say "hi" \\ ok', $tokens[0]->value);
    }

    public function testTokenizesTwoCharacterOperators(): void
    {
        foreach (['<=', '>=', '==', '!=', '<>'] as $operator) {
            $tokens = (new Lexer())->tokenize("1 {$operator} 2");
            self::assertSame($operator, $tokens[1]->value, "Failed for operator {$operator}");
        }
    }

    public function testTokenizesArithmeticOperators(): void
    {
        foreach (['+', '-', '*', '/'] as $operator) {
            $tokens = (new Lexer())->tokenize("1 {$operator} 2");
            self::assertSame(TokenType::ArithmeticOperator, $tokens[1]->type, "Failed for operator {$operator}");
            self::assertSame($operator, $tokens[1]->value, "Failed for operator {$operator}");
        }
    }

    public function testMinusAfterAValueIsSubtractionNotANegativeNumber(): void
    {
        $tokens = (new Lexer())->tokenize('[[X]]-5');

        $types = array_map(static fn ($token) => $token->type, $tokens);

        self::assertSame([
            TokenType::Variable,
            TokenType::ArithmeticOperator,
            TokenType::Number,
            TokenType::Eof,
        ], $types);

        self::assertSame('-', $tokens[1]->value);
        self::assertSame('5', $tokens[2]->value);
    }

    public function testMinusAfterAnOperatorStartsANegativeNumber(): void
    {
        $tokens = (new Lexer())->tokenize('5- -5');

        $types = array_map(static fn ($token) => $token->type, $tokens);

        self::assertSame([
            TokenType::Number,
            TokenType::ArithmeticOperator,
            TokenType::Number,
            TokenType::Eof,
        ], $types);

        self::assertSame('5', $tokens[0]->value);
        self::assertSame('-', $tokens[1]->value);
        self::assertSame('-5', $tokens[2]->value);
    }

    public function testThrowsOnUnterminatedVariable(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated variable');

        (new Lexer())->tokenize('[[Income');
    }

    public function testThrowsOnUnterminatedString(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unterminated string literal');

        (new Lexer())->tokenize('"poor');
    }

    public function testThrowsOnEmptyVariableName(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Variable name cannot be empty');

        (new Lexer())->tokenize('[[ ]]');
    }

    public function testThrowsOnUnexpectedCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unexpected character "$"');

        (new Lexer())->tokenize('$foo');
    }
}
