<?php

namespace App\Service\Currency;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CbrCurrencyRateProvider
{
    private const CBR_DAILY_RATES_URL = 'https://www.cbr.ru/scripts/XML_daily.asp';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Возвращает официальный курс ЦБ РФ за 1 единицу валюты на дату.
     *
     * В XML ЦБ у некоторых валют Nominal может быть 10, 100 и т.д.
     * Поэтому курс за одну единицу считаем как Value / Nominal.
     *
     * @return string decimal-строка с 8 знаками после точки, например "87.66610000"
     */
    public function getRateForDate(string $currency, \DateTimeInterface $date): string
    {
        $currency = strtoupper($currency);

        if ($currency === 'RUB') {
            return '1.00000000';
        }

        $response = $this->httpClient->request('GET', self::CBR_DAILY_RATES_URL, [
            'query' => [
                'date_req' => $date->format('d/m/Y'),
            ],
        ]);

        $content = $response->getContent();

        $xml = simplexml_load_string($content);

        if (!$xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException('Не удалось прочитать ответ ЦБ РФ с курсами валют.');
        }

        foreach ($xml->Valute as $valute) {
            $charCode = strtoupper((string) $valute->CharCode);

            if ($charCode !== $currency) {
                continue;
            }

            $nominal = $this->normalizeDecimal((string) $valute->Nominal, 0);
            $value = $this->normalizeDecimal((string) $valute->Value, 8);

            if ($nominal === '0' || $nominal === '0.00000000') {
                throw new \RuntimeException(sprintf('ЦБ РФ вернул нулевой nominal для валюты %s.', $currency));
            }

            return bcdiv($value, $nominal, 8);
        }

        throw new \RuntimeException(sprintf(
            'Курс валюты %s на дату %s не найден в ответе ЦБ РФ.',
            $currency,
            $date->format('d.m.Y'),
        ));
    }

    private function normalizeDecimal(string $value, int $scale): string
    {
        $normalized = trim($value);
        $normalized = str_replace(["\u{00A0}", ' '], '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        if (!is_numeric($normalized)) {
            throw new \RuntimeException(sprintf('Некорректное decimal-значение от ЦБ РФ: "%s".', $value));
        }

        return bcadd($normalized, '0', $scale);
    }
}
