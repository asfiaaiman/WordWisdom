<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('validates required fields', function () {
	$response = $this->post(route('insights.store'), []);
	$response->assertSessionHasErrors(['word', 'topic']);
});

it('generates and optionally saves when authenticated', function () {
	// Fake AI by binding the service to a stub
app()->bind(\App\Services\AIInsightService::class, function ($app) {
        return new class($app->make(\GuzzleHttp\Client::class)) extends \App\Services\AIInsightService {
                public function generate(string $word, string $topic, ?string $tone = null): string
                {
                        return "Test insight about {$topic} using {$word}.";
                }
        };
});

	$user = User::factory()->create();
	$this->actingAs($user);

	$response = $this->post(route('insights.store'), [
		'word' => 'hegemony',
		'topic' => 'climate change',
		'tone' => 'critical',
		'save' => true,
	]);

	$response->assertRedirect(route('insights.create'));
	$response->assertSessionHas('generated');

	$this->assertDatabaseHas('insights', [
		'user_id' => $user->id,
		'word' => 'hegemony',
		'topic' => 'climate change',
	]);
});


