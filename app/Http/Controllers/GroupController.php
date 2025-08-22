<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Http\Resources\GroupResource;
use App\Http\Resources\PaginatedCollection;
use App\Lib\Alert;
use App\Lib\Filter\FilterBuilder;
use App\Lib\Toast;
use App\Models\AccessControl;
use App\Models\Group;
use App\Rules\AccessControlsRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    public function index(Request $request, ?Group $group)
    {
        $query = Group::query()
            ->leftJoin('user_group AS ug', 'ug.group_id', '=', 'groups.id')
            ->leftJoin('users AS u', 'u.id', '=', 'ug.user_id')
            ->groupBy([
                'groups.id',
                'groups.name',
                'groups.created_at',
            ])
            ->select([
                'groups.id',
                'groups.name',
                'groups.created_at',
                DB::raw('COUNT(u.id) AS users_count')
            ]);

        $groups = FilterBuilder::make()
            ->withString('name', [FilterBuilder::class, 'filterStringContains'])
            ->sortable(['name', 'created_at' => 'id', 'users' => 'users_count'])
            ->sortBy('created_at')
            ->apply($query, $request, $meta);

        if ($group->exists) {
            $group->load(['access_controls']);
            $view = GroupResource::make($group);
        } else {
            $view = null;
        }

        return inertia('groups/index', [
            'groups' => PaginatedCollection::make($groups, GroupResource::class, $meta),
            'view' => $view,
        ]);
    }

    public function store(Request $request, ?Group $group)
    {
        $action = $group->exists ? 'update' : 'create';

        if (!$request->user()->is_admin) {
            return redirect()->back()
                ->with('alert',
                    Alert::make(
                        "Insufficient permissions",
                        "You do not have permission to $action group."
                    )
                );
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'access_controls' => ['array', new AccessControlsRule()],
        ]);

        if ($group->exists) {
            $group->name = $data['name'];
            $group->save();
            $group->access_controls()->delete();
        } else {
            $group = Group::create([
                'name' => $data['name'],
            ]);
        }

        AccessControl::syncWith($group, $data['access_controls'] ?? []);

        return redirect()->back()
            ->with('toast',
                Toast::success(
                    "Group {$action}d",
                    "The group has been successfully {$action}d."
                )
            );
    }

    public function destroy(Request $request, Group $group)
    {
        if (!$request->user()->is_admin) {
            return redirect()->back()
                ->with('alert',
                    Alert::make(
                        "Insufficient permissions",
                        "You do not have permission to delete group."
                    )
                );
        }

        $group->delete();

        return redirect()->back()
            ->with('toast',
                Toast::success(
                    "Group deleted",
                    "The group has been successfully deleted."
                )
            );
    }
}
