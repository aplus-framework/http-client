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

use Exception;
use Framework\HTTP\Cookie;
use Framework\HTTP\Header;
use Framework\HTTP\Message;
use Framework\HTTP\ResponseHeader;
use Framework\HTTP\ResponseInterface;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Override;

/**
 * Class Response.
 *
 * @package http-client
 */
class Response extends Message implements ResponseInterface
{
    protected Request $request;
    protected string $protocol;
    protected int $statusCode;
    protected string $statusReason;
    /**
     * Response curl info.
     *
     * @var array<mixed>
     */
    protected array $info = [];
    protected int $jsonFlags = 0;

    /**
     * Response constructor.
     *
     * @param Request $request
     * @param string $protocol
     * @param int $status
     * @param string $reason
     * @param array<string,array<int,string>> $headers
     * @param string $body
     * @param array<mixed> $info
     */
    public function __construct(
        Request $request,
        string $protocol,
        int $status,
        string $reason,
        array $headers,
        string $body,
        array $info = []
    ) {
        $this->request = $request;
        $this->setProtocol($protocol);
        $this->setStatusCode($status);
        $this->setStatusReason($reason);
        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $this->appendHeader($name, $value);
            }
        }
        $this->setBody($body);
        \ksort($info);
        $this->info = $info;
    }

    public function getRequest() : Request
    {
        return $this->request;
    }

    /**
     * @return array<mixed>
     */
    #[ArrayShape([
        'appconnect_time_us' => 'int',
        'certinfo' => 'array',
        'connect_time' => 'float',
        'connect_time_us' => 'int',
        'content_type' => 'string',
        'download_content_length' => 'float',
        'effective_method' => 'string',
        'filetime' => 'int',
        'header_size' => 'int',
        'http_code' => 'int',
        'http_version' => 'int',
        'local_ip' => 'string',
        'local_port' => 'int',
        'namelookup_time' => 'float',
        'namelookup_time_us' => 'int',
        'pretransfer_time' => 'float',
        'pretransfer_time_us' => 'int',
        'primary_ip' => 'string',
        'primary_port' => 'int',
        'protocol' => 'int',
        'redirect_count' => 'int',
        'redirect_time' => 'float',
        'redirect_time_us' => 'int',
        'redirect_url' => 'string',
        'request_size' => 'int',
        'scheme' => 'string',
        'size_download' => 'float',
        'size_upload' => 'float',
        'speed_download' => 'float',
        'speed_upload' => 'float',
        'ssl_verify_result' => 'int',
        'ssl_verifyresult' => 'int',
        'starttransfer_time' => 'float',
        'starttransfer_time_us' => 'int',
        'total_time' => 'float',
        'total_time_us' => 'int',
        'upload_content_length' => 'float',
        'url' => 'string',
    ])]
    public function getInfo() : array
    {
        return $this->info;
    }

    #[Override]
    #[Pure]
    public function getStatusCode() : int
    {
        return parent::getStatusCode();
    }

    /**
     * @param int $code
     *
     * @throws InvalidArgumentException if status code is invalid
     *
     * @return bool
     */
    #[Override]
    public function isStatusCode(int $code) : bool
    {
        return parent::isStatusCode($code);
    }

    #[Pure]
    public function getStatusReason() : string
    {
        return $this->statusReason;
    }

    /**
     * @param string $statusReason
     *
     * @return static
     */
    protected function setStatusReason(string $statusReason) : static
    {
        $this->statusReason = $statusReason;
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @throws Exception if Cookie::setExpires fail
     *
     * @return static
     */
    #[Override]
    protected function setHeader(string $name, string $value) : static
    {
        if (\strtolower($name) === 'set-cookie') {
            $values = \str_contains($value, "\n")
                ? \explode("\n", $value)
                : [$value];
            foreach ($values as $val) {
                $cookie = Cookie::parse($val);
                if ($cookie) {
                    $this->setCookie($cookie);
                }
            }
        }
        return parent::setHeader($name, $value);
    }

    /**
     * Get body as decoded JSON.
     *
     * @param bool $assoc
     * @param int|null $flags [optional] <p>
     * Bitmask consisting of <b>JSON_BIGINT_AS_STRING</b>,
     * <b>JSON_INVALID_UTF8_IGNORE</b>,
     * <b>JSON_INVALID_UTF8_SUBSTITUTE</b>,
     * <b>JSON_OBJECT_AS_ARRAY</b>,
     * <b>JSON_THROW_ON_ERROR</b>.
     * </p>
     * <p>Default is none when null.</p>
     * @param int<1,max> $depth
     *
     * @see https://www.php.net/manual/en/function.json-decode.php
     * @see https://www.php.net/manual/en/json.constants.php
     *
     * @return array<string,mixed>|false|object
     */
    public function getJson(bool $assoc = false, int $flags = null, int $depth = 512) : array | object | false
    {
        if ($flags === null) {
            $flags = $this->getJsonFlags();
        }
        $body = \json_decode($this->getBody(), $assoc, $depth, $flags);
        if (\json_last_error() !== \JSON_ERROR_NONE) {
            return false;
        }
        return $body;
    }

    #[Pure]
    public function isJson() : bool
    {
        return $this->parseContentType() === 'application/json';
    }

    #[Pure]
    public function getStatus() : string
    {
        return $this->getStatusCode() . ' ' . $this->getStatusReason();
    }

    /**
     * Get parsed Link header as array.
     *
     * NOTE: To be parsed, links must be in the GitHub REST API format.
     *
     * @see https://docs.github.com/en/rest/guides/using-pagination-in-the-rest-api#using-link-headers
     * @see https://docs.aplus-framework.com/guides/libraries/pagination/index.html#http-header-link
     * @see https://datatracker.ietf.org/doc/html/rfc5988
     *
     * @return array<string,string> Associative array with rel as keys and links
     * as values
     */
    public function getLinks() : array
    {
        $link = $this->getHeader(ResponseHeader::LINK);
        $result = [];
        if ($link) {
            $result = $this->parseLinkHeader($link);
        }
        return $result;
    }

    /**
     * @param string $headerLink
     *
     * @return array<string,string>
     */
    protected function parseLinkHeader(string $headerLink) : array
    {
        $links = [];
        $parts = \explode(',', $headerLink, 10);
        foreach ($parts as $part) {
            $section = \explode(';', $part, 10);
            if (\count($section) !== 2) {
                continue;
            }
            $url = \preg_replace('#<(.*)>#', '$1', $section[0]);
            $name = \preg_replace('#rel="(.*)"#', '$1', $section[1]);
            $url = \trim($url);
            $name = \trim($name);
            $links[$name] = $url;
        }
        return $links;
    }
}
