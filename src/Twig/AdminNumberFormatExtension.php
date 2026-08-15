<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AdminNumberFormatExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('admin_number', [$this, 'formatNumber']),
            new TwigFilter('admin_numbers', [$this, 'formatNumbersInText']),
            new TwigFilter('admin_json_numbers', [$this, 'formatNumbersForJson']),
        ];
    }

    public function formatNumber(mixed $value): string
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            return (string) $value;
        }

        $raw = trim((string) $value);
        $normalized = str_replace([" ", "\u{00A0}"], '', $raw);
        $normalized = str_replace(',', '.', $normalized);

        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            return $raw;
        }

        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');

        [$integer, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $integer);
        $fraction = rtrim($fraction, '0');

        return ($negative ? '-' : '') . $integer . ($fraction !== '' ? '.' . $fraction : '');
    }

    public function formatNumbersInText(mixed $value): string
    {
        $text = (string) $value;

        return preg_replace_callback(
            '/(?<![\p{L}\p{N}_.,-])-?\d+(?:[.,]\d+)?(?![\p{L}\p{N}_.,-])/u',
            fn (array $matches): string => $this->formatNumber($matches[0]),
            $text,
        ) ?? $text;
    }

    public function formatNumbersForJson(mixed $value): mixed
    {
        if (is_array($value)) {
            $formatted = [];

            foreach ($value as $key => $item) {
                $formatted[$key] = $this->formatNumbersForJson($item);
            }

            return $formatted;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return $this->formatNumber($value);
        }

        return $value;
    }
}
