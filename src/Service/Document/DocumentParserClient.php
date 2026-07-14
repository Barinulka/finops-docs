<?php

namespace App\Service\Document;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class DocumentParserClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(PARSER_BASE_URI)%')]
        private string $parserBaseUri,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function parsePdf(string $pdfContent, string $filename): array
    {
        if ($pdfContent === '') {
            throw new \InvalidArgumentException('PDF content must not be empty.');
        }

        $formData = new FormDataPart([
            'file' => new DataPart($pdfContent, $filename, 'application/pdf'),
        ]);

        try {
            $response = $this->httpClient->request('POST', $this->endpoint('/parse'), [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToIterable(),
                'timeout' => 60,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(sprintf(
                    'Parser API returned HTTP %d: %s',
                    $statusCode,
                    $response->getContent(false),
                ));
            }

            $result = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new \RuntimeException(sprintf(
                'Parser API is unavailable: %s',
                $exception->getMessage(),
            ), previous: $exception);
        } catch (DecodingExceptionInterface $exception) {
            throw new \RuntimeException(sprintf(
                'Parser API returned invalid JSON: %s',
                $exception->getMessage(),
            ), previous: $exception);
        }

        $this->validateResult($result);

        return $result;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->parserBaseUri, '/') . $path;
    }

    /**
     * @param array<mixed> $result
     */
    private function validateResult(array $result): void
    {
        foreach (['parserVersion', 'documentType', 'confidence', 'rawText', 'fields', 'warnings'] as $key) {
            if (!array_key_exists($key, $result)) {
                throw new \RuntimeException(sprintf('Parser API response does not contain "%s".', $key));
            }
        }

        if (!is_array($result['fields'])) {
            throw new \RuntimeException('Parser API response field "fields" must be an object.');
        }

        if (!is_array($result['warnings'])) {
            throw new \RuntimeException('Parser API response field "warnings" must be an array.');
        }
    }
}