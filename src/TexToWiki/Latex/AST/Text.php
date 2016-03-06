<?php

namespace TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Text extends Node
{

	/** @var string */
	private $value;

	public function __construct(string $value)
	{
		parent::__construct();
		$this->value = $value;
	}

	public function getValue() : string
	{
		return $this->value;
	}

	public function __toString()
	{
		return $this->value;
	}

}
