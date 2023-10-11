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

use Framework\HTTP\Client\Request;
use Framework\HTTP\Client\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    protected Response $response;

    protected function setUp() : void
    {
        $this->response = new Response(
            new Request('http://localhost'),
            'HTTP/1.1',
            200,
            'OK',
            [
                'Foo' => ['Foo'],
                'content-Type' => ['text/html'],
                'set-cookie' => ['foo=bar; expires=Thu, 11-Jul-2019 04:57:19 GMT; Max-Age=0'],
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
        self::assertFalse($this->response->isStatusCode(404));
        self::assertTrue($this->response->isStatusCode(200));
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
        self::assertFalse($this->response->isJson());
        self::assertFalse($this->response->getJson());
        $this->response = new Response(
            new Request('http://localhost'),
            'HTTP/1.1',
            200,
            'OK',
            ['content-type' => ['application/json']],
            '{"a":1}'
        );
        self::assertTrue($this->response->isJson());
        self::assertIsObject($this->response->getJson());
    }

    public function testStatus() : void
    {
        self::assertSame('200 OK', $this->response->getStatus());
    }

    public function testToString() : void
    {
        $startLine = 'HTTP/1.1 200 OK';
        $headerLines = [
            'foo: Foo',
            'Content-Type: text/html',
            'Set-Cookie: foo=bar; expires=Thu, 11-Jul-2019 04:57:19 GMT; Max-Age=0',
        ];
        $blankLine = '';
        $body = 'body';
        self::assertSame(
            \implode("\r\n", [$startLine, ...$headerLines, $blankLine, $body]),
            (string) $this->response
        );
    }

    public function testCookies() : void
    {
        $response = new Response(
            new Request('http://domain.tld'),
            'HTTP/1.1',
            200,
            'OK',
            [
                'content-type' => ['application/json'],
                'set-cookie' => [
                    'foo=bar; expires=Thu, 11-Jul-2019 04:57:19 GMT; Max-Age=0',
                    'baz=baba',
                ],
            ],
            '{"a":1}'
        );
        self::assertSame(['foo', 'baz'], \array_keys($response->getCookies()));
    }

    /**
     * @return array<array<mixed>>
     */
    public function linksProvider() : array
    {
        return [
            [
                '<https://domain.tld/search?page=2>; rel="next", ' .
                '<https://domain.tld/search?page=34>; rel="last"',
                [
                    'next' => 'https://domain.tld/search?page=2',
                    'last' => 'https://domain.tld/search?page=34',
                ],
            ],
            [
                '<https://domain.tld/search?page=15>; rel="next", ' .
                '<https://domain.tld/search?page=34>; rel="last", ' .
                '<https://domain.tld/search?page=1>; rel="first", ' .
                '<https://domain.tld/search?page=13>; rel="prev"',
                [
                    'next' => 'https://domain.tld/search?page=15',
                    'last' => 'https://domain.tld/search?page=34',
                    'first' => 'https://domain.tld/search?page=1',
                    'prev' => 'https://domain.tld/search?page=13',
                ],
            ],
            [
                '<https://domain.tld/search?page=15>; rel="next", ' .
                '<https://domain.tld/search?page=34>; rel="last"; rel="error", ' .
                '<https://domain.tld/search?page=1>; rel="first", ' .
                '<https://domain.tld/search?page=13>; rel="prev"',
                [
                    'next' => 'https://domain.tld/search?page=15',
                    'first' => 'https://domain.tld/search?page=1',
                    'prev' => 'https://domain.tld/search?page=13',
                ],
            ],
            [
                'foo',
                [],
            ],
            [
                '0',
                [],
            ],
        ];
    }

    /**
     * @dataProvider linksProvider
     *
     * @param string $header
     * @param array<string,string> $links
     */
    public function testLinks(string $header, array $links) : void
    {
        $response = new Response(
            new Request('https://domain.tld'),
            'HTTP/1.1',
            200,
            'OK',
            [
                'link' => [$header],
            ],
            ''
        );
        self::assertSame($links, $response->getLinks());
    }
}
