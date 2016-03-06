<?php

namespace TexToWiki\Latex\AST\Theorem;

use TexToWiki\Latex\AST;

/**
 * Řešení
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class Solution extends Theorem
{

	const NAME = 'solution';

	public static function fromCommand(AST\Command $command) : Solution
	{
		$begin = new AST\Command($command->getName(), new AST\CommandArgument(false, new AST\Text('Solution')));
		$body = $command->getFirstArgument()->getChildren()->toArray();

		return new Solution($begin, ...$body);
	}

}
