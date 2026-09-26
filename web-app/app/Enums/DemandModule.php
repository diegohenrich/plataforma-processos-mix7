<?php

namespace App\Enums;

enum DemandModule: string
{
    case SocialCreative = 'social_creative';
    case WebsiteReview = 'website_review';

    public function label(): string
    {
        return match ($this) {
            self::SocialCreative => 'Criativo para redes sociais',
            self::WebsiteReview => 'Revisão de site',
        };
    }

    public function version(): int
    {
        return 1;
    }
}
