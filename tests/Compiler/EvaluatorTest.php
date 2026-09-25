<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Tests\Compiler;

use ReefLabs\FormulaEngine\Compiler\Evaluator;
use ReefLabs\FormulaEngine\Exception\UndefinedFunctionException;
use ReefLabs\FormulaEngine\Exception\UndefinedVariableException;
use ReefLabs\FormulaEngine\Lexer\Lexer;
use ReefLabs\FormulaEngine\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class EvaluatorTest extends TestCase
{
    private function evaluate(string $formula, array $variables, array $functions = []): mixed
    {
        $tokens = (new Lexer())->tokenize($formula);
        $ast = (new Parser($tokens))->parse();

        return (new Evaluator($variables, $functions))->evaluate($ast);
    }

    public function testEvaluatesIfTrueBranch(): void
    {
        $result = $this->evaluate('If ([[Income]] < 1000)|"poor"|"rich"', ['Income' => 500]);

        self::assertSame('poor', $result);
    }

    public function testEvaluatesIfFalseBranch(): void
    {
        $result = $this->evaluate('If ([[Income]] < 1000)|"poor"|"rich"', ['Income' => 5000]);

        self::assertSame('rich', $result);
    }

    public function testEvaluatesCaseMatchingBranch(): void
    {
        $formula = 'Case ([[Status]])|"Approved","green"|"Denied","red"';

        self::assertSame('green', $this->evaluate($formula, ['Status' => 'Approved']));
        self::assertSame('red', $this->evaluate($formula, ['Status' => 'Denied']));
    }

    public function testEvaluatesCaseDefaultBranch(): void
    {
        $formula = 'Case ([[Status]])|"Approved","green"|"Denied","red"|"gray"';

        self::assertSame('gray', $this->evaluate($formula, ['Status' => 'Pending']));
    }

    public function testEvaluatesCaseWithoutDefaultReturnsNull(): void
    {
        $formula = 'Case ([[Status]])|"Approved","green"|"Denied","red"';

        self::assertNull($this->evaluate($formula, ['Status' => 'Pending']));
    }

    public function testEvaluatesLogicalAndOr(): void
    {
        $formula = 'If ([[A]] > 0 AND [[B]] > 0)|"both positive"|"not both"';

        self::assertSame('both positive', $this->evaluate($formula, ['A' => 1, 'B' => 1]));
        self::assertSame('not both', $this->evaluate($formula, ['A' => 1, 'B' => -1]));
    }

    public function testEvaluatesNot(): void
    {
        $formula = 'If (NOT ([[Active]] == TRUE))|"inactive"|"active"';

        self::assertSame('inactive', $this->evaluate($formula, ['Active' => false]));
        self::assertSame('active', $this->evaluate($formula, ['Active' => true]));
    }

    public function testEvaluatesArithmeticOperators(): void
    {
        self::assertSame(7, $this->evaluate('If (TRUE)|3 + 4|0', []));
        self::assertSame(-1, $this->evaluate('If (TRUE)|3 - 4|0', []));
        self::assertSame(12, $this->evaluate('If (TRUE)|3 * 4|0', []));
        self::assertSame(2.5, $this->evaluate('If (TRUE)|5 / 2|0', []));
    }

    public function testEvaluatesArithmeticWithVariablesAndPrecedence(): void
    {
        $formula = 'If (([[Income]] + [[Raise]]) <= 1000)|"poor"|"rich"';

        self::assertSame('poor', $this->evaluate($formula, ['Income' => 500, 'Raise' => 400]));
        self::assertSame('rich', $this->evaluate($formula, ['Income' => 900, 'Raise' => 400]));
        self::assertSame(14, $this->evaluate('If (TRUE)|2 + 3 * 4|0', []));
    }

    public function testEvaluatesUnaryMinus(): void
    {
        self::assertSame(-5, $this->evaluate('If (TRUE)|-[[X]]|0', ['X' => 5]));
        self::assertSame(10, $this->evaluate('If (TRUE)|5 - -5|0', []));
    }

    public function testEvaluatesNestedFormulas(): void
    {
        $formula = 'If ([[A]] < 1)|If ([[B]] < 1)|"both small"|"a small"|"neither"';

        self::assertSame('both small', $this->evaluate($formula, ['A' => 0, 'B' => 0]));
        self::assertSame('a small', $this->evaluate($formula, ['A' => 0, 'B' => 5]));
        self::assertSame('neither', $this->evaluate($formula, ['A' => 5, 'B' => 5]));
    }

    public function testThrowsOnUndefinedVariable(): void
    {
        $this->expectException(UndefinedVariableException::class);
        $this->expectExceptionMessage('Undefined variable "Income"');

        $this->evaluate('If ([[Income]] < 1000)|"poor"|"rich"', []);
    }

    public function testTreatsNullVariableAsDefined(): void
    {
        $result = $this->evaluate('If ([[Income]] == NULL)|"unknown"|"known"', ['Income' => null]);

        self::assertSame('unknown', $result);
    }

    public function testEvaluatesBuiltInTodayFunctionWithNoArguments(): void
    {
        $result = $this->evaluate('If ([[Date]] == Today())|"today"|"not today"', [
            'Date' => (new \DateTimeImmutable())->format('Y-m-d'),
        ]);

        self::assertSame('today', $result);
    }

    public function testEvaluatesBuiltInTomorrowFunctionWithNoArguments(): void
    {
        $result = $this->evaluate('If ([[Date]] == Tomorrow())|"tomorrow"|"not tomorrow"', [
            'Date' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
        ]);

        self::assertSame('tomorrow', $result);
    }

    public function testEvaluatesBuiltInNowFunctionWithNoArguments(): void
    {
        $before = new \DateTimeImmutable();
        $result = $this->evaluate('If (TRUE)|Now()|"no"', []);
        $after = new \DateTimeImmutable();

        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);

        $resultDate = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $result);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $resultDate->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $resultDate->getTimestamp());
    }

    public function testEvaluatesBuiltInToDateFunctionWithRecognizableFormats(): void
    {
        $formula = 'If (TRUE)|ToDate([[Value]])|"no"';

        self::assertSame('2024-03-05', $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('2024-03-05', $this->evaluate($formula, ['Value' => 'March 5, 2024']));
        self::assertSame('2024-03-05', $this->evaluate($formula, ['Value' => '03/05/2024']));
    }

    public function testEvaluatesBuiltInAbsFunction(): void
    {
        $formula = 'If (TRUE)|Abs([[Value]])|"no"';

        self::assertSame(5, $this->evaluate($formula, ['Value' => -5]));
        self::assertSame(5, $this->evaluate($formula, ['Value' => 5]));
        self::assertSame(5.5, $this->evaluate($formula, ['Value' => -5.5]));
    }

    public function testEvaluatesBuiltInIntFunction(): void
    {
        $formula = 'If (TRUE)|Int([[Value]])|"no"';

        self::assertSame(5, $this->evaluate($formula, ['Value' => 5.9]));
        self::assertSame(5, $this->evaluate($formula, ['Value' => '5']));
        self::assertSame(5, $this->evaluate($formula, ['Value' => '5.9']));
        self::assertSame(-5, $this->evaluate($formula, ['Value' => -5.9]));
    }

    public function testEvaluatesBuiltInToIntFunction(): void
    {
        $formula = 'If (TRUE)|ToInt([[Value]])|"no"';

        self::assertSame(5, $this->evaluate($formula, ['Value' => 5.9]));
        self::assertSame(5, $this->evaluate($formula, ['Value' => '5']));
        self::assertSame(1, $this->evaluate($formula, ['Value' => true]));
        self::assertSame(0, $this->evaluate($formula, ['Value' => false]));
        self::assertSame(0, $this->evaluate($formula, ['Value' => null]));
    }

    public function testEvaluatesBuiltInToBooleanFunction(): void
    {
        $formula = 'If (ToBoolean([[Value]]))|"yes"|"no"';

        self::assertSame('yes', $this->evaluate($formula, ['Value' => 1]));
        self::assertSame('yes', $this->evaluate($formula, ['Value' => 'anything']));
        self::assertSame('no', $this->evaluate($formula, ['Value' => 0]));
        self::assertSame('no', $this->evaluate($formula, ['Value' => '']));
        self::assertSame('no', $this->evaluate($formula, ['Value' => '0']));
        self::assertSame('no', $this->evaluate($formula, ['Value' => null]));
    }

    public function testEvaluatesBuiltInDayFunction(): void
    {
        $formula = 'If (TRUE)|Day([[Value]])|"no"';

        self::assertSame(5, $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInMonthFunction(): void
    {
        $formula = 'If (TRUE)|Month([[Value]])|"no"';

        self::assertSame(3, $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInYearFunction(): void
    {
        $formula = 'If (TRUE)|Year([[Value]])|"no"';

        self::assertSame(2024, $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInDayOfWeekFunction(): void
    {
        $formula = 'If (TRUE)|DayOfWeek([[Value]])|"no"';

        self::assertSame('Tuesday', $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInDayOfWeekShortFunction(): void
    {
        $formula = 'If (TRUE)|DayOfWeekShort([[Value]])|"no"';

        self::assertSame('Tue', $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInDayOfWeekNumericFunction(): void
    {
        $formula = 'If (TRUE)|DayOfWeekNumeric([[Value]])|"no"';

        self::assertSame(2, $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame(0, $this->evaluate($formula, ['Value' => '2024-03-03']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInDayOfYearFunction(): void
    {
        $formula = 'If (TRUE)|DayOfYear([[Value]])|"no"';

        self::assertSame(1, $this->evaluate($formula, ['Value' => '2024-01-01']));
        self::assertSame(65, $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInWeekFunction(): void
    {
        $formula = 'If (TRUE)|Week([[Value]])|"no"';

        self::assertSame(1, $this->evaluate($formula, ['Value' => '2024-01-01']));
        self::assertSame(10, $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInQuarterFunction(): void
    {
        $formula = 'If (TRUE)|Quarter([[Value]])|"no"';

        self::assertSame(1, $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame(4, $this->evaluate($formula, ['Value' => '2024-12-31']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInFirstDayOfMonthFunction(): void
    {
        $formula = 'If (TRUE)|FirstDayOfMonth([[Value]])|"no"';

        self::assertSame('2024-03-01', $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInFirstDayOfQuarterFunction(): void
    {
        $formula = 'If (TRUE)|FirstDayOfQuarter([[Value]])|"no"';

        self::assertSame('2024-01-01', $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('2024-10-01', $this->evaluate($formula, ['Value' => '2024-12-31']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInFirstDayOfWeekFunction(): void
    {
        $formula = 'If (TRUE)|FirstDayOfWeek([[Value]])|"no"';

        self::assertSame('2024-03-03', $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('2024-12-29', $this->evaluate($formula, ['Value' => '2024-12-31']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInFirstDayOfYearFunction(): void
    {
        $formula = 'If (TRUE)|FirstDayOfYear([[Value]])|"no"';

        self::assertSame('2024-01-01', $this->evaluate($formula, ['Value' => '2024-03-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInHourFunction(): void
    {
        $formula = 'If (TRUE)|Hour([[Value]])|"no"';

        self::assertSame(2, $this->evaluate($formula, ['Value' => '2024-03-05 14:30:45']));
        self::assertSame(12, $this->evaluate($formula, ['Value' => '00:15:05']));
        self::assertSame(12, $this->evaluate($formula, ['Value' => '12:00:00']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInHour24Function(): void
    {
        $formula = 'If (TRUE)|Hour24([[Value]])|"no"';

        self::assertSame(14, $this->evaluate($formula, ['Value' => '2024-03-05 14:30:45']));
        self::assertSame(0, $this->evaluate($formula, ['Value' => '00:15:05']));
        self::assertSame(23, $this->evaluate($formula, ['Value' => '23:59:59']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInMinuteFunction(): void
    {
        $formula = 'If (TRUE)|Minute([[Value]])|"no"';

        self::assertSame(30, $this->evaluate($formula, ['Value' => '2024-03-05 14:30:45']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInSecondFunction(): void
    {
        $formula = 'If (TRUE)|Second([[Value]])|"no"';

        self::assertSame(45, $this->evaluate($formula, ['Value' => '2024-03-05 14:30:45']));
        self::assertSame('not a date', $this->evaluate($formula, ['Value' => 'not a date']));
    }

    public function testEvaluatesBuiltInSecondsFunction(): void
    {
        $formula = 'If (TRUE)|Seconds([[From]], [[To]])|"no"';

        self::assertSame(5, $this->evaluate($formula, ['From' => '10:00:00', 'To' => '10:00:05']));
        self::assertSame(-5, $this->evaluate($formula, ['From' => '10:00:05', 'To' => '10:00:00']));
        self::assertSame(
            3600,
            $this->evaluate($formula, ['From' => '2024-03-05 10:00:00', 'To' => '2024-03-05 11:00:00'])
        );
        self::assertSame('not a date', $this->evaluate($formula, ['From' => 'not a date', 'To' => '10:00:00']));
    }

    public function testEvaluatesBuiltInMinutesFunction(): void
    {
        $formula = 'If (TRUE)|Minutes([[From]], [[To]])|"no"';

        self::assertSame(1, $this->evaluate($formula, ['From' => '10:00:00', 'To' => '10:01:30']));
        self::assertSame(-1, $this->evaluate($formula, ['From' => '10:01:30', 'To' => '10:00:00']));
        self::assertSame('not a date', $this->evaluate($formula, ['From' => 'not a date', 'To' => '10:00:00']));
    }

    public function testEvaluatesBuiltInHoursFunction(): void
    {
        $formula = 'If (TRUE)|Hours([[From]], [[To]])|"no"';

        self::assertSame(1, $this->evaluate($formula, ['From' => '10:00:00', 'To' => '11:30:00']));
        self::assertSame(-1, $this->evaluate($formula, ['From' => '11:30:00', 'To' => '10:00:00']));
        self::assertSame('not a date', $this->evaluate($formula, ['From' => 'not a date', 'To' => '10:00:00']));
    }

    public function testEvaluatesBuiltInDaysFunctionWithDateStrings(): void
    {
        $formula = 'If (TRUE)|Days([[From]], [[To]])|"no"';

        self::assertSame(7, $this->evaluate($formula, ['From' => '2024-01-01', 'To' => '2024-01-08']));
        self::assertSame(-7, $this->evaluate($formula, ['From' => '2024-01-08', 'To' => '2024-01-01']));
        self::assertSame(
            1,
            $this->evaluate($formula, ['From' => '2024-01-01 23:00:00', 'To' => '2024-01-02 01:00:00'])
        );
        self::assertSame('not a date', $this->evaluate($formula, ['From' => 'not a date', 'To' => '2024-01-01']));
    }

    public function testEvaluatesBuiltInDaysFunctionWithIntegers(): void
    {
        $result = $this->evaluate('If (TRUE)|Days([[From]], [[To]])|"no"', ['From' => 5, 'To' => 10]);

        self::assertSame(5, $result);
    }

    public function testEvaluatesBuiltInWeeksFunction(): void
    {
        $formula = 'If (TRUE)|Weeks([[From]], [[To]])|"no"';

        self::assertSame(2, $this->evaluate($formula, ['From' => '2024-01-01', 'To' => '2024-01-15']));
        self::assertSame(-2, $this->evaluate($formula, ['From' => '2024-01-15', 'To' => '2024-01-01']));
        self::assertSame('not a date', $this->evaluate($formula, ['From' => 'not a date', 'To' => '2024-01-01']));
    }

    public function testEvaluatesBuiltInMonthsFunction(): void
    {
        $formula = 'If (TRUE)|Months([[From]], [[To]])|"no"';

        self::assertSame(2, $this->evaluate($formula, ['From' => '2024-01-15', 'To' => '2024-03-10']));
        self::assertSame(-2, $this->evaluate($formula, ['From' => '2024-03-10', 'To' => '2024-01-15']));
        self::assertSame(1, $this->evaluate($formula, ['From' => '2023-12-20', 'To' => '2024-01-05']));
        self::assertSame('not a date', $this->evaluate($formula, ['From' => 'not a date', 'To' => '2024-01-01']));
    }

    public function testEvaluatesBuiltInYearsFunctionWithDateStrings(): void
    {
        $formula = 'If (TRUE)|Years([[From]], [[To]])|"no"';

        self::assertSame(3, $this->evaluate($formula, ['From' => '2020-06-15', 'To' => '2023-01-05']));
        self::assertSame(-3, $this->evaluate($formula, ['From' => '2023-01-05', 'To' => '2020-06-15']));
        self::assertSame('not a date', $this->evaluate($formula, ['From' => 'not a date', 'To' => '2024-01-01']));
    }

    public function testEvaluatesBuiltInYearsFunctionWithIntegers(): void
    {
        $result = $this->evaluate('If (TRUE)|Years([[From]], [[To]])|"no"', ['From' => 2020, 'To' => 2023]);

        self::assertSame(3, $result);
    }

    public function testToDateReturnsNotADateStringForUnrecognizableInput(): void
    {
        $result = $this->evaluate('If (TRUE)|ToDate([[Value]])|"no"', ['Value' => 'not a date']);

        self::assertSame('not a date', $result);
    }

    public function testEvaluatesBuiltInNameOfMonthFunction(): void
    {
        $formula = 'If (TRUE)|NameOfMonth([[Value]])|"no"';

        self::assertSame('January', $this->evaluate($formula, ['Value' => 1]));
        self::assertSame('December', $this->evaluate($formula, ['Value' => 12]));
        self::assertSame('Invalid Month', $this->evaluate($formula, ['Value' => 0]));
        self::assertSame('Invalid Month', $this->evaluate($formula, ['Value' => 13]));
    }

    public function testEvaluatesBuiltInNameOfMonthShortFunction(): void
    {
        $formula = 'If (TRUE)|NameOfMonthShort([[Value]])|"no"';

        self::assertSame('Jan', $this->evaluate($formula, ['Value' => 1]));
        self::assertSame('Dec', $this->evaluate($formula, ['Value' => 12]));
        self::assertSame('Inv', $this->evaluate($formula, ['Value' => 0]));
        self::assertSame('Inv', $this->evaluate($formula, ['Value' => 13]));
    }

    public function testEvaluatesBuiltInBeginsWithFunction(): void
    {
        $formula = 'If (BeginsWith([[Value]], "foo"))|"yes"|"no"';

        self::assertSame('yes', $this->evaluate($formula, ['Value' => 'foobar']));
        self::assertSame('no', $this->evaluate($formula, ['Value' => 'barfoo']));
    }

    public function testEvaluatesBuiltInEndsWithFunction(): void
    {
        $formula = 'If (EndsWith([[Value]], "bar"))|"yes"|"no"';

        self::assertSame('yes', $this->evaluate($formula, ['Value' => 'foobar']));
        self::assertSame('no', $this->evaluate($formula, ['Value' => 'barfoo']));
    }

    public function testEvaluatesBuiltInContainsFunction(): void
    {
        $formula = 'If (Contains([[Value]], "oob"))|"yes"|"no"';

        self::assertSame('yes', $this->evaluate($formula, ['Value' => 'foobar']));
        self::assertSame('no', $this->evaluate($formula, ['Value' => 'barbaz']));
    }

    public function testEvaluatesBuiltInReplaceFunction(): void
    {
        $formula = 'If (TRUE)|Replace([[Value]], "oo", "aa")|"no"';

        self::assertSame('faabaor', $this->evaluate($formula, ['Value' => 'foobaor']));
        self::assertSame('none', $this->evaluate($formula, ['Value' => 'none']));
    }

    public function testEvaluatesBuiltInReplaceFunctionReplacesAllInstances(): void
    {
        $result = $this->evaluate('If (TRUE)|Replace([[Value]], "-", "/")|"no"', ['Value' => '2024-03-05']);

        self::assertSame('2024/03/05', $result);
    }

    public function testEvaluatesBuiltInJoinFunction(): void
    {
        $formula = 'If (TRUE)|Join("https://example.com/?id=", [[ID]], "&mode=view")|"no"';

        self::assertSame('https://example.com/?id=42&mode=view', $this->evaluate($formula, ['ID' => 42]));
    }

    public function testJoinReturnsEmptyStringForNoArguments(): void
    {
        self::assertSame('', $this->evaluate('If (TRUE)|Join()|"no"', []));
    }

    public function testEvaluatesBuiltInJoinWithFunctionDropsNullValues(): void
    {
        $formula = 'If (TRUE)|JoinWith(" - ", [[A]], [[B]], [[C]])|"no"';

        self::assertSame('first - third', $this->evaluate($formula, ['A' => 'first', 'B' => null, 'C' => 'third']));
        self::assertSame('first - second - third', $this->evaluate($formula, ['A' => 'first', 'B' => 'second', 'C' => 'third']));
    }

    public function testJoinWithReturnsEmptyStringWhenAllValuesAreNull(): void
    {
        self::assertSame(
            '',
            $this->evaluate('If (TRUE)|JoinWith(" - ", [[A]], [[B]])|"no"', ['A' => null, 'B' => null])
        );
    }

    public function testEvaluatesBuiltInTrimFunction(): void
    {
        $formula = 'If (TRUE)|Trim([[Value]])|"no"';

        self::assertSame('foo', $this->evaluate($formula, ['Value' => "  foo  \t\n"]));
        self::assertSame('foo', $this->evaluate($formula, ['Value' => 'foo']));
    }

    public function testTrimReturnsNullForNullInput(): void
    {
        self::assertNull($this->evaluate('If (TRUE)|Trim([[Value]])|"no"', ['Value' => null]));
    }

    public function testEvaluatesBuiltInToLowerFunction(): void
    {
        $formula = 'If (TRUE)|ToLower([[Value]])|"no"';

        self::assertSame('foo bar', $this->evaluate($formula, ['Value' => 'FOO Bar']));
    }

    public function testEvaluatesBuiltInToUpperFunction(): void
    {
        $formula = 'If (TRUE)|ToUpper([[Value]])|"no"';

        self::assertSame('FOO BAR', $this->evaluate($formula, ['Value' => 'foo Bar']));
    }

    public function testEvaluatesBuiltInCountFunction(): void
    {
        $formula = 'If (TRUE)|Count([[A]], [[B]], [[C]], [[D]])|"no"';

        self::assertSame(3, $this->evaluate($formula, ['A' => 1, 'B' => null, 'C' => 'x', 'D' => 0]));
        self::assertSame(0, $this->evaluate($formula, ['A' => null, 'B' => null, 'C' => null, 'D' => null]));
    }

    public function testCountReturnsZeroForNoArguments(): void
    {
        self::assertSame(0, $this->evaluate('If (TRUE)|Count()|"no"', []));
    }

    public function testEvaluatesBuiltInSumFunction(): void
    {
        self::assertSame(6, $this->evaluate('If (TRUE)|Sum(1, 2, 3)|"no"', []));
        self::assertSame(6.5, $this->evaluate('If (TRUE)|Sum(1, 2.5, 3)|"no"', []));
        self::assertSame(10, $this->evaluate('If (TRUE)|Sum([[A]], [[B]])|"no"', ['A' => 4, 'B' => 6]));
    }

    public function testEvaluatesBuiltInAvgFunction(): void
    {
        self::assertSame(2, $this->evaluate('If (TRUE)|Avg(1, 2, 3)|"no"', []));
        self::assertSame(1.5, $this->evaluate('If (TRUE)|Avg(1, 2)|"no"', []));
        self::assertSame(5, $this->evaluate('If (TRUE)|Avg([[A]], [[B]])|"no"', ['A' => 4, 'B' => 6]));
    }

    public function testEvaluatesBuiltInMedianFunctionWithOddCount(): void
    {
        self::assertSame(2, $this->evaluate('If (TRUE)|Median(3, 1, 2)|"no"', []));
    }

    public function testEvaluatesBuiltInMedianFunctionWithEvenCount(): void
    {
        self::assertSame(2.5, $this->evaluate('If (TRUE)|Median(1, 2, 3, 4)|"no"', []));
    }

    public function testEvaluatesCallerSuppliedFunctionWithVariableArgument(): void
    {
        $formula = 'If ([[ID]] > 0)|GetNameFromID([[ID]])|"none"';

        $result = $this->evaluate($formula, ['ID' => 7], [
            'GetNameFromID' => fn (int $id): string => "User-{$id}",
        ]);

        self::assertSame('User-7', $result);
    }

    public function testCallerSuppliedFunctionOverridesBuiltIn(): void
    {
        $result = $this->evaluate('If (TRUE)|Today()|"no"', [], [
            'Today' => fn (): string => 'overridden',
        ]);

        self::assertSame('overridden', $result);
    }

    public function testThrowsOnUndefinedFunction(): void
    {
        $this->expectException(UndefinedFunctionException::class);
        $this->expectExceptionMessage('Undefined function "NotAFunction"');

        $this->evaluate('If (TRUE)|NotAFunction()|"no"', []);
    }
}
