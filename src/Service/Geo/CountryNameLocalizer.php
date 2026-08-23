<?php

namespace App\Service\Geo;

final readonly class CountryNameLocalizer
{
    /**
     * @var array<string, string>
     */
    private const RU_NAMES = [
        'bahrain' => 'Бахрейн',
        'bulgaria' => 'Болгария',
        'china' => 'Китай',
        'czech republic' => 'Чехия',
        'ecuador' => 'Эквадор',
        'egypt' => 'Египет',
        'germany' => 'Германия',
        'hong kong' => 'Гонконг',
        'israel' => 'Израиль',
        'republika serbia' => 'Сербия',
        'serbia' => 'Сербия',
        'south africa' => 'ЮАР',
        'switzerland' => 'Швейцария',
        'turkey' => 'Турция',
        'türkiye' => 'Турция',
        'viet nam' => 'Вьетнам',
        'vietnam' => 'Вьетнам',
    ];

    public function toRussian(mixed $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }

        $country = trim((string) $country);
        $key = mb_strtolower($country);

        return self::RU_NAMES[$key] ?? $country;
    }
}
