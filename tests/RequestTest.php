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
use Framework\HTTP\Cookie;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    protected Request $request;

    protected function setUp() : void
    {
        $this->request = new Request('http://localhost');
    }

    public function testProtocol() : void
    {
        self::assertSame('HTTP/1.1', $this->request->getProtocol());
        $this->request->setProtocol('HTTP/2.0');
        self::assertSame('HTTP/2.0', $this->request->getProtocol());
    }

    public function testMethod() : void
    {
        self::assertSame('GET', $this->request->getMethod());
        $this->request->setMethod('post');
        self::assertSame('POST', $this->request->getMethod());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP Request Method: Foo');
        $this->request->setMethod('Foo');
    }

    public function testURL() : void
    {
        self::assertSame('http://localhost/', (string) $this->request->getUrl());
    }

    public function testHeaders() : void
    {
        self::assertSame([
            'host' => 'localhost',
        ], $this->request->getHeaders());
        self::assertNull($this->request->getHeader('Foo'));
        $this->request->setHeaders([
            'Foo' => 'Foo',
            'content-Type' => 'text/html',
            'custom' => 'a',
        ]);
        self::assertSame('Foo', $this->request->getHeader('Foo'));
        self::assertSame('text/html', $this->request->getHeader('content-type'));
        self::assertSame('a', $this->request->getHeader('custom'));
        $this->request->removeHeader('custom');
        self::assertNull($this->request->getHeader('custom'));
        self::assertSame([
            'host' => 'localhost',
            'foo' => 'Foo',
            'content-type' => 'text/html',
        ], $this->request->getHeaders());
        $this->request->removeHeaders();
        self::assertSame([], $this->request->getHeaders());
    }

    public function testCookies() : void
    {
        self::assertEmpty($this->request->getCookies());
        self::assertNull($this->request->getHeader('cookie'));
        $this->request->setCookie(new Cookie('session', 'abc123'));
        self::assertNotEmpty($this->request->getCookies());
        self::assertSame('session=abc123', $this->request->getHeader('cookie'));
        self::assertSame('abc123', $this->request->getCookie('session')->getValue());
        $this->request->setCookie(new Cookie('foo', 'bar'));
        self::assertSame('session=abc123; foo=bar', $this->request->getHeader('cookie'));
        $this->request->removeCookie('session');
        self::assertSame('foo=bar', $this->request->getHeader('cookie'));
        $this->request->removeCookies(['foo']);
        self::assertNull($this->request->getHeader('cookie'));
        $this->request->setCookies([
            new Cookie('j', 'jota'),
            new Cookie('m', 'eme'),
        ]);
        self::assertSame('j=jota; m=eme', $this->request->getHeader('cookie'));
    }

    public function testBody() : void
    {
        self::assertSame('', $this->request->getBody());
        $this->request->setBody('body');
        self::assertSame('body', $this->request->getBody());
        $this->request->setBody(['a' => 1]);
        self::assertSame('a=1', $this->request->getBody());
    }

    public function testContentType() : void
    {
        self::assertNull($this->request->getHeader('content-type'));
        $this->request->setContentType('text/html');
        self::assertSame('text/html; charset=UTF-8', $this->request->getHeader('content-type'));
    }

    public function testFiles() : void
    {
        self::assertFalse($this->request->hasFiles());
        self::assertSame('GET', $this->request->getMethod());
        self::assertSame([], $this->request->getFiles());
        $this->request->setFiles(['upload' => __FILE__]);
        self::assertTrue($this->request->hasFiles());
        self::assertSame('POST', $this->request->getMethod());
        self::assertInstanceOf(\CURLFile::class, $this->request->getFiles()['upload']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'foo' does not match a file: /tmp/unknown-00");
        $this->request->setFiles(['foo' => '/tmp/unknown-00']);
    }

    public function testPOST() : void
    {
        self::assertSame('GET', $this->request->getMethod());
        $this->request->setPost(['a' => 1, 'b' => 2]);
        self::assertSame('POST', $this->request->getMethod());
        self::assertSame('a=1&b=2', $this->request->getBody());
    }

    public function testJSON() : void
    {
        self::assertNull($this->request->getHeader('content-type'));
        $this->request->setJson(['a' => 1]);
        self::assertSame(
            'application/json; charset=UTF-8',
            $this->request->getHeader('content-type')
        );
        self::assertSame('{"a":1}', $this->request->getBody());
    }

    public function testBasicAuth() : void
    {
        self::assertEmpty($this->request->getHeader('authorization'));
        $this->request->setBasicAuth('foo', 'bar');
        self::assertSame(
            'Basic ' . \base64_encode('foo:bar'),
            $this->request->getHeader('authorization')
        );
    }

    public function testUserAgent() : void
    {
        self::assertEmpty($this->request->getHeader('user-agent'));
        $this->request->setUserAgent();
        self::assertSame('HTTP Client', $this->request->getHeader('user-agent'));
        $this->request->setUserAgent('Other');
        self::assertSame('Other', $this->request->getHeader('user-agent'));
    }

    public function testToString() : void
    {
        $startLine = 'GET / HTTP/1.1';
        $headerLines = [
            'Host: localhost',
        ];
        $blankLine = '';
        $body = '';
        self::assertSame(
            \implode("\r\n", [$startLine, ...$headerLines, $blankLine, $body]),
            (string) $this->request
        );
    }
}
