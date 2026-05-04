<?php
<?php

namespace App\Http\Controllers;

use App\Models\ReferralClick;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralAnalyticsController extends Controller
{
    public function index(): View
    {
        $clicks = ReferralClick::selectRaw('referrer, COUNT(*) as total')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->get();

        return view('referral.analytics', compact('clicks'));
    }
}
