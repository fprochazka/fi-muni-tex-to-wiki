<?php

namespace TexToWiki\Latex\AST\Toc;

use TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SubSection extends AST\Section
{

	public function validateParent(AST\Node $parent) : bool
	{
		return $parent instanceof Section;
	}

}
