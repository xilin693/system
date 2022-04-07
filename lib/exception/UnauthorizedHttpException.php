<?php

namespace king\lib\exception;

class UnauthorizedHttpException extends HttpException
{
    const HTTP_CODE = 401;
    const ERROR = 'unauthorized';

    public function __construct($error = '')
    {
        $error = $error ?: self::ERROR;
        parent::__construct(self::HTTP_CODE, $error);
    }
}
