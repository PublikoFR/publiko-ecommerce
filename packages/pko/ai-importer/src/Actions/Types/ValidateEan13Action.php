<?php

declare(strict_types=1);

namespace Pko\AiImporter\Actions\Types;

use Pko\AiImporter\Actions\Action;
use Pko\AiImporter\Actions\ExecutionContext;

final class ValidateEan13Action extends Action
{
    public static function type(): string
    {
        return 'validate_ean13';
    }

    public function execute(mixed $value, ExecutionContext $ctx): mixed
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (strlen($digits) !== 13) {
            return '';
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $digits[$i]) * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $digits[12] ? $digits : '';
    }
}
