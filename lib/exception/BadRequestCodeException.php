<?php

namespace king\lib\exception;

class BadRequestCodeException extends CodeException
{
    const HTTP_CODE = 400;
    const ERROR = 'bad_request';

    public function __construct($error = '')
    {
        $error = $error ?: self::ERROR;
        parent::__construct(self::HTTP_CODE, $error);
    }
}
