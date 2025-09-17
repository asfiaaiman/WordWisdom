<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInsightRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	public function rules(): array
	{
		return [
			'word' => ['required', 'string', 'max:64'],
			'topic' => ['required_without:article', 'nullable', 'string', 'max:2000'],
			'article' => ['required_without:topic', 'nullable', 'string', 'max:20000'],
			'tone' => ['nullable', 'string', 'max:32'],
			'target_lang' => ['nullable', 'string', 'size:2'],
			'enable_ner' => ['nullable', 'boolean'],
			'enable_summary' => ['nullable', 'boolean'],
			'enable_keywords' => ['nullable', 'boolean'],
			'enable_translation' => ['nullable', 'boolean'],
			'async' => ['nullable', 'boolean'],
			'save' => ['nullable', 'boolean'],
		];
	}
}


