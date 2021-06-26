<?php declare(strict_types=1);
/*
 * This file is part of The Framework HTTP Client Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\HTTP\Client;

use RuntimeException;

/**
 * Class Client.
 */
class Client
{
	/**
	 * Client default cURL options.
	 *
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

	/**
	 * Set cURL options.
	 *
	 * @param int $option A cURL CURLOPT_* constant
	 * @param mixed $value
	 *
	 * @return static
	 */
	public function setOption(int $option, mixed $value) : static
	{
		$this->options[$option] = $value;
		return $this;
	}

	/**
	 * Get default options replaced by custom.
	 *
	 * @return array<int,mixed>
	 */
	public function getOptions() : array
	{
		return \array_replace($this->defaultOptions, $this->options);
	}

	/**
	 * Get cURL info for the last request.
	 *
	 * @return array<string,mixed>
	 */
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
	protected function setHTTPVersion(string $version) : static
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
	 * Returns array for Content-Type multipart/form-data and string
	 * for application/x-www-form-urlencoded.
	 *
	 * @see https://www.php.net/manual/en/function.curl-setopt.php CURLOPT_POSTFIELDS
	 *
	 * @param Request $request
	 *
	 * @return array<string,mixed>|string
	 */
	protected function getPostAndFiles(Request $request) : array | string
	{
		if ($request->hasFiles()) {
			$body = $request->getBody();
			\parse_str($body, $body);
			return \array_replace_recursive($body, $request->getFiles());
		}
		return $request->getBody();
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
		$this->setHTTPVersion($request->getProtocol());
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
