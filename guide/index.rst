HTTP Client
===========

.. image:: image.png
    :alt: Aplus Framework HTTP Client Library

Aplus Framework HTTP (HyperText Transfer Protocol) Client Library.

- `Installation`_
- `Usage`_
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
    $request->setMethod('POST');
    $request->setBasicAuth('johndoe', 'abc123');
    $request->setJson(['name' => 'John Doe']);

    $response = $client->run($request);

    echo $response->getStatus();
    echo $response->getBody();

Conclusion
----------

Aplus HTTP Client Library is an easy-to-use tool for, beginners and experienced,
PHP developers. 
It is perfect for building, simple and full-featured, HTTP interactions. 
The more you use it, the more you will learn.

.. note::
    Did you find something wrong? 
    Be sure to let us know about it with an
    `issue <https://gitlab.com/aplus-framework/libraries/http-client/issues>`_. 
    Thank you!
