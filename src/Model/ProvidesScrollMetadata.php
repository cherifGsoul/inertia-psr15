<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

/** Framework-neutral pagination metadata contract for Inertia::scroll(). */
interface ProvidesScrollMetadata
{
    public function getPageName(): string;

    public function getPreviousPage(): int|string|null;

    public function getNextPage(): int|string|null;

    public function getCurrentPage(): int|string|null;
}
