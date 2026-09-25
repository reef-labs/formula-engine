<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Tests\Compiler;

use ReefLabs\FormulaEngine\Compiler\FunctionCollector;
use ReefLabs\FormulaEngine\Lexer\Lexer;
use ReefLabs\FormulaEngine\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class FunctionCollectorTest extends TestCase
{
    private function collect(string $formula): array
    {
        $tokens = (new Lexer())->tokenize($formula);
        $ast = (new Parser($tokens))->parse();

        return (new FunctionCollector())->collect($ast);
    }

    public function testCollectsSingleFunctionCall(): void
    {
        self::assertSame(['Today'], $this->collect('If ([[SignupDate]] == Today())|"new"|"old"'));
    }

    public function testCollectsMultipleFunctionsInOrderOfFirstAppearance(): void
    {
        $formula = 'If (TRUE)|GetNameFromID([[ID]])|GetOtherName([[ID]])';

        self::assertSame(['GetNameFromID', 'GetOtherName'], $this->collect($formula));
    }

    public function testDeduplicatesRepeatedFunctionCalls(): void
    {
        $formula = 'If (Today() == Today())|"same"|"different"';

        self::assertSame(['Today'], $this->collect($formula));
    }

    public function testCollectsFunctionsFromNestedFunctionArguments(): void
    {
        self::assertSame(['Outer', 'Inner'], $this->collect('If (TRUE)|Outer(Inner([[ID]]))|"none"'));
    }

    public function testCollectsFunctionsFromCaseBranches(): void
    {
        $formula = 'Case ([[Status]])|"Approved",First()|"Denied",Second()|Third()';

        self::assertSame(['First', 'Second', 'Third'], $this->collect($formula));
    }

    public function testReturnsEmptyArrayWhenFormulaHasNoFunctionCalls(): void
    {
        self::assertSame([], $this->collect('If ([[A]] < 1)|"yes"|"no"'));
    }
}
