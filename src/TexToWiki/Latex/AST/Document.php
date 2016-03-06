<?php

namespace TexToWiki\Latex\AST;

use Doctrine\Common\Collections\Collection;
use TexToWiki\Latex\AST\Toc\Section;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Document extends Node
{

	/**
	 * @return Section[]|Collection
	 */
	public function getSections() : Collection
	{
		return $this->getChildren()
			->filter(self::filterSections());
	}

	public function validateParent(Node $parent) : bool
	{
		return false;
	}

	private static function filterSections() : \Closure
	{
		return function (Node $node) : bool {
			return $node instanceof Section;
		};
	}

}
