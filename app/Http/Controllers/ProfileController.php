<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccessTokenResource;
use App\Http\Resources\PaginatedCollection;
use App\Lib\Filter\FilterBuilder;
use App\Lib\Toast;
use App\Models\AccessControl;
use App\Models\AccessToken;
use App\Models\User;
use App\Rules\AccessControlsRule;
use App\Rules\PasswordMustDifferentRule;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function index(Request $request, AccessToken $token)
    {
        $user = $request->user();

        if($token->exists && $token->user_id != $user->id) {
            abort(404);
        }

        $tokens = FilterBuilder::make()
            ->sortable(['created_at', 'expires_at', 'description', 'used_by' => 'last_used_ip', 'used_at' => 'last_used_at'])
            ->sortBy('created_at', 'desc')
            ->withString('search', function (Builder $query, string $keyword) {
                $des = $query->qualifyColumn('description');
                $ip = $query->qualifyColumn('last_used_ip');
                $query->where(fn(Builder $query)=> $query
                    ->whereRaw("LOCALE(?, $des) > 0", [$keyword])
                    ->orWhereRaw("LOCALE(?, $ip) > 0", [$keyword])
                );
            })
            ->apply($user->access_tokens(), $request, $meta);

        $view = $token->exists ?
            AccessTokenResource::make($token->load('access_controls')) :
            null;

        return inertia("profile/index", [
            'tokens' => PaginatedCollection::make($tokens, AccessTokenResource::class, $meta),
            'view' => $view,
            '_created' => session()->get('created'),
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

    public function createToken(Request $request)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:2', 'max:255'],
            'expired_at' => ['nullable', 'date', 'after_or_equal:now'],
            'access_controls' => ['array', 'min:1', new AccessControlsRule()],
        ]);

        $token = AccessToken::create([
            'user_id' => $request->user()->id,
            'description' => $data['description'],
            'expired_at' => ($data['expired_at'] ?? null) ?
                Carbon::parse($data['expired_at'], $request->cookie('tz')) : null,
        ]);

        AccessControl::syncWith($token, $data['access_controls'] ?? []);

        return redirect()->route('profile.index')
            ->with('created', $token->getGeneratedToken());
    }

    public function revokeToken(AccessToken $token)
    {
        if ($token->user_id != auth()->id()) {
            abort(404);
        }

        $token->access_controls()->delete();
        $token->delete();

        return redirect()->back()
            ->with('toast', Toast::success(
                "Token Revoked",
                "The access token has been successfully revoked."
            ));
    }
}
