<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

use Closure;

final class OptionalProp extends Prop
{
    public function __construct(callable $callable)
    {
        parent::__construct(Closure::fromCallable($callable));
    }
}
