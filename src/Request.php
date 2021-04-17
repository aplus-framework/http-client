<?php namespace Framework\HTTP\Client;

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
	 * @var array|\CURLFile[]
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

	public function setURL($url)
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

	public function setMethod(string $method)
	{
		return parent::setMethod($method);
	}

	public function setProtocol(string $protocol)
	{
		return parent::setProtocol($protocol);
	}

	/**
	 * Set the request body.
	 *
	 * @param array|string $body
	 *
	 * @return Request
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
	 * @param int   $options [optional] <p>
	 *                       Bitmask consisting of <b>JSON_HEX_QUOT</b>,
	 *                       <b>JSON_HEX_TAG</b>,
	 *                       <b>JSON_HEX_AMP</b>,
	 *                       <b>JSON_HEX_APOS</b>,
	 *                       <b>JSON_NUMERIC_CHECK</b>,
	 *                       <b>JSON_PRETTY_PRINT</b>,
	 *                       <b>JSON_UNESCAPED_SLASHES</b>,
	 *                       <b>JSON_FORCE_OBJECT</b>,
	 *                       <b>JSON_UNESCAPED_UNICODE</b>.
	 *                       <b>JSON_THROW_ON_ERROR</b>
	 *                       </p>
	 *                       <p>Default is <b>JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE</b>
	 *                       when null</p>
	 * @param int   $depth   [optional] <p>
	 *                       Set the maximum depth. Must be greater than zero.
	 *                       </p>
	 *
	 * @throws JsonException if json_encode() fails
	 *
	 * @return $this
	 */
	public function setJSON($data, int $options = null, int $depth = 512)
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
	 * @param array $data
	 *
	 * @return $this
	 */
	public function setPOST(array $data)
	{
		$this->setMethod('POST');
		$this->setContentType('application/x-www-form-urlencoded');
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
	 * @return array|\CURLFile[]
	 */
	public function getFiles() : array
	{
		return $this->files;
	}

	/**
	 * Set files for upload.
	 *
	 * @param array|string[] $files Fields as keys, paths of files as values
	 *
	 * @throws InvalidArgumentException for invalid file path
	 *
	 * @return $this
	 */
	public function setFiles(array $files)
	{
		$this->setMethod('POST');
		$this->setHeader('Content-Type', 'multipart/form-data');
		$this->files = [];
		foreach ($files as $field => $file) {
			if ( ! \is_file($file)) {
				throw new InvalidArgumentException(
					"Field '{$field}' does not match a file: {$file}"
				);
			}
			$this->files[$field] = \curl_file_create($file, \mime_content_type($file));
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

	public function setCookie(Cookie $cookie)
	{
		parent::setCookie($cookie);
		$this->setCookieHeader();
		return $this;
	}

	public function setCookies(array $cookies)
	{
		return parent::setCookies($cookies);
	}

	public function removeCookie(string $name)
	{
		parent::removeCookie($name);
		$this->setCookieHeader();
		return $this;
	}

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

	public function setHeader(string $name, string $value)
	{
		return parent::setHeader($name, $value);
	}

	public function setHeaders(array $headers)
	{
		return parent::setHeaders($headers);
	}

	public function removeHeader(string $name)
	{
		return parent::removeHeader($name);
	}

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
	 * @param string|null $user_agent
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent
	 *
	 * @return $this
	 */
	public function setUserAgent(string $user_agent = null)
	{
		$user_agent ??= 'HTTP Client';
		return $this->setHeader('User-Agent', $user_agent);
	}
}
