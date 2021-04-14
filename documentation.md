# HTTP Client Library *documentation*

To obtain external data it is necessary to use a client to interact with a server.

The HTTP Client library contains a powerful Client that can be used as:

```php
use Framework\HTTP\Client\Client;
use Framework\HTTP\Client\Request;

$request = new Request('http://domain.tld');
$client = new Client();
$request->setJSON(['name' => 'John']);
$response = $client->run($request);
echo $response->getBody();
```
