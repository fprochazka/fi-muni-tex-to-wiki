<?php

namespace TexToWiki\Latex\AST;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Section extends Node
{

	/** @var Text */
	private $name;

	/** @var CommandArgument[] */
	private $arguments;

	/** @var Node[] */
	private $body;

	public function __construct(Command $beginCommand, ...$body)
	{
		parent::__construct($beginCommand, ...$body);

		if (($firstArgument = $beginCommand->getFirstArgument()) && ($name = $firstArgument->getFirstValue()) && $name instanceof Text) {
			$this->name = $name;
		}

		$this->arguments = $beginCommand->getArguments()->slice(1);
		$this->body = $body;
	}

	/**
	 * @return \TexToWiki\Latex\AST\Text|null
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return CommandArgument[]|Collection
	 */
	public function getArguments() : Collection
	{
		return new ArrayCollection($this->arguments);
	}

	/**
	 * @return Node[]|Collection
	 */
	public function getBody() : Collection
	{
		return new ArrayCollection($this->body);
	}

	public static function filterByName(string ...$names) : \Closure
	{
		return function (Section $section) use ($names) : bool {
			return in_array($section->getName()->getValue(), $names, true);
		};
	}

}
