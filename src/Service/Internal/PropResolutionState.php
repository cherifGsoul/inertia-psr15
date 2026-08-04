<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Service\Internal;

use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/** @internal */
final class PropResolutionState
{
    /** @var array<string, list<string>> */ public array $deferred = [];

    /** @var list<string> */ public array $rescued = [];

    /** @var array<string, array{prop: string, expiresAt: ?int}> */ public array $once = [];

    /** @var list<string> */ public array $merge = [];

    /** @var list<string> */ public array $prepend = [];

    /** @var list<string> */ public array $deepMerge = [];

    /** @var list<string> */ public array $matchOn = [];

    /** @var array<string, array<string, null|bool|int|string>> */ public array $scroll = [];

    /**
     * @param list<string> $onlyPaths
     * @param list<string> $exceptPaths
     * @param list<string> $reset
     * @param list<string> $exceptOnce
     */
    public function __construct(
        public readonly bool $explicitOnly,
        public readonly bool $isPartial,
        private readonly array $onlyPaths,
        private readonly array $exceptPaths,
        private readonly array $reset,
        private readonly array $exceptOnce,
        public readonly string $scrollMergeIntent
    ) {}

    public function explicitlyRequested(string $path): bool
    {
        foreach ($this->onlyPaths as $onlyPath) {
            if ($onlyPath === $path || str_contains($onlyPath, $path . '.') || str_contains($path, $onlyPath . '.')) {
                return true;
            }
        }

        return false;
    }

    public function isReset(string $path): bool
    {
        return $this->explicitlyRequestedFrom($this->reset, $path);
    }

    public function exceptOnce(string $key): bool
    {
        return $this->explicitlyRequestedFrom($this->exceptOnce, $key);
    }

    /** @return list<string> */
    public function excludedChildren(string $path): array
    {
        $children = [];
        $prefix   = $path . '.';
        foreach ($this->exceptPaths as $exceptPath) {
            if (str_starts_with($exceptPath, $prefix)) {
                $children[] = substr($exceptPath, strlen($prefix));
            }
        }

        return $children;
    }

    /** @param list<string> $paths */
    private function explicitlyRequestedFrom(array $paths, string $path): bool
    {
        foreach ($paths as $candidate) {
            if ($candidate === $path || str_contains($candidate, $path . '.') || str_contains($path, $candidate . '.')) {
                return true;
            }
        }

        return false;
    }
}
