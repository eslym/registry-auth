<?php

namespace App\Models\Concerns;

use App\Lib\ACLGlob;
use App\Registry\Grant;
use App\Registry\ResourceType;

trait GrantRegistryToken
{
    public function grantScope(string|Grant $scope): ?Grant
    {
        $grant = is_string($scope) ? Grant::parse($scope) : $scope;
        if ($grant->isCatalog()) {
            return $this->canListCatalog() ? $grant : null;
        }
        if ($grant->type !== ResourceType::REPOSITORY) {
            return null;
        }
        foreach ($this->getAllAccessControls() as $control) {
            if (ACLGlob::match($control->rule, $grant->name)) {
                $allowed = $control->access_level->toActions();
                $grant = $grant->restrictTo($allowed);
                if (empty($grant->actions)) {
                    return null;
                }
                return $grant;
            }
        }
        return null;
    }

    /**
     * @return array<array-key, Grant>
     */
    public function grant(string $scopes): array
    {
        return collect(explode(' ', $scopes))->map(function (string $scope) {
            if (empty($scope)) {
                return null;
            }
            return $this->grantScope($scope);
        })
            ->filter(fn($g) => !empty($g?->actions))
            ->all();
    }
}
