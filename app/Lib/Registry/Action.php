<?php

namespace App\Lib\Registry;

enum Action: string
{
    case ANY = '*';
    case PULL = 'pull';
    case PUSH = 'push';
    case DELETE = 'delete';
}
