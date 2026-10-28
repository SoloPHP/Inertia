<?php

declare(strict_types=1);

namespace Solo\Inertia\Internal;

/**
 * @internal
 */
final class Paths
{
    /**
     * Whether $path is one of $paths or below one: "auth" covers "auth.user"
     *
     * @param list<string> $paths
     */
    public static function within(string $path, array $paths): bool
    {
        foreach ($paths as $selected) {
            if ($path === $selected || str_starts_with($path, "$selected.")) {
                return true;
            }
        }

        return false;
    }
}
