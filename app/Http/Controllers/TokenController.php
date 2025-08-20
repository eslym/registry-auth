<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Models\User;
use App\Registry\ErrorCode;
use App\Registry\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class TokenController extends Controller
{
    public function issue(Request $request)
    {
        if ($request->query('service') !== config('registry.service')) {
            return $this->failed(ErrorCode::DENIED, 'Invalid service');
        }

        $grantable = null;
        if ($username = $request->getUser()) {
            $user = User::where('username', $username)->first();
            if (!$user) {
                return $this->failed(ErrorCode::UNAUTHORIZED, 'Invalid credentials');
            }
            $password = $request->getPassword();
            if (preg_match('/^([1-9][0-9]*)\|([a-z0-9]+)$/i', $password, $matches)) {
                [, $tokenId, $tokenSecret] = $matches;
                /** @var AccessToken $token */
                $token = $user->access_tokens()
                    ->where('id', $tokenId)
                    ->first();
                if ($token && Hash::check($tokenSecret, $token->token)) {
                    if ($token->expires_at && $token->expires_at->isPast()) {
                        return $this->failed(ErrorCode::UNAUTHORIZED, 'Token expired');
                    }
                    $grantable = $token;
                }
            }
            if (!$grantable && Hash::check($password, $user->password)) {
                if ($user->password_expired_at && $user->password_expired_at->isPast()) {
                    return $this->failed(ErrorCode::UNAUTHORIZED, 'Password has expired, please reset it');
                }
                $grantable = $user;
            }
        } else {
            $grantable = User::whereNull('username')->first();
        }
        if (!$grantable) {
            return $this->failed(ErrorCode::UNAUTHORIZED, 'Invalid credentials');
        }

        try {
            $grants = $grantable->grant($request->query->get('scopes', ''));
            return response()->json(Token::issue($grantable->getUsername(), $grants));
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

    private function failed(ErrorCode $code, string $message, mixed $detail = null)
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
