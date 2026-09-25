<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Lexer;

enum TokenType
{
    case Identifier;
    case Variable;
    case String;
    case Number;
    case Operator;
    case ArithmeticOperator;
    case LParen;
    case RParen;
    case Pipe;
    case Comma;
    case Eof;
}
