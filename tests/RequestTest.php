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
        self::assertSame('Aplus HTTP Client', $this->request->getHeader('user-agent'));
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

    public function testToStringMultipart() : void
    {
        $file = __DIR__ . '/support/foo.txt';
        $request = new Request('http://localhost');
        $request->setPost(['location' => ['country' => 'br']]);
        $request->setFiles([
            'upload' => $file,
            'foo' => [
                'bar' => new \CURLFile($file, posted_filename: 'chikorita.ppk'),
            ],
        ]);
        $message = (string) $request;
        self::assertStringContainsString(
            'Content-Type: multipart/form-data; charset=UTF-8; boundary=',
            $message
        );
        self::assertStringContainsString(
            'Content-Length: 466',
            $message
        );
        self::assertStringContainsString(
            'Content-Disposition: form-data; name="location[country]"',
            $message
        );
        self::assertStringContainsString(
            'Content-Disposition: form-data; name="upload"; filename="foo.txt"',
            $message
        );
        self::assertStringContainsString(
            'Content-Disposition: form-data; name="foo[bar]"; filename="chikorita.ppk"',
            $message
        );
        self::assertStringContainsString(
            'Content-Type: text/plain',
            $message
        );
        self::assertStringContainsString(
            'Content-Type: application/octet-stream',
            $message
        );
        self::assertStringContainsString(
            \file_get_contents($file), // @phpstan-ignore-line
            $message
        );
    }

    public function testOptions() : void
    {
        $options = $this->request->getOptions();
        self::assertArrayHasKey(\CURLOPT_PROTOCOLS, $options);
        self::assertArrayHasKey(\CURLOPT_CONNECTTIMEOUT, $options);
        self::assertArrayHasKey(\CURLOPT_TIMEOUT, $options);
        self::assertArrayHasKey(\CURLOPT_FOLLOWLOCATION, $options);
        self::assertArrayHasKey(\CURLOPT_MAXREDIRS, $options);
        self::assertArrayHasKey(\CURLOPT_AUTOREFERER, $options);
        self::assertArrayHasKey(\CURLOPT_RETURNTRANSFER, $options);
        self::assertArrayHasKey(\CURLOPT_HTTP_VERSION, $options);
        self::assertArrayHasKey(\CURLOPT_CUSTOMREQUEST, $options);
        self::assertArrayHasKey(\CURLOPT_HEADER, $options);
        self::assertArrayHasKey(\CURLOPT_URL, $options);
        self::assertArrayHasKey(\CURLOPT_HTTPHEADER, $options);
    }

    public function testDownloadFunction() : void
    {
        $this->request->setDownloadFunction(static function () : void {
        });
        self::assertArrayHasKey(
            \CURLOPT_WRITEFUNCTION,
            $this->request->getOptions()
        );
    }

    public function testPostAndFiles() : void
    {
        $request = new Request('https://www.google.com');
        $request->setFiles(['file' => __FILE__]);
        self::assertTrue($request->hasFiles());
    }

    public function testGetPostAndFiles() : void
    {
        $request = new Request('http://foo.com');
        self::assertSame('', $request->getPostAndFiles());
        $request->setBody(['foo' => 123]);
        self::assertSame('foo=123', $request->getPostAndFiles());
        $request->setFiles([
            'one' => __FILE__,
            'two' => [
                'three' => __FILE__,
            ],
            'four' => new \CURLFile(__FILE__),
        ]);
        $postAndFiles = $request->getPostAndFiles();
        self::assertSame('123', $postAndFiles['foo']); // @phpstan-ignore-line
        self::assertInstanceOf(\CURLFile::class, $postAndFiles['one']); // @phpstan-ignore-line
        self::assertInstanceOf(\CURLFile::class, $postAndFiles['one']); // @phpstan-ignore-line
        self::assertInstanceOf(\CURLFile::class, $postAndFiles['four']); // @phpstan-ignore-line
        $request->setFiles([
            'foo' => 'bar.war',
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Field 'foo' does not match a file: bar.war"
        );
        $request->getPostAndFiles();
    }

    public function testCheckOptionBool() : void
    {
        $this->request->setCheckOptions();
        $this->request->setOption(\CURLOPT_AUTOREFERER, true);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            \sprintf('The value of option %d should be of bool type', \CURLOPT_AUTOREFERER)
        );
        $this->request->setOption(\CURLOPT_AUTOREFERER, 1);
    }

    public function testCheckOptionInt() : void
    {
        $this->request->setCheckOptions();
        $this->request->setOption(\CURLOPT_TIMEOUT, 1000);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            \sprintf('The value of option %d should be of int type', \CURLOPT_TIMEOUT)
        );
        $this->request->setOption(\CURLOPT_TIMEOUT, '1000');
    }

    public function testCheckOptionString() : void
    {
        $this->request->setCheckOptions();
        $this->request->setOption(\CURLOPT_URL, 'http://foo.com');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            \sprintf('The value of option %d should be of string type', \CURLOPT_URL)
        );
        $this->request->setOption(\CURLOPT_URL, true);
    }

    public function testCheckOptionArray() : void
    {
        $this->request->setCheckOptions();
        $this->request->setOption(\CURLOPT_HTTPHEADER, ['Accept: */*']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            \sprintf('The value of option %d should be of array type', \CURLOPT_HTTPHEADER)
        );
        $this->request->setOption(\CURLOPT_HTTPHEADER, 'Accept: */*');
    }

    public function testCheckOptionFopen() : void
    {
        $this->request->setCheckOptions();
        $file = \fopen(__FILE__, 'rb');
        $this->request->setOption(\CURLOPT_FILE, $file);
        \fclose($file); // @phpstan-ignore-line
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            \sprintf('The value of option %d should be a fopen() resource', \CURLOPT_FILE)
        );
        $this->request->setOption(\CURLOPT_FILE, __FILE__);
    }

    public function testCheckOptionFunction() : void
    {
        $this->request->setCheckOptions();
        $this->request->setOption(\CURLOPT_HEADERFUNCTION, static function () : void {
        });
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            \sprintf('The value of option %d should be a callable', \CURLOPT_HEADERFUNCTION)
        );
        $this->request->setOption(\CURLOPT_HEADERFUNCTION, 23);
    }

    public function testCheckOptionCurlShareInit() : void
    {
        $this->request->setCheckOptions();
        $this->request->setOption(\CURLOPT_SHARE, \curl_share_init());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            \sprintf(
                'The value of option %d should be a result of curl_share_init()',
                \CURLOPT_SHARE
            )
        );
        $this->request->setOption(\CURLOPT_SHARE, 'foo');
    }

    public function testCheckOptionInvalidConstant() : void
    {
        $this->request->setCheckOptions();
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage(
            'Invalid cURL constant option: 123456'
        );
        $this->request->setOption(123456, 'foo');
    }
}
