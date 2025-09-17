<?php

use App\Services\AIInsightService;

it('falls back to simple regex NER when server is unavailable', function () {
    $service = new AIInsightService(new \GuzzleHttp\Client());
    putenv('AI_SERVER_URL=');
    $ents = $service->ner('In 2020, Aristotle visited https://example.com in Athens.');
    expect($ents)->toBeArray()->not->toBeEmpty();
});

it('summarize falls back to sentence trimming', function () {
    $service = new AIInsightService(new \GuzzleHttp\Client());
    putenv('AI_SERVER_URL=');
    $text = 'Sentence one. Sentence two. Sentence three.';
    $sum = $service->summarize($text, 2);
    expect($sum)->toContain('Sentence one.')->toContain('Sentence two.');
});

it('keywords fallback returns frequency-based list', function () {
    $service = new AIInsightService(new \GuzzleHttp\Client());
    putenv('AI_SERVER_URL=');
    $kws = $service->keywords('Freedom freedom justice justice justice virtue');
    expect($kws)->toBeArray()->not->toBeEmpty();
});


