<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine;

use ReefLabs\FormulaEngine\Ast\Node;
use ReefLabs\FormulaEngine\Compiler\Evaluator;
use ReefLabs\FormulaEngine\Compiler\FunctionCollector;
use ReefLabs\FormulaEngine\Compiler\PhpCodeCompiler;
use ReefLabs\FormulaEngine\Compiler\SqlCompiler;
use ReefLabs\FormulaEngine\Compiler\VariableCollector;
use ReefLabs\FormulaEngine\Exception\SyntaxException;
use ReefLabs\FormulaEngine\Exception\UndefinedFunctionException;
use ReefLabs\FormulaEngine\Lexer\Lexer;
use ReefLabs\FormulaEngine\Parser\Parser;
use ReefLabs\FormulaEngine\Runtime\FormulaRuntime;

/**
 * Entry point for parsing and compiling user-authored formulas such as:
 *
 *   If ([[Income]] < 1000)|"poor"|"rich"
 *   Case ([[Status]])|"Approved","green"|"Denied","red"
 *   If ([[SignupDate]] == Today())|"new"|GetNameFromID([[ID]])
 *
 * Functions such as `Today()` or a caller-supplied `GetNameFromID([[ID]])` can
 * be called by name; see the `$functions` parameter of {@see evaluate()}
 * and {@see toClosure()}.
 */
final class FormulaEngine
{
    public function parse(string $formula): Node
    {
        $tokens = (new Lexer())->tokenize($formula);

        return (new Parser($tokens))->parse();
    }

    /**
     * Compile the formula into a raw SQL expression (a CASE WHEN clause).
     *
     * @param string $identifierQuoteChar Character used to quote variable
     *                                     names as SQL identifiers, e.g. '`'
     *                                     for MySQL or '"' for ANSI SQL.
     */
    public function toSql(string $formula, string $identifierQuoteChar = ''): string
    {
        return (new SqlCompiler($identifierQuoteChar))->compile($this->parse($formula));
    }

    /**
     * Compile the formula into the source code of a PHP anonymous function
     * that accepts an array of variables and an array of functions, and
     * returns the formula's result.
     */
    public function toPhpCode(string $formula): string
    {
        $expression = (new PhpCodeCompiler())->compile($this->parse($formula));

        return "static function (array \$variables, array \$functions = []): mixed {\n    return {$expression};\n}";
    }

    /**
     * Compile the formula into an actual, callable PHP Closure.
     *
     * The returned closure accepts `(array $variables, array $functions = [])`;
     * see {@see evaluate()} for what `$functions` is for.
     */
    public function toClosure(string $formula): \Closure
    {
        $code = $this->toPhpCode($formula);

        /** @var \Closure $closure */
        $closure = eval("return {$code};");

        return $closure;
    }

    /**
     * Parse and immediately evaluate the formula against a set of variables.
     *
     * @param array<string, mixed> $variables
     * @param array<string, callable> $functions Functions callable by name
     *                                            from the formula (e.g.
     *                                            `Today()`, or a
     *                                            caller-supplied
     *                                            `GetNameFromID([[ID]])`
     *                                            closing over an external
     *                                            lookup). A function here
     *                                            overrides a built-in of
     *                                            the same name.
     */
    public function evaluate(string $formula, array $variables, array $functions = []): mixed
    {
        return (new Evaluator($variables, $functions))->evaluate($this->parse($formula));
    }

    /**
     * Return the distinct names of every [[Variable]] referenced by the
     * formula, in order of first appearance.
     *
     * @return string[]
     */
    public function getVariables(string $formula): array
    {
        return (new VariableCollector())->collect($this->parse($formula));
    }

    /**
     * Return the distinct names of every function the formula calls (both
     * built-in and custom), in order of first appearance.
     *
     * @return string[]
     */
    public function getFunctions(string $formula): array
    {
        return (new FunctionCollector())->collect($this->parse($formula));
    }

    /**
     * Return the distinct names of every custom (non-built-in) function the
     * formula calls, in order of first appearance.
     *
     * @return string[]
     */
    public function getCustomFunctions(string $formula): array
    {
        $builtIn = FormulaRuntime::builtInFunctionNames();

        return array_values(array_filter(
            $this->getFunctions($formula),
            static fn (string $name): bool => !in_array($name, $builtIn, true)
        ));
    }

    /**
     * Check whether a formula will be able to run, without actually running
     * it.
     *
     * Syntax is checked first: if the formula can't be tokenized or parsed,
     * that failure is reported and function names aren't checked. Once the
     * formula parses, every function it calls must either be a built-in
     * (e.g. `Today()`) or be named in `$customFunctionNames` — the names you
     * intend to pass in the `$functions` array at evaluation time.
     *
     * This does not check that `[[Variable]]` references exist, since the
     * variables available aren't known until evaluation time; see
     * {@see getVariables()} to inspect them ahead of time instead.
     *
     * @param string[] $customFunctionNames Names of the custom functions
     *                                       you'll supply at evaluation
     *                                       time (i.e. the keys of the
     *                                       `$functions` array you intend to
     *                                       pass to {@see evaluate()} or the
     *                                       compiled closure).
     * @return string|null An error message describing why the formula is
     *                      invalid, or `null` if it's valid.
     */
    public function validate(string $formula, array $customFunctionNames = []): ?string
    {
        try {
            $ast = $this->parse($formula);
        } catch (SyntaxException $e) {
            return $e->getMessage();
        }

        $knownFunctions = [...FormulaRuntime::builtInFunctionNames(), ...$customFunctionNames];

        foreach ((new FunctionCollector())->collect($ast) as $name) {
            if (!in_array($name, $knownFunctions, true)) {
                return (new UndefinedFunctionException($name))->getMessage();
            }
        }

        return null;
    }
}
