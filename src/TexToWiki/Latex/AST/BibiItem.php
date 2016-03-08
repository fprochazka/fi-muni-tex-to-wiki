<?php

namespace TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class BibiItem extends Command
{

	/** @var CommandArgument|null */
	private $refName;

	/** @var Command|null */
	private $book;

	/** @var CommandArgument|null */
	private $bookAuthor;

	/** @var CommandArgument|null */
	private $bookName;

	/** @var CommandArgument|null */
	private $bookPublisher;

	/** @var CommandArgument|null */
	private $bookSource;

	public function __construct($name, ...$children)
	{
		parent::__construct($name, ...$children);

		$this->refName = $this->getArguments()->get(0);
		$this->bookAuthor = $this->getArguments()->get(1);
		$this->bookName = $this->getArguments()->get(2);
		$this->bookPublisher = $this->getArguments()->get(3);
		$this->bookSource = $this->getArguments()->get(4);
	}

	/**
	 * @return \TexToWiki\Latex\AST\Text|null
	 */
	public function getRefName()
	{
		return $this->refName ? $this->refName->getFirstValue() : null;
	}

	/**
	 * @return Command|null
	 */
	public function getBook()
	{
		return $this->book;
	}

	/**
	 * @return null|CommandArgument
	 */
	public function getBookAuthor()
	{
		return $this->bookAuthor;
	}

	/**
	 * @return null|CommandArgument
	 */
	public function getBookName()
	{
		return $this->bookName;
	}

	/**
	 * @return null|CommandArgument
	 */
	public function getBookPublisher()
	{
		return $this->bookPublisher;
	}

	/**
	 * @return null|CommandArgument
	 */
	public function getBookSource()
	{
		return $this->bookSource;
	}

	public static function filterByName(string ...$names) : \Closure
	{
		return function (BibiItem $node) use ($names) : bool {
			return in_array($node->getRefName()->getValue(), $names, true);
		};
	}

}
