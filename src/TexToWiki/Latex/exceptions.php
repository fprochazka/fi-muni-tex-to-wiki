<?php

namespace TexToWiki\Latex;

use TexToWiki\Exception;
use TexToWiki\Latex\AST\Command;
use TexToWiki\Latex\AST\Node;
use TexToWiki\Latex\AST\SectionBoundary;

class ParserException extends \RuntimeException implements Exception
{

}

class UnexpectedTokenException extends ParserException
{

	/** @var array */
	private $token;

	public function __construct(array $token, $expected)
	{
		parent::__construct(sprintf('Unexpected token %s, expected %s', json_encode(Tokenizer::tokenToAssoc($token)), $expected));
		$this->token = $token;
	}

	public function getToken() : array
	{
		return $this->token;
	}

}

class UnexpectedNodeException extends ParserException
{

	/** @var \TexToWiki\Latex\AST\Node */
	private $node;

	public function __construct(Node $node, $expected = NULL)
	{
		parent::__construct(sprintf('Unexpected token %s, expected %s', $node, $expected));
		$this->node = $node;
	}

	public function getNode() : Node
	{
		return $this->node;
	}

}

class InvalidNodeParentException extends ParserException
{

	/** @var \TexToWiki\Latex\AST\Node */
	private $parent;

	/** @var \TexToWiki\Latex\AST\Node */
	private $child;

	public function __construct(Node $parent, Node $child)
	{
		parent::__construct(sprintf('The node %s cannot be a child of %s', get_class($child), get_class($parent)));
		$this->parent = $parent;
		$this->child = $child;
	}

	public function getParent() : Node
	{
		return $this->parent;
	}

	public function getChild() : Node
	{
		return $this->child;
	}

}

class SectionEndDoesNotMatchSectionBeginException extends ParserException
{

	/** @var \TexToWiki\Latex\AST\SectionBoundary */
	private $begin;

	/** @var \TexToWiki\Latex\AST\Command */
	private $end;

	public function __construct(SectionBoundary $begin, Command $end)
	{
		parent::__construct(sprintf('The ending %s doesn\'t match the opening %s', $end, $begin));
		$this->begin = $begin;
		$this->end = $end;
	}

	public function getBegin() : SectionBoundary
	{
		return $this->begin;
	}

	public function getEnd() : Command
	{
		return $this->end;
	}

}
