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
    protected bool $isGettingInfo = false;

    /**
     * Get cURL info based in a Request id.
     *
     * @param int|string $id
     *
     * @return array<string,mixed>|null
     */
    #[Pure]
    public function getInfo(int | string $id = '_') : ?array
    {
        return $this->info[$id] ?? null;
    }

    /**
     * @return static
     */
    public function enableGetInfo() : static
    {
        $this->isGettingInfo = true;
        return $this;
    }

    /**
     * @return static
     */
    public function disableGetInfo() : static
    {
        $this->isGettingInfo = false;
        return $this;
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
        if ($this->isGettingInfo) {
            $this->setInfo('_', \curl_getinfo($handle));
        }
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
     * Run multiple HTTP Requests.
     *
     * @param Request[] $requests An associative array of Request instances
     * with ids as keys
     *
     * @return Generator<Response> The Requests ids as keys and its respective
     * Responses as values
     */
    public function runMulti(array $requests) : Generator
    {
        $multiHandle = \curl_multi_init();
        $handles = [];
        foreach ($requests as $id => $request) {
            $handle = \curl_init();
            $options = $request->getOptions();
            $options[\CURLOPT_HEADERFUNCTION] = [$this, 'parseHeaderLine'];
            \curl_setopt_array($handle, $options);
            $handles[$id] = $handle;
            \curl_multi_add_handle($multiHandle, $handle);
        }
        do {
            $status = \curl_multi_exec($multiHandle, $stillRunning);
            $message = \curl_multi_info_read($multiHandle);
            if ($message) {
                foreach ($handles as $id => $handle) {
                    if ($message['handle'] === $handle) {
                        if ($this->isGettingInfo) {
                            $this->setInfo($id, \curl_getinfo($handle));
                        }
                        $objectId = \spl_object_id($handle);
                        if ( ! isset($this->parsed[$objectId])) {
                            unset($handles[$id]);
                            break;
                        }
                        yield $id => new Response(
                            $this->parsed[$objectId]['protocol'],
                            $this->parsed[$objectId]['code'],
                            $this->parsed[$objectId]['reason'],
                            $this->parsed[$objectId]['headers'],
                            (string) \curl_multi_getcontent($message['handle'])
                        );
                        unset($this->parsed[$objectId], $handles[$id]);
                        break;
                    }
                }
                \curl_multi_remove_handle($multiHandle, $message['handle']);
            }
        } while ($stillRunning && $status === \CURLM_OK);
        \curl_multi_close($multiHandle);
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
        $trimmedLine = \trim($line);
        $lineLength = \strlen($line);
        if ($trimmedLine === '') {
            return $lineLength;
        }
        $id = \spl_object_id($curlHandle);
        if ( ! \str_contains($trimmedLine, ':')) {
            if (\str_starts_with($trimmedLine, 'HTTP/')) {
                $parts = \explode(' ', $trimmedLine, 3);
                $this->parsed[$id]['protocol'] = $parts[0];
                $this->parsed[$id]['code'] = (int) ($parts[1] ?? 200);
                $this->parsed[$id]['reason'] = $parts[2]
                    ?? Response::getReasonByCode($this->parsed[$id]['code'], '');
            }
            return $lineLength;
        }
        [$name, $value] = \explode(':', $trimmedLine, 2);
        $name = \trim($name);
        $value = \trim($value);
        if ($name !== '' && $value !== '') {
            $this->parsed[$id]['headers'][\strtolower($name)][] = $value;
        }
        return $lineLength;
    }
}
