<?php

declare(strict_types=1);

namespace Solo\Inertia;

/**
 * Where an infinite scroll prop is in its list: the client's <InfiniteScroll> asks for
 * the previous or next page with the query parameter $pageName.
 */
final readonly class ScrollMetadata
{
    public function __construct(
        public string $pageName = 'page',
        public int|string|null $previousPage = null,
        public int|string|null $nextPage = null,
        public int|string|null $currentPage = null,
    ) {
    }

    /**
     * The cursor of a numbered page: no previous page on the first one, no next page
     * on the last one. With a total, $hasMorePages is `$page * $perPage < $total`.
     */
    public static function forPage(int $page, bool $hasMorePages, string $pageName = 'page'): self
    {
        return new self($pageName, $page > 1 ? $page - 1 : null, $hasMorePages ? $page + 1 : null, $page);
    }

    /**
     * @return array{pageName: string, previousPage: int|string|null, nextPage: int|string|null,
     *               currentPage: int|string|null}
     */
    public function toArray(): array
    {
        return [
            'pageName' => $this->pageName,
            'previousPage' => $this->previousPage,
            'nextPage' => $this->nextPage,
            'currentPage' => $this->currentPage,
        ];
    }
}
