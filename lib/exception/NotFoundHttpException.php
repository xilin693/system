<?php

namespace king\lib\exception;

class NotFoundHttpException extends HttpException
{
    const HTTP_CODE = 404;
    const ERROR = 'not_found';

    public function __construct($error = '')
    {
        $error = $error ?: self::ERROR;
        parent::__construct(self::HTTP_CODE, $error);
    }
}
