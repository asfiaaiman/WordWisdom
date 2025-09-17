<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateInsightRequest;
use App\Models\Insight;
use App\Services\AIInsightService;
use App\Services\TrendingTopicsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightController extends Controller
{
	public function create(Request $request, TrendingTopicsService $topics): Response
	{
		$curated = $this->curatedWords();
		return Inertia::render('Insights/Generator', [
			'curatedWords' => array_keys($curated),
			'wordDefinitions' => $curated,
			'curatedTopics' => $topics->fetch(),
		]);
	}

	public function store(GenerateInsightRequest $request, AIInsightService $ai): RedirectResponse
	{
		$validated = $request->validated();
		$topic = (string) ($validated['topic'] ?? '');
		$article = (string) ($validated['article'] ?? '');
		$contentSource = $article !== '' ? $article : $topic;
		$targetLang = (string) ($validated['target_lang'] ?? 'en');
		$content = $ai->generate($validated['word'], $topic !== '' ? $topic : 'article', $validated['tone'] ?? null);

		$enableNer = (bool) ($validated['enable_ner'] ?? false);
		$enableSummary = (bool) ($validated['enable_summary'] ?? false);
		$enableKeywords = (bool) ($validated['enable_keywords'] ?? false);
		$enableTranslation = (bool) ($validated['enable_translation'] ?? false);

		$detected = $enableTranslation
			? $ai->detectLanguage($contentSource !== '' ? $contentSource : $content)
			: ['lang' => 'en', 'confidence' => 0.0];
		$translated = $enableTranslation && $detected['lang'] !== ($targetLang ?: 'en')
			? $ai->translate($content, $detected['lang'], $targetLang ?: 'en')
			: $content;
		$entities = $enableNer ? $ai->ner($content) : [];
		$summary = $enableSummary ? $ai->summarize($contentSource !== '' ? ($contentSource . '\n\n' . $content) : $content) : null;
		$keywords = $enableKeywords ? $ai->keywords($content) : [];
		$curated = $this->curatedWords();
		$wisdomChain = collect($keywords)
			->pluck('phrase')
			->map(fn ($p) => strtolower((string) $p))
			->flatMap(function ($kw) use ($curated) {
				return collect($curated)
					->keys()
					->filter(fn ($w) => str_contains(strtolower((string) $w), $kw))
					->values();
			})
			->unique()
			->take(6)
			->values()
			->all();

		$insight = [
			'word' => $validated['word'],
			'topic' => $topic,
			'tone' => $validated['tone'] ?? null,
			'content' => $content,
			'entities' => $entities,
			'language' => $detected,
			'translated' => $translated,
			'target_lang' => $targetLang,
			'summary' => $summary,
			'keywords' => $keywords,
			'wisdomChain' => $wisdomChain,
		];

		/** @var \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard $auth */
		$auth = auth();
		if (($validated['save'] ?? false) && $auth->check()) {
			Insight::create($insight + ['user_id' => $auth->id()]);
		}

		return redirect()->route('insights.create')
			->with('generated', $insight);
	}

	public function index(Request $request): Response
	{
		/** @var \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard $auth */
		$auth = auth();
		$insights = Insight::query()
			->when($auth->check(), fn ($q) => $q->where('user_id', $auth->id()))
			->latest('created_at')
			->paginate(12)
			->withQueryString();

		return Inertia::render('Insights/Index', [
			'insights' => $insights,
		]);
	}

	private function curatedWords(): array
	{
		return (array) config('wordwisdom.words');
	}

}


