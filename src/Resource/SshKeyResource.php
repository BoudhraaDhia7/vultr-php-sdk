<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * SSH public keys stored on the account.
 *
 * @see https://www.vultr.com/api/#tag/ssh
 */
final class SshKeyResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/ssh-keys', 'ssh_keys', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/ssh-keys', 'ssh_keys');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $sshKeyId): array
    {
        return $this->unwrap($this->httpGet($this->path($sshKeyId)), 'ssh_key');
    }

    /**
     * @param string $publicKey the contents of an id_*.pub file
     *
     * @return array<string, mixed>
     */
    public function create(string $name, string $publicKey): array
    {
        return $this->unwrap(
            $this->httpPost('v2/ssh-keys', ['name' => $name, 'ssh_key' => $publicKey]),
            'ssh_key',
        );
    }

    public function update(string $sshKeyId, ?string $name = null, ?string $publicKey = null): void
    {
        $this->httpPatch($this->path($sshKeyId), $this->filterNulls([
            'name' => $name,
            'ssh_key' => $publicKey,
        ]));
    }

    public function delete(string $sshKeyId): void
    {
        $this->httpDelete($this->path($sshKeyId));
    }

    private function path(string $sshKeyId): string
    {
        return 'v2/ssh-keys/'.$this->segment($sshKeyId, 'SSH key id');
    }
}
