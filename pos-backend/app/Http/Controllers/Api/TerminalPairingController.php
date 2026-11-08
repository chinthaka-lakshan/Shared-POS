<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Terminal;
use Illuminate\Support\Facades\Hash;

class TerminalPairingController extends Controller
{
    public function pair(Request $request)
    {
        $request->validate([
            'terminal_code' => 'required|string',
            'device_uuid' => 'required|string'
        ]);

        $terminal = Terminal::where('terminal_code', $request->terminal_code)->first();

        if (!$terminal) {
            return response()->json(['message' => 'Invalid terminal code'], 404);
        }

        // Update device uuid
        $terminal->update(['device_uuid' => $request->device_uuid]);

        // Revoke all previous tokens for this terminal (if needed, but for now just issue one)
        $terminal->tokens()->delete();

        // Create new token
        $token = $terminal->createToken('pos-terminal-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'terminal' => $terminal
        ]);
    }
}
