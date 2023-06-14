<?php

namespace king\lib\exception;

use king\lib\Response;

class CodeException extends \RuntimeException
{
    public $status_code;
    public $error;

    public function __construct(int $status_code = null, string $error = null, \Exception $previous = null)
    {
        $this->status_code = $status_code ?? 500;
        $this->error = $error ?? 'internal_server_error';
        Response::sendResponseCode($status_code, $error, $type = 'exception');
    }

    public function addHeader($header)
    {
        $this->headers[] = $header;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->status_code;
    }

    public function setStatusCode(int $status_code): HttpException
    {
        $this->status_code = $statusCode;

        return $this;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function setError(string $error): HttpException
    {
        $this->error = $error;

        return $this;
    }
}
