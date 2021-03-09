<?php

declare(strict_types=1);

namespace Cherif\InertiaPsr15\Exception;

use InvalidArgumentException;

class MissingInertiaConfigExeception extends InvalidArgumentException
{
    public static function fromMessage(string $message): self
    {
        return new MissingInertiaConfigExeception($message);
    }
}