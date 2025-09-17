<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class AIInsightService
{
	public function __construct(private Client $http)
	{
	}

	public function generate(string $word, string $topic, ?string $tone = null): string
	{
		$pythonEndpoint = (string) env('AI_SERVER_URL', '');
		if ($pythonEndpoint !== '') {
			$response = $this->http->post(rtrim($pythonEndpoint, '/') . '/generate', [
				'json' => [ 'word' => $word, 'topic' => $topic, 'tone' => $tone ],
				'timeout' => 10,
			]);
			$payload = json_decode((string) $response->getBody(), true);
			return trim((string) data_get($payload, 'content'));
		}

		$apiKey = (string) config('services.openai.key', env('OPENAI_API_KEY'));
		$model = (string) config('services.openai.model', env('OPENAI_MODEL', 'gpt-4o-mini'));
		$endpoint = (string) config('services.openai.endpoint', env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'));

		$toneText = $tone ? (': tone ' . Str::of($tone)->lower()) : '';
		$prompt = "Compose one profound, shareable insight using the word '" . $word . "' about '" . $topic . "'" . $toneText . ".\n" .
			"Constraints: 1-2 elegant sentences, 300 characters max. No hashtags, no emojis, no quotes around the text. Be original and clear.";

		$response = retry(2, function () use ($endpoint, $apiKey, $model, $prompt) {
			return $this->http->post($endpoint, [
				'headers' => [
					'Authorization' => 'Bearer ' . $apiKey,
					'Content-Type' => 'application/json',
				],
				'json' => [
					'model' => $model,
					'messages' => [
						['role' => 'system', 'content' => 'You are a concise, insightful public intellectual.'],
						['role' => 'user', 'content' => $prompt],
					],
					'temperature' => 0.8,
					'max_tokens' => 160,
				],
			]);
		}, 200);

		$payload = json_decode((string) $response->getBody(), true);
		$text = data_get($payload, 'choices.0.message.content');

		return trim((string) $text);
	}

	/**
	 * Extract named entities from text using the Python AI server when configured.
	 * Falls back to a simple regex-based extraction if the server isn't available.
	 */
	public function ner(string $text): array
	{
		$text = trim($text);
		if ($text === '') return [];

		$pythonEndpoint = (string) env('AI_SERVER_URL', '');
		if ($pythonEndpoint !== '') {
			try {
				$response = $this->http->post(rtrim($pythonEndpoint, '/') . '/ner', [
					'json' => [ 'text' => $text, 'lang' => 'en' ],
					'timeout' => 10,
				]);
				$payload = json_decode((string) $response->getBody(), true);
				$entities = (array) data_get($payload, 'entities', []);
				return array_values(array_map(function ($e) {
					return [
						'text' => (string) data_get($e, 'text', ''),
						'label' => (string) data_get($e, 'label', ''),
						'start' => (int) data_get($e, 'start', 0),
						'end' => (int) data_get($e, 'end', 0),
					];
				}, $entities));
			} catch (\Throwable $e) {
				// fall through to heuristic extraction
			}
		}

		$results = [];
		// naive URL detection
		if (preg_match_all('/https?:\/\/\S+/u', $text, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[0] as $match) {
				[$value, $offset] = $match;
				$results[] = ['text' => $value, 'label' => 'URL', 'start' => $offset, 'end' => $offset + strlen($value)];
			}
		}
		// simple year detection
		if (preg_match_all('/\b(19|20)\d{2}\b/u', $text, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[0] as $match) {
				[$value, $offset] = $match;
				$results[] = ['text' => $value, 'label' => 'DATE', 'start' => $offset, 'end' => $offset + strlen($value)];
			}
		}
		// capitalized word sequences
		if (preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\b/u', $text, $m, PREG_OFFSET_CAPTURE)) {
			foreach ($m[1] as $match) {
				[$value, $offset] = $match;
				if (mb_strtolower($value) === 'i') continue;
				$results[] = ['text' => $value, 'label' => 'PROPN', 'start' => $offset, 'end' => $offset + strlen($value)];
			}
		}

		usort($results, function ($a, $b) {
			if ($a['start'] === $b['start']) return ($b['end'] - $b['start']) <=> ($a['end'] - $a['start']);
			return $a['start'] <=> $b['start'];
		});
		$filtered = [];
		$lastEnd = -1;
		foreach ($results as $ent) {
			if ($ent['start'] < $lastEnd) continue;
			$filtered[] = $ent;
			$lastEnd = $ent['end'];
		}
		return $filtered;
	}

	public function summarize(string $text, int $maxSentences = 2): string
	{
		$text = trim($text);
		if ($text === '') return '';

		$pythonEndpoint = (string) env('AI_SERVER_URL', '');
		if ($pythonEndpoint !== '') {
			try {
				$response = $this->http->post(rtrim($pythonEndpoint, '/') . '/summarize', [
					'json' => [ 'text' => $text, 'max_sentences' => $maxSentences, 'max_tokens' => 128 ],
					'timeout' => 15,
				]);
				$payload = json_decode((string) $response->getBody(), true);
				return trim((string) data_get($payload, 'summary', ''));
			} catch (\Throwable $e) {
				// fallback below
			}
		}

		$parts = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
		$parts = array_values(array_filter($parts, fn ($p) => trim((string) $p) !== ''));
		if ($maxSentences <= 0) $maxSentences = 2;
		return trim(implode(' ', array_slice($parts, 0, $maxSentences)));
	}

	public function detectLanguage(string $text): array
	{
		$text = trim($text);
		if ($text === '') return ['lang' => 'und', 'confidence' => 0.0];

		$pythonEndpoint = (string) env('AI_SERVER_URL', '');
		if ($pythonEndpoint !== '') {
			try {
				$response = $this->http->post(rtrim($pythonEndpoint, '/') . '/detect', [
					'json' => [ 'text' => $text ],
					'timeout' => 8,
				]);
				$payload = json_decode((string) $response->getBody(), true);
				return [
					'lang' => (string) data_get($payload, 'lang', 'und'),
					'confidence' => (float) data_get($payload, 'confidence', 0.0),
				];
			} catch (\Throwable $e) {
				// fall through
			}
		}

		// naive latin letters heuristic
		$hasNonAscii = (bool) preg_match('/[^\x00-\x7F]/u', $text);
		return ['lang' => $hasNonAscii ? 'non-en' : 'en', 'confidence' => 0.3];
	}

	public function translate(string $text, ?string $sourceLang = null, string $targetLang = 'en'): string
	{
		$text = trim($text);
		if ($text === '') return '';
		if ($sourceLang && strtolower($sourceLang) === strtolower($targetLang)) return $text;

		$pythonEndpoint = (string) env('AI_SERVER_URL', '');
		if ($pythonEndpoint !== '') {
			try {
				$response = $this->http->post(rtrim($pythonEndpoint, '/') . '/translate', [
					'json' => [ 'text' => $text, 'source_lang' => $sourceLang, 'target_lang' => $targetLang ],
					'timeout' => 15,
				]);
				$payload = json_decode((string) $response->getBody(), true);
				return (string) data_get($payload, 'translated', $text);
			} catch (\Throwable $e) {
				return $text;
			}
		}

		return $text;
	}

	public function keywords(string $text, int $topK = 8): array
	{
		$text = trim($text);
		if ($text === '') return [];
		$pythonEndpoint = (string) env('AI_SERVER_URL', '');
		if ($pythonEndpoint !== '') {
			try {
				$response = $this->http->post(rtrim($pythonEndpoint, '/') . '/keywords', [
					'json' => [ 'text' => $text, 'top_k' => $topK ],
					'timeout' => 15,
				]);
				$payload = json_decode((string) $response->getBody(), true);
				$kws = (array) data_get($payload, 'keywords', []);
				return array_values(array_map(function ($k) {
					return [
						'phrase' => (string) data_get($k, 'phrase', ''),
						'score' => (float) data_get($k, 'score', 0.0),
					];
				}, $kws));
			} catch (\Throwable $e) {
				// ignore and fallback
			}
		}

		$words = [];
		preg_match_all('/[A-Za-z][A-Za-z\-]+/u', $text, $m);
		foreach ($m[0] as $w) {
			$lw = strtolower($w);
			if (strlen($lw) < 4) continue;
			$words[$lw] = ($words[$lw] ?? 0) + 1;
		}
		arsort($words);
		$top = array_slice($words, 0, $topK, true);
		return array_map(fn ($k, $v) => ['phrase' => $k, 'score' => (float) $v], array_keys($top), array_values($top));
	}
}


