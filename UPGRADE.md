# Upgrading from 1.x to 2.0

Version 2.0 is a rewrite. Nothing from 1.x is source-compatible, so this is a
port rather than a version bump. The work is mechanical, and most call sites
become shorter.

Pin to `^1.0` if you are not ready; 1.0.1 remains installable.

## 1. Update the requirement

```bash
composer require boudhraadhia7/vultr-php-sdk:^2.0
```

PHP 8.2 or newer is required.

## 2. Change the namespace

```diff
-use BoudhraaDhia7\VultrLaravelSymfony\VultrAPI;
+use BoudhraaDhia7\Vultr\VultrClient;
```

## 3. Construct the client

```diff
-$vultr = new VultrAPI($apiKey, 'https://api.vultr.com/', true, false);
+$vultr = VultrClient::create($apiKey);
```

The third and fourth constructor arguments are gone. TLS verification is always
on, and debug mode is replaced by exceptions that always carry full context.

If you were passing `false` to disable TLS verification, point the client at a
CA bundle instead:

```php
use BoudhraaDhia7\Vultr\Config;

$vultr = new VultrClient(
    Config::create($apiKey)->withCaBundle('/etc/ssl/certs/ca-certificates.crt')
);
```

## 4. Drop `setSubId()`

Instance ids are now arguments. This also fixes the 1.x bug where calling any
instance method made every later call on that object require an id.

```diff
-$vultr->setSubId($instanceId);
-$vultr->instanceReboot();
-$vultr->instanceGetBW();
+$vultr->instances()->reboot($instanceId);
+$vultr->instances()->bandwidth($instanceId);
```

## 5. Replace error checks with catch blocks

```diff
-$result = $vultr->listServers();
-
-if (isset($result['ok']) && false === $result['ok']) {
-    error_log('Vultr call failed: ' . ($result['error'] ?? 'unknown'));
-    return;
-}
+use BoudhraaDhia7\Vultr\Exception\VultrExceptionInterface;
+
+try {
+    $instances = $vultr->instances()->list();
+} catch (VultrExceptionInterface $e) {
+    error_log('Vultr call failed: ' . $e->getMessage());
+    return;
+}
```

Catch the specific subclasses when you want to react differently to a 404, a
429 or a transport failure. See the README for the hierarchy.

## 6. Replace the server-create sequence with the builder

```diff
-$vultr->serverCreateDC('ewr');
-$vultr->serverCreatePlan('vc2-1c-1gb');
-$vultr->serverCreateType('OS', '1743');
-$vultr->serverCreateLabel('web-01');
-$vultr->serverCreateHostname('web-01');
-$vultr->serverCreateEnableIpv6(true);
-$response = $vultr->serverCreate();
+use BoudhraaDhia7\Vultr\Builder\InstanceBuilder;
+
+$instance = $vultr->instances()->create(
+    InstanceBuilder::in('ewr')
+        ->plan('vc2-1c-1gb')
+        ->fromOperatingSystem(1743)
+        ->label('web-01')
+        ->hostname('web-01')
+        ->enableIpv6()
+);
```

Note that `serverCreateHostname()` and `serverCreateEnableIpv6()` never worked
in 1.x: they wrote their keys with a trailing space, so Vultr ignored them. If
your instances were coming up without the hostname or IPv6 you asked for, that
was why, and 2.0 will now apply both.

`serverCreateOptions()` is gone. It echoed HTML from inside the library; the
builder's method names and this README serve that purpose.

## 7. Handle decoded return values

1.x returned an array from `doCall()`, while its own helpers and README treated
the result as a JSON string. 2.0 always returns decoded PHP arrays.

```diff
-$instances = json_decode($vultr->listServers(), true);
-foreach ($instances['instances'] as $instance) {
+foreach ($vultr->instances()->all() as $instance) {
```

`all()` also pages for you. 1.x returned only the first page, silently, which
means any account with more than the default page size was being under-reported.

## 8. Method mapping

### Account

| 1.x | 2.0 |
| --- | --- |
| `listAccountInfo()` | `account()->get()` |
| `accountRemainingCredit()` | `account()->remainingCredit()` |

### Instances

| 1.x | 2.0 |
| --- | --- |
| `listServers()` | `instances()->list()` or `instances()->all()` |
| `listServer()` | `instances()->get($id)` |
| `serverCreate()` | `instances()->create($builder)` |
| `instanceUpdate($values)` | `instances()->update($id, $values)` |
| `instanceDestroy()` | `instances()->delete($id)` |
| `instanceStart()` | `instances()->start($id)` |
| `serverStop()` | `instances()->halt($id)` |
| `instanceReboot()` | `instances()->reboot($id)` |
| `instanceReinstall($hostname)` | `instances()->reinstall($id, $hostname)` |
| `instanceSetLabel($label)` | `instances()->setLabel($id, $label)` |
| `instanceSetTag($tag)` | `instances()->setTags($id, [$tag])` |
| `instanceUpgradePlan($plan)` | `instances()->resize($id, $plan)` |
| `instanceOSChange($osId)` | `instances()->changeOperatingSystem($id, $osId)` |
| `instanceChangeApp($appId)` | `instances()->changeApplication($id, $appId)` |
| `instanceOSChangeList()` | `instances()->availableUpgrades($id, 'os')` |
| `instanceGetBW()` | `instances()->bandwidth($id)` |
| `listNeighbors()` | `instances()->neighbors($id)` |
| `instanceUserData()` | `instances()->userData($id)` |
| `instanceFirewallGroup($groupId)` | `instances()->setFirewallGroup($id, $groupId)` |
| `instanceRestoreBackup($backupId)` | `instances()->restoreFromBackup($id, $backupId)` |
| `instanceRestoreSnapshot($snapId)` | `instances()->restoreFromSnapshot($id, $snapId)` |

### Addresses

| 1.x | 2.0 |
| --- | --- |
| `listIpv4()` / `listIpv6()` | `instances()->listIpv4($id)` / `listIpv6($id)` |
| `instanceCreateIpv4($reboot)` | `instances()->createIpv4($id, $reboot)` |
| `instanceDestroyIpv4($ip)` | `instances()->deleteIpv4($id, $ip)` |
| `instanceSetReverseIpv4($ip, $rev)` | `instances()->setReverseIpv4($id, $ip, $rev)` |
| `instanceSetReverseIpv6($ip, $rev)` | `instances()->setReverseIpv6($id, $ip, $rev)` |
| `instanceListReverseIpv4()` | `instances()->listReverseIpv4($id)` |
| `instanceListReverseIpv6()` | `instances()->listReverseIpv6($id)` |
| `instanceDeleteReverseIpv4($ip)` | `instances()->resetReverseIpv4($id, $ip)` |
| `instanceDeleteReverseIpv6($ip)` | `instances()->deleteReverseIpv6($id, $ip)` |

### Backups, snapshots and ISOs

| 1.x | 2.0 |
| --- | --- |
| `instanceBackupEnable()` | `instances()->enableBackups($id)` |
| `instanceBackupDisable()` | `instances()->disableBackups($id)` |
| `instanceBackupSchedule()` | `instances()->backupSchedule($id)` |
| `instanceSetBackupSchedule($t, $h, $dow, $dom)` | `instances()->setBackupSchedule($id, BackupScheduleType::Daily, hour: $h)` |
| `listBackups()` / `getBackupData($id)` | `backups()->list()` / `backups()->get($id)` |
| `listSnapshots()` / `getSnapshotData($id)` | `snapshots()->list()` / `snapshots()->get($id)` |
| `createSnapshot($desc)` | `snapshots()->create($instanceId, $desc)` |
| `createSnapshotFromURL($url)` | `snapshots()->createFromUrl($url)` |
| `updateSnapshot($id, $desc)` | `snapshots()->updateDescription($id, $desc)` |
| `deleteSnapshot($id)` | `snapshots()->delete($id)` |
| `listISOs()` / `listPublicISOs()` | `isos()->list()` / `isos()->listPublic()` |
| `uploadISO($url)` | `isos()->createFromUrl($url)` |
| `destroyISO($id)` | `isos()->delete($id)` |
| `instanceAttachISO($isoId)` | `instances()->attachIso($id, $isoId)` |
| `instanceDetachISO()` | `instances()->detachIso($id)` |
| `instanceISOInfo()` | `instances()->isoStatus($id)` |

### Networking

`instancePrivateNetworkAttach()`, `instancePrivateNetworkDetach()`,
`instancePrivateNetworkEnable()`, `instancePrivateNetworkDisable()` and
`instanceListPrivateNetworks()` targeted Vultr's private networks, which have
been superseded by VPCs:

| 1.x | 2.0 |
| --- | --- |
| `instancePrivateNetworkAttach($netId)` | `instances()->attachVpc($id, $vpcId)` |
| `instancePrivateNetworkDetach($netId)` | `instances()->detachVpc($id, $vpcId)` |
| `instanceListPrivateNetworks()` | `instances()->listVpcs($id)` |

### DNS

| 1.x | 2.0 |
| --- | --- |
| `listDNS()` / `getDNSData($d)` | `domains()->list()` / `domains()->get($d)` |
| `dnsCreateDomain($d, $ip, $sec)` | `domains()->create($d, $ip, $sec)` |
| `dnsDeleteDomain($d)` | `domains()->delete($d)` |
| `dnsListRecordsDomain($d)` | `domains()->listRecords($d)` / `allRecords($d)` |
| `dnsCreateRecord($d, $n, $t, $data)` | `domains()->createRecord($d, $n, DnsRecordType::A, $data)` |
| `dnsUpdateRecord($d, $id, $n, $data)` | `domains()->updateRecord($d, $id, $n, $data)` |
| `dnsDeleteRecord($d, $id)` | `domains()->deleteRecord($d, $id)` |
| `dnsSOAINFO($d)` | `domains()->soa($d)` |
| `dnsUpdateSOA($d, $ns, $email)` | `domains()->updateSoa($d, $ns, $email)` |
| `dnsEnableDNSSEC($d, 'enable')` | `domains()->setDnssec($d, true)` |
| `dnsDNSSECInfo($d)` | `domains()->dnssecInfo($d)` |

### Everything else

| 1.x | 2.0 |
| --- | --- |
| `listPlans($type)` | `plans()->list($type)` |
| `listBareMetalPlans()` | `plans()->listBareMetal()` |
| `listRegions()` | `regions()->list()` |
| `regionAvailability($id, $type)` | `regions()->availablePlans($id, $type)` |
| `listOS()` / `osName($id)` | `operatingSystems()->list()` / `name($id)` |
| `listApps()` | `applications()->list()` |
| `listSSHKeys()` / `getSSHKeyData($id)` | `sshKeys()->list()` / `sshKeys()->get($id)` |
| `createSSHKey($n, $k)` | `sshKeys()->create($n, $k)` |
| `updateSSHKey($id, $n, $k)` | `sshKeys()->update($id, $n, $k)` |
| `destroySSHKey($id)` | `sshKeys()->delete($id)` |
| `listStartupScripts()` | `startupScripts()->list()` |
| `createStartupScript($n, $t, $s)` | `startupScripts()->create($n, $s, $t)` |
| `updateStartupScript($id, $n, $t, $s)` | `startupScripts()->update($id, $n, $s, $t)` |
| `destroyStartupScript($id)` | `startupScripts()->delete($id)` |
| `listReservedIps()` | `reservedIps()->list()` |
| `createIp($type, $region, $label)` | `reservedIps()->create($region, IpType::V4, $label)` |
| `attachIp($ip)` | `reservedIps()->attach($ipId, $instanceId)` |
| `detachIp($ip)` | `reservedIps()->detach($ipId)` |
| `convertIp($ip, $label)` | `reservedIps()->convert($ip, $label)` |
| `destroyIp($ip)` | `reservedIps()->delete($ipId)` |
| `listBlockStorage()` | `blockStorage()->list()` |
| `createBlockStorage($r, $gb, $l)` | `blockStorage()->create($r, $gb, $l)` |
| `attachBlockStorage($id, $live)` | `blockStorage()->attach($id, $instanceId, $live)` |
| `detachBlockStorage($id, $live)` | `blockStorage()->detach($id, $live)` |
| `resizeBlockStorage($id, $gb)` | `blockStorage()->update($id, sizeGb: $gb)` |
| `labelBlockStorage($id, $l)` | `blockStorage()->update($id, label: $l)` |
| `deleteBlockStorage($id)` | `blockStorage()->delete($id)` |
| `listObjectStorage()` | `objectStorage()->list()` |
| `listObjectStorageCluster()` | `objectStorage()->listClusters()` |
| `createObjectStorage($c, $l)` | `objectStorage()->create($c, $l)` |
| `labelObjectStorage($l, $id)` | `objectStorage()->setLabel($id, $l)` |
| `s3keyRegenObjectStorage($id)` | `objectStorage()->regenerateKeys($id)` |
| `deleteObjectStorage($id)` | `objectStorage()->delete($id)` |
| `getUsers()` / `listUser($id)` | `users()->list()` / `users()->get($id)` |
| `createUser(...)` | `users()->create(...)` |
| `updateUser(...)` | `users()->update(...)` |
| `deleteUser($id)` | `users()->delete($id)` |
| `convertBytes($b, 'GB')` | `Bytes::toGigabytes($b)` |
| `boolToInt($b)` | removed |
| `doCall($path, $method, ...)` | `request($method, $path, $query, $body)` |

## 9. Endpoints with no 1.x equivalent

Firewall groups and rules (`firewalls()`), account bandwidth
(`account()->bandwidth()`) and VPC management are new in 2.0.
