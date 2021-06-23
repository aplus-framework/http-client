<?php namespace Framework\HTTP\Client;

use CURLFile;
use Framework\HTTP\Cookie;
use Framework\HTTP\Message;
use Framework\HTTP\RequestInterface;
use Framework\HTTP\URL;
use InvalidArgumentException;
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
	 * @return $this
	 */
	public function setURL(string | URL $url)
	{
		return parent::setURL($url);
	}

	public function getURL() : URL
	{
		return parent::getURL();
	}

	public function getMethod() : string
	{
		return parent::getMethod();
	}

	/**
	 * @param string $method
	 *
	 * @return $this
	 */
	public function setMethod(string $method)
	{
		return parent::setMethod($method);
	}

	/**
	 * @param string $protocol
	 *
	 * @return $this
	 */
	public function setProtocol(string $protocol)
	{
		return parent::setProtocol($protocol);
	}

	/**
	 * Set the request body.
	 *
	 * @param array<string,mixed>|string $body
	 *
	 * @return $this
	 */
	public function setBody(array | string $body)
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
	 * @return $this
	 */
	public function setJSON(mixed $data, int $options = null, int $depth = 512)
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
	 * @return $this
	 */
	public function setPOST(array $data)
	{
		$this->setMethod('POST');
		$this->setBody($data);
		return $this;
	}

	public function hasFiles() : bool
	{
		return ! empty($this->files);
	}

	/**
	 * Get files for upload.
	 *
	 * @return array<string,CURLFile>
	 */
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
	 * @return $this
	 */
	public function setFiles(array $files)
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
	 * @return $this
	 */
	public function setContentType(string $mime, string $charset = 'UTF-8')
	{
		$this->setHeader('Content-Type', $mime . ($charset ? '; charset=' . $charset : ''));
		return $this;
	}

	/**
	 * @param Cookie $cookie
	 *
	 * @return $this
	 */
	public function setCookie(Cookie $cookie)
	{
		parent::setCookie($cookie);
		$this->setCookieHeader();
		return $this;
	}

	/**
	 * @param array<int,Cookie> $cookies
	 *
	 * @return $this
	 */
	public function setCookies(array $cookies)
	{
		return parent::setCookies($cookies);
	}

	/**
	 * @param string $name
	 *
	 * @return $this
	 */
	public function removeCookie(string $name)
	{
		parent::removeCookie($name);
		$this->setCookieHeader();
		return $this;
	}

	/**
	 * @param array<int,string> $names
	 *
	 * @return $this
	 */
	public function removeCookies(array $names)
	{
		parent::removeCookies($names);
		$this->setCookieHeader();
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function setCookieHeader()
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
	 * @return $this
	 */
	public function setHeader(string $name, string $value)
	{
		return parent::setHeader($name, $value);
	}

	/**
	 * @param array<string,string> $headers
	 *
	 * @return $this
	 */
	public function setHeaders(array $headers)
	{
		return parent::setHeaders($headers);
	}

	/**
	 * @param string $name
	 *
	 * @return $this
	 */
	public function removeHeader(string $name)
	{
		return parent::removeHeader($name);
	}

	/**
	 * @return $this
	 */
	public function removeHeaders()
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
	 * @return $this
	 */
	public function setBasicAuth(string $username, string $password)
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
	 * @return $this
	 */
	public function setUserAgent(string $userAgent = null)
	{
		$user_agent ??= 'HTTP Client';
		return $this->setHeader('User-Agent', $userAgent);
	}
}
