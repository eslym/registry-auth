<?php

namespace App\Models;

use App\Lib\Registry\Grant;
use App\Models\Concerns\GrantRegistryToken;
use App\Models\Contracts\CanGrantRegistryAccess;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $token
 * @property bool $is_refresh_token
 * @property int $user_id
 * @property string|null $description
 * @property Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property Carbon|null $expired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AccessControl> $access_controls
 * @property-read int|null $access_controls_count
 * @property-read User $user
 * @method static Builder<static>|AccessToken newModelQuery()
 * @method static Builder<static>|AccessToken newQuery()
 * @method static Builder<static>|AccessToken query()
 * @mixin Eloquent
 */
class AccessToken extends Model implements CanGrantRegistryAccess
{
    use MassPrunable;
    use GrantRegistryToken {
        grantScope as protected grantScopeLocal;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function (AccessToken $token) {
            if (!isset($token->attributes['token'])) {
                $token->generatedToken = Str::random(32);
                $token->token = $token->generatedToken;
            }
        });
    }

    protected $fillable = [
        'is_refresh_token',
        'token',
        'user_id',
        'description',
        'last_used_at',
        'last_used_ip',
        'expired_at',
    ];

    protected $hidden = ['token'];

    protected string|null $generatedToken = null;

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
            'is_refresh_token' => 'boolean',
            'token' => 'hashed',
            'last_used_at' => 'datetime',
            'expired_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getGeneratedToken(): ?string
    {
        return "$this->id|$this->generatedToken";
    }

    public function canListCatalog(): bool
    {
        return $this->user->canListCatalog();
    }

    public function grantScope(string|Grant $scope): ?Grant
    {
        if (is_string($scope)) {
            $scope = Grant::parse($scope);
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

    public function getUsername(): string
    {
        return $this->user->getUsername();
    }

    public function prunable(): Builder
    {
        return static::whereNotNull('expired_at')
            ->where('expired_at', '<=', now()->subDays(90));
    }
}
