<?php

namespace TexToWiki\Latex;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use TexToWiki\InvalidStateException;
use TexToWiki\Latex\AST;
use TexToWiki\NotImplementedException;

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
			case Tokenizer::TOKEN_BRACE_CURLY_LEFT:
				return $this->parseScope($token);
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

	private function parseScope(array $token) : AST\Node
	{
		if (!$this->stream->isNext(Tokenizer::TOKEN_COMMAND)) {
			return new AST\Text($token[Tokenizer::VALUE]);
		}

		$command = $this->parseCommand($this->stream->nextToken());
		if ($command->getChildren()->count()) {
			throw new InvalidStateException('Unexpected command arguments');
		}

		$body = [];
		while (!$this->stream->isNext(Tokenizer::TOKEN_BRACE_CURLY_RIGHT)) {
			$body[] = $this->parseNext();
		}
		$this->stream->nextToken(); // eat right bracket

		return $this->createCommand($command->getName(), [new AST\CommandArgument(false, ...$body)]);
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

			return new AST\MathSection($begin, ...$body);

		} else {
			$name = trim(strtolower($begin->getSectionName()));

			if (in_array($name, ['itemize', 'enumerate'], true)) {
				$nodes = $this->parseSectionItems();

			} else {
				$nodes = [];
				while ($this->stream->isNext() && !$this->stream->isNext(Tokenizer::TOKEN_COMMAND_END)) {
					$nodes[] = $this->parseNext();
				}
			}

			$this->parseSectionEnd($this->stream->nextToken(), $begin); // end command

			return $this->createSection($name, $begin, $nodes);
		}
	}

	private function parseSectionItems() : array
	{
		/** @var AST\Command $itemOpened */
		$itemOpened = null;
		$items = $nodes = [];
		while ($this->stream->isNext() && !$this->stream->isNext(Tokenizer::TOKEN_COMMAND_END)) {
			if (!$this->stream->isNext(Tokenizer::TOKEN_COMMAND)) {
				if ($itemOpened) {
					$nodes[] = $this->parseNext();
				} else {
					$this->parseNext(); // skip
				}
				continue;
			}

			$nextCommand = $this->stream->nextToken();
			$command = $this->parseCommand($nextCommand);
			if ($nextCommand[Tokenizer::VALUE] === '\\item') {
				if ($itemOpened) {
					$items[] = $this->createEnumerationItem($itemOpened, $nodes);
					$nodes = [];
				}
				$itemOpened = $command;

			} else {
				$nodes[] = $command;
			}
		}

		if ($itemOpened && $nodes) {
			$items[] = $this->createEnumerationItem($itemOpened, $nodes);
		}

		return $items;
	}

	private function createEnumerationItem(AST\Command $openingItem, array $body) : AST\EnumerationItem
	{
		$args = $openingItem->getArguments()->toArray();
		$args[] = new AST\CommandArgument(false, ...$body);
		return new AST\EnumerationItem('item', ...$args);
	}

	private function parseMathBlockBody(AST\SectionBoundary $begin) : array
	{
		$body = [];
		$startPosition = $this->stream->position;
		while ($this->stream->isNext()) {
			if ($this->stream->isNext(Tokenizer::TOKEN_COMMAND)) {
				$token = $this->stream->nextToken();
				$commandName = ltrim($token[Tokenizer::VALUE], '\\');
				switch ($commandName) {
					case 'label':
						$body[] = $this->parseCommand($token);
				}

			} elseif ($this->stream->isNext(Tokenizer::TOKEN_COMMAND_BEGIN)) {
				$innerBegin = $this->parseSectionBegin($this->stream->nextToken());
				foreach ($this->parseMathBlockBody($begin) as $node) {
					if ($node instanceof AST\Command) {
						$body[] = $node;
					}
				}
				$this->parseSectionEnd($this->stream->nextToken(), $innerBegin);

			} elseif ($this->stream->isNext(Tokenizer::TOKEN_COMMAND_END)) {
				break;
			}

			$this->stream->nextUntil(Tokenizer::TOKEN_COMMAND, Tokenizer::TOKEN_COMMAND_BEGIN, Tokenizer::TOKEN_COMMAND_END);
		}
		$endPosition = $this->stream->position;

		$text = null;
		for ($this->stream->position = $startPosition; $endPosition > $this->stream->position ;) {
			$text .= $this->stream->nextValue();
		}

		$body[] = new AST\Math(new AST\Text($text), false);
		return $body;
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

	private function createSection($name, AST\Command $begin, array $nodes) : AST\Section
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
			case 'reseni':
			case AST\Theorem\Solution::NAME:
				return new AST\Theorem\Solution($begin, ...$nodes);
			case AST\Theorem\Theorem::NAME:
				return new AST\Theorem\Theorem($begin, ...$nodes);
			default:
				return new AST\Section($begin, ...$nodes);
		}
	}

	/**
	 * @return AST\Command|AST\Section
	 */
	private function parseCommand(array $token) : AST\Node
	{
		$name = ltrim($token[Tokenizer::VALUE], '\\');

		/** @var AST\CommandArgument[] $arguments */
		$arguments = [];
		while ($cursor = $this->stream->lookahead([Tokenizer::TOKEN_BRACE_CURLY_LEFT, Tokenizer::TOKEN_BRACE_SQUARE_LEFT], Tokenizer::TOKEN_WHITESPACE, Tokenizer::TOKEN_NEWLINE)) {
			$this->stream->position = $cursor->position;
			$open = $this->stream->nextToken();
			$arguments[] = $this->parseCommandArgument($open, str_replace('left', 'right', $open[Tokenizer::TYPE]));
		}

		if ($name === 'reseni') {
			if (count($arguments) !== 1) {
				throw new NotImplementedException;
			}

			return $this->createSection(
				$name,
				new AST\Command($name, new AST\CommandArgument(false, new AST\Text($name))),
				$arguments[0]->getChildren()->toArray()
			);
		}

		return $this->createCommand($name, $arguments);
	}

	private function createCommand(string $name, array $arguments) : AST\Node
	{
		switch ($name) {
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
			case 'bf':
				return new AST\Style\Bold($name, ...$arguments);
			case 'textit':
				return new AST\Style\Italic($name, ...$arguments);
			case 'fbox':
				return new AST\Style\Border($name, ...$arguments);
			case 'bibitem':
				return new AST\BibiItem($name, ...$arguments);
			case 'label':
				return new AST\Label($name, ...$arguments);
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
