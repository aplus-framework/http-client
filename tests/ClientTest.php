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
use Framework\HTTP\Protocol;
use Framework\HTTP\ResponseHeader;
use Framework\HTTP\Status;
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
        self::assertSame($request, $response->getRequest());
        self::assertInstanceOf(Response::class, $response);
        self::assertGreaterThan(100, \strlen($response->getBody()));
        self::assertEmpty($response->getInfo());
        $request->setOption(\CURLOPT_RETURNTRANSFER, false);
        \ob_start(); // Avoid terminal output
        $request->setGetResponseInfo();
        $response = $this->client->run($request);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('', $response->getBody());
        self::assertGreaterThan(100, \strlen((string) \ob_get_contents()));
        self::assertArrayHasKey('connect_time', $response->getInfo());
        \ob_end_clean();
    }

    public function testProtocolsAndReasons() : void
    {
        $request = new Request('https://www.google.com');
        $request->setProtocol('HTTP/1.1');
        self::assertSame('HTTP/1.1', $request->getProtocol());
        $response = $this->client->run($request);
        self::assertSame('HTTP/1.1', $response->getProtocol());
        self::assertSame('OK', $response->getStatusReason());
        $request->setProtocol('HTTP/2.0');
        self::assertSame('HTTP/2.0', $request->getProtocol());
        $response = $this->client->run($request);
        self::assertSame('HTTP/2', $response->getProtocol());
        self::assertSame('OK', $response->getStatusReason());
        $request->setProtocol('HTTP/2');
        self::assertSame('HTTP/2', $request->getProtocol());
        $response = $this->client->run($request);
        self::assertSame('HTTP/2', $response->getProtocol());
        $request->setProtocol('HTTP/1.0');
        self::assertSame('HTTP/1.0', $request->getProtocol());
        $response = $this->client->run($request);
        self::assertSame('HTTP/1.0', $response->getProtocol());
        self::assertSame('OK', $response->getStatusReason());
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

    public function testRunMulti() : void
    {
        $req1 = new Request('https://www.google.com/search?q=hihi'); // Third to finish
        $req1->setProtocol(Protocol::HTTP_1_1);
        $req1->setUserAgent('curl/7.68.0');
        $req2 = new Request('http://google.com'); // First to finish
        $req2->setProtocol(Protocol::HTTP_2);
        $req3 = new Request('http://www.google.com'); // Second to finish
        $req3->setProtocol(Protocol::HTTP_2);
        $requests = [
            'req1' => $req1,
            'req2' => $req2,
            'req3' => $req3,
        ];
        $finished = [];
        $responses = $this->client->runMulti($requests);
        while ($responses->valid()) {
            $key = $responses->key();
            self::assertArrayHasKey($key, $requests);
            $current = $responses->current();
            self::assertInstanceOf(Response::class, $current);
            $finished[$key] = $current;
            $responses->next();
        }
        self::assertSame([
            'req2',
            'req3',
            'req1',
        ], \array_keys($finished));
        self::assertSame($requests['req1'], $finished['req1']->getRequest());
        self::assertSame($requests['req2'], $finished['req2']->getRequest());
        self::assertSame($requests['req3'], $finished['req3']->getRequest());
        self::assertSame(
            Status::FORBIDDEN,
            $finished['req1']->getStatusCode()
        );
        self::assertStringContainsString(
            'all we know',
            $finished['req1']->getBody()
        );
        self::assertSame(
            Status::MOVED_PERMANENTLY,
            $finished['req2']->getStatusCode()
        );
        self::assertSame(
            'http://www.google.com/',
            $finished['req2']->getHeader(ResponseHeader::LOCATION)
        );
        self::assertSame(
            Status::OK,
            $finished['req3']->getStatusCode()
        );
    }

    public function testRunMultiWithResponseNotSet() : void
    {
        $requests = [
            new Request('http://not-exist.tld'),
            new Request('https://www.google.com'),
        ];
        $requests[0]->setGetResponseInfo();
        $requests[1]->setGetResponseInfo();
        $responses = $this->client->runMulti($requests);
        $returned = [];
        while ($responses->valid()) {
            $key = $responses->key();
            $current = $responses->current();
            $returned[$key] = $current;
            $responses->next();
        }
        self::assertArrayNotHasKey(0, $returned);
        self::assertArrayHasKey(1, $returned);
        self::assertSame(200, $returned[1]->getInfo()['http_code']);
    }
}
