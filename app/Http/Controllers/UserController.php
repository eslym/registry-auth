<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Http\Resources\GroupResource;
use App\Http\Resources\PaginatedCollection;
use App\Http\Resources\UserResource;
use App\Lib\Alert;
use App\Lib\Filter\FilterBuilder;
use App\Lib\Toast;
use App\Models\AccessControl;
use App\Models\Group;
use App\Models\User;
use App\Models\UserGroup;
use App\Rules\AccessControlsRule;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request, ?User $user)
    {
        $users = FilterBuilder::make()
            ->sortable(['username', 'created_at' => 'id'])
            ->sortBy('created_at')
            ->withString('username', [FilterBuilder::class, 'filterStringContains'])
            ->withInt('group', fn($query, $group) => $query->whereHas('groups', fn($q) => $q->where('id', $group)))
            ->apply(User::query()->select([
                'id',
                'username',
                'is_admin',
                'created_at',
            ]), $request, $meta);

        if ($user->exists) {
            $user->load(['groups', 'access_controls']);
            $view = UserResource::make($user);
        } else {
            $view = null;
        }

        return inertia('users/index', [
            'users' => PaginatedCollection::make($users, UserResource::class, $meta),
            'groups' => GroupResource::collection(Group::all(['id', 'name'])),
            'view' => $view,
        ]);
    }

    public function create(Request $request)
    {
        if (!$request->user()->is_admin) {
            return redirect()->back()
                ->with('alert',
                    Alert::make(
                        "Insufficient permissions",
                        "You do not have permission to create user."
                    )
                );
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'password' => ['required', 'string', 'confirmed:repeat_password', Password::defaults()],
            'repeat_password' => ['required', 'string'],
            'change_password' => ['boolean'],
            'is_admin' => ['boolean'],
            'groups' => ['array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'access_controls' => ['array', new AccessControlsRule()],
        ]);

        if ($data['change_password']) {
            $data['password_expired_at'] = now();
        } else if ($expiration = config('password.expiration')) {
            $data['password_expired_at'] = Carbon::now()->addDays($expiration);
        } else {
            $data['password_expired_at'] = null;
        }

        $user = User::create(Arr::only($data, [
            'username',
            'password',
            'password_expired_at',
            'is_admin',
        ]));

        if (!empty($data['groups'])) {
            $groups = Group::whereIn('id', array_values($data['groups']))
                ->get(['id'])
                ->keyby('id');
            foreach (array_values($data['groups']) as $sort => $id) {
                if (!$groups->has($id)) continue;
                UserGroup::create([
                    'user_id' => $user->id,
                    'group_id' => $id,
                    'sort_order' => $sort,
                ]);
            }
        }

        AccessControl::syncWith($user, $data['access_controls'] ?? []);

        return redirect()->back()
            ->with('toast',
                Toast::success(
                    "User created",
                    "The user has been successfully created."
                )
            );
    }

    public function update(Request $request, User $user)
    {
        if (!$request->user()->is_admin) {
            return redirect()->back()
                ->with('alert',
                    Alert::make(
                        "Insufficient permissions",
                        "You do not have permission to update user."
                    )
                );
        }

        $nonAnonymous = [
            'password' => ['nullable', 'string', 'confirmed:repeat_password', Password::defaults()],
            'repeat_password' => ['required_with:password', 'nullable', 'string'],
            'change_password' => ['boolean'],
            'is_admin' => ['boolean']
        ];

        $data = $request->validate([
            ...($user->username === null || auth()->id() === $user->id ? [] : $nonAnonymous),
            'groups' => ['array'],
            'groups.*' => ['integer'],
            'access_controls' => ['array', new AccessControlsRule()],
        ]);

        if ($user->username !== null && auth()->id() !== $user->id) {
            $user->is_admin = $data['is_admin'] ?? false;
            if (!empty($data['password'])) {
                $user->password = $data['password'];
            }

            if ($data['change_password']) {
                $data['password_expired_at'] = now();
            } else if ($expiration = config('password.expiration')) {
                $data['password_expired_at'] = Carbon::now()->addDays($expiration);
            } else {
                $data['password_expired_at'] = null;
            }

            $user->save();
        }

        if (!empty($data['groups'])) {
            $groups = Group::whereIn('id', array_values($data['groups']))
                ->get(['id'])
                ->keyby('id');
            $user->groups()->whereNotIn('group_id', $groups->keys()->all())->delete();
            foreach (array_values($data['groups']) as $sort => $id) {
                if (!$groups->has($id)) continue;
                UserGroup::upsert([
                    'user_id' => $user->id,
                    'group_id' => $id,
                    'sort_order' => $sort,
                ], ['user_id', 'group_id'], ['sort_order']);
            }
        }

        AccessControl::syncWith($user, $data['access_controls'] ?? []);

        return redirect()->back()
            ->with('toast',
                Toast::success(
                    "User updated",
                    "The user has been successfully updated."
                )
            );
    }

    public function destroy(Request $request, User $user)
    {
        if (!$request->user()->is_admin) {
            return redirect()->back()
                ->with('alert',
                    Alert::make(
                        "Insufficient permissions",
                        "You do not have permission to delete user."
                    )
                );
        }
        if ($user->id === $request->user()->id) {
            return redirect()->back()
                ->with('alert',
                    Alert::make(
                        "Error",
                        "You cannot delete your own user account."
                    )
                );
        }
        if ($user->username === null) {
            return redirect()->back()
                ->with('alert',
                    Alert::make(
                        "Error",
                        "You cannot delete anonymous user account."
                    )
                );
        }
        $user->delete();
        return redirect()->back()
            ->with('toast',
                Toast::success(
                    "User deleted",
                    "The user has been successfully deleted."
                )
            );
    }
}
