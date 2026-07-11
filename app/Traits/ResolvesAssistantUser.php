<?php

namespace App\Traits;

use App\Models\AssistantUser;
use Illuminate\Http\Request;

trait ResolvesAssistantUser
{
	protected function resolveAssistantUser(Request $request, int $assistant): AssistantUser
	{
		return AssistantUser::where('assistant_id', $assistant)
			->where('user_id', $request->user()->id)
			->firstOrFail();
	}
}