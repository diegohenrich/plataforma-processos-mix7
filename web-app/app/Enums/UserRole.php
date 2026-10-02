<?php

namespace App\Enums;

enum UserRole: string
{
    case AgencyOwner = 'agency_owner';
    case MarketingManager = 'marketing_manager';
    case Professional = 'professional';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::AgencyOwner => 'Direção da agência',
            self::MarketingManager => 'Gerência de marketing',
            self::Professional => 'Profissional',
            self::Client => 'Cliente',
        };
    }
}
