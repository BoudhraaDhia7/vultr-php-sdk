<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Exception\ConfigurationException;
use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Sub-account users and their ACLs.
 *
 * @see https://www.vultr.com/api/#tag/users
 */
final class UserResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/users', 'users', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/users', 'users');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $userId): array
    {
        return $this->unwrap($this->httpGet($this->path($userId)), 'user');
    }

    /**
     * @param list<string> $acls permissions such as "manage_users", "subscriptions_view"
     *
     * @return array<string, mixed> the created user, including an API key when $apiEnabled is true
     */
    public function create(
        string $email,
        string $name,
        string $password,
        bool $apiEnabled = false,
        array $acls = ['subscriptions_view'],
    ): array {
        return $this->unwrap($this->httpPost('v2/users', [
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'api_enabled' => $apiEnabled,
            'acls' => array_values($acls),
        ]), 'user');
    }

    /**
     * Update only the fields that are given; the rest are left untouched.
     *
     * @param list<string>|null $acls
     */
    public function update(
        string $userId,
        ?string $email = null,
        ?string $name = null,
        ?string $password = null,
        ?bool $apiEnabled = null,
        ?array $acls = null,
    ): void {
        $changes = $this->filterNulls([
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'api_enabled' => $apiEnabled,
            'acls' => null === $acls ? null : array_values($acls),
        ]);

        if ([] === $changes) {
            throw new ConfigurationException('update() needs at least one field to change.');
        }

        $this->httpPatch($this->path($userId), $changes);
    }

    public function delete(string $userId): void
    {
        $this->httpDelete($this->path($userId));
    }

    private function path(string $userId): string
    {
        return 'v2/users/'.$this->segment($userId, 'user id');
    }
}
