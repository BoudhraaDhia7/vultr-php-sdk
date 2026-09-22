<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Startup scripts run on first boot (type "boot") or at every boot ("pxe").
 *
 * @see https://www.vultr.com/api/#tag/startup
 */
final class StartupScriptResource extends AbstractResource
{
    public function list(?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/startup-scripts', 'startup_scripts', $this->filterNulls([
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(): Generator
    {
        yield from $this->paginate('v2/startup-scripts', 'startup_scripts');
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $scriptId): array
    {
        return $this->unwrap($this->httpGet($this->path($scriptId)), 'startup_script');
    }

    /**
     * @param string $script plain script contents; the SDK base64-encodes it
     * @param string $type   "boot" or "pxe"
     *
     * @return array<string, mixed>
     */
    public function create(string $name, string $script, string $type = 'boot'): array
    {
        return $this->unwrap($this->httpPost('v2/startup-scripts', [
            'name' => $name,
            'type' => $type,
            'script' => base64_encode($script),
        ]), 'startup_script');
    }

    /**
     * @param string|null $script plain script contents; the SDK base64-encodes it
     */
    public function update(
        string $scriptId,
        ?string $name = null,
        ?string $script = null,
        ?string $type = null,
    ): void {
        $this->httpPatch($this->path($scriptId), $this->filterNulls([
            'name' => $name,
            'type' => $type,
            'script' => null === $script ? null : base64_encode($script),
        ]));
    }

    public function delete(string $scriptId): void
    {
        $this->httpDelete($this->path($scriptId));
    }

    private function path(string $scriptId): string
    {
        return 'v2/startup-scripts/'.$this->segment($scriptId, 'startup script id');
    }
}
