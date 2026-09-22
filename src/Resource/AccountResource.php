<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Resource;

/**
 * Account information and billing balance.
 *
 * @see https://www.vultr.com/api/#tag/account
 */
final class AccountResource extends AbstractResource
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->unwrap($this->httpGet('v2/account'), 'account');
    }

    /**
     * Credit left after the charges accrued so far this cycle are settled.
     *
     * Vultr reports `balance` as a negative number when money is owed, so the
     * magnitude is used and the pending charges subtracted from it.
     */
    public function remainingCredit(): float
    {
        $account = $this->get();

        $balance = abs((float) ($account['balance'] ?? 0));
        $pending = (float) ($account['pending_charges'] ?? 0);

        return round($balance - $pending, 2);
    }

    /**
     * Bandwidth usage for the account's current billing period.
     *
     * @return array<string, mixed>
     */
    public function bandwidth(): array
    {
        return $this->httpGet('v2/account/bandwidth');
    }
}
