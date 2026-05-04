<?php

namespace App\Http\Controllers;

use App\Models\ReferralClick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralAnalyticsController extends Controller
{
    public function index(): JsonResponse
    {
        // Ensure user is authenticated
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Ensure user is the admin
        $adminUserId = (int) env('ADMIN_USER_ID', 0);
        if (auth()->id() !== $adminUserId) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $clicks = ReferralClick::selectRaw('referrer, COUNT(*) as total')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->get();

        return response()->json($clicks);
    }
}
