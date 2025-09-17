<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates insight via public endpoint', function () {
    app()->bind(\App\Services\AIInsightService::class, function ($app) {
        return new class($app->make(\GuzzleHttp\Client::class)) extends \App\Services\AIInsightService {
            public function __construct($client) { parent::__construct($client); }
            public function generate(string $word, string $topic, ?string $tone = null): string { return 'mocked insight'; }
        };
    });

	$response = $this->postJson('/api/insights/generate', [
		'word' => 'hegemony',
		'topic' => 'climate change',
	]);
	$response->assertOk()->assertJsonFragment(['content' => 'mocked insight']);
});

it('requires auth to list and store insights', function () {
	$this->getJson('/api/insights')->assertUnauthorized();
	$this->postJson('/api/insights', [])->assertUnauthorized();
});

it('stores and lists insights for authenticated user', function () {
	$user = User::factory()->create();
	$this->actingAs($user);

	$post = $this->postJson('/api/insights', [
		'word' => 'hegemony',
		'topic' => 'climate change',
		'content' => 'mocked insight',
	]);
	$post->assertCreated();

	$list = $this->getJson('/api/insights');
	$list->assertOk()->assertJsonStructure(['data' => [['id','word','topic','tone','content','created_at']]]);
});


