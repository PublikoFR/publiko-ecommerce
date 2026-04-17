<?php

declare(strict_types=1);

namespace Mde\CustomerAuth\Sirene;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}
