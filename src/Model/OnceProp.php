<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

use function is_int;
use function preg_match;
use function str_contains;
use function strlen;
use function trim;

final class OnceProp extends Prop
{
    private bool $fresh     = false;
    private ?int $expiresAt = null;
    private ?string $key    = null;

    public function fresh(bool $fresh = true): self
    {
        $prop        = clone $this;
        $prop->fresh = $fresh;

        return $prop;
    }

    public function until(DateInterval|DateTimeInterface|int $until): self
    {
        if (is_int($until) && $until < 0) {
            throw new InvalidArgumentException('A once prop expiration interval must not be negative.');
        }

        if ($until instanceof DateInterval) {
            $until = (new DateTimeImmutable())->add($until);
        } elseif (is_int($until)) {
            $until = (new DateTimeImmutable())->modify('+' . $until . ' seconds');
        }

        $prop            = clone $this;
        $prop->expiresAt = ((int) $until->format('U')) * 1000 + (int) $until->format('v');

        return $prop;
    }

    public function as(string $key): self
    {
        if (
            '' === $key
            || $key !== trim($key)
            || str_contains($key, ',')
            || 255 < strlen($key)
            || 1 !== preg_match('/^[^\x00-\x1F\x7F]+$/', $key)
        ) {
            throw new InvalidArgumentException('A once prop key must be a non-empty safe string.');
        }

        $prop      = clone $this;
        $prop->key = $key;

        return $prop;
    }

    public function isFresh(): bool
    {
        return $this->fresh;
    }

    public function expiresAt(): ?int
    {
        return $this->expiresAt;
    }

    public function key(): ?string
    {
        return $this->key;
    }
}
