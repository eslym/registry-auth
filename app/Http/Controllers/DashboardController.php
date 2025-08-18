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

    public function updatePassword(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($expired = $user->password_expired) {
            $data = $request->validate([
                'new_password' => [
                    'bail', 'required', 'string',
                    Password::defaults(),
                    'confirmed:repeat_password',
                    new PasswordMustDifferentRule($user->password)
                ],
                'repeat_password' => ['required', 'string'],
            ]);
        } else {
            $data = $request->validate([
                'new_password' => [
                    'bail', 'required', 'string',
                    Password::defaults(),
                    'confirmed:repeat_password',
                ],
                'repeat_password' => ['required', 'string'],
                'current_password' => [
                    'required', 'string', 'current_password',
                ]
            ]);
        }

        $user->update([
            'password' => $data['new_password'],
            'password_expired_at' => null,
        ]);

        return redirect()->back()
            ->with('toast', $expired ?
                Toast::success("Password Updated", "You can now continue to use the application.") :
                Toast::success("Password Updated", "Your password has been updated successfully.")
            );
    }
}
