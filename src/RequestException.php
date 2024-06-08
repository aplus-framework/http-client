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
use RuntimeException;
use Throwable;

/**
 * Class RequestException.
 *
 * @package http-client
 */
class RequestException extends RuntimeException
{
    /**
     * @var array<mixed>
     */
    protected array $info;

    /**
     * RequestException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array<mixed> $info
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $info = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->info = $info;
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
