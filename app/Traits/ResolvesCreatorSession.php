<?php

namespace App\Traits;

use App\Models\WorldSession;
use Illuminate\Http\Request;

trait ResolvesCreatorSession
{
    use ResolvesWorldSession;

    protected function resolveCreatorSession(Request $request, int $world, int $session): WorldSession
    {
        $worldSession = $this->resolveWorldSession($request, $world, $session);
        abort_if($worldSession->creator_mode_at === null, 403, 'Unlock the Creator menu first.');

        return $worldSession;
    }
}
