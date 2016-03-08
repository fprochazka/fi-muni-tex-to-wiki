<?php

namespace TexToWiki\Mediawiki;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Helpers
{

	/**
	 * @see https://github.com/nette/latte/blob/2834c9ef14012403417e17b523f074c36703f088/src/Latte/Runtime/Filters.php#L138
	 * @param string $s UTF-8 encoding or 8-bit
	 * @return string
	 */
	public static function ltrimPerLine(string $s) : string
	{
		return preg_replace_callback(
			'~(</math|</pre|</script|^).*?(?=<math|<pre|<script|\z)~si',
			function (array $m) : string {
				$s = preg_replace('~(\\n|^)[\\t ]+~m', '$1', $m[0]); // ltrim per line
				// $s = preg_replace('~[\\t ]+(\\n|\\z)~', '$1', $s); // rtrim per line
				return $s;
			},
			$s
		);
	}

	/**
	 * @see https://github.com/nette/latte/blob/2834c9ef14012403417e17b523f074c36703f088/src/Latte/Runtime/Filters.php#L138
	 * @param string $s UTF-8 encoding or 8-bit
	 * @return string
	 */
	public static function removeAmbigouseNewlines(string $s) : string
	{
		$result = preg_replace_callback(
			'~(</math|</pre|</script|^).*?(?=<math|<pre|<script|\z)~si',
			function (array $m) : string {
				$s = preg_replace('~(?<!\\n|\\>)\\n(?!\\n|\\<)~m', ' ', $m[0]); // remove newline in paragraph
				return $s;
			},
			$s
		);
		$result = preg_replace_callback(
			'~(?<!\\n:)(<math>.*?</math>)\\n(?!\\n|\\<\\/)~si',
			function (array $m) : string {
				return $m[1] . ' ';
			},
			$result
		);

		return $result;
	}

}
