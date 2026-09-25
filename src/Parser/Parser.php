<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Parser;

use ReefLabs\FormulaEngine\Ast\BinaryExpressionNode;
use ReefLabs\FormulaEngine\Ast\CaseNode;
use ReefLabs\FormulaEngine\Ast\CaseWhenClause;
use ReefLabs\FormulaEngine\Ast\FunctionCallNode;
use ReefLabs\FormulaEngine\Ast\IfNode;
use ReefLabs\FormulaEngine\Ast\LiteralNode;
use ReefLabs\FormulaEngine\Ast\Node;
use ReefLabs\FormulaEngine\Ast\UnaryExpressionNode;
use ReefLabs\FormulaEngine\Ast\VariableNode;
use ReefLabs\FormulaEngine\Exception\SyntaxException;
use ReefLabs\FormulaEngine\Lexer\Token;
use ReefLabs\FormulaEngine\Lexer\TokenType;

/**
 * Recursive-descent parser that turns a token stream into a formula AST.
 *
 * Grammar (informal):
 *   formula    := expression
 *   ifExpr     := "If" "(" expression ")" "|" expression "|" expression
 *   caseExpr   := "Case" "(" expression ")" ( "|" expression ( "," expression )? )+
 *   expression := logicalOr
 *   logicalOr  := logicalAnd ( "OR" logicalAnd )*
 *   logicalAnd := comparison ( "AND" comparison )*
 *   comparison := additive ( operator additive )?
 *   additive   := multiplicative ( ("+" | "-") multiplicative )*
 *   multiplicative := unary ( ("*" | "/") unary )*
 *   unary      := "NOT" unary | "-" unary | primary
 *   primary    := VARIABLE | STRING | NUMBER | "TRUE" | "FALSE" | "NULL"
 *                 | "(" expression ")" | ifExpr | caseExpr | functionCall
 *   functionCall := IDENTIFIER "(" ( expression ( "," expression )* )? ")"
 *
 * Note: because caseExpr consumes "|" branches greedily, a Case nested as
 * a non-final value inside another If/Case (e.g. an If's "then") must be
 * wrapped in parentheses so its branch loop stops at the enclosing ")"
 * instead of swallowing the outer formula's remaining "|" branches.
 */
final class Parser
{
    private int $index = 0;

    /**
     * @param Token[] $tokens
     */
    public function __construct(private readonly array $tokens)
    {
    }

    public function parse(): Node
    {
        $node = $this->parseExpression();
        $this->expect(TokenType::Eof, 'Unexpected trailing input after formula');

        return $node;
    }

    private function parseIf(): Node
    {
        $this->advance();
        $this->expect(TokenType::LParen, 'Expected "(" after "If"');
        $condition = $this->parseExpression();
        $this->expect(TokenType::RParen, 'Expected ")" after If condition');
        $this->expect(TokenType::Pipe, 'Expected "|" after If condition');
        $then = $this->parseExpression();
        $this->expect(TokenType::Pipe, 'Expected "|" separating If then/else branches');
        $else = $this->parseExpression();

        return new IfNode($condition, $then, $else);
    }

    private function parseCase(): Node
    {
        $this->advance();
        $this->expect(TokenType::LParen, 'Expected "(" after "Case"');
        $subject = $this->parseExpression();
        $this->expect(TokenType::RParen, 'Expected ")" after Case subject');

        $whenClauses = [];
        $default = null;

        while ($this->check(TokenType::Pipe)) {
            if ($default !== null) {
                throw new SyntaxException(
                    'A Case default branch must be the last branch',
                    $this->current()->position
                );
            }

            $this->advance();
            $value = $this->parseExpression();

            if ($this->check(TokenType::Comma)) {
                $this->advance();
                $result = $this->parseExpression();
                $whenClauses[] = new CaseWhenClause($value, $result);
            } else {
                $default = $value;
            }
        }

        if ($whenClauses === []) {
            throw new SyntaxException(
                'Case expression requires at least one "value,result" branch',
                $this->current()->position
            );
        }

        return new CaseNode($subject, $whenClauses, $default);
    }

    private function parseExpression(): Node
    {
        return $this->parseLogicalOr();
    }

    private function parseLogicalOr(): Node
    {
        $left = $this->parseLogicalAnd();

        while ($this->checkKeyword('OR')) {
            $this->advance();
            $left = new BinaryExpressionNode($left, 'OR', $this->parseLogicalAnd());
        }

        return $left;
    }

    private function parseLogicalAnd(): Node
    {
        $left = $this->parseComparison();

        while ($this->checkKeyword('AND')) {
            $this->advance();
            $left = new BinaryExpressionNode($left, 'AND', $this->parseComparison());
        }

        return $left;
    }

    private function parseComparison(): Node
    {
        $left = $this->parseAdditive();

        if ($this->check(TokenType::Operator)) {
            $operator = $this->advance()->value;
            $right = $this->parseAdditive();

            return new BinaryExpressionNode($left, $operator, $right);
        }

        return $left;
    }

    private function parseAdditive(): Node
    {
        $left = $this->parseMultiplicative();

        while ($this->checkArithmeticOperator('+') || $this->checkArithmeticOperator('-')) {
            $operator = $this->advance()->value;
            $left = new BinaryExpressionNode($left, $operator, $this->parseMultiplicative());
        }

        return $left;
    }

    private function parseMultiplicative(): Node
    {
        $left = $this->parseUnary();

        while ($this->checkArithmeticOperator('*') || $this->checkArithmeticOperator('/')) {
            $operator = $this->advance()->value;
            $left = new BinaryExpressionNode($left, $operator, $this->parseUnary());
        }

        return $left;
    }

    private function parseUnary(): Node
    {
        if ($this->checkKeyword('NOT')) {
            $this->advance();

            return new UnaryExpressionNode('NOT', $this->parseUnary());
        }

        if ($this->checkArithmeticOperator('-')) {
            $this->advance();

            return new UnaryExpressionNode('-', $this->parseUnary());
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): Node
    {
        $token = $this->current();

        switch ($token->type) {
            case TokenType::Variable:
                $this->advance();

                return new VariableNode($token->value);

            case TokenType::String:
                $this->advance();

                return new LiteralNode($token->value);

            case TokenType::Number:
                $this->advance();

                return new LiteralNode(
                    str_contains($token->value, '.') ? (float) $token->value : (int) $token->value
                );

            case TokenType::LParen:
                $this->advance();
                $expression = $this->parseExpression();
                $this->expect(TokenType::RParen, 'Expected closing ")"');

                return $expression;

            case TokenType::Identifier:
                return match (strtoupper($token->value)) {
                    'TRUE' => $this->consumeLiteral(true),
                    'FALSE' => $this->consumeLiteral(false),
                    'NULL' => $this->consumeLiteral(null),
                    'IF' => $this->parseIf(),
                    'CASE' => $this->parseCase(),
                    default => $this->parseFunctionCall($token),
                };

            default:
                throw new SyntaxException(
                    sprintf('Unexpected token "%s"', $token->value),
                    $token->position
                );
        }
    }

    private function consumeLiteral(bool|null $value): Node
    {
        $this->advance();

        return new LiteralNode($value);
    }

    private function parseFunctionCall(Token $name): Node
    {
        $this->advance();

        if (!$this->check(TokenType::LParen)) {
            throw new SyntaxException(sprintf('Unexpected identifier "%s"', $name->value), $name->position);
        }

        $this->advance();

        $arguments = [];

        if (!$this->check(TokenType::RParen)) {
            $arguments[] = $this->parseExpression();

            while ($this->check(TokenType::Comma)) {
                $this->advance();
                $arguments[] = $this->parseExpression();
            }
        }

        $this->expect(TokenType::RParen, sprintf('Expected ")" after arguments to "%s"', $name->value));

        return new FunctionCallNode($name->value, $arguments);
    }

    private function current(): Token
    {
        return $this->tokens[$this->index];
    }

    private function advance(): Token
    {
        return $this->tokens[$this->index++];
    }

    private function check(TokenType $type): bool
    {
        return $this->current()->type === $type;
    }

    private function checkKeyword(string $keyword): bool
    {
        return $this->check(TokenType::Identifier) && strtoupper($this->current()->value) === $keyword;
    }

    private function checkArithmeticOperator(string $operator): bool
    {
        return $this->check(TokenType::ArithmeticOperator) && $this->current()->value === $operator;
    }

    private function expect(TokenType $type, string $message): Token
    {
        if (!$this->check($type)) {
            throw new SyntaxException(
                sprintf('%s, found "%s"', $message, $this->current()->value),
                $this->current()->position
            );
        }

        return $this->advance();
    }
}
