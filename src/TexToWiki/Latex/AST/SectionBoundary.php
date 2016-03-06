<?php

namespace TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SectionBoundary extends Command
{

	/** @var string */
	private $sectionName;

	public function __construct($name, ...$children)
	{
		parent::__construct($name, ...$children);

		if (($firstArgument = $this->getFirstArgument()) && ($name = $firstArgument->getFirstValue()) && $name instanceof Text) {
			$this->sectionName = $name->getValue();
		}
	}

	/**
	 * @return string|null
	 */
	public function getSectionName()
	{
		return $this->sectionName;
	}

}
