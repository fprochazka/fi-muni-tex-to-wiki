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

	public function __construct($name, array $children)
	{
		parent::__construct($name, $children);

		$this->refName = $this->getFirstArgument();

		$this->book = $this->getChildren(self::filterByType(Command::class))
			->filter(self::filterByName('mciteb'))
			->first() ?: null;

		if ($this->book !== null) {
			$this->bookAuthor = $this->book->getArguments()->get(0);
			$this->bookName = $this->book->getArguments()->get(1);
			$this->bookPublisher = $this->book->getArguments()->get(2);
		}
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

}
