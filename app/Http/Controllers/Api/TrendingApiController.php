<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrendingTopicsService;
use Illuminate\Http\JsonResponse;

class TrendingApiController extends Controller
{
	public function index(TrendingTopicsService $topics): JsonResponse
	{
		return response()->json([
			'data' => $topics->fetch(),
		]);
	}
}


