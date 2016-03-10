<?php

namespace TexToWiki\Latex;

use Nette\Utils\Tokenizer as NTokenizer;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Tokenizer
{

	const VALUE = NTokenizer::VALUE;
	const OFFSET = NTokenizer::OFFSET;
	const TYPE = NTokenizer::TYPE;
	const LINE = 3;
	const COLUMN = 4;

	const TOKEN_COMMENT = 't_comment';
//	const TOKEN_BRACE_LEFT_ESCAPED = 'brace_left_escaped'; // \(
//	const TOKEN_BRACE_LEFT = 'brace_left'; // (
//	const TOKEN_BRACE_RIGHT_ESCAPED = 'brace_right_escaped'; // \)
//	const TOKEN_BRACE_RIGHT = 'brace_right'; // )
	const TOKEN_BRACE_CURLY_LEFT_ESCAPED = 't_brace_curly_left_escaped'; // \{
	const TOKEN_BRACE_CURLY_LEFT = 't_brace_curly_left'; // {
	const TOKEN_BRACE_CURLY_RIGHT_ESCAPED = 't_brace_curly_right_escaped'; // \}
	const TOKEN_BRACE_CURLY_RIGHT = 't_brace_curly_right'; // }
	const TOKEN_BRACE_SQUARE_LEFT_ESCAPED = 't_brace_square_left_escaped'; // \[
	const TOKEN_BRACE_SQUARE_LEFT = 't_brace_square_left'; // [
	const TOKEN_BRACE_SQUARE_RIGHT_ESCAPED = 't_brace_square_right_escaped'; // \]
	const TOKEN_BRACE_SQUARE_RIGHT = 't_brace_square_right'; // ]
	const TOKEN_MATH_INLINE = 't_math_inline'; // $
	const TOKEN_MATH_BLOCK = 't_math_block'; // $$
	const TOKEN_COMMAND_BEGIN = 't_command_begin'; // \begin
	const TOKEN_COMMAND_END = 't_command_end'; // \end
	const TOKEN_COMMAND_SECTION = 't_command_section'; // \section
	const TOKEN_COMMAND_SUBSECTION = 't_command_subsection'; // \subsection
	const TOKEN_COMMAND = 't_command'; // \command
	const TOKEN_PIPE = 't_pipe'; // |
	const TOKEN_TILDA = 't_tilda'; // ~
	const TOKEN_EQUALS = 't_equals'; // =
	const TOKEN_COMMA = 't_comma'; // ,
	const TOKEN_BACKSLASH = 't_backslash'; // \
	const TOKEN_NEWLINE = 't_newline';
	const TOKEN_WHITESPACE = 't_whitespace';
	const TOKEN_STRING = 't_string';

	/** @var \Nette\Utils\Tokenizer */
	private $tokenizer;

	public function __construct()
	{
		$q = function (string $s) : string {
			return preg_quote($s, '~');
		};
		$command = function (string $name) : string {
			return '\\\\' . $name . '(?![a-zA-Z0-9])';
		};

		$this->tokenizer = new NTokenizer([
			self::TOKEN_COMMENT => '(?:(?<=\\n)|^)\\%[^\\n]*[\\n]',
//			self::TOKEN_BRACE_LEFT_ESCAPED => $q('\\('),
//			self::TOKEN_BRACE_LEFT => $q('('),
//			self::TOKEN_BRACE_RIGHT_ESCAPED => $q('\\)'),
//			self::TOKEN_BRACE_RIGHT => $q(')'),
			self::TOKEN_BRACE_CURLY_LEFT_ESCAPED => $q('\\{'),
			self::TOKEN_BRACE_CURLY_LEFT => $q('{'),
			self::TOKEN_BRACE_CURLY_RIGHT_ESCAPED => $q('\\}'),
			self::TOKEN_BRACE_CURLY_RIGHT => $q('}'),
			self::TOKEN_BRACE_SQUARE_LEFT_ESCAPED => $q('\\['),
			self::TOKEN_BRACE_SQUARE_LEFT => $q('['),
			self::TOKEN_BRACE_SQUARE_RIGHT_ESCAPED => $q('\\]'),
			self::TOKEN_BRACE_SQUARE_RIGHT => $q(']'),
			self::TOKEN_MATH_BLOCK => $q('$$'),
			self::TOKEN_MATH_INLINE => $q('$'),
			self::TOKEN_COMMAND_BEGIN => $command('begin'),
			self::TOKEN_COMMAND_END => $command('end'),
			self::TOKEN_COMMAND_SECTION => $command('section'),
			self::TOKEN_COMMAND_SUBSECTION => $command('subsection'),
			self::TOKEN_COMMAND => '(?:(?<!\\\\)|^)\\\\[a-zA-Z0-9]+',
			self::TOKEN_PIPE => $q('|'),
			self::TOKEN_TILDA => $q('~'),
			self::TOKEN_EQUALS => $q('='),
			self::TOKEN_COMMA => $q(','),
			self::TOKEN_BACKSLASH => '\\\\+',
			self::TOKEN_NEWLINE => '\\n',
			self::TOKEN_WHITESPACE => '\\s+',
			self::TOKEN_STRING => '[^' . $q('$[]{}|~=,\\') . '\\n]+', // ()
		], 'm');
	}

	public function tokenize(string $string) : TokenIterator
	{
		$line = $column = 1;

		$tokens = $this->tokenizer->tokenize($string);
		foreach ($tokens as $i => $token) {
			$tokens[$i][self::LINE] = $line;
			$tokens[$i][self::COLUMN] = $column;

			$line += mb_substr_count($token[self::VALUE], "\n");
			if (stripos($token[self::VALUE], "\n") !== FALSE) {
				$column = 0; // tokens always end with newline
			} else {
				$column += mb_strlen($token[self::VALUE]);
			}
		}

		return new TokenIterator($tokens);
	}

	public static function tokenToAssoc(array $token)
	{
		return [
			'value' => $token[self::VALUE],
			'offset' => $token[self::OFFSET],
			'type' => $token[self::TYPE],
			'line' => $token[self::LINE],
			'column' => $token[self::COLUMN],
		];
	}

}
