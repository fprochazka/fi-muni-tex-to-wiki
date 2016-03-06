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

}
