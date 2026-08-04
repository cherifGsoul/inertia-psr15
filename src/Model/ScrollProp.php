<?php

declare(strict_types=1);

namespace Sirix\InertiaPsr15\Model;

use Closure;
use InvalidArgumentException;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;
use function preg_match;
use function strlen;

final class ScrollProp extends Prop
{
    /** @param null|array<string, mixed>|Closure(mixed): ProvidesScrollMetadata|ProvidesScrollMetadata $metadata */
    public function __construct(
        mixed $value,
        private readonly string $wrapper = 'data',
        private readonly array|Closure|ProvidesScrollMetadata|null $metadata = null
    ) {
        if ('' === $wrapper || 255 < strlen($wrapper) || 1 !== preg_match('/^[^.\x00-\x1F\x7F]+(?:\.[^.\x00-\x1F\x7F]+)*$/', $wrapper)) {
            throw new InvalidArgumentException('The scroll wrapper must be a safe dot path.');
        }

        parent::__construct($value);
    }

    public function wrapper(): string
    {
        return $this->wrapper;
    }

    /** @return array<string, null|int|string> */
    public function metadata(mixed $value): array
    {
        $metadata = $this->metadata;
        if ($metadata instanceof Closure) {
            $metadata = $metadata($value);
        }

        if ($metadata instanceof ProvidesScrollMetadata) {
            return [
                'pageName'     => $metadata->getPageName(),
                'previousPage' => $metadata->getPreviousPage(),
                'nextPage'     => $metadata->getNextPage(),
                'currentPage'  => $metadata->getCurrentPage(),
            ];
        }

        if (is_array($metadata)) {
            return $this->validateMetadata($metadata);
        }

        if (is_array($value)) {
            $metadata = [
                'pageName'     => $value['pageName'] ?? $value['page_name'] ?? 'page',
                'previousPage' => $value['previousPage'] ?? $value['prev_page'] ?? $value['prev_page_url'] ?? null,
                'nextPage'     => $value['nextPage'] ?? $value['next_page'] ?? $value['next_page_url'] ?? null,
                'currentPage'  => $value['currentPage'] ?? $value['current_page'] ?? 1,
            ];

            return $this->validateMetadata($metadata);
        }

        throw new InvalidArgumentException('Inertia::scroll() needs pagination metadata for non-array data.');
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, null|int|string>
     */
    private function validateMetadata(array $metadata): array
    {
        foreach (['pageName', 'previousPage', 'nextPage', 'currentPage'] as $key) {
            if (! array_key_exists($key, $metadata)) {
                throw new InvalidArgumentException('Scroll metadata must contain ' . $key . '.');
            }
        }

        if (! is_string($metadata['pageName']) || '' === $metadata['pageName'] || 255 < strlen($metadata['pageName']) || 1 === preg_match('/[\x00-\x1F\x7F]/', $metadata['pageName'])) {
            throw new InvalidArgumentException('Scroll metadata pageName must be a non-empty safe string.');
        }

        foreach (['previousPage', 'nextPage', 'currentPage'] as $key) {
            $value = $metadata[$key];
            if (null !== $value && ! is_int($value) && ! is_string($value)) {
                throw new InvalidArgumentException('Scroll metadata ' . $key . ' must be an integer, string, or null.');
            }

            if (is_string($value) && (4096 < strlen($value) || 1 === preg_match('/[\x00-\x1F\x7F]/', $value))) {
                throw new InvalidArgumentException('Scroll metadata ' . $key . ' must not contain control characters.');
            }
        }

        return [
            'pageName'     => $metadata['pageName'],
            'previousPage' => $metadata['previousPage'],
            'nextPage'     => $metadata['nextPage'],
            'currentPage'  => $metadata['currentPage'],
        ];
    }
}
