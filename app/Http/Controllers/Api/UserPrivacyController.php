<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserPrivacyController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'hide_from_leaderboard_feed' => 'required|boolean',
        ]);

        $request->user()->update([
            'hide_from_leaderboard_feed' => $request->boolean('hide_from_leaderboard_feed'),
        ]);

        return response()->json(['message' => 'Preferensi berhasil disimpan.']);
    }
}
