<?php namespace Framework\HTTP\Client;

use RuntimeException;

/**
 * Class Client.
 */
class Client
{
	/**
	 * Client default cURL options.
	 *
	 * @var array
	 */
	protected array $defaultOptions = [
		\CURLOPT_CONNECTTIMEOUT => 10,
		\CURLOPT_TIMEOUT => 60,
		\CURLOPT_FOLLOWLOCATION => true,
		\CURLOPT_MAXREDIRS => 1,
		\CURLOPT_AUTOREFERER => true,
		\CURLOPT_RETURNTRANSFER => true,
	];
	/**
	 * Custom cURL options.
	 *
	 * @var array|mixed[]
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
	 * @var array|string[]
	 */
	protected array $responseHeaders = [];
	/**
	 * Response cURL info.
	 *
	 * @var array|mixed[]
	 */
	protected array $info = [];

	/**
	 * Set cURL options.
	 *
	 * @param int   $option cURL CURLOPT_* constant
	 * @param mixed $value
	 *
	 * @return $this
	 */
	public function setOption(int $option, $value)
	{
		$this->options[$option] = $value;
		return $this;
	}

	/**
	 * Get default options replaced by custom.
	 *
	 * @return array|mixed[]
	 */
	public function getOptions() : array
	{
		return \array_replace($this->defaultOptions, $this->options);
	}

	/**
	 * Get cURL info for the last request.
	 *
	 * @return array|mixed[]
	 */
	public function getInfo() : array
	{
		return $this->info;
	}

	/**
	 * Set cURL timeout.
	 *
	 * @param int $timeout
	 *
	 * @return $this
	 */
	public function setResponseTimeout(int $timeout)
	{
		$this->setOption(\CURLOPT_TIMEOUT, $timeout);
		return $this;
	}

	/**
	 * Set cURL connect timeout.
	 *
	 * @param int $timeout
	 *
	 * @return $this
	 */
	public function setRequestTimeout(int $timeout)
	{
		$this->setOption(\CURLOPT_CONNECTTIMEOUT, $timeout);
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
	 * @return $this
	 */
	protected function setHTTPVersion(string $version)
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
	 * Run the Request.
	 *
	 * @param Request $request
	 *
	 * @throws RuntimeException for curl error
	 *
	 * @return Response
	 */
	public function run(Request $request) : Response
	{
		$this->setHTTPVersion($request->getProtocol());
		switch ($request->getMethod()) {
			case 'POST':
				$this->setOption(\CURLOPT_POST, true);
				$this->setOption(
					\CURLOPT_POSTFIELDS,
					$request->hasFiles() ? $request->getFiles() : $request->getBody()
				);
				break;
			case 'PUT':
			case 'PATCH':
			case 'DELETE':
				$this->setOption(\CURLOPT_POSTFIELDS, $request->getBody());
				break;
		}
		$this->setOption(\CURLOPT_CUSTOMREQUEST, $request->getMethod());
		$this->setOption(\CURLOPT_HEADER, false);
		$this->setOption(\CURLOPT_URL, $request->getURL()->getURL());
		$headers = [];
		foreach ($request->getHeaders() as $name => $value) {
			$headers[] = $name . ': ' . $value;
		}
		$this->setOption(\CURLOPT_HTTPHEADER, $headers);
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
			$body
		);
	}

	/**
	 * Parses Header line.
	 *
	 * @param resource $curl
	 * @param string   $line
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
				[
					$this->responseProtocol,
					$this->responseCode,
					$this->responseReason,
				] = \array_pad(\explode(' ', $trimmed_line, 3), 3, '');
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
