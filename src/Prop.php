<?php

declare(strict_types=1);

namespace Solo\Inertia;

use BackedEnum;
use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

/**
 * A prop with its own rules for when it is sent and how the client treats it. The
 * factories on Inertia create one: always(), optional(), defer(), merge(), deepMerge(),
 * once() and scroll(). Each method below returns a changed copy, so the categories
 * combine: `Inertia::defer(fn() => ...)->merge()->once()`.
 *
 * A Closure value is called only when the prop is sent, as for a plain prop.
 */
final readonly class Prop
{
    /**
     * @param mixed                     $value     The value, or a Closure that returns it
     * @param bool                      $always    Sent on every response, even a partial reload that did not ask for it
     * @param bool                      $optional  Sent only when a partial reload asks for it
     * @param string|null               $group     Deferred: left out of the first visit and loaded by the client
     *                                             right after it, in one request per group
     * @param bool                      $rescue    A deferred prop that throws is left out and reported
     * @param 'append'|'prepend'|'deep'|null $merge How the client merges the prop into what it has, at the root
     *                                             of the prop unless $appendAt or $prependAt name paths in it
     * @param list<string>              $appendAt  Paths in the prop whose arrays the client appends to
     * @param list<string>              $prependAt Paths in the prop whose arrays the client prepends to
     * @param list<string>              $matchOn   Paths, from the prop, of the field that identifies an item:
     *                                             the client replaces a matching item instead of adding it again
     * @param bool                      $once      The client remembers the prop and the server skips it while
     *                                             the client has it
     * @param string|null               $key       The key a once prop is remembered under, the prop's path by default
     * @param int|null                  $expiresAt Milliseconds since the epoch after which the client forgets it
     * @param bool                      $fresh     A once prop sent even though the client has it
     * @param ScrollMetadata|Closure|null $scroll  Infinite scroll: the cursor, or a Closure that takes the
     *                                             resolved value and returns it
     * @param string                    $wrapper   Infinite scroll: the key of the items in the value
     */
    public function __construct(
        public mixed $value,
        public bool $always = false,
        public bool $optional = false,
        public ?string $group = null,
        public bool $rescue = false,
        public ?string $merge = null,
        public array $appendAt = [],
        public array $prependAt = [],
        public array $matchOn = [],
        public bool $once = false,
        public ?string $key = null,
        public ?int $expiresAt = null,
        public bool $fresh = false,
        public ScrollMetadata|Closure|null $scroll = null,
        public string $wrapper = 'data',
    ) {
        if ($value instanceof self) {
            throw new LogicException('A prop wraps a value or a Closure, not another Prop: chain its methods instead');
        }
        if ((int) $always + (int) $optional + (int) ($group !== null) > 1) {
            throw new LogicException('A prop is always, optional or deferred, not more than one of them');
        }
        if ($rescue && $group === null) {
            throw new LogicException('Only a deferred prop can be rescued');
        }
        if ($always && $once) {
            throw new LogicException('An always prop is sent on every response, so it cannot be a once prop');
        }
        if (!$once && ($key !== null || $expiresAt !== null || $fresh)) {
            throw new LogicException('A key, an expiry and fresh() are for once props only');
        }
        if ($scroll !== null && $merge !== null) {
            throw new LogicException('An infinite scroll prop merges at its wrapper; it takes no other merge');
        }
        if (($appendAt !== [] || $prependAt !== []) && $merge !== 'append' && $merge !== 'prepend') {
            throw new LogicException('A deep merge merges the whole prop; it takes no append or prepend paths');
        }
        if ($matchOn !== [] && $merge === null && $scroll === null) {
            throw new LogicException('Only a merge or infinite scroll prop matches items on a key');
        }
    }

    /**
     * Leaves the prop out of the first visit. The client loads it right after, in one
     * request with the other deferred props of $group.
     */
    public function defer(string $group = 'default'): self
    {
        return $this->with(group: $group);
    }

    /** The client appends the prop to what it has, if it is an array, instead of replacing it */
    public function merge(): self
    {
        return $this->with(merge: $this->merge ?? 'append');
    }

    /** The client merges the prop into what it has at every depth */
    public function deepMerge(): self
    {
        return $this->with(merge: 'deep');
    }

    /**
     * The client appends to the array at $path in the prop, or to the prop itself
     * without a path. $matchOn names the field that identifies an item.
     */
    public function append(?string $path = null, ?string $matchOn = null): self
    {
        return $this->mergeAt('append', $path, $matchOn);
    }

    /**
     * The client prepends to the array at $path in the prop, or to the prop itself
     * without a path. $matchOn names the field that identifies an item.
     */
    public function prepend(?string $path = null, ?string $matchOn = null): self
    {
        return $this->mergeAt('prepend', $path, $matchOn);
    }

    /**
     * The fields that identify an item, as paths from the prop, such as "id" or
     * "data.id": the client replaces a matching item instead of adding it again.
     */
    public function matchOn(string ...$paths): self
    {
        return $this->with(matchOn: array_values($paths));
    }

    /**
     * The client remembers the prop and sends its key back; the server then leaves
     * it out until the client forgets it, on a page without it or when it expires.
     */
    public function once(): self
    {
        return $this->with(once: true);
    }

    /** A once prop remembered under $key, which pages that call the prop differently can share */
    public function as(string|BackedEnum $key): self
    {
        return $this->with(once: true, key: $key instanceof BackedEnum ? (string) $key->value : $key);
    }

    /** A once prop the client forgets after $ttl, given in seconds, as an interval or as a time */
    public function until(DateTimeInterface|DateInterval|int $ttl): self
    {
        $expires = match (true) {
            $ttl instanceof DateTimeInterface => $ttl->getTimestamp(),
            $ttl instanceof DateInterval => (new DateTimeImmutable())->add($ttl)->getTimestamp(),
            default => time() + $ttl,
        };

        return $this->with(once: true, expiresAt: $expires * 1000);
    }

    /** A once prop sent even though the client has it, when the data behind it has changed */
    public function fresh(bool $fresh = true): self
    {
        return $this->with(once: true, fresh: $fresh);
    }

    /** @param 'append'|'prepend' $mode */
    private function mergeAt(string $mode, ?string $path, ?string $matchOn): self
    {
        if ($path === null) {
            return $this->with(
                merge: $mode,
                matchOn: $matchOn === null ? null : [...$this->matchOn, $matchOn],
            );
        }

        return $this->with(
            merge: $this->merge ?? $mode,
            appendAt: $mode === 'append' ? [...$this->appendAt, $path] : null,
            prependAt: $mode === 'prepend' ? [...$this->prependAt, $path] : null,
            matchOn: $matchOn === null ? null : [...$this->matchOn, "$path.$matchOn"],
        );
    }

    /**
     * A copy with the given fields changed; null keeps a field as it is.
     *
     * @param 'append'|'prepend'|'deep'|null $merge
     * @param list<string>|null $appendAt
     * @param list<string>|null $prependAt
     * @param list<string>|null $matchOn
     */
    private function with(
        ?string $group = null,
        ?string $merge = null,
        ?array $appendAt = null,
        ?array $prependAt = null,
        ?array $matchOn = null,
        ?bool $once = null,
        ?string $key = null,
        ?int $expiresAt = null,
        ?bool $fresh = null,
    ): self {
        return new self(
            $this->value,
            $this->always,
            $this->optional,
            $group ?? $this->group,
            $this->rescue,
            $merge ?? $this->merge,
            $appendAt ?? $this->appendAt,
            $prependAt ?? $this->prependAt,
            $matchOn ?? $this->matchOn,
            $once ?? $this->once,
            $key ?? $this->key,
            $expiresAt ?? $this->expiresAt,
            $fresh ?? $this->fresh,
            $this->scroll,
            $this->wrapper,
        );
    }
}
