<?php

namespace TexToWiki\Latex\AST;

use Nette\Utils\Strings;
use TexToWiki\Latex\AST\Theorem\Theorem;
use TexToWiki\NotImplementedException;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Label extends Command
{

	/** @var Text */
	private $labelName;

	public function __construct($name, ...$children)
	{
		parent::__construct($name, ...$children);

		$this->labelName = $this->getFirstArgument()->getFirstValue();
	}

	public function getLabelName() : Text
	{
		return $this->labelName;
	}

	/**
	 * @return null|\TexToWiki\Latex\AST\Toc\Section
	 */
	public function getTocSection()
	{
		return $this->getParent(Node::filterByType(Toc\Section::class));
	}

	/**
	 * @return null|\TexToWiki\Latex\AST\Toc\SubSection
	 */
	public function getTocSubSection()
	{
		return $this->getParent(Node::filterByType(Toc\SubSection::class));
	}

	public function getLabelId() : string
	{
		$labelValue = Strings::replace($this->getLabelName()->getValue(), '~^[a-z]+\\:~i');
		return trim(Strings::webalize($labelValue), '-');
	}

	public function getLabelType() : string
	{
		return explode(':', $this->labelName, 2)[0];
	}

	public function getCompleteLabelId() : string
	{
		$parent = $this->getParent(Node::filterByType(Theorem::class, MathSection::class));
		if ($parent instanceof Theorem) {
			return 'cst-' . $parent::NAME . '-' . $this->getLabelId();

		} elseif ($parent instanceof MathSection) {
			return 'equation-' . $this->getLabelId();

		} else {
			throw new NotImplementedException;
		}
	}

	public static function filterByValue(string ...$names) : \Closure
	{
		return function (Label $node) use ($names) : bool {
			return in_array($node->getLabelName()->getValue(), $names, true);
		};
	}

}
