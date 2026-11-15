<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\FreelancerProfile;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, PaymentProvider $payments)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'role' => ['required', Rule::in(['owner', 'freelancer'])],
            'company' => ['nullable', 'string', 'max:160'],
            'industry' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'timezone'],
            'plan' => ['nullable', Rule::in(['starter', 'growth', 'scale'])],
            'headline' => ['nullable', 'string', 'max:190'],
        ]);

        $user = DB::transaction(function () use ($data, $payments) {
            $role = $data['role'];
            $plan = $role === 'owner' ? ($data['plan'] ?? 'starter') : null;
            $user = User::create([
                'name' => $data['name'], 'email' => Str::lower($data['email']), 'password' => $data['password'],
                'role' => $role, 'status' => $role === 'freelancer' ? 'pending' : 'active',
                'avatar' => 'https://api.dicebear.com/7.x/notionists/svg?seed='.rawurlencode($data['name']),
                'company' => $data['company'] ?? null, 'plan' => $plan,
                'ai_credits_limit' => $role === 'owner' ? ['starter' => 200, 'growth' => 600, 'scale' => 2000][$plan] : 50,
            ]);
            if ($role === 'owner') {
                $workspace = Workspace::create(['owner_id' => $user->id, 'name' => $data['company'] ?? $data['name']."'s workspace", 'industry' => $data['industry'] ?? 'Other', 'timezone' => $data['timezone'] ?? 'UTC']);
                $payment = $payments->subscribe($plan, $workspace->id);
                Subscription::create(['workspace_id' => $workspace->id, 'plan' => $plan, 'status' => 'trialing', 'amount' => ['starter' => 49, 'growth' => 149, 'scale' => 399][$plan], 'current_period_ends_at' => now()->addDays(14)]);
                Transaction::create(['workspace_id' => $workspace->id, 'amount' => 0, 'type' => 'subscription', 'status' => 'pending', 'provider_reference' => $payment['reference'], 'created_at' => now()]);
            } else {
                FreelancerProfile::create(['user_id' => $user->id, 'headline' => $data['headline'] ?? 'New freelancer — complete your profile', 'bio' => '', 'skills' => [], 'platforms' => [], 'public_visible' => false]);
            }
            return $user;
        });

        return $this->authenticatedResponse($user, 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', Str::lower($credentials['email']))->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid email or password.']);
        }
        abort_if($user->status === 'suspended', 403, 'This account is suspended.');
        $user->tokens()->where('created_at', '<', now()->subDays(30))->delete();
        return $this->authenticatedResponse($user);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load(['workspace', 'freelancerProfile']));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['data' => ['loggedOut' => true]])->withCookie(cookie()->forget('rawijli_token', '/'));
    }

    private function authenticatedResponse(User $user, int $status = 200)
    {
        $token = $user->createToken('rawijli-web', ['*'], now()->addDays(7))->plainTextToken;
        $cookie = cookie('rawijli_token', $token, 60 * 24 * 7, '/', null, app()->environment('production'), true, false, 'lax');
        return (new UserResource($user->load(['workspace', 'freelancerProfile'])))
            ->response()->setStatusCode($status)->withCookie($cookie);
    }
}
