<?php

namespace TexToWiki\Latex\AST;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use TexToWiki\Latex\InvalidNodeParentException;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
abstract class Node
{

	/** @var Node[] */
	protected $children = [];

	public function __construct(...$children)
	{
		foreach ($children as $child) {
			$this->addChild($child);
		}
	}

	private function addChild(Node $child)
	{
		if (!$child->validateParent($this)) {
			throw new InvalidNodeParentException($this, $child);
		}
		$this->children[] = $child;
	}

	/**
	 * @param \Closure|null $filter
	 * @return Node[]|Collection
	 */
	public function getChildren(\Closure $filter = null) : Collection
	{
		if ($filter === null) {
			return new ArrayCollection($this->children);
		}

		$result = [];
		foreach ($this->children as $child) {
			if ($filter($child) === true) {
				$result[] = $child;
			}
		}

		return new ArrayCollection($result);
	}

	/**
	 * @param \Closure|null $filter
	 * @return Node[]|Collection
	 */
	public function getChildrenRecursive(\Closure $filter = null) : Collection
	{
		$results = [];
		$queue = [$this];
		while ($node = array_shift($queue)) {
			/** @var Node $node */
			foreach ($node->getChildren() as $child) {
				$queue[] = $child;
				if ($filter === null || $filter($child) === true) {
					$results[] = $child;
				}
			}
		}

		return new ArrayCollection($results);
	}

	public function getNodeType() : string
	{
		return str_replace(__NAMESPACE__ . '\\', '', get_class($this));
	}

	public function __toString()
	{
		return $this->getNodeType() . '#' . substr(md5(spl_object_hash($this)), 0, 6) . '(' . count($this->children) . ')';
	}

	public function validateParent(Node $parent) : bool
	{
		return true;
	}

	public static function filterByType(string ...$types)
	{
		return function (Node $node) use ($types) : bool {
			foreach ($types as $type) {
				if ($node instanceof $type) {
					return true;
				}
			}
			return false;
		};
	}

}
