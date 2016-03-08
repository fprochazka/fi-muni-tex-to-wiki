<?php

namespace TexToWiki\Latex;

use Nette\Utils\TokenIterator as NTokenIterator;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class TokenIterator extends NTokenIterator
{

	public function without(...$types) : TokenIterator
	{
		$tokens = array_filter($this->tokens, function (array $token) use ($types) : bool {
			return !in_array($token[Tokenizer::TYPE], $types, true);
		});

		return new TokenIterator(array_values($tokens));
	}

	/**
	 * @param string|string[] $lookingFor
	 * @param array ...$ignore
	 * @return \TexToWiki\Latex\TokenIterator|null
	 */
	public function lookahead($lookingFor, ...$ignore)
	{
		$copy = clone $this;
		while ($copy->isNext(...$ignore)) {
			$copy->nextToken();
		}
		$lookingFor = is_array($lookingFor) ? $lookingFor : [$lookingFor];
		return $copy->isNext(...$lookingFor) ? $copy : null;
	}

	public function slice(int $offset, int $length) : TokenIterator
	{
		$copy = clone $this;
		$copy->tokens = array_slice($copy->tokens, $offset, $length);
		$copy->position = -1;
		return $copy;
	}

}
