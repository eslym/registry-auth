<?php

namespace App\Lib\Registry;

enum ErrorCode: string
{
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case DENIED = 'DENIED';
    case UNSUPPORTED = 'UNSUPPORTED';
}
