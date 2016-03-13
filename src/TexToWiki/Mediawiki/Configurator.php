<?php

namespace TexToWiki\Mediawiki;

use Nette\Utils\Strings;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Configurator
{

	public static function configureMB102() : LatexMacroExpansion
	{
		$expansion = (new LatexMacroExpansion())
			->addMacroReplacements([
				'Rbb' => 'R',
				'Zbb' => 'Z',
				'Nbb' => 'N',
				'Cbb' => 'C',
				'Ibb' => 'I',
				'Qbb' => 'Q',
				'Dbb' => 'D',
				'D' => 'mathcal{D}',
				'H' => 'mathcal{H}',
				'L' => 'mathcal{L}',
				'R' => 'mathcal{R}',
				'P' => 'mathcal{P}',
				'eps' => 'varepsilon',
				'dx' => 'mathrm{d}x',
				'e' => 'mathrm{e}',
				'la' => 'lambda',
				'al' => 'alpha',
				'be' => 'beta',
				'ps' => 'psi',
				'De' => 'Delta',
			])
			->addMacroHandler('mdet', 1, LatexMacroExpansion::mask('\left|\,\begin{matrix} {#1} \end{matrix}\,\right|'))
			->addMacroHandler('mmatrix', 1, LatexMacroExpansion::mask('\left(\begin{matrix} {#1} \end{matrix}\right)'))
			->addMacroHandler('bigseq', 3, LatexMacroExpansion::mask('\big\{{#1}\big\}_{{#2}={#3}}^\infty'))
			->addMacroHandler('bigtyp', 1, LatexMacroExpansion::mask('\quad\big| \text{ typ } {#1}\ \big|'))
			->addMacroHandler('biggtyp', 1, LatexMacroExpansion::mask('\quad\bigg| \text{ typ } {#1}\ \bigg|'))
			->addMacroHandler('perpartes', 4, LatexMacroExpansion::mask("\\quad\\bigg| \\begin{array}{ll}\n  u'={#1} \\quad & u={#2} \\\\\n  v={#3} \\quad & v'={#4}\n\\end{array} \\bigg|"))
			->addMacroHandler('substituce', 2, LatexMacroExpansion::mask('\quad\left| \begin{array}{l} {#1} \\\\ {#2} \end{array}\ \right|'))
			->addMacroHandler('lowint', 2, LatexMacroExpansion::mask('{\ul{\int}}_{\,\, {#1}}^{\,\, {#2}}'))
			->addMacroHandler('upint', 2, LatexMacroExpansion::mask('{\overline{\int}}_{\!\!\! {#1}}^{\,\,\, {#2}}'))
			->addMacroHandler('bigmeze', 3, LatexMacroExpansion::mask('\big[\,{#1}\,\big]_{{#2}}^{{#3}}'))
			->addMacroHandler('biggmeze', 3, LatexMacroExpansion::mask('\bigg[\,{#1}\,\bigg]_{{#2}}^{{#3}}'))
			->addMacroHandler('rada', 3, LatexMacroExpansion::mask('\sum_{{#2}={#3}}^\infty {#1}'))
			->addMacroHandler('mathbox', 1, LatexMacroExpansion::mask('\fbox{$\displaystyle \, {#1} \, $}\,'))
			->addMacroHandler('qtextq', 1, LatexMacroExpansion::mask('\quad\text{{#1}}\quad'))
			->addMacroHandler('qqtextqq', 1, LatexMacroExpansion::mask('\qquad\text{{#1}}\qquad'))
			->addMacroHandler('label', 1, LatexMacroExpansion::mask(''));

		$expansion->addMacroHandler('ul', 1, $ul = function (array $context, string ...$args) : string {
			$parentContext = isset($context[$i = count($context) - 2]) ? $context[$i] : null;

			if ($parentContext === 'text') {
				return '}\\underline{\\text{' . $args[0] . '}}\\text{';

			} else {
				return '\\underline{' . $args[0] . '}';
			}
		});
		$expansion->addMacroHandler('underline', 1, $ul);

		$expansion->addMacroHandler('text', 1, function (string ...$args) use ($expansion) : string {
			$content = $args[0];
			if (empty($content)) {
				return '';
			}

			$expansion = new LatexMacroExpansion();
			if ($m = Strings::match($args[0], '~^(?P<macro>\\\\(scriptsize|small))\\s~')) {
				$expansion->addMacroHandler('text', 1, function (string ...$args) use ($m) : string {
					return '{' . $m['macro'] . '\\text{' . $args[0] . '}}';
				});
				$content = substr($args[0], strlen($m['macro']));

			} elseif (stripos($args[0], $prefix = '\\rm') === 0) {
				$expansion->addMacroHandler('text', 1, function (string ...$args) : string {
					return '\\textrm{' . $args[0] . '}';
				});
				$content = substr($args[0], strlen($prefix));

			} else {
				$expansion->addMacroHandler('text', 1, function (string ...$args) : string {
					return empty($args[0]) ? '' : '\\text{' . $args[0] . '}';
				});
			}

			return $expansion->expand('\\text{' . $content . '}');
		});

		$expansion->addMacroHandler('operatorname', 1, function (string ...$args) : string {
			if (in_array($args[0], ['sin', 'cos', 'e'], true)) {
				return '\\' . $args[0];
			}

			return '\\operatorname{' . $args[0] . '}';
		});

		$expansion->addMacroHandler('uv', 1, function (string ...$args) : string {
			return '„' . $args[0] . '“';
		});

		return $expansion;
	}

}
