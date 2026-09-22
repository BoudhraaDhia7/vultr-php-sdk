<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Enum;

/**
 * Cadences accepted by the instance backup-schedule endpoint.
 */
enum BackupScheduleType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case DailyAlternateEven = 'daily_alt_even';
    case DailyAlternateOdd = 'daily_alt_odd';

    /**
     * True when the schedule needs a day-of-week value.
     */
    public function requiresDayOfWeek(): bool
    {
        return self::Weekly === $this;
    }

    /**
     * True when the schedule needs a day-of-month value.
     */
    public function requiresDayOfMonth(): bool
    {
        return self::Monthly === $this;
    }
}
