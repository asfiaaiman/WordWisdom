<?php

namespace App\Services;

use GuzzleHttp\Client;

class DefinitionLookupService
{
	public function __construct(private Client $http)
	{
	}

	public function lookup(string $word): ?string
	{
		$word = trim($word);
		if ($word === '') return null;

		try {
			$resp = $this->http->get('https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($word), [ 'timeout' => 3 ]);
			$data = json_decode((string) $resp->getBody(), true);
			$extract = (string) ($data['extract'] ?? '');
			if ($extract !== '') return $this->shorten($extract);
		} catch (\Throwable) {
		}

		try {
			$resp = $this->http->get('https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($word), [ 'timeout' => 3 ]);
			$data = json_decode((string) $resp->getBody(), true);
			$meaning = (string) ($data[0]['meanings'][0]['definitions'][0]['definition'] ?? '');
			if ($meaning !== '') return $this->shorten($meaning);
		} catch (\Throwable) {
		}

		return null;
	}

	private function shorten(string $text): string
	{
		$text = trim($text);
		return mb_strlen($text) > 240 ? rtrim(mb_substr($text, 0, 237)) . '…' : $text;
	}
}


