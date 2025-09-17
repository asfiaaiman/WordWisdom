<?php

namespace App\Services;

use GuzzleHttp\Client;

class TrendingTopicsService
{
	public function __construct(private Client $http)
	{
	}

	public function fetch(int $limit = 12): array
	{
		try {
			$resp = $this->http->get('https://en.wikipedia.org/api/rest_v1/feed/featured/2025/01/01', [
				'timeout' => 3,
			]);
			$data = json_decode((string) $resp->getBody(), true);
			$titles = collect(data_get($data, 'mostread.articles', []))
				->pluck('normalizedtitle')
				->filter()
				->take($limit)
				->values()
				->all();
			return $titles;
		} catch (\Throwable) {
			return [
				'climate change','wealth inequality','Syrian elections','AI governance','healthcare access','migration','digital privacy','global south debt','nuclear proliferation','food security','press freedom','labor rights','education equity'
			];
		}
	}
}


