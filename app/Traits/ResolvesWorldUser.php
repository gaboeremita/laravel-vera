<?php

namespace App\Traits;

use App\Models\WorldUser;
use Illuminate\Http\Request;

trait ResolvesWorldUser
{
    protected function resolveWorldUser(Request $request, int $world): WorldUser
    {
        return WorldUser::where('world_id', $world)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
