<?php

use App\Services\AIInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bindFakeAi(array $overrides = []): void {
    app()->bind(AIInsightService::class, function ($app) use ($overrides) {
        return new class($overrides) extends AIInsightService {
            public function __construct(private array $overrides) { /* do not call parent */ }
            public function generate(string $word, string $topic, ?string $tone = null): string { return $this->overrides['generate'] ?? 'Insight about '.$word.' and '.$topic; }
            public function ner(string $text): array { return $this->overrides['ner'] ?? [['text' => 'Socrates', 'label' => 'PERSON', 'start' => 0, 'end' => 8]]; }
            public function summarize(string $text, int $maxSentences = 2): string { return $this->overrides['summarize'] ?? 'Short summary.'; }
            public function detectLanguage(string $text): array { return $this->overrides['detect'] ?? ['lang' => 'es', 'confidence' => 0.9]; }
            public function translate(string $text, ?string $sourceLang = null, string $targetLang = 'en'): string { return $this->overrides['translate'] ?? 'Translated to '.$targetLang; }
            public function keywords(string $text, int $topK = 8): array { return $this->overrides['keywords'] ?? [ ['phrase' => 'virtue ethics', 'score' => 0.8] ]; }
        };
    });
}

it('generates with all toggles enabled and returns NLP fields', function () {
    bindFakeAi();

    $response = $this->post(route('insights.store'), [
        'word' => 'arete',
        'topic' => 'ancient philosophy',
        'tone' => 'reflective',
        'target_lang' => 'en',
        'enable_ner' => true,
        'enable_summary' => true,
        'enable_keywords' => true,
        'enable_translation' => true,
        'save' => false,
    ]);

    $response->assertRedirect(route('insights.create'));
    $response->assertSessionHas('generated', function ($g) {
        expect($g['content'])->toBeString();
        expect($g['entities'])->toBeArray()->not->toBeEmpty();
        expect($g['summary'])->toBeString();
        expect($g['language']['lang'])->toBe('es');
        expect($g['translated'])->toContain('Translated');
        expect($g['keywords'])->toBeArray()->not->toBeEmpty();
        expect($g['wisdomChain'])->toBeArray();
        return true;
    });
});

it('respects disabled toggles (no NLP fields)', function () {
    bindFakeAi();

    $response = $this->post(route('insights.store'), [
        'word' => 'arete',
        'topic' => 'ancient philosophy',
        'enable_ner' => false,
        'enable_summary' => false,
        'enable_keywords' => false,
        'enable_translation' => false,
        'save' => false,
    ]);

    $response->assertRedirect(route('insights.create'));
    $response->assertSessionHas('generated', function ($g) {
        expect($g['entities'])->toBeArray()->toBeEmpty();
        expect($g['summary'])->toBeNull();
        expect($g['language']['lang'])->toBe('en');
        expect($g['translated'])->toBe($g['content']);
        expect($g['keywords'])->toBeArray()->toBeEmpty();
        expect($g['wisdomChain'])->toBeArray()->toBeEmpty();
        return true;
    });
});


