<?php

namespace App\Models\Contracts;

use App\Models\AccessControl;
use App\Registry\Grant;
use Illuminate\Support\Collection;

interface CanGrantRegistryAccess
{
    function getUsername(): string;

    /**
     * @return bool
     */
    function canListCatalog(): bool;

    /**
     * @return Collection<array-key, AccessControl>
     */
    function getAllAccessControls(): Collection;

    /**
     * @param string|Grant $scope
     * @return Grant|null
     */
    function grantScope(string|Grant $scope): ?Grant;

    /**
     * @return array<array-key, Grant>
     */
    function grant(string $scopes): array;
}
