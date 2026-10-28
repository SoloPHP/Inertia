# Header

`Solo\Inertia\Header` — the header names of the [Inertia protocol](https://inertiajs.com/docs/v3/core-concepts/the-protocol), for tests and for middleware of your own.

| Constant | Header | |
|---|---|---|
| `INERTIA` | `X-Inertia` | Sent by the client on every Inertia request, and by `render()` on every page object |
| `VERSION` | `X-Inertia-Version` | The client's asset version; echoed on a version conflict |
| `LOCATION` | `X-Inertia-Location` | On a `409`: a full page load of this URL |
| `REDIRECT` | `X-Inertia-Redirect` | On a `409`: an Inertia visit to this URL, fragment included |
| `PARTIAL_COMPONENT` | `X-Inertia-Partial-Component` | The component a partial reload is for |
| `PARTIAL_DATA` | `X-Inertia-Partial-Data` | The paths a partial reload asks for |
| `PARTIAL_EXCEPT` | `X-Inertia-Partial-Except` | The paths a partial reload leaves out |
| `RESET` | `X-Inertia-Reset` | Props the client replaces instead of merging into |
| `ERROR_BAG` | `X-Inertia-Error-Bag` | The error bag a form's errors go under |
| `INFINITE_SCROLL_MERGE_INTENT` | `X-Inertia-Infinite-Scroll-Merge-Intent` | `append` or `prepend` |
| `EXCEPT_ONCE_PROPS` | `X-Inertia-Except-Once-Props` | The once props the client has |
| `PURPOSE` | `Purpose` | `prefetch` on a prefetch request |
| `PRECOGNITION` | `Precognition` | `true` on a request that only validates, and on its answer |
| `PRECOGNITION_VALIDATE_ONLY` | `Precognition-Validate-Only` | The fields a Precognition request validates |
| `PRECOGNITION_SUCCESS` | `Precognition-Success` | `true` on the `204` of a valid Precognition request |

Comma-separated values may have spaces around the commas.
