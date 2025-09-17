<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns trending topics from service', function () {
app()->bind(\App\Services\TrendingTopicsService::class, function ($app) {
        return new class($app->make(\GuzzleHttp\Client::class)) extends \App\Services\TrendingTopicsService {
                public function fetch(int $limit = 12): array { return ['topic A','topic B']; }
        };
});

	$response = $this->getJson('/api/trending-topics');
	$response->assertOk()
		->assertJson([
			'data' => ['topic A','topic B']
		]);
});


