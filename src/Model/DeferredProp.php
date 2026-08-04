<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

use Closure;

use function is_callable;

final class DeferredProp extends Prop
{
    public function __construct(mixed $value, private readonly ?string $group = null, private readonly bool $rescue = false)
    {
        parent::__construct(is_callable($value) ? Closure::fromCallable($value) : $value);
    }

    public function group(): string
    {
        return $this->group ?? 'default';
    }

    public function rescue(): bool
    {
        return $this->rescue;
    }
}
