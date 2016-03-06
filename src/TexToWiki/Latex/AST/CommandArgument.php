<?php

namespace TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class CommandArgument extends Node
{

	/** @var bool */
	private $optional;

	public function __construct(bool $optional, ...$children)
	{
		parent::__construct(...$children);
		$this->optional = $optional;
	}

	/**
	 * @return Node|Text
	 */
	public function getFirstValue() : Node
	{
		return $this->children[0];
	}

	public function isOptional() : bool
	{
		return $this->optional;
	}

	public function validateParent(Node $parent) : bool
	{
		return $parent instanceof Command;
	}

	public static function filterOptional(bool $optional = true) : \Closure
	{
		return function (CommandArgument $argument) use ($optional) : bool {
			return $argument->isOptional() === $optional;
		};
	}

}
