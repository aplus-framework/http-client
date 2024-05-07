<?php declare(strict_types=1);
/*
 * This file is part of Aplus Framework HTTP Client Library.
 *
 * (c) Natan Felles <natanfelles@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Framework\HTTP\Client;

use JetBrains\PhpStorm\ArrayShape;
use Stringable;

/**
 * Class ResponseError.
 *
 * @package http-client
 */
class ResponseError implements Stringable
{
    protected Request $request;
    protected string $error;
    protected int $errorNumber;
    /**
     * @var array<mixed>
     */
    protected array $info;

    /**
     * @param Request $request
     * @param string $error
     * @param int $errorNumber
     * @param array<mixed> $info
     */
    public function __construct(
        Request $request,
        string $error,
        int $errorNumber,
        array $info
    ) {
        $this->request = $request;
        $this->error = $error;
        $this->errorNumber = $errorNumber;
        $this->info = $info;
    }

    public function __toString() : string
    {
        return 'Error ' . $this->getErrorNumber() . ': ' . $this->getError();
    }

    public function getRequest() : Request
    {
        return $this->request;
    }

    public function getError() : string
    {
        return $this->error;
    }

    public function getErrorNumber() : int
    {
        return $this->errorNumber;
    }

    /**
     * @return array<mixed>
     */
    #[ArrayShape([
        'appconnect_time_us' => 'int',
        'certinfo' => 'array',
        'connect_time' => 'float',
        'connect_time_us' => 'int',
        'content_type' => 'string',
        'download_content_length' => 'float',
        'effective_method' => 'string',
        'filetime' => 'int',
        'header_size' => 'int',
        'http_code' => 'int',
        'http_version' => 'int',
        'local_ip' => 'string',
        'local_port' => 'int',
        'namelookup_time' => 'float',
        'namelookup_time_us' => 'int',
        'pretransfer_time' => 'float',
        'pretransfer_time_us' => 'int',
        'primary_ip' => 'string',
        'primary_port' => 'int',
        'protocol' => 'int',
        'redirect_count' => 'int',
        'redirect_time' => 'float',
        'redirect_time_us' => 'int',
        'redirect_url' => 'string',
        'request_size' => 'int',
        'scheme' => 'string',
        'size_download' => 'float',
        'size_upload' => 'float',
        'speed_download' => 'float',
        'speed_upload' => 'float',
        'ssl_verify_result' => 'int',
        'ssl_verifyresult' => 'int',
        'starttransfer_time' => 'float',
        'starttransfer_time_us' => 'int',
        'total_time' => 'float',
        'total_time_us' => 'int',
        'upload_content_length' => 'float',
        'url' => 'string',
    ])]
    public function getInfo() : array
    {
        return $this->info;
    }
}
