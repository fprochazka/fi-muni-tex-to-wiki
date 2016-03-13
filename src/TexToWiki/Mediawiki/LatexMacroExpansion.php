<?php

namespace TexToWiki\Mediawiki;

use Nette\Utils\Strings;
use TexToWiki\InvalidArgumentException;
use TexToWiki\InvalidStateException;
use TexToWiki\Latex\TokenIterator;
use TexToWiki\Latex\Tokenizer;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LatexMacroExpansion
{

	const TOKEN_REDUCED = 't_reduced';

	/** @var array */
	private $macros = [];

	/** @var string[] */
	private $context;

	/** @var Tokenizer */
	private $tokenizer;

	public function __construct()
	{
		$this->tokenizer = new Tokenizer();
	}

	public function addMacroReplacements(array $map)
	{
		foreach ($map as $name => $replacement) {
			$this->addMacroReplacement($name, $replacement);
		}
		return $this;
	}

	public function addMacroReplacement(string $name, string $replacement) : self
	{
		$this->macros[$name] = (object) [
			'arguments' => 0,
			'handler' => $c = function () use ($replacement) : string {
				return '\\' . $replacement;
			},
			'paramsReflection' => (new \ReflectionFunction($c))->getParameters(),
		];
		return $this;
	}

	public function addMacroHandler(string $name, int $argumentsCount, \Closure $handler) : self
	{
		$this->macros[$name] = (object) [
			'arguments' => $argumentsCount,
			'handler' => $handler,
			'paramsReflection' => (new \ReflectionFunction($handler))->getParameters(),
		];
		return $this;
	}

	public function expand(string $latex)
	{
		$this->context = [];
		$stream = $this->tokenizer->tokenize($latex);
		while ($stream->isNext()) {
			$this->reduceNext($stream);
		}

		return self::streamToString($stream);
	}

	private function reduceNext(TokenIterator $stream)
	{
		$token = $stream->nextToken();
		switch ($token[Tokenizer::TYPE]) {
			case Tokenizer::TOKEN_COMMAND_BEGIN:
			case Tokenizer::TOKEN_COMMAND_END:
			case Tokenizer::TOKEN_COMMAND_SECTION:
			case Tokenizer::TOKEN_COMMAND_SUBSECTION:
			case Tokenizer::TOKEN_COMMAND:
				return $this->reduceCommand($stream, $token);
			case Tokenizer::TOKEN_BRACE_CURLY_LEFT:
				return $this->reduceScope($stream, $token);
			case Tokenizer::TOKEN_MATH_BLOCK:
			case Tokenizer::TOKEN_MATH_INLINE:
				return $this->reduceMath($stream, $token);
		}
	}

	private function reduceCommand(TokenIterator $stream, array $beginToken)
	{
		$name = substr($beginToken[Tokenizer::VALUE], 1);
		$beginPosition = $stream->position;

		try {
			$this->context[] = $name;
			$arguments = $this->reduceCommandArguments($stream, $name);

			if (array_key_exists($name, $this->macros)) {
				$replacement = $this->callCommandHandler($name, $arguments);

			} else {
				$replacement = self::streamToString($stream, $beginPosition, $stream->position + 1);
			}

			$reduced = self::reduceRange($stream, $replacement, $beginPosition, $stream->position + 1);
			$stream->tokens = $reduced->tokens;
			$stream->position = $reduced->position;

		} finally {
			array_pop($this->context);
		}
	}

	private function callCommandHandler(string $name, array $arguments) : string
	{
		$macro = $this->macros[$name];
		if ($macro->arguments !== count($arguments)) {
			throw new InvalidArgumentException;
		}

		if (isset($macro->paramsReflection[0]) && $macro->paramsReflection[0]->getName() === 'context') {
			array_unshift($arguments, $this->context);
		}

		return call_user_func_array($macro->handler, $arguments);
	}

	private function reduceCommandArguments(TokenIterator $stream, string $name) : array
	{
		$expectedArguments = array_key_exists($name, $this->macros)
			? $this->macros[$name]->arguments
			: NULL;

		if ($expectedArguments === 0) {
			return [];
		}

		$lookForBraces = [Tokenizer::TOKEN_BRACE_CURLY_LEFT];
		if ($expectedArguments !== null) {
			$lookForBraces[] = Tokenizer::TOKEN_BRACE_SQUARE_LEFT;
		}

		$arguments = [];
		while ($cursor = $stream->lookahead($lookForBraces, Tokenizer::TOKEN_WHITESPACE, Tokenizer::TOKEN_NEWLINE)) {
			$stream->position = $cursor->position;
			$arguments[] = $this->reduceScope($stream, $stream->nextToken());

			if ($expectedArguments !== null && $expectedArguments === count($arguments)) {
				break;
			}
		}

		return $arguments;
	}

	private function reduceScope(TokenIterator $stream, array $begin) : string
	{
		$beginPosition = $stream->position;
		while (!$stream->isNext(str_replace('left', 'right', $begin[Tokenizer::TYPE]))) {
			if (!$stream->isNext()) {
				throw new InvalidStateException;
			}
			$this->reduceNext($stream);
		}

		$stream->nextToken(); // skip brace
		$content = self::streamToString($stream, $beginPosition + 1, $stream->position);
		$replacement = self::streamToString($stream, $beginPosition, $stream->position + 1);

		$reduced = self::reduceRange($stream, $replacement, $beginPosition, $stream->position + 1);
		$stream->tokens = $reduced->tokens;
		$stream->position = $reduced->position;

		return $content;
	}

	private function reduceMath(TokenIterator $stream, array $token)
	{
		if (in_array('text', $this->context, true) || in_array('fbox', $this->context, true)) {
			return; // ignore
		}

		unset($stream->tokens[$stream->position]);
		$stream->tokens = array_values($stream->tokens); // reset numbering
		$stream->position--;
	}

	private static function reduceRange(TokenIterator $stream, string $replacement, int $begin, int $end, array $meta = []) : TokenIterator
	{
		$copy = clone $stream;
		$copy->tokens = array_values(array_merge(
				array_slice($stream->tokens, 0, $begin),
				[
					[
						Tokenizer::VALUE => $replacement,
						Tokenizer::OFFSET => $stream->tokens[$begin][Tokenizer::OFFSET],
						Tokenizer::TYPE => self::TOKEN_REDUCED,
					],
				],
				array_slice($stream->tokens, $end)
			)
		);
		$copy->position = $begin;
		return $copy;
	}

	public static function mask(string $mask)
	{
		return function (string ...$args) use ($mask) : string {
			return Strings::replace($mask, '~\\{\\#(?P<n>\d+)\\}~', function (array $m) use ($args) : string {
				return $args[$m['n'] - 1];
			});
		};
	}

	private static function streamToString(TokenIterator $stream, int $offset = null, int $stop = null) : string
	{
		$result = '';
		$end = $stop ?? count($stream->tokens);
		for ($i = ($offset ?? 0); $i < $end ;$i++) {
			$result .= $stream->tokens[$i][Tokenizer::VALUE];
		}

		return $result;
	}

}
