<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns on-demand definition from service', function () {
app()->bind(\App\Services\DefinitionLookupService::class, function ($app) {
        return new class($app->make(\GuzzleHttp\Client::class)) extends \App\Services\DefinitionLookupService {
                public function lookup(string $word): ?string { return "Definition for {$word}"; }
        };
});

	$response = $this->getJson('/api/definitions?word=ontology');
	$response->assertOk()
		->assertJson([
			'word' => 'ontology',
			'definition' => 'Definition for ontology',
		]);
});


