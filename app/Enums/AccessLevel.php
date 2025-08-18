<?php

namespace App\Enums;

use App\Registry\Action;

enum AccessLevel: string
{
    case DENIED = 'denied';
    case PULL_ONLY = 'pull-only';
    case PULL_PUSH = 'pull-push';
    case FULL = 'full';

    public function toActions(): array {
        return match ($this) {
            self::DENIED => [],
            self::PULL_ONLY => [Action::PULL],
            self::PULL_PUSH => [Action::PULL, Action::PUSH],
            self::FULL => [Action::PULL, Action::PUSH, Action::DELETE],
        };
    }
}
