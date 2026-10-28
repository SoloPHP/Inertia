<?php

declare(strict_types=1);

namespace Solo\Inertia;

/**
 * What one response leaves for the next page Inertia renders: flash data, validation
 * errors and two history flags. Inertia::redirect() and back() hand it to the flash
 * store; render() takes it back, shows it once and lets the store forget it.
 */
final readonly class Flash
{
    /**
     * @param array<string, mixed> $data             One-time data, the page's `flash`
     * @param array<string, mixed> $errors           Validation errors, the page's `errors` prop: field => messages
     * @param bool                 $clearHistory     The page rotates the key of the encrypted history
     * @param bool                 $preserveFragment The page keeps the URL fragment of the visit that led to it
     */
    public function __construct(
        public array $data = [],
        public array $errors = [],
        public bool $clearHistory = false,
        public bool $preserveFragment = false,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->data === [] && $this->errors === [] && !$this->clearHistory && !$this->preserveFragment;
    }

    /**
     * This one with what a later response adds: its data over this data, its errors
     * instead of these, and either one's history flags.
     */
    public function merge(self $later): self
    {
        return new self(
            array_replace($this->data, $later->data),
            $later->errors !== [] ? $later->errors : $this->errors,
            $this->clearHistory || $later->clearHistory,
            $this->preserveFragment || $later->preserveFragment,
        );
    }
}
