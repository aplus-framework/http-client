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

/**
 * Class ClientMock.
 */
class ClientMock extends Client
{
    public function getPostAndFiles(Request $request) : array | string
    {
        return parent::getPostAndFiles($request);
    }
}
