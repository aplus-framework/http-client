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

use CURLFile;
use Framework\HTTP\Cookie;
use Framework\HTTP\Message;
use Framework\HTTP\RequestInterface;
use Framework\HTTP\URL;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use JsonException;

/**
 * Class Request.
 */
class Request extends Message implements RequestInterface
{
    /**
     * HTTP Request Method.
     */
    protected string $method = 'GET';
    /**
     * HTTP Request URL.
     */
    protected URL $url;
    /**
     * POST files.
     *
     * @var array<string,CURLFile>
     */
    protected array $files = [];

    /**
     * Request constructor.
     *
     * @param string|URL $url
     */
    public function __construct(URL | string $url)
    {
        $this->setURL($url);
    }

    /**
     * @param string|URL $url
     *
     * @return static
     */
    public function setURL(string | URL $url) : static
    {
        return parent::setURL($url);
    }

    #[Pure]
    public function getURL() : URL
    {
        return parent::getURL();
    }

    #[Pure]
    public function getMethod() : string
    {
        return parent::getMethod();
    }

    /**
     * @param string $method
     *
     * @return static
     */
    public function setMethod(string $method) : static
    {
        return parent::setMethod($method);
    }

    /**
     * @param string $protocol
     *
     * @return static
     */
    public function setProtocol(string $protocol) : static
    {
        return parent::setProtocol($protocol);
    }

    /**
     * Set the request body.
     *
     * @param array<string,mixed>|string $body
     *
     * @return static
     */
    public function setBody(array | string $body) : static
    {
        if (\is_array($body)) {
            $body = \http_build_query($body);
        }
        return parent::setBody($body);
    }

    /**
     * Set body with JSON data.
     *
     * @param mixed $data
     * @param int $options [optional] <p>
     * Bitmask consisting of <b>JSON_HEX_QUOT</b>,
     * <b>JSON_HEX_TAG</b>,
     * <b>JSON_HEX_AMP</b>,
     * <b>JSON_HEX_APOS</b>,
     * <b>JSON_NUMERIC_CHECK</b>,
     * <b>JSON_PRETTY_PRINT</b>,
     * <b>JSON_UNESCAPED_SLASHES</b>,
     * <b>JSON_FORCE_OBJECT</b>,
     * <b>JSON_UNESCAPED_UNICODE</b>.
     * <b>JSON_THROW_ON_ERROR</b>
     * </p>
     * <p>Default is <b>JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE</b>
     * when null</p>
     * @param int $depth [optional] Set the maximum depth. Must be greater than zero.
     *
     * @throws JsonException if json_encode() fails
     *
     * @return static
     */
    public function setJSON(mixed $data, int $options = null, int $depth = 512) : static
    {
        if ($options === null) {
            $options = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;
        }
        $data = \json_encode($data, $options | \JSON_THROW_ON_ERROR, $depth);
        $this->setContentType('application/json');
        $this->setBody($data);
        return $this;
    }

    /**
     * Set POST data simulating a browser request.
     *
     * @param array<string,mixed> $data
     *
     * @return static
     */
    public function setPOST(array $data) : static
    {
        $this->setMethod('POST');
        $this->setBody($data);
        return $this;
    }

    #[Pure]
    public function hasFiles() : bool
    {
        return ! empty($this->files);
    }

    /**
     * Get files for upload.
     *
     * @return array<string,CURLFile>
     */
    #[Pure]
    public function getFiles() : array
    {
        return $this->files;
    }

    /**
     * Set files for upload.
     *
     * @param array<string,string> $files Fields as keys, paths of files as values
     *
     * @throws InvalidArgumentException for invalid file path
     *
     * @return static
     */
    public function setFiles(array $files) : static
    {
        $this->setMethod('POST');
        $this->files = [];
        foreach ($files as $field => $file) {
            if ( ! \is_file($file)) {
                throw new InvalidArgumentException(
                    "Field '{$field}' does not match a file: {$file}"
                );
            }
            $this->files[$field] = \curl_file_create(
                $file,
                \mime_content_type($file) ?: 'application/octet-stream'
            );
        }
        return $this;
    }

    /**
     * Set the Content-Type header.
     *
     * @param string $mime
     * @param string $charset
     *
     * @return static
     */
    public function setContentType(string $mime, string $charset = 'UTF-8') : static
    {
        $this->setHeader('Content-Type', $mime . ($charset ? '; charset=' . $charset : ''));
        return $this;
    }

    /**
     * @param Cookie $cookie
     *
     * @return static
     */
    public function setCookie(Cookie $cookie) : static
    {
        parent::setCookie($cookie);
        $this->setCookieHeader();
        return $this;
    }

    /**
     * @param array<int,Cookie> $cookies
     *
     * @return static
     */
    public function setCookies(array $cookies) : static
    {
        return parent::setCookies($cookies);
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function removeCookie(string $name) : static
    {
        parent::removeCookie($name);
        $this->setCookieHeader();
        return $this;
    }

    /**
     * @param array<int,string> $names
     *
     * @return static
     */
    public function removeCookies(array $names) : static
    {
        parent::removeCookies($names);
        $this->setCookieHeader();
        return $this;
    }

    /**
     * @return static
     */
    protected function setCookieHeader() : static
    {
        $line = [];
        foreach ($this->getCookies() as $cookie) {
            $line[] = $cookie->getName() . '=' . $cookie->getValue();
        }
        if ($line) {
            $line = \implode('; ', $line);
            return $this->setHeader('Cookie', $line);
        }
        return $this->removeHeader('Cookie');
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return static
     */
    public function setHeader(string $name, string $value) : static
    {
        return parent::setHeader($name, $value);
    }

    /**
     * @param array<string,string> $headers
     *
     * @return static
     */
    public function setHeaders(array $headers) : static
    {
        return parent::setHeaders($headers);
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function removeHeader(string $name) : static
    {
        return parent::removeHeader($name);
    }

    /**
     * @return static
     */
    public function removeHeaders() : static
    {
        return parent::removeHeaders();
    }

    /**
     * Set Authorization header with Basic type.
     *
     * @param string $username
     * @param string $password
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Authorization
     *
     * @return static
     */
    public function setBasicAuth(string $username, string $password) : static
    {
        return $this->setHeader(
            'Authorization',
            'Basic ' . \base64_encode($username . ':' . $password)
        );
    }

    /**
     * Set the User-Agent header.
     *
     * @param string|null $userAgent
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent
     *
     * @return static
     */
    public function setUserAgent(string $userAgent = null) : static
    {
        $userAgent ??= 'HTTP Client';
        return $this->setHeader('User-Agent', $userAgent);
    }
}
