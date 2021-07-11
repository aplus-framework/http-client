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

use Framework\HTTP\Client\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    protected Response $response;

    protected function setUp() : void
    {
        $this->response = new Response(
            'HTTP/1.1',
            200,
            'OK',
            [
                'Foo' => 'Foo',
                'content-Type' => 'text/html',
                'set-cookie' => 'foo=bar; expires=Thu, 11-Jul-2019 04:57:19 GMT; Max-Age=0',
            ],
            'body'
        );
    }

    public function testProtocol() : void
    {
        self::assertSame('HTTP/1.1', $this->response->getProtocol());
    }

    public function testStatusCode() : void
    {
        self::assertSame(200, $this->response->getStatusCode());
    }

    public function testStatusReason() : void
    {
        self::assertSame('OK', $this->response->getStatusReason());
    }

    public function testHeaders() : void
    {
        self::assertSame([
            'foo' => 'Foo',
            'content-type' => 'text/html',
            'set-cookie' => 'foo=bar; expires=Thu, 11-Jul-2019 04:57:19 GMT; Max-Age=0',
        ], $this->response->getHeaders());
        self::assertSame('Foo', $this->response->getHeader('Foo'));
        self::assertSame('text/html', $this->response->getHeader('content-type'));
    }

    public function testBody() : void
    {
        self::assertSame('body', $this->response->getBody());
    }

    public function testJson() : void
    {
        self::assertFalse($this->response->isJSON());
        self::assertFalse($this->response->getJSON());
        $this->response = new Response(
            'HTTP/1.1',
            200,
            'OK',
            ['content-type' => 'application/json'],
            '{"a":1}'
        );
        self::assertTrue($this->response->isJSON());
        self::assertIsObject($this->response->getJSON());
    }

    public function testStatusLine() : void
    {
        self::assertSame('200 OK', $this->response->getStatusLine());
    }
}
