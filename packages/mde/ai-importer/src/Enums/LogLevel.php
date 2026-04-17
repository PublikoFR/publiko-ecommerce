<?php

declare(strict_types=1);

namespace Mde\AiImporter\Enums;

enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
}
