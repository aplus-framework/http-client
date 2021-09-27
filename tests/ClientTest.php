<?php
/*
 * This file is part of Aplus Framework HTTP Client Library.
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

    public function testRun() : void
    {
        $request = new Request('https://www.google.com');
        $request->setHeader('Content-Type', 'text/html');
        $response = $this->client->run($request);
        self::assertInstanceOf(Response::class, $response);
        self::assertGreaterThan(100, \strlen($response->getBody()));
        $request->setOption(\CURLOPT_RETURNTRANSFER, false);
        \ob_start(); // Avoid terminal output
        $response = $this->client->run($request);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('', $response->getBody());
        self::assertGreaterThan(100, \strlen((string) \ob_get_contents()));
        self::assertArrayHasKey('connect_time', $this->client->getInfo());
        \ob_end_clean();
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
        $request->setProtocol('HTTP/2');
        self::assertSame('HTTP/2', $request->getProtocol());
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

    public function testRunWithRequestDownloadFunction() : void
    {
        $page = '';
        $request = new Request('https://www.google.com');
        $request->setDownloadFunction(static function ($data, $handle) use (&$page) : void {
            self::assertIsString($data);
            self::assertInstanceOf(\CurlHandle::class, $handle);
            $page .= $data;
        });
        $response = $this->client->run($request);
        self::assertSame('', $response->getBody());
        self::assertStringContainsString('<!doctype html>', $page);
        self::assertStringContainsString('</html>', $page);
    }
}
