<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Tests\Compiler;

use ReefLabs\FormulaEngine\Compiler\VariableCollector;
use ReefLabs\FormulaEngine\Lexer\Lexer;
use ReefLabs\FormulaEngine\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class VariableCollectorTest extends TestCase
{
    private function collect(string $formula): array
    {
        $tokens = (new Lexer())->tokenize($formula);
        $ast = (new Parser($tokens))->parse();

        return (new VariableCollector())->collect($ast);
    }

    public function testCollectsSingleVariableFromIf(): void
    {
        self::assertSame(['Income'], $this->collect('If ([[Income]] < 1000)|"poor"|"rich"'));
    }

    public function testCollectsVariableFromCaseSubject(): void
    {
        self::assertSame(['Status'], $this->collect('Case ([[Status]])|"Approved","green"|"Denied","red"'));
    }

    public function testCollectsMultipleVariablesInOrderOfFirstAppearance(): void
    {
        $formula = 'If ([[B]] > 0 AND [[A]] > 0)|[[C]]|[[A]]';

        self::assertSame(['B', 'A', 'C'], $this->collect($formula));
    }

    public function testDeduplicatesRepeatedVariables(): void
    {
        $formula = 'If ([[Income]] < 1000 AND [[Income]] > 0)|"low"|"other"';

        self::assertSame(['Income'], $this->collect($formula));
    }

    public function testCollectsVariablesFromNestedFormulasAndCaseBranches(): void
    {
        // The nested Case must be parenthesized here: otherwise its branch
        // loop would greedily consume the outer If's "|[[E]]" as one of its
        // own "|value" branches instead of stopping at the enclosing ")".
        $formula = 'If ([[A]] < 1)|(Case ([[B]])|"x",[[C]]|"y",[[D]])|[[E]]';

        self::assertSame(['A', 'B', 'C', 'D', 'E'], $this->collect($formula));
    }

    public function testReturnsEmptyArrayWhenFormulaHasNoVariables(): void
    {
        self::assertSame([], $this->collect('If (1 < 2)|"yes"|"no"'));
    }

    public function testCollectsVariablesFromFunctionArguments(): void
    {
        self::assertSame(['ID'], $this->collect('If (TRUE)|GetNameFromID([[ID]])|"none"'));
    }
}
