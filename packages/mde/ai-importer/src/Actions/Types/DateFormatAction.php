<?php

declare(strict_types=1);

namespace Mde\AiImporter\Actions\Types;

use Mde\AiImporter\Actions\Action;
use Mde\AiImporter\Actions\ExecutionContext;

final class DateFormatAction extends Action
{
    public function __construct(
        public readonly string $from = 'Y-m-d',
        public readonly string $to = 'd/m/Y',
    ) {}

    public static function type(): string
    {
        return 'date_format';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $s = (string) $value;
        if ($s === '') {
            return $s;
        }
        $dt = \DateTime::createFromFormat($this->from, $s);

        return $dt ? $dt->format($this->to) : $s;
    }
}
