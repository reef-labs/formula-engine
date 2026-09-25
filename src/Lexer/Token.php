<?php

declare(strict_types=1);

namespace ReefLabs\FormulaEngine\Lexer;

final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly int $position,
    ) {
    }
}
