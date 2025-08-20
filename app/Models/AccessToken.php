<?php

namespace App\Models;

use App\Models\Concerns\GrantRegistryToken;
use App\Models\Contracts\CanGrantRegistryAccess;
use App\Registry\Grant;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @property int $id
 * @property string $token
 * @property int $user_id
 * @property string|null $description
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AccessControl> $access_controls
 * @property-read int|null $access_controls_count
 * @property-read Collection<int, AccessToken> $access_tokens
 * @property-read int|null $access_tokens_count
 * @property-read User $user
 * @method static Builder<static>|AccessToken newModelQuery()
 * @method static Builder<static>|AccessToken newQuery()
 * @method static Builder<static>|AccessToken query()
 * @mixin Eloquent
 */
class AccessToken extends Model implements CanGrantRegistryAccess
{
    use GrantRegistryToken {
        grantScope as protected grantScopeLocal;
    }

    /**
     * @return BelongsTo<User, static>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return MorphMany<AccessControl, static>
     */
    public function access_controls(): MorphMany
    {
        return $this->morphMany(AccessControl::class, 'owner')
            ->orderBy('sort_order');
    }

    public function casts(): array
    {
        return [
            'token' => 'hashed',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function canListCatalog(): bool
    {
        return $this->user->canListCatalog();
    }

    public function grantScope(string|Grant $scope): ?Grant
    {
        if (is_string($scope)) {
            $scope = Grant::fromString($scope);
        }
        $userGrant = $this->user->grantScope($scope);
        if (!$userGrant) return null;
        $localGrant = $this->grantScopeLocal($scope);
        if (!$localGrant) return null;
        return $userGrant->restrictTo($localGrant->actions);
    }

    public function getAllAccessControls(): BaseCollection
    {
        return $this->access_controls;
    }

    function getUsername(): string
    {
        return $this->user->getUsername();
    }
}
