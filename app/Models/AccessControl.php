<?php

namespace App\Models;

use App\Enums\AccessLevel;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $rule
 * @property AccessLevel $access_level
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|Group|AccessToken $owner
 * @method static Builder<static>|AccessControl newModelQuery()
 * @method static Builder<static>|AccessControl newQuery()
 * @method static Builder<static>|AccessControl query()
 * @mixin Eloquent
 */
class AccessControl extends Model
{
    use MassPrunable;

    protected $table = 'access_controls';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'rule',
        'access_level',
        'sort_order',
    ];

    /**
     * @return MorphTo<User|Group|AccessToken, static>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function casts(): array
    {
        return [
            'access_level' => AccessLevel::class,
        ];
    }

    /**
     * @throws Throwable
     */
    public static function syncWith(Model $owner, array $controls): void
    {
        DB::beginTransaction();
        try {
            $base = static::query()->where([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ]);
            if(empty($controls)) {
                $base->delete();
                DB::commit();
                return;
            }

            $existsRule = Arr::pluck($controls, 'rule');
            $updates = Arr::keyBy($controls, 'rule');

            $set = [];

            /** @var Collection<int, AccessControl> $exists */
            $exists = $base->clone()->whereIn('rule', $existsRule)->get()
                ->keyby('rule');

            foreach ($exists as $control) {
                $set[] = $control->id;
                $control->update($updates[$control->rule]);
                $exists->forget([$control->rule]);
                unset($updates[$control->rule]);
            }

            $exists = $exists->keyby('id');
            $inserts = [];

            foreach ($updates as $data) {
                if (!isset($data['id']) || in_array($set, $data['id']) || !$exists->has($data['id'])) {
                    $inserts[] = [
                        'owner_type' => $owner->getMorphClass(),
                        'owner_id' => $owner->getKey(),
                        'rule' => $data['rule'],
                        'access_level' => $data['access_level'],
                        'sort_order' => $data['sort_order'],
                    ];
                    continue;
                }
                $control = $exists->get($data['id']);
                $control->update($data);
                $set[] = $control->id;
                $exists->forget([$control->id]);
            }

            if ($exists->isNotEmpty()) {
                $base->clone()->whereIn('id', $exists->keys())->delete();
            }

            if (!empty($inserts)) {
                static::insert(array_values($inserts));
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function prunable(): Builder
    {
        return static::whereDoesntHaveMorph('owner', [User::class, Group::class, AccessToken::class]);
    }
}
