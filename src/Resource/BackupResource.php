<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

use BoudhraaDhia7\Vultr\Pagination\Page;
use Generator;

/**
 * Automatic instance backups. Backups are read-only: Vultr creates them on the
 * schedule configured per instance.
 *
 * @see \BoudhraaDhia7\Vultr\Resource\InstanceResource::setBackupSchedule()
 * @see https://www.vultr.com/api/#tag/backup
 */
final class BackupResource extends AbstractResource
{
    /**
     * @param string|null $instanceId restrict to the backups of one instance
     */
    public function list(?string $instanceId = null, ?int $perPage = null, ?string $cursor = null): Page
    {
        return $this->page('v2/backups', 'backups', $this->filterNulls([
            'instance_id' => $instanceId,
            'per_page' => $perPage,
            'cursor' => $cursor,
        ]));
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function all(?string $instanceId = null): Generator
    {
        yield from $this->paginate('v2/backups', 'backups', $this->filterNulls([
            'instance_id' => $instanceId,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $backupId): array
    {
        return $this->unwrap(
            $this->httpGet('v2/backups/'.$this->segment($backupId, 'backup id')),
            'backup',
        );
    }
}
