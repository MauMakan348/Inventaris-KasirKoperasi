<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Register a new member account.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        // Role is never read from the request; the database default keeps new accounts as MEMBER.
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ])->refresh();
    }

    /**
     * Authenticate a user and issue a Sanctum token.
     *
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        // Same exception for unknown email and wrong password so the message stays generic.
        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Email atau password salah.');
        }

        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }
}
