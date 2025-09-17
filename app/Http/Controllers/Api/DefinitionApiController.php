<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DefinitionLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DefinitionApiController extends Controller
{
	public function show(Request $request, DefinitionLookupService $defs): JsonResponse
	{
		$word = (string) $request->query('word', '');
		$definition = $defs->lookup($word);
		return response()->json([
			'word' => $word,
			'definition' => $definition,
		]);
	}
}


