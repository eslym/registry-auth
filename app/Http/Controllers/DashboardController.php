<?php

namespace App\Http\Controllers;

use App\Lib\Toast;
use App\Models\Group;
use App\Models\User;
use App\Rules\PasswordMustDifferentRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class DashboardController extends Controller
{
    public function index()
    {
        $users = User::whereNotNull('username')->count();
        $groups = Group::count();

        return inertia("dashboard/index", [
            'users' => $users,
            'groups' => $groups,
        ]);
    }
}
