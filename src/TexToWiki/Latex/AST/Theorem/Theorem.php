<?php

namespace TexToWiki\Latex\AST\Theorem;

use TexToWiki\Latex\AST;

/**
 * Věta
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class Theorem extends AST\Section
{

	const NAME = 'theorem';

	/** @var AST\Label|null */
	private $label;

	/** @var AST\Text|null */
	private $title;

	public function __construct(AST\Command $beginCommand, ...$body)
	{
		parent::__construct($beginCommand, ...$body);

		$this->label = $this->getChildren(AST\Node::filterByType(AST\Label::class))
			->first() ?: null;

		/** @var AST\Style\Bold $boldTitle */
		$boldTitle = $this->getChildrenRecursive(AST\Node::filterByType(AST\Command::class))
			->filter(AST\Command::filterByName('bf'))
			->first() ?: null;
		if ($boldTitle) {
			$this->title = $boldTitle->getFirstArgument()->getFirstValue();

		} elseif (($title = $this->getArguments()->get(1)) && $title->isOptional()) {
			/** @var AST\CommandArgument $title */
			$this->title = $title->getFirstValue();
		}
	}

	/**
	 * @return null|AST\Label
	 */
	public function getLabel()
	{
		return $this->label;
	}

	/**
	 * @return null|AST\Text
	 */
	public function getTitle()
	{
		return $this->title;
	}

}
