<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a png from image generation endpoint', function () {
	$response = $this->post(route('image.generate'), [
		'text' => 'An elegant sentence worth sharing.',
		'word' => 'ontology',
		'topic' => 'climate change',
	]);
	$response->assertOk();
	expect($response->headers->get('content-type'))->toBe('image/png');
});


