<?php

namespace TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class MathSection extends Section
{

	/** @var \TexToWiki\Latex\AST\Math */
	private $formulae;

	public function __construct(Command $beginCommand, ...$body)
	{
		parent::__construct($beginCommand, ...$body);
		$this->formulae = $this->getChildrenRecursive(Node::filterByType(Math::class))
			->first() ?: null;
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
