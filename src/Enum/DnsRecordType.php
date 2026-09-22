<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Enum;

/**
 * Record types supported by Vultr's managed DNS.
 */
enum DnsRecordType: string
{
    case A = 'A';
    case AAAA = 'AAAA';
    case CNAME = 'CNAME';
    case NS = 'NS';
    case MX = 'MX';
    case SRV = 'SRV';
    case TXT = 'TXT';
    case CAA = 'CAA';
    case SSHFP = 'SSHFP';
}
