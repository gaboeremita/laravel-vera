<?php

namespace App\Traits;

use App\Models\WorldSession;
use Illuminate\Http\Request;

trait ResolvesWorldSession
{
    use ResolvesWorldUser;

    protected function resolveWorldSession(Request $request, int $world, int $session): WorldSession
    {
        return $this->resolveWorldUser($request, $world)->sessions()->with('worldUser.world')->findOrFail($session);
    }
}
