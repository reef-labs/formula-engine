<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Tests;

use ReefLabs\FormulaEngine\Exception\SyntaxException;
use ReefLabs\FormulaEngine\FormulaEngine;
use PHPUnit\Framework\TestCase;

final class FormulaEngineTest extends TestCase
{
    public function testEvaluateReturnsExpectedResultForIf(): void
    {
        $engine = new FormulaEngine();

        self::assertSame('poor', $engine->evaluate('If ([[Income]] < 1000)|"poor"|"rich"', ['Income' => 100]));
        self::assertSame('rich', $engine->evaluate('If ([[Income]] < 1000)|"poor"|"rich"', ['Income' => 100000]));
    }

    public function testEvaluateReturnsExpectedResultForCase(): void
    {
        $engine = new FormulaEngine();
        $formula = 'Case ([[Status]])|"Approved","green"|"Denied","red"';

        self::assertSame('green', $engine->evaluate($formula, ['Status' => 'Approved']));
        self::assertSame('red', $engine->evaluate($formula, ['Status' => 'Denied']));
    }

    public function testToSqlAndToClosureAgreeWithEvaluate(): void
    {
        $engine = new FormulaEngine();
        $formula = 'Case ([[Status]])|"Approved","green"|"Denied","red"|"gray"';

        $sql = $engine->toSql($formula, '`');
        self::assertSame(
            "CASE WHEN (`Status`) = ('Approved') THEN 'green' WHEN (`Status`) = ('Denied') THEN 'red' ELSE 'gray' END",
            $sql
        );

        $closure = $engine->toClosure($formula);

        foreach (['Approved', 'Denied', 'Pending'] as $status) {
            self::assertSame(
                $engine->evaluate($formula, ['Status' => $status]),
                $closure(['Status' => $status])
            );
        }
    }

    public function testInvalidFormulaThrowsSyntaxException(): void
    {
        $engine = new FormulaEngine();

        $this->expectException(SyntaxException::class);

        $engine->evaluate('NotAFunction ([[A]])|1|2', []);
    }

    public function testEvaluateCallsBuiltInAndCallerSuppliedFunctions(): void
    {
        $engine = new FormulaEngine();
        $formula = 'If ([[SignupDate]] == Today())|"new"|GetNameFromID([[ID]])';

        self::assertSame(
            'new',
            $engine->evaluate($formula, ['SignupDate' => (new \DateTimeImmutable())->format('Y-m-d'), 'ID' => 1])
        );

        self::assertSame(
            'User-42',
            $engine->evaluate(
                $formula,
                ['SignupDate' => '2000-01-01', 'ID' => 42],
                ['GetNameFromID' => fn (int $id): string => "User-{$id}"]
            )
        );
    }

    public function testGetVariablesReturnsDistinctNamesInOrderOfAppearance(): void
    {
        $engine = new FormulaEngine();

        self::assertSame(
            ['Income'],
            $engine->getVariables('If ([[Income]] < 1000)|"poor"|"rich"')
        );

        self::assertSame(
            ['Status'],
            $engine->getVariables('Case ([[Status]])|"Approved","green"|"Denied","red"')
        );

        self::assertSame(
            ['B', 'A', 'C'],
            $engine->getVariables('If ([[B]] > 0 AND [[A]] > 0)|[[C]]|[[A]]')
        );
    }

    public function testGetFunctionsReturnsDistinctNamesInOrderOfAppearanceIncludingBuiltIns(): void
    {
        $engine = new FormulaEngine();
        $formula = 'If ([[SignupDate]] == Today())|"new"|GetNameFromID([[ID]])';

        self::assertSame(['Today', 'GetNameFromID'], $engine->getFunctions($formula));
    }

    public function testGetCustomFunctionsExcludesBuiltIns(): void
    {
        $engine = new FormulaEngine();
        $formula = 'If ([[SignupDate]] == Today())|"new"|GetNameFromID([[ID]])';

        self::assertSame(['GetNameFromID'], $engine->getCustomFunctions($formula));
    }

    public function testGetCustomFunctionsReturnsEmptyArrayWhenOnlyBuiltInsAreUsed(): void
    {
        $engine = new FormulaEngine();

        self::assertSame([], $engine->getCustomFunctions('If (Today() == Today())|"same"|"different"'));
    }

    public function testValidateReturnsNullForWellFormedFormulaUsingOnlyBuiltIns(): void
    {
        $engine = new FormulaEngine();

        self::assertNull($engine->validate('If ([[Income]] < 1000)|"poor"|"rich"'));
        self::assertNull($engine->validate('If ([[SignupDate]] == Today())|"new"|"old"'));
    }

    public function testValidateReturnsErrorMessageForInvalidSyntax(): void
    {
        $engine = new FormulaEngine();

        self::assertSame(
            'Unexpected trailing input after formula, found "|" at position 20',
            $engine->validate('NotAFunction ([[A]])|1|2')
        );
    }

    public function testValidateReturnsErrorMessageForUnknownFunctionByDefault(): void
    {
        $engine = new FormulaEngine();

        self::assertSame(
            'Undefined function "GetNameFromID"',
            $engine->validate('If ([[ID]] > 0)|GetNameFromID([[ID]])|"unknown"')
        );
    }

    public function testValidateAcceptsCustomFunctionNamesSuppliedByCaller(): void
    {
        $engine = new FormulaEngine();

        self::assertNull(
            $engine->validate('If ([[ID]] > 0)|GetNameFromID([[ID]])|"unknown"', ['GetNameFromID'])
        );
    }
}
