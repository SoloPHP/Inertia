<?php

declare(strict_types=1);

namespace Solo\Inertia;

/**
 * The headers of the Inertia protocol.
 *
 * @see https://inertiajs.com/docs/v3/core-concepts/the-protocol
 */
final class Header
{
    /** Sent by the client on every Inertia request, and by the server on every page it answers with JSON */
    public const string INERTIA = 'X-Inertia';

    /** The asset version the client's page was built from; answered with it on a version mismatch */
    public const string VERSION = 'X-Inertia-Version';

    /** On a 409: where the client goes with a full page load */
    public const string LOCATION = 'X-Inertia-Location';

    /** On a 409: where the client goes with a new Inertia visit, fragment included */
    public const string REDIRECT = 'X-Inertia-Redirect';

    /** The component a partial reload is for */
    public const string PARTIAL_COMPONENT = 'X-Inertia-Partial-Component';

    /** Comma-separated props a partial reload asks for */
    public const string PARTIAL_DATA = 'X-Inertia-Partial-Data';

    /** Comma-separated props a partial reload leaves out */
    public const string PARTIAL_EXCEPT = 'X-Inertia-Partial-Except';

    /** Comma-separated props the client replaces instead of merging into */
    public const string RESET = 'X-Inertia-Reset';

    /** The error bag a form's validation errors go under */
    public const string ERROR_BAG = 'X-Inertia-Error-Bag';

    /** Whether an infinite scroll page is appended or prepended: "append" or "prepend" */
    public const string INFINITE_SCROLL_MERGE_INTENT = 'X-Inertia-Infinite-Scroll-Merge-Intent';

    /** Comma-separated keys of the once props the client already has */
    public const string EXCEPT_ONCE_PROPS = 'X-Inertia-Except-Once-Props';

    /** "prefetch" on a request the client makes ahead of a visit */
    public const string PURPOSE = 'Purpose';

    /** "true" on a request that asks for validation only, and on the answer to it */
    public const string PRECOGNITION = 'Precognition';

    /** Comma-separated fields a Precognition request asks to validate */
    public const string PRECOGNITION_VALIDATE_ONLY = 'Precognition-Validate-Only';

    /** "true" on the 204 that answers a valid Precognition request */
    public const string PRECOGNITION_SUCCESS = 'Precognition-Success';
}
