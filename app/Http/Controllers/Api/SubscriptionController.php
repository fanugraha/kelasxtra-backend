<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * GET /api/subscription-plans
     * Publik: daftar plan langganan yang aktif dijual (mirror PackageController@index).
     */
    public function plans()
    {
        return SubscriptionPlan::with('program')
            ->where('is_active', true)
            ->latest()
            ->get();
    }

    /**
     * GET /api/my-subscription
     * Auth: subscription aktif milik user (kalau ada) + program yang di-cover.
     */
    public function mySubscription(Request $request)
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->with('plan.program', 'programs')
            ->active()
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json(['subscription' => null]);
        }

        return [
            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'covered_program_ids' => $subscription->programs()->pluck('programs.id'),
            ],
        ];
    }
}
