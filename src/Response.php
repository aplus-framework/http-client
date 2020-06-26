<?php namespace Framework\HTTP\Client;

use Framework\HTTP\Cookie;
use Framework\HTTP\Message;
use Framework\HTTP\ResponseInterface;

/**
 * Class Response.
 */
class Response extends Message implements ResponseInterface
{
	protected string $protocol;
	protected int $statusCode;
	protected string $statusReason;

	public function __construct(
		string $protocol,
		int $status,
		string $reason,
		array $headers,
		string $body
	) {
		$this->setProtocol($protocol);
		$this->setStatusCode($status);
		$this->setStatusReason($reason);
		foreach ($headers as $name => $value) {
			$this->setHeader($name, $value);
		}
		$this->setBody($body);
	}

	public function getStatusCode() : int
	{
		return $this->statusCode;
	}

	protected function setStatusCode(int $statusCode)
	{
		$this->statusCode = $statusCode;
		return $this;
	}

	public function getStatusReason() : string
	{
		return $this->statusReason;
	}

	protected function setStatusReason(string $statusReason)
	{
		$this->statusReason = $statusReason;
		return $this;
	}

	protected function setHeader(string $name, string $value)
	{
		if (\strtolower($name) === 'set-cookie') {
			$cookie = Cookie::parse($value);
			if ($cookie) {
				$this->setCookie($cookie);
			}
		}
		return parent::setHeader($name, $value);
	}

	/**
	 * @param bool $assoc
	 * @param int  $options
	 * @param int  $depth
	 *
	 * @return array|false|object
	 */
	public function getJSON(bool $assoc = false, int $options = null, int $depth = 512)
	{
		if ($options === null) {
			$options = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
		}
		$body = \json_decode($this->getBody(), $assoc, $depth, $options);
		if (\json_last_error() !== \JSON_ERROR_NONE) {
			return false;
		}
		return $body;
	}

	public function isJSON() : bool
	{
		return $this->parseContentType() === 'application/json';
	}

	public function getStatusLine() : string
	{
		return $this->getStatusCode() . ' ' . $this->getStatusReason();
	}
}
