<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use BoudhraaDhia7\Vultr\Exception\TransportException;
use BoudhraaDhia7\Vultr\Http\Transport;
use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Shared plumbing for the resource classes.
 *
 * Subclasses expose one method per API operation and never build URLs or
 * decode payloads themselves beyond what these helpers provide.
 *
 * The helpers are prefixed with "http" so that subclasses stay free to name
 * their public API after the operation (get, list, create, update, delete).
 */
abstract class AbstractResource
{
    /**
     * Largest page size the Vultr API accepts.
     */
    protected const MAX_PER_PAGE = 500;

    public function __construct(protected readonly Transport $transport)
    {
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    protected function httpGet(string $path, array $query = []): array
    {
        return $this->transport->request('GET', $path, $query);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    protected function httpPost(string $path, array $body = []): array
    {
        return $this->transport->request('POST', $path, [], $body);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    protected function httpPatch(string $path, array $body): array
    {
        return $this->transport->request('PATCH', $path, [], $body);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    protected function httpPut(string $path, array $body): array
    {
        return $this->transport->request('PUT', $path, [], $body);
    }

    /**
     * Vultr answers deletions with 204 and no body, so nothing is returned.
     *
     * @param array<string, mixed> $body
     */
    protected function httpDelete(string $path, array $body = []): void
    {
        $this->transport->send('DELETE', $path, [], [] === $body ? null : $body);
    }

    /**
     * Fire an action endpoint that answers 204 with no body.
     *
     * @param array<string, mixed> $body
     */
    protected function httpAction(string $path, array $body = []): void
    {
        $this->transport->send('POST', $path, [], [] === $body ? null : $body);
    }

    /**
     * Pull a single record out of a keyed response envelope.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    protected function unwrap(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (!is_array($value)) {
            throw new TransportException(sprintf(
                'Expected key "%s" in the Vultr response but it was missing or not an object.',
                $key,
            ));
        }

        return $value;
    }

    /**
     * Pull a list out of a keyed response envelope.
     *
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    protected function unwrapList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * Fetch one page of a collection.
     *
     * @param array<string, mixed> $query supports per_page and cursor
     */
    protected function page(string $path, string $collectionKey, array $query = []): Page
    {
        return Page::fromPayload($this->httpGet($path, $query), $collectionKey);
    }

    /**
     * Walk every page of a collection, yielding one record at a time.
     *
     * Requests are made lazily, so breaking out of the loop early stops the
     * pagination instead of downloading the remaining pages.
     *
     * @param array<string, mixed> $query
     *
     * @return Generator<int, array<string, mixed>>
     */
    protected function paginate(string $path, string $collectionKey, array $query = []): Generator
    {
        $query['per_page'] ??= self::MAX_PER_PAGE;
        $cursor = null;
        $index = 0;

        do {
            if (null !== $cursor) {
                $query['cursor'] = $cursor;
            }

            $page = $this->page($path, $collectionKey, $query);

            foreach ($page as $item) {
                yield $index++ => $item;
            }

            $cursor = $page->nextCursor();
            // A page that comes back empty while still advertising a cursor
            // would otherwise loop forever.
        } while (null !== $cursor && !$page->isEmpty());
    }

    /**
     * Drop null entries so optional parameters are omitted rather than sent as
     * JSON nulls, which several endpoints reject.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function filterNulls(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => null !== $value);
    }

    /**
     * Guard and URL-encode a path segment that carries a resource id.
     */
    protected function segment(string $id, string $label = 'id'): string
    {
        $id = trim($id);

        if ('' === $id) {
            throw new ConfigurationException(sprintf(
                'A non-empty %s is required for this operation.',
                $label,
            ));
        }

        return rawurlencode($id);
    }
}
