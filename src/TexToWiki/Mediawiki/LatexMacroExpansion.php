<?php

namespace TexToWiki\Mediawiki;

use Nette\Utils\Strings;
use Nette\Utils\Tokenizer;
use TexToWiki\InvalidArgumentException;
use TexToWiki\Latex\TokenIterator;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LatexMacroExpansion
{

	/** @var string */
	private $name;

	/** @var int */
	private $argumentsCount;

	/** @var \Closure */
	private $handler;

	public function __construct(string $name, int $argumentsCount, \Closure $handler)
	{
		$this->name = $name;
		$this->argumentsCount = $argumentsCount;
		$this->handler = $handler;
	}

	public function getName() : string
	{
		return $this->name;
	}

	public function getArgumentsCount() : int
	{
		return $this->argumentsCount;
	}

	public function __invoke(string $latex)
	{
		if (!Strings::match($latex, '~' . preg_quote('\\' . $this->name) . '(?![a-zA-Z0-9])~')) {
			return $latex;
		}

		return $this->expand($this->tokenize($latex));
	}

	private function tokenize(string $latex)
	{
		$tokenizer = new Tokenizer([
			'command' => '(?:(?<!\\\\)|^)\\\\[a-z0-9]+',
//			'brace_left' => '(?:\\{|\\[)',
			'brace_left' => '(?:\\{)',
//			'brace_right' => '(?:\\}|\\])',
			'brace_right' => '(?:\\})',
			'backslash' => '\\\\+',
			'whitespace' => '[\\n\\r\\t ]+',
			'other' => '[^' . preg_quote('{}\\', '~') . '\\s]+', // []
		], 'i');
		return new TokenIterator($tokenizer->tokenize($latex));
	}

	private function expand(TokenIterator $stream) : string
	{
		$begin = $argumentBegin = false;
		$braces = $arguments = [];
		while ($token = $stream->nextToken()) {
			if ($begin === false) {
				if ($token[Tokenizer::TYPE] !== 'command' || $stream->currentValue() !== '\\' . $this->name) {
					continue;
				}
				$begin = $stream->position;
				$argumentBegin = false;
				continue;
			}

			if ($token[Tokenizer::TYPE] === 'brace_left') {
				$braces[] = $token[Tokenizer::VALUE];
				if (count($braces) === 1) {
					$argumentBegin = $stream->position + 1;
				}
				continue;

			} elseif ($token[Tokenizer::TYPE] === 'brace_right') {
				$pair = array_pop($braces);
				if (($pair === '{' && $token[Tokenizer::VALUE] !== '}') || ($pair === '[' && $token[Tokenizer::VALUE] !== ']')) {
					throw new InvalidArgumentException('braces do not match');
				}

				if (count($braces) !== 0) {
					continue;
				}

				$arguments[] = $this->expand($stream->slice($argumentBegin, $stream->position - $argumentBegin));
				$argumentBegin = false;

				if (count($arguments) === $this->argumentsCount) {
					$replacement = call_user_func_array($this->handler, $arguments);
					$arguments = [];
					$stream->tokens = array_values(array_merge(
							array_slice($stream->tokens, 0, $begin),
							[[
								Tokenizer::VALUE => $replacement,
								Tokenizer::OFFSET => -1,
								Tokenizer::TYPE => 'replacement',
							]],
							array_slice($stream->tokens, $stream->position + 1)
						)
					);
					$stream->position = $begin;
					$begin = false;
				}
			}
		}

		return self::streamToString($stream);
	}

	private static function streamToString(TokenIterator $stream) : string
	{
		$result = '';
		foreach ($stream->tokens as $token) {
			$result .= $token[Tokenizer::VALUE];
		}

		return $result;
	}

	public static function mask(string $mask)
	{
		return function (string ...$args) use ($mask) : string {
			return Strings::replace($mask, '~\\{\\#(?P<n>\d+)\\}~', function (array $m) use ($args) : string {
				return $args[$m['n'] - 1];
			});
		};
	}

}
