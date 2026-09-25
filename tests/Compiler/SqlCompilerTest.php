<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Tests\Compiler;

use ReefLabs\FormulaEngine\Compiler\SqlCompiler;
use ReefLabs\FormulaEngine\Exception\UnsupportedFunctionException;
use ReefLabs\FormulaEngine\Lexer\Lexer;
use ReefLabs\FormulaEngine\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class SqlCompilerTest extends TestCase
{
    private function compile(string $formula, string $quoteChar = ''): string
    {
        $tokens = (new Lexer())->tokenize($formula);
        $ast = (new Parser($tokens))->parse();

        return (new SqlCompiler($quoteChar))->compile($ast);
    }

    public function testCompilesIfToCaseWhen(): void
    {
        $sql = $this->compile('If ([[Income]] < 1000)|"poor"|"rich"');

        self::assertSame("CASE WHEN (Income < 1000) THEN 'poor' ELSE 'rich' END", $sql);
    }

    public function testCompilesCaseToCaseWhen(): void
    {
        $sql = $this->compile('Case ([[Status]])|"Approved","green"|"Denied","red"');

        self::assertSame(
            "CASE WHEN (Status) = ('Approved') THEN 'green' WHEN (Status) = ('Denied') THEN 'red' END",
            $sql
        );
    }

    public function testCompilesCaseWithDefaultToElseClause(): void
    {
        $sql = $this->compile('Case ([[Status]])|"Approved","green"|"Denied","red"|"gray"');

        self::assertSame(
            "CASE WHEN (Status) = ('Approved') THEN 'green' WHEN (Status) = ('Denied') THEN 'red' ELSE 'gray' END",
            $sql
        );
    }

    public function testQuotesIdentifiersWhenRequested(): void
    {
        $sql = $this->compile('If ([[Income]] < 1000)|"poor"|"rich"', '`');

        self::assertSame("CASE WHEN (`Income` < 1000) THEN 'poor' ELSE 'rich' END", $sql);
    }

    public function testEscapesSingleQuotesInStringLiterals(): void
    {
        $sql = $this->compile('If ([[Name]] == "O\'Brien")|"match"|"no match"');

        self::assertStringContainsString("'O''Brien'", $sql);
    }

    public function testCompilesLogicalAndOrAndNot(): void
    {
        $sql = $this->compile('If (NOT [[A]] AND ([[B]] > 1 OR [[C]] > 1))|1|0');

        self::assertSame(
            'CASE WHEN (NOT (A) AND ((B > 1) OR (C > 1))) THEN 1 ELSE 0 END',
            $sql
        );
    }

    public function testCompilesBooleanAndNullLiterals(): void
    {
        $sql = $this->compile('If ([[Active]] == TRUE)|NULL|FALSE');

        self::assertSame('CASE WHEN (Active = TRUE) THEN NULL ELSE FALSE END', $sql);
    }

    public function testCompilesEqualsNullComparisonToIsNull(): void
    {
        self::assertSame(
            'CASE WHEN (Income IS NULL) THEN 1 ELSE 0 END',
            $this->compile('If ([[Income]] == NULL)|1|0')
        );
        self::assertSame(
            'CASE WHEN (Income IS NULL) THEN 1 ELSE 0 END',
            $this->compile('If (NULL == [[Income]])|1|0')
        );
    }

    public function testCompilesNotEqualsNullComparisonToIsNotNull(): void
    {
        self::assertSame(
            'CASE WHEN (Income IS NOT NULL) THEN 1 ELSE 0 END',
            $this->compile('If ([[Income]] != NULL)|1|0')
        );
        self::assertSame(
            'CASE WHEN (Income IS NOT NULL) THEN 1 ELSE 0 END',
            $this->compile('If ([[Income]] <> NULL)|1|0')
        );
    }

    public function testCompilesCaseWithNullBranchToIsNull(): void
    {
        $sql = $this->compile('Case ([[Status]])|NULL,"unknown"|"Approved","green"');

        self::assertSame(
            "CASE WHEN (Status) IS NULL THEN 'unknown' WHEN (Status) = ('Approved') THEN 'green' END",
            $sql
        );
    }

    public function testCompilesArithmeticOperatorsWithPrecedence(): void
    {
        $sql = $this->compile('If (([[Income]] + [[Raise]]) <= 1000)|"poor"|"rich"');

        self::assertSame("CASE WHEN ((Income + Raise) <= 1000) THEN 'poor' ELSE 'rich' END", $sql);
    }

    public function testCompilesUnaryMinus(): void
    {
        $sql = $this->compile('If (TRUE)|-[[Income]]|0');

        self::assertSame('CASE WHEN TRUE THEN -(Income) ELSE 0 END', $sql);
    }

    public function testThrowsWhenFormulaContainsFunctionCall(): void
    {
        $this->expectException(UnsupportedFunctionException::class);
        $this->expectExceptionMessage('Function "Today" cannot be compiled to SQL');

        $this->compile('If ([[Date]] == Today())|"today"|"not today"');
    }
}
