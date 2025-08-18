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
            return response()->json([
                'errors' => [
                    [
                        'code' => ErrorCode::DENIED,
                        'message' => 'Invalid service',
                    ]
                ],
            ], 401);
        }
        if ($request->getUser()) {
            $user = null;
            if (Auth::once([
                'username' => $request->getUser(),
                'password' => $request->getPassword(),
            ])) {
                $user = Auth::user();
                if ($user->password_expired) {
                    return response()->json([
                        'errors' => [
                            [
                                'code' => ErrorCode::UNAUTHORIZED,
                                'message' => 'Password has expired, please change your password',
                            ]
                        ],
                    ], 401);
                }
            } else {
                return response()->json([
                    'errors' => [
                        [
                            'code' => ErrorCode::UNAUTHORIZED,
                            'message' => 'Invalid username or password',
                        ]
                    ],
                ], 401);
            }
        } else {
            $user = User::whereNull('username')->first();
        }
        try {
            $grants = $this->grant($user, explode(' ', $request->query->getString('scope')));
            return response()->json(Token::issue($user->username ?? '', $grants->all()));
        } catch (Throwable $e) {
            Log::error($e);
            return response()->json([
                'errors' => [
                    [
                        'code' => ErrorCode::DENIED,
                        'message' => config('app.debug') ?
                            $e->getMessage() : 'Access denied',
                        'detail' => config('app.debug') ?
                            $e->getTraceAsString() : null,
                    ]
                ]
            ], 401);
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
}
