<?php declare(strict_types=1);
/*
 * This file is part of Aplus Framework HTTP Client Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\HTTP\Client;

use CurlHandle;
use Generator;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use RuntimeException;

/**
 * Class Client.
 *
 * @see https://www.php.net/manual/en/function.curl-setopt.php
 * @see https://curl.se/libcurl/c/curl_easy_setopt.html
 * @see https://php.watch/articles/php-curl-security-hardening
 *
 * @package http-client
 */
class Client
{
    /**
     * @var array<mixed>
     */
    protected array $parsed = [];
    /**
     * Response cURL info.
     *
     * @var array<mixed>
     */
    protected array $info = [];

    /**
     * Get cURL info based in a Request id.
     *
     * @param int|string $id
     *
     * @return array<string,mixed>
     */
    #[Pure]
    public function getInfo(int | string $id = '_') : array
    {
        return $this->info[$id];
    }

    /**
     * @param int|string $id
     * @param array<string,mixed> $data
     *
     * @return static
     */
    protected function setInfo(int | string $id, array $data) : static
    {
        $this->info[$id] = $data;
        \ksort($this->info[$id]);
        return $this;
    }

    /**
     * Reset to default values.
     */
    public function reset() : void
    {
        $this->info = [];
        $this->parsed = [];
    }

    /**
     * Run the Request.
     *
     * @param Request $request
     *
     * @throws InvalidArgumentException for invalid Request Protocol
     * @throws RuntimeException for cURL error
     *
     * @return Response
     */
    public function run(Request $request) : Response
    {
        $handle = \curl_init();
        $options = $request->getOptions();
        $options[\CURLOPT_HEADERFUNCTION] = [$this, 'parseHeaderLine'];
        \curl_setopt_array($handle, $options);
        $body = \curl_exec($handle);
        $this->setInfo('_', \curl_getinfo($handle));
        if ($body === false) {
            throw new RuntimeException(\curl_error($handle), \curl_errno($handle));
        }
        \curl_close($handle);
        if ($body === true) {
            $body = '';
        }
        $objectId = \spl_object_id($handle);
        return new Response(
            $this->parsed[$objectId]['protocol'],
            $this->parsed[$objectId]['code'],
            $this->parsed[$objectId]['reason'],
            $this->parsed[$objectId]['headers'],
            $body
        );
    }

    /**
     * Parses Header line.
     *
     * @param CurlHandle $curlHandle
     * @param string $line
     *
     * @return int
     */
    protected function parseHeaderLine(CurlHandle $curlHandle, string $line) : int
    {
        $id = \spl_object_id($curlHandle);
        $trimmedLine = \trim($line);
        if ($trimmedLine === '') {
            return \strlen($line);
        }
        if ( ! \str_contains($trimmedLine, ':')) {
            if (\str_starts_with($trimmedLine, 'HTTP/')) {
                $parts = \explode(' ', $trimmedLine, 3);
                $this->parsed[$id]['protocol'] = $parts[0];
                $this->parsed[$id]['code'] = (int) ($parts[1] ?? 200);
                $this->parsed[$id]['reason'] = $parts[2] ?? 'OK';
            }
            return \strlen($line);
        }
        [$name, $value] = \explode(':', $trimmedLine, 2);
        $name = \trim($name);
        $value = \trim($value);
        if ($name !== '' && $value !== '') {
            $this->parsed[$id]['headers'][\strtolower($name)][] = $value;
        }
        return \strlen($line);
    }
}
