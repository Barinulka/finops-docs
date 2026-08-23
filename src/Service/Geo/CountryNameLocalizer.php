<?php

namespace App\Service\Geo;

use Symfony\Component\Intl\Countries;

final readonly class CountryNameLocalizer
{
    /**
     * Исключения и варианты написания, которые встречаются в документах
     * или должны совпадать с выпадающим списком Google Sheets.
     *
     * @var array<string, string>
     */
    private const array OVERRIDES = [
        'czech republic' => 'Чехия',
        'hong kong' => 'Гонконг',
        'republika serbia' => 'Сербия',
        'serbia' => 'Сербия',
        'south africa' => 'ЮАР',
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
        $key = $this->normalize($country);

        if (isset(self::OVERRIDES[$key])) {
            return self::OVERRIDES[$key];
        }

        $countryCode = $this->findCountryCodeByEnglishName($key);

        if ($countryCode === null) {
            return $country;
        }

        return Countries::getName($countryCode, 'ru');
    }

    private function findCountryCodeByEnglishName(string $normalizedCountry): ?string
    {
        foreach (Countries::getNames('en') as $code => $englishName) {
            if ($this->normalize($englishName) === $normalizedCountry) {
                return $code;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
