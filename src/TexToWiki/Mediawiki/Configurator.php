<?php

namespace TexToWiki\Mediawiki;

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
				'st' => 'operatorname{\\textrm{st}}',
				'sgn' => 'operatorname{\\textrm{sgn}}',
				'tg' => 'operatorname{\\textrm{tg}}',
				'cotg' => 'operatorname{\\textrm{cotg}}',
				'arctg' => 'operatorname{\\textrm{arctg}}',
				'arccotg' => 'operatorname{\\textrm{arccotg}}',
				'Gr' => 'operatorname{\\textrm{Gr}}',
				'Eigen' => 'operatorname{\\textrm{Eigen}}',
				'ul' => 'underline',
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
			->addMacroHandler('label', 1, LatexMacroExpansion::mask(''))
			->addMacroHandler('ref', 1, LatexMacroExpansion::mask(''));

		$expansion->addMacroHandler('text', 1, function (...$args) {
			if (stripos($args[0], $prefix = '\\scriptsize') === 0) {
				return '\\LARGE{' . substr($args[0], strlen($prefix)) . '}';
			}
			if (stripos($args[0], $prefix = '\\rm') === 0) {
				return '\\textrm{' . substr($args[0], strlen($prefix)) . '}';
			}

			return '\\text{' . $args[0] . '}';
		});

		return $expansion;
	}

}
