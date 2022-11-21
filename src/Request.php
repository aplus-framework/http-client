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
use CURLStringFile;
use Framework\Helpers\ArraySimple;
use Framework\HTTP\Cookie;
use Framework\HTTP\Header;
use Framework\HTTP\Message;
use Framework\HTTP\Method;
use Framework\HTTP\Protocol;
use Framework\HTTP\RequestHeader;
use Framework\HTTP\RequestInterface;
use Framework\HTTP\URL;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonException;
use OutOfBoundsException;

/**
 * Class Request.
 *
 * @package http-client
 */
class Request extends Message implements RequestInterface
{
    /**
     * HTTP Request Method.
     */
    protected string $method = Method::GET;
    /**
     * HTTP Request URL.
     */
    protected URL $url;
    /**
     * POST files.
     *
     * @var array<string,array<mixed>|string>
     */
    protected array $files = [];
    /**
     * Client default curl options.
     *
     * @var array<int,mixed>
     */
    protected array $defaultOptions = [
        \CURLOPT_CONNECTTIMEOUT => 10,
        \CURLOPT_TIMEOUT => 60,
        \CURLOPT_FOLLOWLOCATION => false,
        \CURLOPT_MAXREDIRS => 1,
        \CURLOPT_AUTOREFERER => true,
        \CURLOPT_RETURNTRANSFER => true,
        \CURLOPT_ENCODING => '',
    ];
    /**
     * Custom curl options.
     *
     * @var array<int,mixed>
     */
    protected array $options = [];
    protected bool $checkOptions = false;
    protected bool $getResponseInfo = false;

    /**
     * Request constructor.
     *
     * @param string|URL $url
     */
    public function __construct(URL | string $url)
    {
        $this->setUrl($url);
    }

    public function __toString() : string
    {
        if ($this->parseContentType() === 'multipart/form-data') {
            $this->setBody($this->getMultipartBody());
        }
        if ( ! $this->hasHeader(RequestHeader::ACCEPT)) {
            $accept = '*/*';
            $this->setHeader(RequestHeader::ACCEPT, $accept);
        }
        $options = $this->getOptions();
        if (isset($options[\CURLOPT_ENCODING])
            && ! $this->hasHeader(RequestHeader::ACCEPT_ENCODING)
        ) {
            $encoding = $options[\CURLOPT_ENCODING] === ''
                ? 'deflate, gzip, br, zstd'
                : $options[\CURLOPT_ENCODING];
            $this->setHeader(RequestHeader::ACCEPT_ENCODING, $encoding);
        }
        $message = parent::__toString();
        if (isset($accept)) {
            $this->removeHeader(RequestHeader::ACCEPT);
        }
        if (isset($encoding)) {
            $this->removeHeader(RequestHeader::ACCEPT_ENCODING);
        }
        return $message;
    }

    protected function getMultipartBody() : string
    {
        $bodyParts = [];
        \parse_str($this->getBody(), $post);
        /**
         * @var array<string,string> $post
         */
        $post = ArraySimple::convert($post);
        foreach ($post as $field => $value) {
            $field = \htmlspecialchars($field, \ENT_QUOTES | \ENT_HTML5);
            $bodyParts[] = \implode("\r\n", [
                "Content-Disposition: form-data; name=\"{$field}\"",
                '',
                $value,
            ]);
        }
        /**
         * @var array<string,CURLFile|string> $files
         */
        $files = ArraySimple::convert($this->getFiles());
        foreach ($files as $field => $file) {
            $field = (string) $field;
            $field = \htmlspecialchars($field, \ENT_QUOTES | \ENT_HTML5);
            $info = $this->getFileInfo($file);
            $filename = \htmlspecialchars($info['filename'], \ENT_QUOTES | \ENT_HTML5);
            $bodyParts[] = \implode("\r\n", [
                'Content-Disposition: form-data; name="' . $field . '"; filename="' . $filename . '"',
                'Content-Type: ' . $info['mime'],
                '',
                $info['data'],
            ]);
        }
        unset($info);
        $boundary = \str_repeat('-', 24) . \substr(\md5(\implode("\r\n", $bodyParts)), 0, 16);
        $this->setHeader(
            Header::CONTENT_TYPE,
            'multipart/form-data; charset=UTF-8; boundary=' . $boundary
        );
        foreach ($bodyParts as &$part) {
            $part = "--{$boundary}\r\n{$part}";
        }
        unset($part);
        $bodyParts[] = "--{$boundary}--";
        $bodyParts[] = '';
        $bodyParts = \implode("\r\n", $bodyParts);
        $this->setHeader(
            Header::CONTENT_LENGTH,
            (string) \strlen($bodyParts)
        );
        return $bodyParts;
    }

    /**
     * @param CURLFile|CURLStringFile|string $file
     *
     * @return array<string,string>
     */
    #[ArrayShape(['filename' => 'string', 'data' => 'string', 'mime' => 'string'])]
    protected function getFileInfo(CURLFile | CURLStringFile | string $file) : array
    {
        if ($file instanceof CURLFile) {
            return [
                'filename' => $file->getPostFilename(),
                'data' => (string) \file_get_contents($file->getFilename()),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
            ];
        }
        if ($file instanceof CURLStringFile) {
            return [
                'filename' => $file->postname,
                'data' => $file->data,
                'mime' => $file->mime,
            ];
        }
        return [
            'filename' => \basename($file),
            'data' => (string) \file_get_contents($file),
            'mime' => \mime_content_type($file) ?: 'application/octet-stream',
        ];
    }

    /**
     * @param string|URL $url
     *
     * @return static
     */
    public function setUrl(string | URL $url) : static
    {
        if ( ! $url instanceof URL) {
            $url = new URL($url);
        }
        $this->setHeader(RequestHeader::HOST, $url->getHost());
        return parent::setUrl($url);
    }

    #[Pure]
    public function getUrl() : URL
    {
        return parent::getUrl();
    }

    #[Pure]
    public function getMethod() : string
    {
        return parent::getMethod();
    }

    /**
     * @param string $method
     *
     * @throws InvalidArgumentException for invalid method
     *
     * @return bool
     */
    public function isMethod(string $method) : bool
    {
        return parent::isMethod($method);
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
     * @param int<1,max> $depth [optional] Set the maximum depth. Must be greater than zero.
     *
     * @throws JsonException if json_encode() fails
     *
     * @return static
     */
    public function setJson(mixed $data, int $options = null, int $depth = 512) : static
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
    public function setPost(array $data) : static
    {
        $this->setMethod(Method::POST);
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
     * @return array<mixed>
     */
    #[Pure]
    public function getFiles() : array
    {
        return $this->files;
    }

    /**
     * Set files for upload.
     *
     * @param array<mixed> $files Fields as keys, files (CURLFile,
     * CURLStringFile or string filename) as values.
     * Multi-dimensional array is allowed.
     *
     * @throws InvalidArgumentException for invalid file path
     *
     * @return static
     */
    public function setFiles(array $files) : static
    {
        $this->setMethod(Method::POST);
        $this->setContentType('multipart/form-data');
        $this->files = $files;
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
        $this->setHeader(
            Header::CONTENT_TYPE,
            $mime . ($charset ? '; charset=' . $charset : '')
        );
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
            return $this->setHeader(RequestHeader::COOKIE, $line);
        }
        return $this->removeHeader(RequestHeader::COOKIE);
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
            RequestHeader::AUTHORIZATION,
            'Basic ' . \base64_encode($username . ':' . $password)
        );
    }

    /**
     * Set Authorization header with Bearer type.
     *
     * @param string $token
     *
     * @return static
     */
    public function setBearerAuth(string $token) : static
    {
        return $this->setHeader(
            RequestHeader::AUTHORIZATION,
            'Bearer ' . $token
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
        $userAgent ??= 'Aplus HTTP Client';
        return $this->setHeader(RequestHeader::USER_AGENT, $userAgent);
    }

    /**
     * Set a callback to write the response body with chunks.
     *
     * Used to write data to files, databases, etc...
     *
     * NOTE: Using this function makes the Response body, returned in the
     * {@see Client::run()} method, be set with an empty string.
     *
     * @param callable $callback A callback with the response body $data chunk
     * as first argument and the CurlHandle as the second. Return is not
     * necessary.
     *
     * @return static
     */
    public function setDownloadFunction(callable $callback) : static
    {
        $function = static function (\CurlHandle $handle, string $data) use ($callback) : int {
            $callback($data, $handle);
            return \strlen($data);
        };
        $this->setOption(\CURLOPT_WRITEFUNCTION, $function);
        return $this;
    }

    /**
     * Set curl options.
     *
     * @param int $option A curl constant
     * @param mixed $value
     *
     * @see Client::$defaultOptions
     *
     * @return static
     */
    public function setOption(int $option, mixed $value) : static
    {
        if ($this->isCheckingOptions()) {
            $this->checkOption($option, $value);
        }
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * Set many curl options.
     *
     * @param array<int,mixed> $options Curl constants as keys and their respective values
     *
     * @return static
     */
    public function setOptions(array $options) : static
    {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
        return $this;
    }

    /**
     * Get default options replaced by custom.
     *
     * @return array<int,mixed>
     */
    public function getOptions() : array
    {
        $options = \array_replace($this->defaultOptions, $this->options);
        $options[\CURLOPT_PROTOCOLS] = \CURLPROTO_HTTPS | \CURLPROTO_HTTP;
        $options[\CURLOPT_HTTP_VERSION] = match ($this->getProtocol()) {
            Protocol::HTTP_1_0 => \CURL_HTTP_VERSION_1_0,
            Protocol::HTTP_1_1 => \CURL_HTTP_VERSION_1_1,
            Protocol::HTTP_2_0 => \CURL_HTTP_VERSION_2_0,
            Protocol::HTTP_2 => \CURL_HTTP_VERSION_2,
            default => throw new InvalidArgumentException(
                'Invalid Request Protocol: ' . $this->getProtocol()
            )
        };
        switch ($this->getMethod()) {
            case Method::POST:
                $options[\CURLOPT_POST] = true;
                $options[\CURLOPT_POSTFIELDS] = $this->getPostAndFiles();
                break;
            case Method::DELETE:
            case Method::PATCH:
            case Method::PUT:
                $options[\CURLOPT_POSTFIELDS] = $this->getBody();
                break;
        }
        $options[\CURLOPT_CUSTOMREQUEST] = $this->getMethod();
        $options[\CURLOPT_HEADER] = false;
        $options[\CURLOPT_URL] = $this->getUrl()->toString();
        $options[\CURLOPT_HTTPHEADER] = $this->getHeaderLines();
        return $options;
    }

    public function getOption(int $option) : mixed
    {
        return $this->getOptions()[$option] ?? null;
    }

    /**
     * Returns string if the Request has not files and curl will set the
     * Content-Type header to application/x-www-form-urlencoded. If the Request
     * has files, returns an array and curl will set the Content-Type to
     * multipart/form-data.
     *
     * If the Request has files, the $post and $files arrays are converted to
     * the array_simple format. Because curl does not understand the PHP
     * multi-dimensional arrays.
     *
     * @see https://www.php.net/manual/en/function.curl-setopt.php CURLOPT_POSTFIELDS
     * @see ArraySimple::convert()
     *
     * @return array<string,mixed>|string
     */
    public function getPostAndFiles() : array | string
    {
        if ( ! $this->hasFiles()) {
            return $this->getBody();
        }
        \parse_str($this->getBody(), $post);
        $post = ArraySimple::convert($post);
        foreach ($post as &$value) {
            $value = (string) $value;
        }
        unset($value);
        $files = ArraySimple::convert($this->getFiles());
        foreach ($files as $field => &$file) {
            if ($file instanceof CURLFile
                || $file instanceof CURLStringFile
            ) {
                continue;
            }
            if ( ! \is_file($file)) {
                throw new InvalidArgumentException(
                    "Field '{$field}' does not match a file: {$file}"
                );
            }
            $file = new CURLFile(
                $file,
                \mime_content_type($file) ?: 'application/octet-stream',
                \basename($file)
            );
        }
        unset($file);
        return \array_replace($post, $files);
    }

    public function setGetResponseInfo(bool $get = true) : static
    {
        $this->getResponseInfo = $get;
        return $this;
    }

    public function isGettingResponseInfo() : bool
    {
        return $this->getResponseInfo;
    }

    /**
     * @param bool $check
     *
     * @return static
     */
    public function setCheckOptions(bool $check = true) : static
    {
        $this->checkOptions = $check;
        return $this;
    }

    public function isCheckingOptions() : bool
    {
        return $this->checkOptions;
    }

    /**
     * @param int $option The curl option
     * @param mixed $value The curl option value
     *
     * @throws InvalidArgumentException if the option value does not match the
     * expected type
     * @throws OutOfBoundsException if the option is invalid
     */
    protected function checkOption(int $option, mixed $value) : void
    {
        $types = [
            'bool' => [
                \CURLOPT_AUTOREFERER,
                \CURLOPT_COOKIESESSION,
                \CURLOPT_CERTINFO,
                \CURLOPT_CONNECT_ONLY,
                \CURLOPT_CRLF,
                \CURLOPT_DISALLOW_USERNAME_IN_URL,
                \CURLOPT_DNS_SHUFFLE_ADDRESSES,
                \CURLOPT_HAPROXYPROTOCOL,
                \CURLOPT_SSH_COMPRESSION,
                \CURLOPT_DNS_USE_GLOBAL_CACHE,
                \CURLOPT_FAILONERROR,
                \CURLOPT_SSL_FALSESTART,
                \CURLOPT_FILETIME,
                \CURLOPT_FOLLOWLOCATION,
                \CURLOPT_FORBID_REUSE,
                \CURLOPT_FRESH_CONNECT,
                \CURLOPT_FTP_USE_EPRT,
                \CURLOPT_FTP_USE_EPSV,
                \CURLOPT_FTP_CREATE_MISSING_DIRS,
                \CURLOPT_FTPAPPEND,
                \CURLOPT_TCP_NODELAY,
                // CURLOPT_FTPASCII,
                \CURLOPT_FTPLISTONLY,
                \CURLOPT_HEADER,
                \CURLINFO_HEADER_OUT,
                \CURLOPT_HTTP09_ALLOWED,
                \CURLOPT_HTTPGET,
                \CURLOPT_HTTPPROXYTUNNEL,
                \CURLOPT_HTTP_CONTENT_DECODING,
                \CURLOPT_KEEP_SENDING_ON_ERROR,
                // CURLOPT_MUTE,
                \CURLOPT_NETRC,
                \CURLOPT_NOBODY,
                \CURLOPT_NOPROGRESS,
                \CURLOPT_NOSIGNAL,
                \CURLOPT_PATH_AS_IS,
                \CURLOPT_PIPEWAIT,
                \CURLOPT_POST,
                \CURLOPT_PUT,
                \CURLOPT_RETURNTRANSFER,
                \CURLOPT_SASL_IR,
                \CURLOPT_SSL_ENABLE_ALPN,
                \CURLOPT_SSL_ENABLE_NPN,
                \CURLOPT_SSL_VERIFYPEER,
                \CURLOPT_SSL_VERIFYSTATUS,
                \CURLOPT_PROXY_SSL_VERIFYPEER,
                \CURLOPT_SUPPRESS_CONNECT_HEADERS,
                \CURLOPT_TCP_FASTOPEN,
                \CURLOPT_TFTP_NO_OPTIONS,
                \CURLOPT_TRANSFERTEXT,
                \CURLOPT_UNRESTRICTED_AUTH,
                \CURLOPT_UPLOAD,
                \CURLOPT_VERBOSE,
            ],
            'int' => [
                \CURLOPT_BUFFERSIZE,
                \CURLOPT_CONNECTTIMEOUT,
                \CURLOPT_CONNECTTIMEOUT_MS,
                \CURLOPT_DNS_CACHE_TIMEOUT,
                \CURLOPT_EXPECT_100_TIMEOUT_MS,
                \CURLOPT_HAPPY_EYEBALLS_TIMEOUT_MS,
                \CURLOPT_FTPSSLAUTH,
                \CURLOPT_HEADEROPT,
                \CURLOPT_HTTP_VERSION,
                \CURLOPT_HTTPAUTH,
                \CURLOPT_INFILESIZE,
                \CURLOPT_LOW_SPEED_LIMIT,
                \CURLOPT_LOW_SPEED_TIME,
                \CURLOPT_MAXCONNECTS,
                \CURLOPT_MAXREDIRS,
                \CURLOPT_PORT,
                \CURLOPT_POSTREDIR,
                \CURLOPT_PROTOCOLS,
                \CURLOPT_PROXYAUTH,
                \CURLOPT_PROXYPORT,
                \CURLOPT_PROXYTYPE,
                \CURLOPT_REDIR_PROTOCOLS,
                \CURLOPT_RESUME_FROM,
                \CURLOPT_SOCKS5_AUTH,
                \CURLOPT_SSL_OPTIONS,
                \CURLOPT_SSL_VERIFYHOST,
                \CURLOPT_SSLVERSION,
                \CURLOPT_PROXY_SSL_OPTIONS,
                \CURLOPT_PROXY_SSL_VERIFYHOST,
                \CURLOPT_PROXY_SSLVERSION,
                \CURLOPT_STREAM_WEIGHT,
                \CURLOPT_TCP_KEEPALIVE,
                \CURLOPT_TCP_KEEPIDLE,
                \CURLOPT_TCP_KEEPINTVL,
                \CURLOPT_TIMECONDITION,
                \CURLOPT_TIMEOUT,
                \CURLOPT_TIMEOUT_MS,
                \CURLOPT_TIMEVALUE,
                \CURLOPT_TIMEVALUE_LARGE,
                \CURLOPT_MAX_RECV_SPEED_LARGE,
                \CURLOPT_MAX_SEND_SPEED_LARGE,
                \CURLOPT_SSH_AUTH_TYPES,
                \CURLOPT_IPRESOLVE,
                \CURLOPT_FTP_FILEMETHOD,
            ],
            'string' => [
                \CURLOPT_ABSTRACT_UNIX_SOCKET,
                \CURLOPT_CAINFO,
                \CURLOPT_CAPATH,
                \CURLOPT_COOKIE,
                \CURLOPT_COOKIEFILE,
                \CURLOPT_COOKIEJAR,
                \CURLOPT_COOKIELIST,
                \CURLOPT_CUSTOMREQUEST,
                \CURLOPT_DEFAULT_PROTOCOL,
                \CURLOPT_DNS_INTERFACE,
                \CURLOPT_DNS_LOCAL_IP4,
                \CURLOPT_DNS_LOCAL_IP6,
                \CURLOPT_DOH_URL,
                \CURLOPT_EGDSOCKET,
                \CURLOPT_ENCODING,
                \CURLOPT_FTPPORT,
                \CURLOPT_INTERFACE,
                \CURLOPT_KEYPASSWD,
                \CURLOPT_KRB4LEVEL,
                \CURLOPT_LOGIN_OPTIONS,
                \CURLOPT_PINNEDPUBLICKEY,
                \CURLOPT_POSTFIELDS,
                \CURLOPT_PRIVATE,
                \CURLOPT_PRE_PROXY,
                \CURLOPT_PROXY,
                \CURLOPT_PROXY_SERVICE_NAME,
                \CURLOPT_PROXY_CAINFO,
                \CURLOPT_PROXY_CAPATH,
                \CURLOPT_PROXY_CRLFILE,
                \CURLOPT_PROXY_KEYPASSWD,
                \CURLOPT_PROXY_PINNEDPUBLICKEY,
                \CURLOPT_PROXY_SSLCERT,
                \CURLOPT_PROXY_SSLCERTTYPE,
                \CURLOPT_PROXY_SSL_CIPHER_LIST,
                \CURLOPT_PROXY_TLS13_CIPHERS,
                \CURLOPT_PROXY_SSLKEY,
                \CURLOPT_PROXY_SSLKEYTYPE,
                \CURLOPT_PROXY_TLSAUTH_PASSWORD,
                \CURLOPT_PROXY_TLSAUTH_TYPE,
                \CURLOPT_PROXY_TLSAUTH_USERNAME,
                \CURLOPT_PROXYUSERPWD,
                \CURLOPT_RANDOM_FILE,
                \CURLOPT_RANGE,
                \CURLOPT_REFERER,
                \CURLOPT_SERVICE_NAME,
                \CURLOPT_SSH_HOST_PUBLIC_KEY_MD5,
                \CURLOPT_SSH_PUBLIC_KEYFILE,
                \CURLOPT_SSH_PRIVATE_KEYFILE,
                \CURLOPT_SSL_CIPHER_LIST,
                \CURLOPT_SSLCERT,
                \CURLOPT_SSLCERTPASSWD,
                \CURLOPT_SSLCERTTYPE,
                \CURLOPT_SSLENGINE,
                \CURLOPT_SSLENGINE_DEFAULT,
                \CURLOPT_SSLKEY,
                \CURLOPT_SSLKEYPASSWD,
                \CURLOPT_SSLKEYTYPE,
                \CURLOPT_TLS13_CIPHERS,
                \CURLOPT_UNIX_SOCKET_PATH,
                \CURLOPT_URL,
                \CURLOPT_USERAGENT,
                \CURLOPT_USERNAME,
                \CURLOPT_PASSWORD,
                \CURLOPT_USERPWD,
                \CURLOPT_XOAUTH2_BEARER,
            ],
            'array' => [
                \CURLOPT_CONNECT_TO,
                \CURLOPT_HTTP200ALIASES,
                \CURLOPT_HTTPHEADER,
                \CURLOPT_POSTQUOTE,
                \CURLOPT_PROXYHEADER,
                \CURLOPT_QUOTE,
                \CURLOPT_RESOLVE,
            ],
            'fopen' => [
                \CURLOPT_FILE,
                \CURLOPT_INFILE,
                \CURLOPT_STDERR,
                \CURLOPT_WRITEHEADER,
            ],
            'function' => [
                \CURLOPT_HEADERFUNCTION,
                // CURLOPT_PASSWDFUNCTION,
                \CURLOPT_PROGRESSFUNCTION,
                \CURLOPT_READFUNCTION,
                \CURLOPT_WRITEFUNCTION,
            ],
            'curl_share_init' => [
                \CURLOPT_SHARE,
            ],
        ];
        foreach ($types as $type => $constants) {
            foreach ($constants as $constant) {
                if ($option !== $constant) {
                    continue;
                }
                if ($value === null) {
                    return;
                }
                $valid = match ($type) {
                    'bool' => \is_bool($value),
                    'int' => \is_int($value),
                    'string' => \is_string($value),
                    'array' => \is_array($value),
                    'fopen' => \is_resource($value),
                    'function' => \is_callable($value),
                    'curl_share_init' => $value instanceof \CurlShareHandle
                };
                if ($valid) {
                    return;
                }
                $message = match ($type) {
                    'bool' => 'The value of option %d should be of bool type',
                    'int' => 'The value of option %d should be of int type',
                    'string' => 'The value of option %d should be of string type',
                    'array' => 'The value of option %d should be of array type',
                    'fopen' => 'The value of option %d should be a fopen() resource',
                    'function' => 'The value of option %d should be a callable',
                    'curl_share_init' => 'The value of option %d should be a result of curl_share_init()'
                };
                throw new InvalidArgumentException(\sprintf($message, $option));
            }
        }
        throw new OutOfBoundsException('Invalid curl constant option: ' . $option);
    }
}
