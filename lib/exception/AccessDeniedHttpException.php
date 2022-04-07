<?php

namespace king\lib\exception;

class AccessDeniedHttpException extends HttpException
{
    const HTTP_CODE = 403;
    const ERROR = 'access_denied';

    public function __construct($error = '')
    {
        $error = $error ?: self::ERROR;
        parent::__construct(self::HTTP_CODE, $error);
    }
}
