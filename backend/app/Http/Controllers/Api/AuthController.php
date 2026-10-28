<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Security\PepperedHashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected AuditService $auditService;
    protected PepperedHashService $pepperedHashService;

    public function __construct(AuditService $auditService, PepperedHashService $pepperedHashService)
    {
        $this->auditService = $auditService;
        $this->pepperedHashService = $pepperedHashService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('institution')->where('email', $request->email)->first();

        // Defense-in-depth: Verify against Peppered Argon2id (HMAC-SHA512 + 64MB Argon2id)
        // If legacy bcrypt hash, verify and automatically seamlessly upgrade to Peppered Argon2id
        $passwordValid = false;
        if ($user) {
            if ($this->pepperedHashService->verify($request->password, $user->password)) {
                $passwordValid = true;
            } elseif (str_starts_with($user->password, '$2y$') || str_starts_with($user->password, '$2a$')) {
                try {
                    if (Hash::check($request->password, $user->password)) {
                        $passwordValid = true;
                        // Seamlessly upgrade to Peppered Argon2id
                        $user->forceFill([
                            'password' => $this->pepperedHashService->hash($request->password),
                        ])->save();
                    }
                } catch (\Throwable $e) {
                    $passwordValid = false;
                }
            }
        }

        if (!$user || !$passwordValid) {
            $this->auditService->log('LOGIN_FAILED', 'User', null, ['email' => $request->email], 'failure', null, $request);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if ($user->status !== 'active') {
            $this->auditService->log('LOGIN_BLOCKED_INACTIVE', 'User', (string) $user->id, null, 'blocked', $user, $request);
            return response()->json(['message' => 'Account is inactive or suspended. Contact AICTE administrator.'], 403);
        }

        $token = $user->createToken('aicte-fdr-portal')->plainTextToken;

        $this->auditService->log('LOGIN_SUCCESS', 'User', (string) $user->id, null, 'success', $user, $request);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'designation' => $user->designation,
                'phone' => $user->phone,
                'institution_id' => $user->institution_id,
                'institution' => $user->institution ? [
                    'id' => $user->institution->id,
                    'aicte_id' => $user->institution->aicte_id,
                    'name' => $user->institution->name,
                    'category' => $user->institution->category,
                ] : null,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('institution');
        return response()->json([
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $this->auditService->log('LOGOUT', 'User', (string) $user->id, null, 'success', $user, $request);

        return response()->json(['message' => 'Successfully logged out.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();
        $currentValid = $this->pepperedHashService->verify($request->current_password, $user->password)
            || Hash::check($request->current_password, $user->password);

        if (!$currentValid) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->forceFill([
            'password' => $this->pepperedHashService->hash($request->new_password),
        ])->save();

        $this->auditService->log('CHANGE_PASSWORD', 'User', (string) $user->id, null, 'success', $user, $request);

        return response()->json(['message' => 'Password changed successfully with Peppered Argon2id cryptographic protection.']);
    }
}
