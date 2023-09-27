HTTP Client
===========

.. image:: image.png
    :alt: Aplus Framework HTTP Client Library

Aplus Framework HTTP (HyperText Transfer Protocol) Client Library.

- `Installation`_
- `Usage`_
- `Request`_
- `Client`_
- `Response`_
- `Conclusion`_

Installation
------------

The installation of this library can be done with Composer:

.. code-block::

    composer require aplus/http-client

Usage
-----

The HTTP Client library is very simple and powerful which can be used as follows:

.. code-block:: php

    use Framework\HTTP\Client\Client;
    use Framework\HTTP\Client\Request;

    $client = new Client();

    $request = new Request('https://domain.tld/profile');
    $request->setMethod('POST'); // static
    $request->setBasicAuth('johndoe', 'abc123'); // static
    $request->setJson(['name' => 'John Doe']); // static

    $response = $client->run($request); // Framework\HTTP\Client\Response

    echo $response->getStatus();
    echo $response->getBody();

Request
-------

To perform the hypertext transfer it is necessary to send a request message.

The HTTP client needs objects of the Request class to connect to a URL address.

The object can be instantiated by passing the URL in the constructor:

.. code-block:: php

    use Framework\HTTP\Client\Request;

    $request = new Request('http://domain.tld');

Request URL
###########

The URL can be changed using the ``setUrl`` method:

.. code-block:: php

    $request->setUrl('http://domain.tld'); // static

Note that when the URL is changed, the Host header will be as well.

Request Protocol
################

With the Request object instantiated, it is possible to set the desired HTTP
protocol, through a string or a constant of the Protocol class:

.. code-block:: php

    use Framework\HTTP\Protocol;

    $request->setProtocol('HTTP/2'); // static
    $request->setProtocol(Protocol::HTTP_2); // static

Request Method
##############

By default, the request method is ``GET``. And, it can be changed through the
``setMethod`` method, passing a string or a constant from the Method class:

.. code-block:: php

    use Framework\HTTP\Method;

    $request->setMethod('post'); // static
    $request->setMethod(Method::POST); // static

Request Headers
###############

Headers can be passed via the header set methods.

Below we see an example using string and a constant of the Header class:

.. code-block:: php

    use Framework\HTTP\Header;

    $request->setHeader('Content-Type', 'application/json'); // static
    $request->setHeader(Header::CONTENT_TYPE, 'application/json'); // static

To set the Content-Type it is possible to use a method for this:

.. code-block:: php

    $request->setContentType('application/json'); // static

JSON
""""

When the request has the Content-Type as ``application/json`` and the body is a
JSON string, it is possible to set the header and the body at once using the
``setJson`` method:

.. code-block:: php

    $request->setJson($data); // static

Authorization
"""""""""""""

When working with APIs it is very common that a username and password (or token)
is required to perform authorization.

To set Authorization as ``Basic``, just use the ``setBasicAuth`` method:

.. code-block:: php

    $username = 'johndoe';
    $password = 'secr3t';
    $request->setBasicAuth($username, $password); // static

User-Agent
""""""""""

The default User-Agent can be set by calling the ``setUserAgent`` method and it
is also possible to pass a name to it:

.. code-block:: php

    $request->setUserAgent(); // static
    $request->setUserAgent('Aplus HTTP Client'); // static

Cookies
"""""""

Cookies can be set by the ``setCookie`` method:

.. code-block:: php

    use Framework\HTTP\Cookie;

    $cookie = new Cookie('session_id', 'abc123');
    $request->setCookie($cookie); // static

Post Forms
##########

To send data to a form you can set an array with the fields and values using the
``setPost`` method:

.. code-block:: php

    $request->setPost([
        'name' => 'John Doe',
        'email' => 'johndoe@foo.com',
    ]); // static

Request with Upload
###################

You can upload files with the ``setFiles`` method.

In it, you set the name of the array keys as field names and the values can be
the path to a file, an instance of **CURLFile** or **CURLStringFile**:

.. code-block:: php

    $request->setFiles([
        'invoices' => [
            __DIR__ . '/foo/invoice-10001.pdf',
            __DIR__ . '/foo/invoice-10002.pdf',
        ],
        'foo' => new CURLStringFile('foo', 'foo.txt', 'text/plain')
    ]); // static

Request to Download
###################

When making requests to download files, define a callback in the
``setDownloadFunction`` method, with the first parameter receiving the data
chunk:

.. code-block:: php

    $request->setDownloadFunction(function (string $data) {
        file_put_contents(__DIR__ . '/video.mp4', $data, FILE_APPEND);
    }); // static

Note that when this function is set the response body will be set to an
empty string.

Client
------

The HTTP client is capable of performing synchronous and asynchronous requests.

Let's see how to instantiate it:

.. code-block:: php

    use Framework\HTTP\Client\Client;

    $client = new Client();

A request can be made by passing a Request instance in the ``run`` method, which
will return a Response:

.. code-block:: php

    $response = $client->run($request); // Framework\HTTP\Client\Response

To perform asynchronous requests use the ``runMulti`` method, passing an array
with request identifiers as keys and Requests as values.

The ``runMulti`` method will return a **Generator** with the request id in the
key and a Response instance as a value.

Responses will be delivered as requests are finalized:

.. code-block:: php

    $requests = [
        1 => new Request('https://domain.tld/foo'),
        2 => new Request('https://domain.tld/bar'),
    ];

    foreach($client->runMulti($requests) as $id => $response) {
        echo "Request $id responded:";
        echo '<pre>' . htmlentities((string) $response) . '</pre>';
    }

Response
--------

After running a request in the client, it will return an instance of the
Response class.

Response Protocol
#################

With it it is possible to obtain the protocol:

.. code-block:: php

    $protocol = $response->getProtocol(); // string

Response Status
###############

Also, you can get the response status:

.. code-block:: php

    $response->getStatusCode(); // int
    $response->getStatusReason(); // string
    $response->getStatus(); // string

Response Headers
################

It is also possible to get all headers at once:

.. code-block:: php

    $headers = $response->getHeaders(); // array

Or, get the headers individually:

.. code-block:: php

    use Framework\HTTP\Header;

    $response->getHeader('Content-Type'); // string or null
    $response->getHeader(Header::CONTENT_TYPE); // string or null

Response Body
#############

The message body, when set, can be obtained with the ``getBody`` method:

.. code-block:: php

    $body = $response->getBody(); // string

JSON Response
#############

Also, you can check if the response content type is JSON and get the JSON data
as an object or array in PHP:

.. code-block:: php

    if ($response->isJson()) {
        $data = $response->getJson(); // object, array or false
    }

Conclusion
----------

Aplus HTTP Client Library is an easy-to-use tool for, beginners and experienced,
PHP developers. 
It is perfect for building, simple and full-featured, HTTP interactions. 
The more you use it, the more you will learn.

.. note::
    Did you find something wrong? 
    Be sure to let us know about it with an
    `issue <https://github.com/aplus-framework/http-client/issues>`_. 
    Thank you!
