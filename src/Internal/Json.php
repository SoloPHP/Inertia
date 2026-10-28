<?php

declare(strict_types=1);

namespace Solo\Inertia\Internal;

/**
 * @internal
 */
final class Json
{
    /** How the package writes JSON everywhere but in a <script> element */
    public const int FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
}
