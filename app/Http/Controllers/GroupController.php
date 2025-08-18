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
            'access_controls' => ['array'],
            'access_controls.*' => ['array'],
            'access_controls.*.repository' => ['required', 'string', 'max:255'],
            'access_controls.*.access_level' => ['required', Rule::enum(AccessLevel::class)],
        ], [], [
            'access_controls.*.repository' => 'repository pattern',
        ]);

        if (!empty($data['access_controls'])) {
            $errors = $this->validateAccessControls($data['access_controls']);
            if (!empty($errors)) {
                return redirect()->back()
                    ->withErrors($errors);
            }
        }

        if ($group->exists) {
            $group->name = $data['name'];
            $group->save();
            $group->access_controls()->delete();
        } else {
            $group = Group::create([
                'name' => $data['name'],
            ]);
        }

        if (!empty($data['access_controls'])) {
            $inserts = collect($data['access_controls'])->values()->map(fn($v, $sort) => [
                'owner_type' => $group->getMorphClass(),
                'owner_id' => $group->id,
                'repository' => strtolower($v['repository']),
                'access_level' => $v['access_level'],
                'sort_order' => $sort,
            ])->all();
            AccessControl::insert($inserts);
        }

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

    private function validateAccessControls(array $accessControls): array
    {
        $exists = [];
        $errors = [];
        $repositories = collect($accessControls)->pluck('repository');
        foreach ($repositories as $index => $repo) {
            if (isset($exists[$repo])) {
                $errors["access_controls.$exists[$repo].repository"] = ["Duplicated entry."];
                $errors["access_controls.$index.repository"] = ["Duplicated entry."];
                continue;
            }
            $exists[$repo] = $index;
        }
        return $errors;
    }
}
