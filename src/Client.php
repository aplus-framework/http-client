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
use Framework\Helpers\ArraySimple;
use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use OutOfBoundsException;
use RuntimeException;

/**
 * Class Client.
 */
class Client
{
    /**
     * Client default cURL options.
     *
     * @see https://www.php.net/manual/en/function.curl-setopt.php
     * @see https://php.watch/articles/php-curl-security-hardening
     *
     * @var array<int,mixed>
     */
    protected array $defaultOptions = [
        \CURLOPT_CONNECTTIMEOUT => 10,
        \CURLOPT_TIMEOUT => 60,
        \CURLOPT_PROTOCOLS => \CURLPROTO_HTTPS | \CURLPROTO_HTTP,
        \CURLOPT_FOLLOWLOCATION => false,
        \CURLOPT_MAXREDIRS => 1,
        \CURLOPT_AUTOREFERER => true,
        \CURLOPT_RETURNTRANSFER => true,
        // CURLOPT_HTTP09_ALLOWED enabled by default to allow accept custom
        // Response status without throw an exception
        \CURLOPT_HTTP09_ALLOWED => true,
    ];
    /**
     * Custom cURL options.
     *
     * @var array<int,mixed>
     */
    protected array $options = [];
    /**
     * Response HTTP protocol.
     *
     * @var string|null
     */
    protected ?string $responseProtocol = null;
    /**
     * Response HTTP status code.
     *
     * @var int|null
     */
    protected ?int $responseCode = null;
    /**
     * Response HTTP status reason.
     *
     * @var string|null
     */
    protected ?string $responseReason = null;
    /**
     * Response headers.
     *
     * @var array<string,string>
     */
    protected array $responseHeaders = [];
    /**
     * Response cURL info.
     *
     * @var array<string,mixed>
     */
    protected array $info = [];
    protected bool $checkOptions = false;

    /**
     * Set cURL options.
     *
     * @param int $option A cURL constant
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
     * @param int $option The cURL option
     * @param mixed $value The cURL option value
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
                \CURLOPT_FTPASCII,
                \CURLOPT_FTPLISTONLY,
                \CURLOPT_HEADER,
                \CURLINFO_HEADER_OUT,
                \CURLOPT_HTTP09_ALLOWED,
                \CURLOPT_HTTPGET,
                \CURLOPT_HTTPPROXYTUNNEL,
                \CURLOPT_HTTP_CONTENT_DECODING,
                \CURLOPT_KEEP_SENDING_ON_ERROR,
                \CURLOPT_MUTE,
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
                \CURLOPT_PASSWDFUNCTION,
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
        throw new OutOfBoundsException('Invalid cURL constant option: ' . $option);
    }

    /**
     * Get default options replaced by custom.
     *
     * @return array<int,mixed>
     */
    #[Pure]
    public function getOptions() : array
    {
        return \array_replace($this->defaultOptions, $this->options);
    }

    /**
     * Get cURL info for the last request.
     *
     * @return array<string,mixed>
     */
    #[Pure]
    public function getInfo() : array
    {
        return $this->info;
    }

    /**
     * Set cURL timeout.
     *
     * @param int $seconds The maximum number of seconds to allow cURL
     * functions to execute
     *
     * @return static
     */
    public function setResponseTimeout(int $seconds) : static
    {
        $this->setOption(\CURLOPT_TIMEOUT, $seconds);
        return $this;
    }

    /**
     * Set cURL connect timeout.
     *
     * @param int $seconds The number of seconds to wait while trying to connect.
     * Use 0 to wait indefinitely.
     *
     * @return static
     */
    public function setRequestTimeout(int $seconds) : static
    {
        $this->setOption(\CURLOPT_CONNECTTIMEOUT, $seconds);
        return $this;
    }

    /**
     * Reset to default values.
     */
    public function reset() : void
    {
        $this->options = [];
        $this->responseProtocol = null;
        $this->responseCode = null;
        $this->responseReason = null;
        $this->responseHeaders = [];
        $this->info = [];
    }

    /**
     * @param string $version
     *
     * @return static
     */
    protected function setHttpVersion(string $version) : static
    {
        if ($version === 'HTTP/1.0') {
            return $this->setOption(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_0);
        }
        if ($version === 'HTTP/2.0') {
            return $this->setOption(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_2_0);
        }
        return $this->setOption(\CURLOPT_HTTP_VERSION, \CURL_HTTP_VERSION_1_1);
    }

    /**
     * Returns string if the Request has not files and cURL will set the
     * Content-Type header to application/x-www-form-urlencoded. If the Request
     * has files, returns an array and cURL will set the Content-Type to
     * multipart/form-data.
     *
     * If the Request has files, the $post and $files arrays are converted to
     * the array_simple format. Because cURL does not understand the PHP
     * multi-dimensional arrays.
     *
     * @see https://www.php.net/manual/en/function.curl-setopt.php CURLOPT_POSTFIELDS
     *
     * @param Request $request
     *
     * @see ArraySimple::convert()
     *
     * @return array<string,CURLFile|string>|string
     */
    protected function getPostAndFiles(Request $request) : array | string
    {
        if ( ! $request->hasFiles()) {
            return $request->getBody();
        }
        \parse_str($request->getBody(), $post);
        $post = ArraySimple::convert($post);
        foreach ($post as &$value) {
            $value = (string) $value;
        }
        unset($value);
        $files = ArraySimple::convert($request->getFiles());
        foreach ($files as $field => &$file) {
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

    /**
     * Run the Request.
     *
     * @param Request $request
     *
     * @throws RuntimeException for cURL error
     *
     * @return Response
     */
    public function run(Request $request) : Response
    {
        $this->setHttpVersion($request->getProtocol());
        switch ($request->getMethod()) {
            case 'POST':
                $this->setOption(\CURLOPT_POST, true);
                $this->setOption(\CURLOPT_POSTFIELDS, $this->getPostAndFiles($request));
                break;
            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                $this->setOption(\CURLOPT_POSTFIELDS, $request->getBody());
                break;
        }
        $this->setOption(\CURLOPT_CUSTOMREQUEST, $request->getMethod());
        $this->setOption(\CURLOPT_HEADER, false);
        $this->setOption(\CURLOPT_URL, $request->getURL()->getAsString());
        $this->setOption(\CURLOPT_HTTPHEADER, $request->getHeaderLines());
        $this->setOption(\CURLOPT_HEADERFUNCTION, [$this, 'parseHeaderLine']);
        $curl = \curl_init();
        \curl_setopt_array($curl, $this->getOptions());
        $body = \curl_exec($curl);
        if ($body === false) {
            throw new RuntimeException(\curl_error($curl), \curl_errno($curl));
        }
        if (isset($this->options[\CURLOPT_RETURNTRANSFER])
            && $this->options[\CURLOPT_RETURNTRANSFER] === false) {
            $body = '';
        }
        $this->info = \curl_getinfo($curl);
        \ksort($this->info);
        \curl_close($curl);
        return new Response(
            $this->responseProtocol,
            $this->responseCode,
            $this->responseReason,
            $this->responseHeaders,
            // @phpstan-ignore-next-line
            $body
        );
    }

    /**
     * Parses Header line.
     *
     * @param resource $curl
     * @param string $line
     *
     * @return int
     */
    protected function parseHeaderLine($curl, string $line) : int
    {
        $trimmed_line = \trim($line);
        if ($trimmed_line === '') {
            return \strlen($line);
        }
        if ( ! \str_contains($trimmed_line, ':')) {
            if (\str_starts_with($trimmed_line, 'HTTP/')) {
                $parts = \explode(' ', $trimmed_line, 3);
                $this->responseProtocol = $parts[0];
                $this->responseCode = (int) ($parts[1] ?? 200);
                $this->responseReason = $parts[2] ?? 'OK';
            }
            return \strlen($line);
        }
        [$name, $value] = \explode(':', $trimmed_line, 2);
        $name = \trim($name);
        $value = \trim($value);
        if ($name !== '' && $value !== '') {
            $this->responseHeaders[\strtolower($name)] = $value;
        }
        return \strlen($line);
    }
}
