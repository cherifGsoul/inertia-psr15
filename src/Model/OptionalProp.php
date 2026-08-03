<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

use function call_user_func;

final class OptionalProp
{
    /** @var callable */
    private $callback;

    public function __construct(callable $callable)
    {
        $this->callback = $callable;
    }

    public function __invoke(): mixed
    {
        return call_user_func($this->callback);
    }
}
