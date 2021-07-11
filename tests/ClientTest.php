<?php
/*
 * This file is part of The Framework HTTP Client Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Tests\HTTP\Client;

use Framework\HTTP\Client\Client;
use Framework\HTTP\Client\Request;
use Framework\HTTP\Client\Response;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    protected Client $client;

    protected function setUp() : void
    {
        $this->client = new Client();
    }

    public function testOptions() : void
    {
        $defaultOptions = [
            \CURLOPT_CONNECTTIMEOUT => 10,
            \CURLOPT_TIMEOUT => 60,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTPS | \CURLPROTO_HTTP,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_MAXREDIRS => 1,
            \CURLOPT_AUTOREFERER => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HTTP09_ALLOWED => true,
        ];
        self::assertSame($defaultOptions, $this->client->getOptions());
        $this->client->setOption(\CURLOPT_RETURNTRANSFER, false);
        self::assertSame([
            \CURLOPT_CONNECTTIMEOUT => 10,
            \CURLOPT_TIMEOUT => 60,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTPS | \CURLPROTO_HTTP,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_MAXREDIRS => 1,
            \CURLOPT_AUTOREFERER => true,
            \CURLOPT_RETURNTRANSFER => false,
            \CURLOPT_HTTP09_ALLOWED => true,
        ], $this->client->getOptions());
        $this->client->reset();
        self::assertSame($defaultOptions, $this->client->getOptions());
    }

    public function testRun() : void
    {
        $request = new Request('https://www.google.com');
        $request->setHeader('Content-Type', 'text/html');
        $response = $this->client->run($request);
        self::assertInstanceOf(Response::class, $response);
        self::assertGreaterThan(100, \strlen($response->getBody()));
        $this->client->setOption(\CURLOPT_RETURNTRANSFER, false);
        \ob_start(); // Avoid terminal output
        $response = $this->client->run($request);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('', $response->getBody());
        self::assertGreaterThan(100, \strlen((string) \ob_get_contents()));
        self::assertArrayHasKey('connect_time', $this->client->getInfo());
        \ob_end_clean();
    }

    public function testTimeout() : void
    {
        $this->client->setRequestTimeout(10);
        $this->client->setResponseTimeout(20);
        self::assertContainsEquals([
            \CURLOPT_CONNECTTIMEOUT => 10,
            \CURLOPT_TIMEOUT => 20,
        ], $this->client->getOptions());
    }

    public function testProtocols() : void
    {
        $request = new Request('https://www.google.com');
        $request->setProtocol('HTTP/1.1');
        self::assertSame('HTTP/1.1', $request->getProtocol());
        $response = $this->client->run($request);
        self::assertSame('HTTP/1.1', $response->getProtocol());
        $this->client->reset();
        $request->setProtocol('HTTP/2.0');
        self::assertSame('HTTP/2.0', $request->getProtocol());
        $response = $this->client->run($request);
        self::assertSame('HTTP/2', $response->getProtocol());
        $this->client->reset();
        $request->setProtocol('HTTP/1.0');
        self::assertSame('HTTP/1.0', $request->getProtocol());
        $response = $this->client->run($request);
        self::assertSame('HTTP/1.0', $response->getProtocol());
    }

    public function testMethods() : void
    {
        $request = new Request('https://www.google.com');
        $request->setMethod('post');
        self::assertSame('POST', $request->getMethod());
        $response = $this->client->run($request);
        self::assertInstanceOf(Response::class, $response);
        $request->setMethod('put');
        self::assertSame('PUT', $request->getMethod());
        $response = $this->client->run($request);
        self::assertInstanceOf(Response::class, $response);
    }

    public function testRunError() : void
    {
        $request = new Request('http://domain.tld');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not resolve host: domain.tld');
        $this->client->run($request);
    }

    public function testPostAndFiles() : void
    {
        $request = new Request('https://www.google.com');
        $request->setFiles(['file' => __FILE__]);
        self::assertTrue($request->hasFiles());
        $this->client->run($request);
    }
}
