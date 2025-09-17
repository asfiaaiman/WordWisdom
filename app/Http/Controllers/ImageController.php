<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController extends Controller
{
	public function generate(Request $request): StreamedResponse
	{
		$text = trim((string) $request->string('text')->toString());
		$word = (string) $request->string('word')->toString();
		$topic = (string) $request->string('topic')->toString();

		$width = 1200;
		$height = 630;
		$image = imagecreatetruecolor($width, $height);

		$bg1 = imagecolorallocate($image, 250, 250, 250);
		$bg2 = imagecolorallocate($image, 230, 230, 230);
		for ($y = 0; $y < $height; $y++) {
			$ratio = $y / $height;
			$r = (int) (250 - 20 * $ratio);
			$g = (int) (250 - 20 * $ratio);
			$b = (int) (250 - 20 * $ratio);
			$color = imagecolorallocate($image, $r, $g, $b);
			imageline($image, 0, $y, $width, $y, $color);
		}

		$textColor = imagecolorallocate($image, 17, 17, 17);
		$metaColor = imagecolorallocate($image, 107, 114, 128);

		$left = 60;
		$right = $width - 60;
		$top = 80;

		$fontPath = resource_path('fonts/DejaVuSans.ttf');
		$useTtf = function_exists('imagettftext') && is_file($fontPath);

		if ($useTtf) {
			$fontSize = 36; // points
			$lineHeight = 52;
			$lines = $this->wrapTtf($text, $fontPath, $fontSize, $right - $left);
			$y = $top;
			foreach ($lines as $line) {
				imagettftext($image, $fontSize, 0, $left, $y, $textColor, $fontPath, $line);
				$y += $lineHeight;
			}
			$meta = trim("Word: {$word} • Topic: {$topic}");
			imagettftext($image, 20, 0, $left, $height - 80, $metaColor, $fontPath, $meta);
		} else {
			// Fallback to built-in GD font
			$font = 5;
			$lineWidthPx = $right - $left;
			$charWidth = imagefontwidth($font);
			$maxChars = max(1, (int) floor($lineWidthPx / $charWidth));
			$lines = $this->wrapPlain($text, $maxChars);
			$y = $top;
			foreach ($lines as $line) {
				imagestring($image, $font, $left, $y, $line, $textColor);
				$y += imagefontheight($font) + 6;
			}
			$meta = trim("Word: {$word} • Topic: {$topic}");
			imagestring($image, 3, $left, $height - 80, $meta, $metaColor);
		}

		return response()->streamDownload(function () use ($image) {
			header('Content-Type: image/png');
			imagepng($image);
			imagedestroy($image);
		}, 'wordwisdom.png', [
			'Content-Type' => 'image/png',
			'Cache-Control' => 'no-store',
		]);
	}

	private function wrapTtf(string $text, string $fontPath, int $fontSize, int $maxWidthPx): array
	{
		$words = preg_split('/\s+/', trim($text)) ?: [];
		$lines = [];
		$line = '';
		foreach ($words as $word) {
			$test = $line === '' ? $word : $line . ' ' . $word;
			$box = imagettfbbox($fontSize, 0, $fontPath, $test);
			$w = abs($box[4] - $box[0]);
			if ($w > $maxWidthPx && $line !== '') {
				$lines[] = $line;
				$line = $word;
			} else {
				$line = $test;
			}
		}
		if ($line !== '') {
			$lines[] = $line;
		}
		return $lines;
	}

	private function wrapPlain(string $text, int $maxChars): array
	{
		$words = preg_split('/\s+/', trim($text)) ?: [];
		$lines = [];
		$line = '';
		foreach ($words as $word) {
			$test = $line === '' ? $word : $line . ' ' . $word;
			if (mb_strlen($test) > $maxChars && $line !== '') {
				$lines[] = $line;
				$line = $word;
			} else {
				$line = $test;
			}
		}
		if ($line !== '') {
			$lines[] = $line;
		}
		return $lines;
	}
}


