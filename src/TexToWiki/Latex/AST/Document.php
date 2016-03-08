<?php

namespace TexToWiki\Latex\AST;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use TexToWiki\Latex\AST\Toc\Section;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Document extends Node
{

	/** @var BibiItem[] */
	private $bibiItems;

	public function __construct(...$children)
	{
		parent::__construct(...$children);

		$this->bibiItems = $this->getChildrenRecursive(Node::filterByType(BibiItem::class))
			->toArray();
	}

	/**
	 * @return Section[]|Collection
	 */
	public function getSections() : Collection
	{
		return $this->getChildren()
			->filter(self::filterSections());
	}

	/**
	 * @return BibiItem[]|Collection
	 */
	public function getBibiItems() : Collection
	{
		return new ArrayCollection($this->bibiItems);
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
