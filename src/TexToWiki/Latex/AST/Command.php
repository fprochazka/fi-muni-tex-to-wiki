<?php

namespace TexToWiki\Latex\AST;

use Doctrine\Common\Collections\Collection;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Command extends Node
{

	/** @var string */
	private $name;

	public function __construct(string $name, ...$children)
	{
		$this->name = $name;
		parent::__construct(...$children);
	}

	public function getName() : string
	{
		return $this->name;
	}

	/**
	 * @return CommandArgument[]|Collection
	 */
	public function getArguments() : Collection
	{
		return $this->getChildren();
	}

	/**
	 * @return CommandArgument|null
	 */
	public function getFirstArgument()
	{
		return $this->getChildren()->first() ?: null;
	}

	/**
	 * @return CommandArgument|NULL
	 */
	public function getBody()
	{
		return $this->getChildren()->last() ?: null;
	}

	public function __toString()
	{
		$text = '\\' . $this->name;
		foreach ($this->getArguments() as $argument) {
			$text .= $argument->isOptional() ? '[' : '{';
			$text .= implode($argument->getChildren());
			$text .= $argument->isOptional() ? ']' : '}';
		}
		return $text;
	}

	public static function filterByName(string ...$names) : \Closure
	{
		return function (Command $command) use ($names) : bool {
			return in_array($command->getName(), $names, true);
		};
	}

}
