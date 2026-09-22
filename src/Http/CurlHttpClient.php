<?php

declare(strict_types=1);

namespace BoudhraaDhia7\Vultr\Http;

use BoudhraaDhia7\Vultr\Exception\TransportException;

/**
 * The default transport: a thin, hardened wrapper around ext-curl.
 *
 * Certificate verification is always on and cannot be switched off. If a local
 * machine cannot verify Vultr's certificate, point cURL at a valid CA bundle
 * with the $caBundle argument instead of disabling verification.
 */
final class CurlHttpClient implements HttpClientInterface
{
    /** @var non-empty-string|null */
    private readonly ?string $caBundle;

    /**
     * @param float       $connectTimeout seconds to wait for the connection to be established
     * @param float       $timeout        seconds to wait for the whole transfer
     * @param string|null $caBundle       absolute path to a CA bundle file or directory
     */
    public function __construct(
        private readonly float $connectTimeout = 10.0,
        private readonly float $timeout = 30.0,
        ?string $caBundle = null,
    ) {
        if (!extension_loaded('curl')) {
            throw new TransportException('The curl extension is required by CurlHttpClient.');
        }

        if (null !== $caBundle && '' === $caBundle) {
            throw new TransportException('The CA bundle path cannot be an empty string.');
        }

        $this->caBundle = $caBundle;
    }

    public function send(Request $request): Response
    {
        $uri = $request->uri();
        $method = strtoupper($request->method());

        if ('' === $uri || '' === $method) {
            throw new TransportException('A request needs both a URI and an HTTP method.');
        }

        $handle = curl_init();

        if (false === $handle) {
            throw new TransportException('Unable to initialise a cURL handle.');
        }

        $responseHeaders = [];

        $options = [
            CURLOPT_URL => $uri,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $request->headerLines(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($this->connectTimeout * 1000),
            CURLOPT_TIMEOUT_MS => (int) round($this->timeout * 1000),
            CURLOPT_ACCEPT_ENCODING => 'gzip, deflate',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HEADERFUNCTION => static function ($_, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);

                if (2 === count($parts)) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ];

        if (null !== $this->caBundle) {
            $options[is_dir($this->caBundle) ? CURLOPT_CAPATH : CURLOPT_CAINFO] = $this->caBundle;
        }

        $body = $request->body();

        if (null !== $body) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options);

        $raw = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $errorMessage = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if (0 !== $errorNumber || !is_string($raw)) {
            throw new TransportException(sprintf(
                '%s %s failed at the transport level: %s (cURL error %d).',
                $method,
                $uri,
                '' !== $errorMessage ? $errorMessage : 'unknown error',
                $errorNumber,
            ), $errorNumber);
        }

        if (0 === $statusCode) {
            throw new TransportException(sprintf(
                '%s %s produced no HTTP status code.',
                $method,
                $uri,
            ));
        }

        return new Response($statusCode, $responseHeaders, $raw);
    }
}
