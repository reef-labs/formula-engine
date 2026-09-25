<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Runtime;

use ReefLabs\FormulaEngine\Exception\UndefinedFunctionException;
use ReefLabs\FormulaEngine\Exception\UndefinedVariableException;

/**
 * Helper functions referenced by name from compiled PHP code, kept outside
 * of the compiled closures so the generated source stays small and
 * behaves identically to the {@see \ReefLabs\FormulaEngine\Compiler\Evaluator}.
 */
final class FormulaRuntime
{
    public static function getVariable(array $variables, string $name): mixed
    {
        if (!array_key_exists($name, $variables)) {
            throw new UndefinedVariableException($name);
        }

        return $variables[$name];
    }

    /**
     * Call a named formula function with already-evaluated arguments.
     *
     * A caller-supplied function (e.g. one closing over an external
     * lookup, such as `GetNameFromID`) takes precedence over a built-in of
     * the same name.
     *
     * @param array<string, callable> $functions
     * @param list<mixed> $arguments
     */
    public static function callFunction(array $functions, string $name, array $arguments): mixed
    {
        $callable = $functions[$name] ?? self::builtInFunctions()[$name] ?? null;

        if ($callable === null) {
            throw new UndefinedFunctionException($name);
        }

        return $callable(...$arguments);
    }

    /**
     * The names of every built-in function, e.g. for distinguishing them
     * from caller-supplied custom functions.
     *
     * @return string[]
     */
    public static function builtInFunctionNames(): array
    {
        return array_keys(self::builtInFunctions());
    }

    /**
     * Functions available to every formula unless a caller overrides them
     * with a function of the same name. Add a new built-in by adding one
     * entry here.
     *
     * @return array<string, callable>
     */
    private static function builtInFunctions(): array
    {
        return [
            'Today' => static fn (): string => (new \DateTimeImmutable())->format('Y-m-d'),
            'Tomorrow' => static fn (): string => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d'),
            'Now' => static fn (): string => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'Abs' => static fn (int|float $number): int|float => abs($number),
            'Int' => static fn (int|float|string $value): int => (int) $value,
            'ToInt' => static fn (mixed $value): int => (int) $value,
            'ToBoolean' => static fn (mixed $value): bool => (bool) $value,
            'ToDate' => static fn (string $value): string => self::tryParseDateTime($value)?->format('Y-m-d') ?? 'not a date',
            'Day' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('j') : 'not a date';
            },
            'Month' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('n') : 'not a date';
            },
            'Year' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('Y') : 'not a date';
            },
            'DayOfWeek' => static function (string $value): string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? $date->format('l') : 'not a date';
            },
            'DayOfWeekShort' => static function (string $value): string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? $date->format('D') : 'not a date';
            },
            'DayOfWeekNumeric' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('w') : 'not a date';
            },
            'DayOfYear' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('z') + 1 : 'not a date';
            },
            'Week' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('W') : 'not a date';
            },
            'Quarter' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) ceil(((int) $date->format('n')) / 3) : 'not a date';
            },
            'FirstDayOfMonth' => static function (string $value): string {
                $date = self::tryParseDateTime($value);

                return $date !== null
                    ? $date->setDate((int) $date->format('Y'), (int) $date->format('n'), 1)->format('Y-m-d')
                    : 'not a date';
            },
            'FirstDayOfQuarter' => static function (string $value): string {
                $date = self::tryParseDateTime($value);

                if ($date === null) {
                    return 'not a date';
                }

                $quarterStartMonth = ((int) ceil(((int) $date->format('n')) / 3) - 1) * 3 + 1;

                return $date->setDate((int) $date->format('Y'), $quarterStartMonth, 1)->format('Y-m-d');
            },
            'FirstDayOfWeek' => static function (string $value): string {
                $date = self::tryParseDateTime($value);

                return $date !== null
                    ? $date->modify('-' . $date->format('w') . ' days')->format('Y-m-d')
                    : 'not a date';
            },
            'FirstDayOfYear' => static function (string $value): string {
                $date = self::tryParseDateTime($value);

                return $date !== null
                    ? $date->setDate((int) $date->format('Y'), 1, 1)->format('Y-m-d')
                    : 'not a date';
            },
            'Hour' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('g') : 'not a date';
            },
            'Hour24' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('G') : 'not a date';
            },
            'Minute' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('i') : 'not a date';
            },
            'Second' => static function (string $value): int|string {
                $date = self::tryParseDateTime($value);

                return $date !== null ? (int) $date->format('s') : 'not a date';
            },
            'Seconds' => static fn (string $from, string $to): int|string
                => self::tryDiffInSeconds($from, $to) ?? 'not a date',
            'Minutes' => static function (string $from, string $to): int|string {
                $seconds = self::tryDiffInSeconds($from, $to);

                return $seconds !== null ? intdiv($seconds, 60) : 'not a date';
            },
            'Hours' => static function (string $from, string $to): int|string {
                $seconds = self::tryDiffInSeconds($from, $to);

                return $seconds !== null ? intdiv($seconds, 3600) : 'not a date';
            },
            'Days' => static function (int|string $from, int|string $to): int|string {
                $fromDay = self::toDayNumber($from);
                $toDay = self::toDayNumber($to);

                return $fromDay !== null && $toDay !== null ? $toDay - $fromDay : 'not a date';
            },
            'Weeks' => static function (string $from, string $to): int|string {
                $fromDay = self::toDayNumber($from);
                $toDay = self::toDayNumber($to);

                return $fromDay !== null && $toDay !== null ? intdiv($toDay - $fromDay, 7) : 'not a date';
            },
            'Months' => static function (string $from, string $to): int|string {
                $fromMonth = self::toMonthNumber($from);
                $toMonth = self::toMonthNumber($to);

                return $fromMonth !== null && $toMonth !== null ? $toMonth - $fromMonth : 'not a date';
            },
            'Years' => static function (int|string $from, int|string $to): int|string {
                $fromYear = self::toYearNumber($from);
                $toYear = self::toYearNumber($to);

                return $fromYear !== null && $toYear !== null ? $toYear - $fromYear : 'not a date';
            },
            'NameOfMonth' => static fn (int $number): string => $number >= 1 && $number <= 12
                ? \DateTimeImmutable::createFromFormat('!n', (string) $number)->format('F')
                : 'Invalid Month',
            'NameOfMonthShort' => static fn (int $number): string => $number >= 1 && $number <= 12
                ? \DateTimeImmutable::createFromFormat('!n', (string) $number)->format('M')
                : 'Inv',
            'BeginsWith' => static fn (string $haystack, string $needle): bool => str_starts_with($haystack, $needle),
            'EndsWith' => static fn (string $haystack, string $needle): bool => str_ends_with($haystack, $needle),
            'Contains' => static fn (string $haystack, string $needle): bool => str_contains($haystack, $needle),
            'Replace' => static fn (string $haystack, string $needle, string $text): string
                => str_replace($needle, $text, $haystack),
            'Join' => static fn (mixed ...$values): string => implode('', array_map(
                static fn (mixed $value): string => (string) $value,
                $values
            )),
            'JoinWith' => static fn (string $separator, mixed ...$values): string => implode(
                $separator,
                array_map(
                    static fn (mixed $value): string => (string) $value,
                    array_filter($values, static fn (mixed $value): bool => $value !== null)
                )
            ),
            'Trim' => static fn (?string $value): ?string => $value === null ? null : trim($value),
            'ToLower' => static fn (string $value): string => mb_strtolower($value),
            'ToUpper' => static fn (string $value): string => mb_strtoupper($value),
            'Count' => static fn (mixed ...$values): int => count(array_filter(
                $values,
                static fn (mixed $value): bool => $value !== null
            )),
            'Sum' => static fn (int|float ...$numbers): int|float => array_sum($numbers),
            'Avg' => static fn (int|float ...$numbers): int|float => array_sum($numbers) / count($numbers),
            'Median' => static function (int|float ...$numbers): int|float {
                sort($numbers);
                $count = count($numbers);
                $middle = intdiv($count, 2);

                return $count % 2 === 1
                    ? $numbers[$middle]
                    : ($numbers[$middle - 1] + $numbers[$middle]) / 2;
            },
        ];
    }

    /**
     * Parse a date and/or time string in any format PHP's `DateTime`
     * recognizes, or return `null` if it isn't recognizable.
     */
    private static function tryParseDateTime(string $value): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * The difference in seconds between two date/time strings (`$to` minus
     * `$from`, negative if `$to` is earlier), or `null` if either isn't
     * recognizable.
     */
    private static function tryDiffInSeconds(string $from, string $to): ?int
    {
        $fromDate = self::tryParseDateTime($from);
        $toDate = self::tryParseDateTime($to);

        if ($fromDate === null || $toDate === null) {
            return null;
        }

        return $toDate->getTimestamp() - $fromDate->getTimestamp();
    }

    /**
     * The day number of a date/time string (its midnight timestamp divided
     * by the number of seconds in a day, so two of these subtracted give a
     * whole number of calendar days), or the integer itself unchanged if
     * one was given directly instead of a string. Returns `null` if a
     * string isn't a recognizable date.
     */
    private static function toDayNumber(int|string $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        $date = self::tryParseDateTime($value);

        return $date !== null ? intdiv($date->setTime(0, 0)->getTimestamp(), 86400) : null;
    }

    /**
     * The month number of a date/time string, counted from an arbitrary
     * fixed point so two of these subtracted give a whole number of
     * calendar months (year * 12 + month), or `null` if it isn't a
     * recognizable date.
     */
    private static function toMonthNumber(string $value): ?int
    {
        $date = self::tryParseDateTime($value);

        return $date !== null ? ((int) $date->format('Y')) * 12 + (int) $date->format('n') : null;
    }

    /**
     * The calendar year of a date/time string, or the integer itself
     * unchanged if one was given directly instead of a string. Returns
     * `null` if a string isn't a recognizable date.
     */
    private static function toYearNumber(int|string $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        $date = self::tryParseDateTime($value);

        return $date !== null ? (int) $date->format('Y') : null;
    }
}
