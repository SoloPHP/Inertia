<?php

declare(strict_types=1);

namespace Solo\Inertia\Internal;

use Closure;
use JsonSerializable;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Solo\Inertia\Header;
use Solo\Inertia\Inertia;
use Solo\Inertia\Prop;
use Solo\Inertia\ScrollMetadata;
use Throwable;
use UnexpectedValueException;

/**
 * Resolves the props of one page for one request, following the protocol's prop
 * evaluation model, and collects the metadata the client needs about them.
 *
 * A full visit resolves every prop except optional and deferred ones, and once props
 * the client already has. A partial reload of the same component resolves the props
 * it asks for by path: "auth" selects all of auth, "auth.user" only that branch.
 * Always props are resolved on both. A value resolved as a whole, such as a Closure
 * that returns an array, is sent whole.
 *
 * @internal
 */
final class PropsResolver
{
    private readonly bool $inertia;
    private readonly bool $partial;
    /** @var list<string>|null */
    private readonly ?array $only;
    /** @var list<string>|null */
    private readonly ?array $except;
    /** @var list<string> */
    private readonly array $reset;
    /** @var list<string> */
    private readonly array $loadedOnce;
    private readonly bool $prependScroll;

    /** @var list<string> */
    private array $sharedProps = [];
    /** @var list<string> */
    private array $mergeProps = [];
    /** @var list<string> */
    private array $prependProps = [];
    /** @var list<string> */
    private array $deepMergeProps = [];
    /** @var list<string> */
    private array $matchPropsOn = [];
    /** @var array<string, list<string>> */
    private array $deferredProps = [];
    /** @var array<string, true> */
    private array $rescued = [];
    /** @var array<string, array<string, mixed>> */
    private array $scrollProps = [];
    /** @var array<string, array{prop: string, expiresAt: int|null}> */
    private array $onceProps = [];

    public function __construct(
        ServerRequestInterface $request,
        string $component,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->inertia = Inertia::isInertia($request);
        $this->partial = $this->inertia && $request->getHeaderLine(Header::PARTIAL_COMPONENT) === $component;
        $this->only = self::list($request, Header::PARTIAL_DATA);
        $this->except = self::list($request, Header::PARTIAL_EXCEPT);
        $this->reset = self::list($request, Header::RESET) ?? [];
        $this->loadedOnce = self::list($request, Header::EXCEPT_ONCE_PROPS) ?? [];
        $this->prependScroll = $request->getHeaderLine(Header::INFINITE_SCROLL_MERGE_INTENT) === 'prepend';
    }

    /**
     * @param array<array-key, mixed> $shared Props shared with every page
     * @param array<array-key, mixed> $props  The page's own props, which win over shared ones
     * @return array{array<array-key, mixed>, array<string, mixed>} The props and the page object's metadata
     */
    public function resolve(array $shared, array $props): array
    {
        foreach (array_keys($shared) as $key) {
            $this->sharedProps[] = explode('.', (string) $key, 2)[0];
        }
        $this->sharedProps = array_values(array_unique($this->sharedProps));

        $props = $this->resolveProps(self::unpackDotted(array_replace($shared, $props)), '', false);

        return [$props, $this->metadata()];
    }

    /**
     * @param array<array-key, mixed> $props
     * @param bool $whole The props are part of a value resolved as a whole, so the partial filters skip them
     * @return array<array-key, mixed>
     */
    private function resolveProps(array $props, string $prefix, bool $whole): array
    {
        $result = [];

        foreach ($props as $key => $prop) {
            // A plain value needs no path, unless a partial reload has to select it
            if (($whole || !$this->partial) && !is_array($prop) && !is_object($prop)) {
                $result[$key] = $prop;
                continue;
            }

            $path = $prefix === '' ? (string) $key : "$prefix.$key";

            if ($this->partial && !$whole && !($prop instanceof Prop && $prop->always) && !$this->selected($path)) {
                continue;
            }
            if ($this->skippedOnFullVisit($prop, $path)) {
                continue;
            }

            $value = $this->value($prop, $path);

            // A Closure may return a Prop, which then decides as if it had been given directly
            if ($value instanceof Prop) {
                $prop = $value;
                if ($this->skippedOnFullVisit($prop, $path)) {
                    continue;
                }
                $value = $this->value($prop, $path);
            }

            if (isset($this->rescued[$path])) {
                continue;
            }

            if ($prop instanceof Prop) {
                $this->collect($prop, $path, $value);
            }

            $result[$key] = is_array($value) ? $this->resolveProps($value, $path, $whole || !is_array($prop)) : $value;
        }

        return $result;
    }

    /**
     * Whether a full visit leaves the prop out: an optional or deferred prop, or a once
     * prop the client has. Its metadata is collected all the same, so the client knows
     * what to load and what to keep.
     */
    private function skippedOnFullVisit(mixed $prop, string $path): bool
    {
        if ($this->partial || !$prop instanceof Prop) {
            return false;
        }

        if ($prop->optional || $prop->group !== null) {
            if ($prop->group !== null && !$this->clientHas($prop, $path)) {
                $this->deferredProps[$prop->group][] = $path;
            }
            $this->collectMerge($prop, $path);
            $this->collectOnce($prop, $path);

            return true;
        }

        if ($this->inertia && $this->clientHas($prop, $path)) {
            $this->collectOnce($prop, $path);

            return true;
        }

        return false;
    }

    private function clientHas(Prop $prop, string $path): bool
    {
        return $prop->once && !$prop->fresh && in_array($prop->key ?? $path, $this->loadedOnce, true);
    }

    private function value(mixed $prop, string $path): mixed
    {
        try {
            $value = $prop instanceof Prop ? $prop->value : $prop;
            if ($value instanceof Closure) {
                $value = $value();
            }

            return $value instanceof JsonSerializable ? $value->jsonSerialize() : $value;
        } catch (Throwable $e) {
            if (!$prop instanceof Prop || !$prop->rescue) {
                throw $e;
            }

            $this->logger?->error('Deferred prop {prop} failed and was left out: {message}', [
                'prop' => $path,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            $this->rescued[$path] = true;

            return null;
        }
    }

    private function collect(Prop $prop, string $path, mixed $value): void
    {
        $this->collectMerge($prop, $path);
        if ($prop->scroll !== null) {
            $this->collectScroll($prop, $path, $value);
        }
        $this->collectOnce($prop, $path);
    }

    private function collectMerge(Prop $prop, string $path): void
    {
        if ($prop->merge === null && $prop->scroll === null) {
            return;
        }
        // A reset prop is sent unlabeled, so the client replaces it
        if (in_array($path, $this->reset, true) || !$this->inMetadata($path)) {
            return;
        }

        if ($prop->scroll !== null) {
            if ($this->prependScroll) {
                $this->prependProps[] = "$path.$prop->wrapper";
            } else {
                $this->mergeProps[] = "$path.$prop->wrapper";
            }
        } elseif ($prop->merge === 'deep') {
            $this->deepMergeProps[] = $path;
        } elseif ($prop->appendAt === [] && $prop->prependAt === []) {
            if ($prop->merge === 'append') {
                $this->mergeProps[] = $path;
            } else {
                $this->prependProps[] = $path;
            }
        } else {
            foreach ($prop->appendAt as $at) {
                $this->mergeProps[] = "$path.$at";
            }
            foreach ($prop->prependAt as $at) {
                $this->prependProps[] = "$path.$at";
            }
        }

        foreach ($prop->matchOn as $field) {
            $this->matchPropsOn[] = "$path.$field";
        }
    }

    private function collectScroll(Prop $prop, string $path, mixed $value): void
    {
        $metadata = $prop->scroll instanceof Closure ? ($prop->scroll)($value) : $prop->scroll;
        if (!$metadata instanceof ScrollMetadata) {
            throw new UnexpectedValueException(sprintf(
                'The scroll metadata of prop %s must be a %s, %s returned',
                $path,
                ScrollMetadata::class,
                get_debug_type($metadata),
            ));
        }

        $this->scrollProps[$path] = $metadata->toArray() + ['reset' => in_array($path, $this->reset, true)];
    }

    private function collectOnce(Prop $prop, string $path): void
    {
        if (!$prop->once || !$this->inMetadata($path)) {
            return;
        }

        $this->onceProps[$prop->key ?? $path] = ['prop' => $path, 'expiresAt' => $prop->expiresAt];
    }

    /** Whether a partial reload sends the prop at $path, or a prop below it */
    private function selected(string $path): bool
    {
        if ($this->only !== null && !Paths::within($path, $this->only) && !self::leadsTo($path, $this->only)) {
            return false;
        }

        return $this->except === null || !Paths::within($path, $this->except);
    }

    /** Whether the response is a full one, or a partial reload that asked for the prop at $path itself */
    private function inMetadata(string $path): bool
    {
        return !$this->partial || ($this->only === null || Paths::within($path, $this->only))
            && ($this->except === null || !Paths::within($path, $this->except));
    }

    /** @param list<string> $paths */
    private static function leadsTo(string $path, array $paths): bool
    {
        foreach ($paths as $selected) {
            if (str_starts_with($selected, "$path.")) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function metadata(): array
    {
        $metadata = array_filter([
            'sharedProps' => $this->sharedProps,
            'mergeProps' => $this->mergeProps,
            'prependProps' => $this->prependProps,
            'deepMergeProps' => $this->deepMergeProps,
            'matchPropsOn' => $this->matchPropsOn,
            'deferredProps' => $this->deferredProps,
            'rescuedProps' => array_keys($this->rescued),
            'scrollProps' => $this->scrollProps,
            'onceProps' => $this->onceProps,
        ], static fn(array $value): bool => $value !== []);

        // Keyed by group, path or once key: objects in JSON even for a key such as "0"
        foreach (['deferredProps', 'scrollProps', 'onceProps'] as $name) {
            if (isset($metadata[$name])) {
                $metadata[$name] = (object) $metadata[$name];
            }
        }

        return $metadata;
    }

    /**
     * Top-level keys with dots, such as "auth.user", become nested props. A Closure on
     * the way is resolved, so the prop can nest into what it returns.
     *
     * @param array<array-key, mixed> $props
     * @return array<array-key, mixed>
     */
    private static function unpackDotted(array $props): array
    {
        foreach (array_keys($props) as $key) {
            if (!is_string($key) || !str_contains($key, '.')) {
                continue;
            }

            $value = $props[$key];
            unset($props[$key]);

            $segments = explode('.', $key);
            $leaf = array_pop($segments);
            $node = &$props;
            $path = '';
            foreach ($segments as $segment) {
                $path = $path === '' ? $segment : "$path.$segment";
                $branch = $node[$segment] ?? [];
                if ($branch instanceof Closure) {
                    $branch = $branch();
                }
                if (!is_array($branch)) {
                    throw new LogicException("Prop $key cannot be set: $path is not an array");
                }
                $node[$segment] = $branch;
                $node = &$node[$segment];
            }
            $node[$leaf] = $value;
            unset($node);
        }

        return $props;
    }

    /** @return list<string>|null The comma-separated values of a header, or null without any */
    private static function list(ServerRequestInterface $request, string $header): ?array
    {
        return HeaderList::of($request, $header) ?: null;
    }
}
