<?php

namespace App\Registry;

enum ErrorCode: string
{
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case DENIED = 'DENIED';
    case UNSUPPORTED = 'UNSUPPORTED';
}
