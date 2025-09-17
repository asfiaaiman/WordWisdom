<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInsightRequest;
use App\Models\Insight;
use Illuminate\Http\Request;
use App\Services\AIInsightService;
use Illuminate\Http\JsonResponse;

class InsightApiController extends Controller
{
	public function generate(GenerateInsightRequest $request, AIInsightService $ai): JsonResponse
	{
		$validated = $request->validated();
		$content = $ai->generate($validated['word'], $validated['topic'], $validated['tone'] ?? null);
		return response()->json([
			'word' => $validated['word'],
			'topic' => $validated['topic'],
			'tone' => $validated['tone'] ?? null,
			'content' => $content,
		]);
	}

	public function store(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'word' => ['required', 'string', 'max:64'],
			'topic' => ['required', 'string', 'max:160'],
			'tone' => ['nullable', 'string', 'max:32'],
			'content' => ['required', 'string', 'max:1000'],
		]);

		$insight = Insight::create($validated + ['user_id' => $request->user()->id]);

		return response()->json(['id' => $insight->id], 201);
	}

	public function index(Request $request): JsonResponse
	{
		$insights = Insight::query()
			->where('user_id', $request->user()->id)
			->latest('created_at')
			->limit(50)
			->get(['id','word','topic','tone','content','created_at']);

		return response()->json(['data' => $insights]);
	}
}


