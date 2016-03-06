<?php

namespace TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Math extends Node
{

	/** @var Text */
	private $formulae;

	/** @var bool */
	private $inline;

	public function __construct(Text $formulae, bool $inline)
	{
		parent::__construct();
		$this->formulae = $formulae;
		$this->inline = $inline;
	}

	public function getFormulae() : Text
	{
		return $this->formulae;
	}

	public function isInline() : bool
	{
		return $this->inline;
	}

}
