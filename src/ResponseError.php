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

/**
 * Class ResponseError.
 *
 * @package http-client
 */
class ResponseError
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
    public function getInfo() : array
    {
        return $this->info;
    }
}
