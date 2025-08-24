<?php

namespace App\Lib\Registry;

enum ResourceType: string
{
    case REPOSITORY = 'repository';
    case PLUGIN = 'repository(plugin)';
    case REGISTRY = 'registry';
}
