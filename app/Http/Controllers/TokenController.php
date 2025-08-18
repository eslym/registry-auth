<?php

namespace App\Http\Controllers;

use App\Lib\ACLGlob;
use App\Models\User;
use App\Registry\ErrorCode;
use App\Registry\Grant;
use App\Registry\ResourceType;
use App\Registry\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class TokenController extends Controller
{
    public function issue(Request $request)
    {
        if ($request->query('service') !== config('registry.service')) {
            return $this->failed(ErrorCode::DENIED, 'Invalid service');
        }
        if ($request->getUser()) {
            if (Auth::once([
                'username' => $request->getUser(),
                'password' => $request->getPassword(),
            ])) {
                $user = Auth::user();
                if ($user->password_expired) {
                    return $this->failed(
                        ErrorCode::UNAUTHORIZED,
                        'Password has expired, please change your password'
                    );
                }
            } else {
                return $this->failed(
                    ErrorCode::UNAUTHORIZED,
                    'Invalid username or password'
                );
            }
        } else {
            $user = User::whereNull('username')->first();
        }
        try {
            $grants = $this->grant($user, explode(' ', $request->query->getString('scope')));
            return response()->json(Token::issue($user->username ?? '', $grants->all()));
        } catch (Throwable $e) {
            Log::error($e);
            return $this->failed(
                ErrorCode::DENIED,
                config('app.debug') ?
                    $e->getMessage() : 'Authentication Server Error',
                config('app.debug') ?
                    $e->getTraceAsString() : null,
            );
        }
    }

    private function grant(User $user, array $scopes)
    {
        $lazy = function () use ($user, &$lazy) {
            $controls = $user->getAllAccessControls();
            $lazy = fn() => $controls;
            return $controls;
        };

        return collect($scopes)->map(function ($scope) use ($user, $lazy) {
            if (empty($scope)) return null;
            $grant = Grant::parse($scope);
            if ($scope === 'registry:catalog:*') {
                return $user->isAnonymous() && !config('registry.anonymous_catalog', false) ?
                    null : $grant;
            }
            switch ($grant->type) {
                case ResourceType::REGISTRY:
                    return $user->is_admin ? $grant : null;
                case ResourceType::REPOSITORY:
                    $controls = $lazy();
                    foreach ($controls as $control) {
                        if (ACLGlob::match($control->repository, $grant->name)) {
                            $allowed = $control->access_level->toActions();
                            $grant = $grant->restrictTo($allowed);
                            if (empty($grant->actions)) {
                                return null;
                            }
                            return $grant;
                        }
                    }
                    return null;
                default:
                    return null;
            }
        })->filter(fn($v) => $v);
    }

    public function failed(ErrorCode $code, string $message, mixed $detail = null)
    {
        return response()->json([
            'errors' => [
                [
                    'code' => $code,
                    'message' => $message,
                    'detail' => $detail,
                ]
            ]
        ], 401);
    }
}
