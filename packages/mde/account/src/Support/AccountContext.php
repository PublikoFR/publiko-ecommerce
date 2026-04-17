<?php

declare(strict_types=1);

namespace Mde\Account\Support;

use App\Models\User;
use Lunar\Models\Customer;

final class AccountContext
{
    public static function user(): ?User
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user;
    }

    public static function customer(): ?Customer
    {
        $user = self::user();
        if ($user === null) {
            return null;
        }

        /** @var Customer|null $customer */
        $customer = $user->customers()->first();

        return $customer;
    }
}
