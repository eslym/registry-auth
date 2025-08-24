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
        $refreshToken = null;
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
                    if ($token->expired_at && $token->expired_at->isPast()) {
                        return $this->failed(ErrorCode::UNAUTHORIZED, 'Token expired');
                    }
                    if (
                        $token->is_refresh_token &&
                        $token->user->password_expired_at &&
                        $token->user->password_expired_at->isPast()
                    ) {
                        return $this->failed(ErrorCode::UNAUTHORIZED, 'Password has expired, please reset it');
                    }
                    $token->update([
                        'last_used_at' => now(),
                        'last_used_ip' => $request->ip(),
                    ]);
                    $grantable = $token;
                }
            }
            if (!$grantable && Hash::check($password, $user->password)) {
                if ($user->password_expired_at && $user->password_expired_at->isPast()) {
                    return $this->failed(ErrorCode::UNAUTHORIZED, 'Password has expired, please reset it');
                }
                $grantable = $user;
                if ($request->query->getString('offline_token') === 'true') {
                    $token = AccessToken::create([
                        'user_id' => $user->id,
                        'expired_at' => $user->password_expired_at,
                        'is_refresh_token' => true,
                        'last_used_ip' => $request->ip(),
                    ]);
                    $refreshToken = $token->getGeneratedToken();
                }
            }
        } else {
            $grantable = User::whereNull('username')->first();
        }
        if (!$grantable) {
            return $this->failed(ErrorCode::UNAUTHORIZED, 'Invalid credentials');
        }

        try {
            $grants = $grantable->grant($request->query->get('scope', ''));
            $res = Token::issue($grantable->getUsername(), $grants);
            return response()->json($refreshToken ? array_merge($res, ['refresh_token' => $refreshToken]) : $res);
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

    public function byRefreshToken(Request $request) {
        $valid = validator($request->input(), [
            'grant_type' => ['required', 'string', 'in:refresh_token'],
            'service' => ['required', 'string', 'in:' . config('registry.service')],
            'refresh_token' => ['required', 'string'],
            'scope' => ['sometimes', 'string'],
            'client-id' => ['sometimes', 'string'],
        ]);

        if($valid->fails()) {
            return $this->failed(ErrorCode::DENIED, 'Invalid request', $valid->errors());
        }

        $input = $valid->validated();

        if (!preg_match('/^([1-9][0-9]*)\|([a-z0-9]+)$/i', $input['refresh_token'], $matches)) {
            return $this->failed(ErrorCode::UNAUTHORIZED, 'Invalid refresh token');
        }
        [, $tokenId, $tokenSecret] = $matches;
        /** @var AccessToken $token */
        $token = AccessToken::where('id', $tokenId)
            ->where('is_refresh_token', true)
            ->first();
        if (!$token || !Hash::check($tokenSecret, $token->token)) {
            return $this->failed(ErrorCode::UNAUTHORIZED, 'Invalid refresh token');
        }
        if ($token->expired_at && $token->expired_at->isPast()) {
            return $this->failed(ErrorCode::UNAUTHORIZED, 'Refresh token expired');
        }
        if (
            $token->user->password_expired_at &&
            $token->user->password_expired_at->isPast()
        ) {
            return $this->failed(ErrorCode::UNAUTHORIZED, 'Password has expired, please reset it');
        }
        $token->update([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ]);
        try {
            $grants = $token->grant($input['scope'] ?? '');
            $res = Token::issue($token->user->getUsername(), $grants);
            return response()->json($res);
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
                    ...($detail ? ['detail' => $detail] : [])
                ]
            ]
        ], 401);
    }
}
