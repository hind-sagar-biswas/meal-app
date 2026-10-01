<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MemberController extends Controller
{
    /**
     * Get list of active mess members.
     */
    public function __invoke(): JsonResponse
    {
        $members = Cache::rememberForever(User::CACHE_KEY, function () {
            return User::where('is_active', true)->get(['id', 'name', 'email']);
        });

        return response()->json([
            'data' => UserResource::collection($members),
        ]);
    }
}
