<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

use DateInterval;
use DateTimeInterface;

/**
 * Base class for composable Inertia prop decorators.
 *
 * A decorator deliberately keeps its value unresolved. This lets the response
 * builder decide whether a value is needed before invoking user code.
 */
abstract class Prop
{
    public function __construct(private readonly mixed $value) {}

    public function value(): mixed
    {
        return $this->value;
    }

    public function once(): OnceProp
    {
        return new OnceProp($this);
    }

    /**
     * @param null|array<int|string, string>|string $paths
     */
    public function append(array|string|null $paths = null, ?string $matchOn = null): MergeProp
    {
        return (new MergeProp($this))->append($paths, $matchOn);
    }

    /**
     * @param null|array<int|string, string>|string $paths
     */
    public function prepend(array|string|null $paths = null, ?string $matchOn = null): MergeProp
    {
        return (new MergeProp($this))->prepend($paths, $matchOn);
    }

    public function deepMerge(): MergeProp
    {
        return (new MergeProp($this))->deepMerge();
    }

    public function matchOn(string $path): MergeProp
    {
        return (new MergeProp($this))->matchOn($path);
    }

    public function fresh(bool $fresh = true): OnceProp
    {
        return $this->once()->fresh($fresh);
    }

    public function until(DateInterval|DateTimeInterface|int $until): OnceProp
    {
        return $this->once()->until($until);
    }

    public function as(string $key): OnceProp
    {
        return $this->once()->as($key);
    }
}
