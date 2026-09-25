<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Tests\Compiler;

use ReefLabs\FormulaEngine\Exception\UndefinedVariableException;
use ReefLabs\FormulaEngine\FormulaEngine;
use PHPUnit\Framework\TestCase;

final class PhpCodeCompilerTest extends TestCase
{
    public function testGeneratesFunctionSourceContainingReturnStatement(): void
    {
        $code = (new FormulaEngine())->toPhpCode('If ([[Income]] < 1000)|"poor"|"rich"');

        self::assertStringContainsString('static function (array $variables, array $functions = []): mixed', $code);
        self::assertStringContainsString('return', $code);
        self::assertStringContainsString("'poor'", $code);
        self::assertStringContainsString("'rich'", $code);
    }

    public function testGeneratedClosureEvaluatesIfCorrectly(): void
    {
        $closure = (new FormulaEngine())->toClosure('If ([[Income]] < 1000)|"poor"|"rich"');

        self::assertSame('poor', $closure(['Income' => 500]));
        self::assertSame('rich', $closure(['Income' => 5000]));
    }

    public function testGeneratedClosureEvaluatesCaseCorrectly(): void
    {
        $closure = (new FormulaEngine())->toClosure(
            'Case ([[Status]])|"Approved","green"|"Denied","red"|"gray"'
        );

        self::assertSame('green', $closure(['Status' => 'Approved']));
        self::assertSame('red', $closure(['Status' => 'Denied']));
        self::assertSame('gray', $closure(['Status' => 'Pending']));
    }

    public function testGeneratedClosureThrowsOnUndefinedVariable(): void
    {
        $closure = (new FormulaEngine())->toClosure('If ([[Income]] < 1000)|"poor"|"rich"');

        $this->expectException(UndefinedVariableException::class);

        $closure([]);
    }

    public function testGeneratedClosureHandlesLogicalOperators(): void
    {
        $closure = (new FormulaEngine())->toClosure('If ([[A]] > 0 AND [[B]] > 0)|"both"|"not both"');

        self::assertSame('both', $closure(['A' => 1, 'B' => 1]));
        self::assertSame('not both', $closure(['A' => 1, 'B' => -1]));
    }

    public function testGeneratedClosureHandlesNestedFormulas(): void
    {
        $closure = (new FormulaEngine())->toClosure(
            'If ([[A]] < 1)|If ([[B]] < 1)|"both small"|"a small"|"neither"'
        );

        self::assertSame('both small', $closure(['A' => 0, 'B' => 0]));
        self::assertSame('a small', $closure(['A' => 0, 'B' => 5]));
        self::assertSame('neither', $closure(['A' => 5, 'B' => 5]));
    }

    public function testGeneratedClosureHandlesArithmeticOperators(): void
    {
        $closure = (new FormulaEngine())->toClosure(
            'If (([[Income]] + [[Raise]]) <= 1000)|"poor"|"rich"'
        );

        self::assertSame('poor', $closure(['Income' => 500, 'Raise' => 400]));
        self::assertSame('rich', $closure(['Income' => 900, 'Raise' => 400]));
    }

    public function testGeneratedClosureHandlesUnaryMinus(): void
    {
        $closure = (new FormulaEngine())->toClosure('If (TRUE)|-[[X]]|0');

        self::assertSame(-5, $closure(['X' => 5]));
    }

    public function testGeneratedClosureCallsBuiltInFunctionWithoutCallerSupport(): void
    {
        $closure = (new FormulaEngine())->toClosure('If ([[Today]] == Today())|"today"|"not today"');

        self::assertSame('today', $closure(['Today' => (new \DateTimeImmutable())->format('Y-m-d')]));
    }

    public function testGeneratedClosureCallsCallerSuppliedFunction(): void
    {
        $closure = (new FormulaEngine())->toClosure('If ([[ID]] > 0)|GetNameFromID([[ID]])|"none"');

        $result = $closure(['ID' => 42], ['GetNameFromID' => fn (int $id): string => "Name-{$id}"]);

        self::assertSame('Name-42', $result);
    }
}
