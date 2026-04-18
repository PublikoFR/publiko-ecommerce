<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Sirene;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}
