<?php

namespace TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class MathSection extends Section
{

	/** @var \TexToWiki\Latex\AST\Math */
	private $formulae;

	public function __construct(Command $beginCommand, Math $formulae)
	{
		parent::__construct($beginCommand, $formulae);
		$this->formulae = $formulae;
	}

	/**
	 * @return CommandArgument|null
	 */
	public function getFirstArgument()
	{
		return $this->getArguments()
			->first() ?: null;
	}

	/**
	 * @return \TexToWiki\Latex\AST\Text
	 */
	public function getFormulae()
	{
		return $this->formulae->getFormulae();
	}

}
