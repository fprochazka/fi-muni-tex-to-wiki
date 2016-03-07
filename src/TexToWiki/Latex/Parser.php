<?php

namespace TexToWiki\Latex;

use Nette\Utils\Strings;
use TexToWiki\Latex\AST;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Parser
{

	/** @var \TexToWiki\Latex\Tokenizer */
	private $tokenizer;

	/** @var \TexToWiki\Latex\TokenIterator */
	private $stream;

	public function __construct()
	{
		$this->tokenizer = new Tokenizer();
	}

	public function parse(string $content) : AST\Document
	{
		try {
			$content = Strings::normalize($content);
			$this->stream = $this->tokenizer
				->tokenize($content)
				->without(Tokenizer::TOKEN_COMMENT);

			return new AST\Document(...$this->doParse());

		} finally {
			$this->stream = null;
		}
	}

	private function doParse() : array
	{
		$nodes = [];
		while ($this->stream->isNext()) {
			$nodes[] = $this->parseNext();
		}
		return $nodes;
	}

	private function parseNext() : AST\Node
	{
		if (!$token = $this->stream->nextToken()) {
			throw new ParserException('Reached the end of stream');
		}

		switch ($token[Tokenizer::TYPE]) {
			case Tokenizer::TOKEN_COMMAND_SECTION:
				return $this->parseTocSection($token);
			case Tokenizer::TOKEN_COMMAND_SUBSECTION:
				return $this->parseTocSubSection($token);
			case Tokenizer::TOKEN_COMMAND_BEGIN:
				return $this->parseSection($token);
			case Tokenizer::TOKEN_COMMAND_END:
			case Tokenizer::TOKEN_COMMAND:
				return $this->parseCommand($token);
			case Tokenizer::TOKEN_MATH_INLINE:
			case Tokenizer::TOKEN_MATH_BLOCK:
				return $this->parseMath($token);
			default:
				return $this->parseText($token);
		}
	}

	private function parseText(array $token) : AST\Text
	{
		static $terminal = [
			Tokenizer::TOKEN_COMMAND_SECTION,
			Tokenizer::TOKEN_COMMAND_SUBSECTION,
			Tokenizer::TOKEN_COMMAND_BEGIN,
			Tokenizer::TOKEN_COMMAND_END,
			Tokenizer::TOKEN_COMMAND,
			Tokenizer::TOKEN_MATH_INLINE,
			Tokenizer::TOKEN_MATH_BLOCK,
			Tokenizer::TOKEN_BRACE_CURLY_LEFT,
			Tokenizer::TOKEN_BRACE_CURLY_RIGHT,
			Tokenizer::TOKEN_BRACE_SQUARE_LEFT,
			Tokenizer::TOKEN_BRACE_SQUARE_RIGHT,
		];

		$text = $token[Tokenizer::VALUE];
		while ($this->stream->isNext() && !$this->stream->isNext(...$terminal)) {
			$text .= $this->stream->nextValue();
		}

		return new AST\Text(str_replace('~', ' ', $text));
	}

	private function parseTocSection(array $token) : AST\Toc\Section
	{
		$begin = $this->parseCommand($token);
		$nodes = [];
		while ($this->stream->isNext() && !$this->stream->isNext(Tokenizer::TOKEN_COMMAND_SECTION)) {
			$nodes[] = $this->parseNext();
		}
		return new AST\Toc\Section($begin, ...$nodes);
	}

	private function parseTocSubSection(array $token) : AST\Toc\SubSection
	{
		$begin = $this->parseCommand($token);
		$nodes = [];
		while ($this->stream->isNext() && !$this->stream->isNext(Tokenizer::TOKEN_COMMAND_SECTION, Tokenizer::TOKEN_COMMAND_SUBSECTION)) {
			$nodes[] = $this->parseNext();
		}
		return new AST\Toc\SubSection($begin, ...$nodes);
	}

	private function parseSection(array $token) : AST\Section
	{
		$begin = $this->parseSectionBegin($token);
		if (Strings::match($begin->getSectionName(), '~^(align|gather|equation|tabular|eqnarray|pspicture)~i')) {
			$body = $this->parseMathBlockBody($begin);
			$this->parseSectionEnd($this->stream->nextToken(), $begin); // end command

			return new AST\MathSection($begin, $body);

		} else {
			$nodes = [];
			while ($this->stream->isNext() && !$this->stream->isNext(Tokenizer::TOKEN_COMMAND_END)) {
				$nodes[] = $this->parseNext();
			}

			$this->parseSectionEnd($this->stream->nextToken(), $begin); // end command

			return $this->createSection(trim(strtolower($begin->getSectionName())), $begin, $nodes);
		}
	}

	private function parseMathBlockBody(AST\SectionBoundary $begin) : AST\Math
	{
		$startPosition = $this->stream->position;
		while ($this->stream->isNext()) {
			if ($this->stream->isNext(Tokenizer::TOKEN_COMMAND_BEGIN)) {
				$innerBegin = $this->parseSectionBegin($this->stream->nextToken());
				$this->parseMathBlockBody($begin); // throw away
				$this->parseSectionEnd($this->stream->nextToken(), $innerBegin);

			} elseif ($this->stream->isNext(Tokenizer::TOKEN_COMMAND_END)) {
				break;
			}

			$this->stream->nextUntil(Tokenizer::TOKEN_COMMAND_BEGIN, Tokenizer::TOKEN_COMMAND_END);
		}
		$endPosition = $this->stream->position;

		$text = null;
		for ($this->stream->position = $startPosition; $endPosition > $this->stream->position ;) {
			$text .= $this->stream->nextValue();
		}

		return new AST\Math(new AST\Text($text), false);
	}

	private function parseSectionBegin(array $beginToken) : AST\SectionBoundary
	{
		$begin = $this->parseCommand($beginToken);
		if (!$begin instanceof AST\SectionBoundary || $begin->getName() !== 'begin') {
			throw new UnexpectedNodeException($begin, 'command \begin{}');
		}
		return $begin;
	}

	private function parseSectionEnd(array $endToken, AST\SectionBoundary $begin) : AST\SectionBoundary
	{
		$end = $this->parseCommand($endToken);
		if (!$end instanceof AST\SectionBoundary || $end->getName() !== 'end') {
			throw new UnexpectedNodeException($end, 'command \end{}');
		}
		if ($end->getSectionName() !== $begin->getSectionName()) {
			throw new SectionEndDoesNotMatchSectionBeginException($begin, $end);
		}
		return $end;
	}

	private function createSection($name, $begin, array $nodes) : AST\Section
	{
		switch ($name) {
			case AST\Theorem\Assumption::NAME:
				return new AST\Theorem\Assumption($begin, ...$nodes);
			case AST\Theorem\Axiom::NAME:
				return new AST\Theorem\Axiom($begin, ...$nodes);
			case AST\Theorem\Conjecture::NAME:
				return new AST\Theorem\Conjecture($begin, ...$nodes);
			case AST\Theorem\Corollary::NAME:
				return new AST\Theorem\Corollary($begin, ...$nodes);
			case AST\Theorem\Definition::NAME:
				return new AST\Theorem\Definition($begin, ...$nodes);
			case AST\Theorem\Example::NAME:
				return new AST\Theorem\Example($begin, ...$nodes);
			case AST\Theorem\Lemma::NAME:
				return new AST\Theorem\Lemma($begin, ...$nodes);
			case AST\Theorem\Notation::NAME:
				return new AST\Theorem\Notation($begin, ...$nodes);
			case 'pf':
			case AST\Theorem\Proof::NAME:
				return new AST\Theorem\Proof($begin, ...$nodes);
			case AST\Theorem\Proposition::NAME:
				return new AST\Theorem\Proposition($begin, ...$nodes);
			case AST\Theorem\Remark::NAME:
				return new AST\Theorem\Remark($begin, ...$nodes);
			case AST\Theorem\Result::NAME:
				return new AST\Theorem\Result($begin, ...$nodes);
			case AST\Theorem\Theorem::NAME:
				return new AST\Theorem\Theorem($begin, ...$nodes);
			default:
				return new AST\Section($begin, ...$nodes);
		}
	}

	private function parseCommand(array $token) : AST\Command
	{
		$name = ltrim($token[Tokenizer::VALUE], '\\');
		$lName = trim(strtolower($name));

		$arguments = $outerOpen = [];
		while ($this->stream->isNext(Tokenizer::TOKEN_BRACE_CURLY_LEFT, Tokenizer::TOKEN_BRACE_SQUARE_LEFT)) {
			$open = $this->stream->nextToken();
			if ($this->stream->isNext(Tokenizer::TOKEN_BRACE_CURLY_LEFT, Tokenizer::TOKEN_BRACE_SQUARE_LEFT)) {
				$outerOpen = $open;
				$open = $this->stream->nextToken();
			}

			$argument = $this->parseCommandArgument($open, str_replace('left', 'right', $open[Tokenizer::TYPE]));

			if ($outerOpen) {
				$outerClose = str_replace('left', 'right', $outerOpen[Tokenizer::TYPE]);
				if (!$this->stream->isNext($outerClose)) {
					throw new UnexpectedTokenException($this->stream->nextToken(), $outerClose);
				}
				$this->stream->nextToken(); // eat the bracket
				// $argument = new AST\Brackets($argument, $outerOpen[Tokenizer::TYPE] === Tokenizer::TOKEN_BRACE_SQUARE_LEFT); // todo?
				$outerOpen = [];
			}

			$arguments[] = $argument;
		}

		switch ($lName) {
			case 'ms':
			case 'medskip':
			case 'smallskip':
			case 'bigskip':
			case 'par':
				return new AST\Style\NewParagraph($name, ...$arguments);
			case 'uv':
				return new AST\Style\TypographicQuote($name, ...$arguments);
			case 'ul':
				return new AST\Style\Underlined($name, ...$arguments);
			case 'textit':
				return new AST\Style\Italic($name, ...$arguments);
			case 'fbox':
				return new AST\Style\Border($name, ...$arguments);
			case 'begin':
			case 'end':
				return new AST\SectionBoundary($name, ...$arguments);
			default:
				return new AST\Command($name, ...$arguments);
		}
	}

	private function parseCommandArgument(array $open, string $closeType) : AST\CommandArgument
	{
		$nodes = [];
		while (!$this->stream->isNext($closeType)) {
			$nodes[] = $this->parseNext();
		}
		$this->stream->nextToken(); // closing bracket
		return new AST\CommandArgument($open[Tokenizer::TYPE] === Tokenizer::TOKEN_BRACE_SQUARE_LEFT, ...$nodes);
	}

	private function parseMath(array $token) : AST\Math
	{
		$content = $this->stream->joinUntil($token[Tokenizer::TYPE]);
		$this->stream->nextToken();
		return new AST\Math(new AST\Text($content), $token[Tokenizer::TYPE] === Tokenizer::TOKEN_MATH_INLINE);
	}

}
