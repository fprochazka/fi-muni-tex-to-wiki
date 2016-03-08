<?php

namespace TexToWiki\Mediawiki;

use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use TexToWiki\InvalidArgumentException;
use TexToWiki\Latex\AST;
use TexToWiki\NotImplementedException;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Serializer
{

	public $texMacros = [
		'\\Rbb' => '\\R',
		'\\Zbb' => '\\Z',
		'\\Nbb' => '\\N',
		'\\Cbb' => '\\C',
		'\\Ibb' => '\\I',
		'\\Qbb' => '\\Q',
		'\\Dbb' => '\\D',
		'\\D' => '\\mathcal{D}',
		'\\H' => '\\mathcal{H}',
		'\\L' => '\\mathcal{L}',
		'\\R' => '\\mathcal{R}',
		'\\P' => '\\mathcal{P}',
		'\\text\\{\\scriptsize' => '\\LARGE{',
		'\\st' => '\\operatorname{\\textrm{st}}',
		'\\sgn' => '\\operatorname{\\textrm{sgn}}',
		'\\tg' => '\\operatorname{\\textrm{tg}}',
		'\\cotg' => '\\operatorname{\\textrm{cotg}}',
		'\\arctg' => '\\operatorname{\\textrm{arctg}}',
		'\\arccotg' => '\\operatorname{\\textrm{arccotg}}',
		'\\Gr' => '\\operatorname{\\textrm{Gr}}',
		'\\Eigen' => '\\operatorname{\\textrm{Eigen}}',
		'\\ul' => '\\underline',
		'\\eps' => '\\varepsilon',
		'\\dx' => '\\mathrm{d}x',
		'\\e' => '\\mathrm{e}',
		'\\la' => '\\lambda',
		'\\al' => '\\alpha',
		'\\be' => '\\beta',
		'\\ps' => '\\psi',
		'\\De' => '\\Delta',
	];

	public $texCallbacks = [];

	public $mathSectionReplacement = [
		'tabular' => 'array',
	];

	/** @var AST\Document */
	private $document;

	/** @var \ArrayObject[]|string[][]  */
	private $citationsStack = [];

	public function __construct()
	{
		$this->texCallbacks[] = new LatexMacroExpansion('mdet', 1, LatexMacroExpansion::mask('\left|\,\begin{matrix} {#1} \end{matrix}\,\right|'));
		$this->texCallbacks[] = new LatexMacroExpansion('mmatrix', 1, LatexMacroExpansion::mask('\left(\begin{matrix} {#1} \end{matrix}\right)'));
		$this->texCallbacks[] = new LatexMacroExpansion('bigseq', 3, LatexMacroExpansion::mask('\big\{{#1}\big\}_{{#2}={#3}}^\infty'));
		$this->texCallbacks[] = new LatexMacroExpansion('bigtyp', 1, LatexMacroExpansion::mask('\quad\big| \text{ typ } #1\ \big|'));
		$this->texCallbacks[] = new LatexMacroExpansion('biggtyp', 1, LatexMacroExpansion::mask('\quad\bigg| \text{ typ } #1\ \bigg|'));
		$this->texCallbacks[] = new LatexMacroExpansion('perpartes', 4, LatexMacroExpansion::mask("\\quad\\bigg| \\begin{array}{ll}\n  u'={#1} \\quad & u={#2} \\\\\n  v={#3} \\quad & v'={#4}\n\\end{array} \\bigg|"));
		$this->texCallbacks[] = new LatexMacroExpansion('substituce', 2, LatexMacroExpansion::mask('\quad\left| \begin{array}{l} #1 \\\\ #2 \end{array}\ \right|'));
		$this->texCallbacks[] = new LatexMacroExpansion('lowint', 2, LatexMacroExpansion::mask('{\ul{\int}}_{\,\,#1}^{\,\,#2}'));
		$this->texCallbacks[] = new LatexMacroExpansion('upint', 2, LatexMacroExpansion::mask('{\overline{\int}}_{\!\!\!#1}^{\,\,\,#2}'));
		$this->texCallbacks[] = new LatexMacroExpansion('bigmeze', 3, LatexMacroExpansion::mask('\big[\,{#1}\,\big]_{{#2}}^{{#3}}'));
		$this->texCallbacks[] = new LatexMacroExpansion('biggmeze', 3, LatexMacroExpansion::mask('\bigg[\,{#1}\,\bigg]_{{#2}}^{{#3}}'));
		$this->texCallbacks[] = new LatexMacroExpansion('rada', 3, LatexMacroExpansion::mask('\sum_{{#2}={#3}}^\infty {#1}'));
		$this->texCallbacks[] = new LatexMacroExpansion('mathbox', 1, LatexMacroExpansion::mask('\fbox{$\displaystyle \, {#1} \, $}\,'));
		$this->texCallbacks[] = new LatexMacroExpansion('qtextq', 1, LatexMacroExpansion::mask('\quad\text{ {#1} }\quad'));
		$this->texCallbacks[] = new LatexMacroExpansion('qqtextqq', 1, LatexMacroExpansion::mask('\qquad\text{ {#1} }\qquad'));
	}

	public function convert(AST\Document $document) : \Generator
	{
		Html::$xhtml = true;

		try {
			$this->document = $document;
			foreach ($document->getSections() as $section) {
				$name = $section->getName()->getValue();
				yield $name => $this->convertTocSection($section);
			}

		} finally {
			$this->document = null;
			$this->citationsStack = [];
		}
	}

	private function convertTocSection(AST\Toc\Section $section) : string
	{
		ob_start();

		echo '# ', $section->getName()->getValue(), "\n\n";
		$this->citationsStack[] = new ArrayHash();
		$this->convertNodeChildren($section);
		$this->convertReferences(array_pop($this->citationsStack));

		$content = ob_get_clean();
		$content = Strings::replace($content, '~\\n([\\t ]*\\n)+~', "\n\n");
		$content = Helpers::ltrimPerLine($content);
		$content = Helpers::removeAmbigouseNewlines($content);
		$content = Strings::replace($content, '~(?<!\\n)(\\:\\<math\\>)~', "\n\$1");

		return $content;
	}

	private function convertTocSubSection(AST\Toc\SubSection $subSection)
	{
		echo '## ', $subSection->getName()->getValue(), "\n\n";
		$this->citationsStack[] = new ArrayHash();
		$this->convertNodeChildren($subSection);
		$this->convertReferences(array_pop($this->citationsStack));
	}

	private function convertNodeChildren(AST\Node $node)
	{
		$this->convertNodes($node->getChildren());
	}

	private function convertNodes(\Traversable $nodes)
	{
		foreach ($nodes as $child) {
			$this->convertNode($child);
		}
	}

	private function convertNode(AST\Node $node)
	{
		switch (get_class($node)) {
			case AST\Toc\SubSection::class:
				return $this->convertTocSubSection($node);
			case AST\Math::class:
				return $this->convertMath($node);
			case AST\Text::class:
				return $this->convertText($node);
			case AST\Style\NewParagraph::class:
				return $this->convertNewParagraph($node);
			case AST\Style\TypographicQuote::class:
				return $this->convertTypographicQuote($node);
			case AST\Style\Underlined::class:
				return $this->convertUnderlined($node);
			case AST\Style\Italic::class:
				return $this->convertItalic($node);
			case AST\Style\Border::class:
				return $this->convertBorder($node);
			case AST\Command::class:
				return $this->convertCommand($node);
			case AST\Theorem\Assumption::class:
			case AST\Theorem\Axiom::class:
			case AST\Theorem\Conjecture::class:
			case AST\Theorem\Corollary::class:
			case AST\Theorem\Definition::class:
			case AST\Theorem\Example::class:
			case AST\Theorem\Lemma::class:
			case AST\Theorem\Notation::class:
			case AST\Theorem\Proof::class:
			case AST\Theorem\Proposition::class:
			case AST\Theorem\Remark::class:
			case AST\Theorem\Result::class:
			case AST\Theorem\Theorem::class:
				return $this->convertTheorem($node);
			case AST\MathSection::class:
				return $this->convertMathSection($node);
			case AST\Section::class:
				return $this->convertSection($node);
			default:
				throw new NotImplementedException((string) $node);
		}
	}

	private function convertText(AST\Text $text)
	{
		$rawText = $text->getValue();
		$rawText = str_replace('--', '&ndash;', $rawText);

		echo $rawText;
	}

	private function convertNewParagraph(AST\Style\NewParagraph $paragraph)
	{
		echo "\n\n";
	}

	private function convertMath(AST\Math $math)
	{
		ob_start();
		$this->convertFormulae($math->getFormulae());
		$formulae = ob_get_clean();

		if ($math->isInline()) {
			$formulae = str_replace("\n", ' ', $formulae);

		} else {
			echo "\n:";
		}
		echo '<math>', $formulae, '</math>';
		if (!$math->isInline()) {
			echo "\n";
		}
	}

	private function convertTypographicQuote(AST\Style\TypographicQuote $quote)
	{
		echo '„';
		$this->convertNodeChildren($quote->getBody());
		echo '“';
	}

	private function convertUnderlined(AST\Style\Underlined $underlined)
	{
		echo '<u>';
		$this->convertNodeChildren($underlined->getBody());
		echo '</u>';
	}

	private function convertItalic(AST\Style\Italic $italic)
	{
		echo "''";
		$this->convertNodeChildren($italic->getBody());
		echo "''";
	}

	private function convertBorder(AST\Style\Border $border)
	{
		echo '<span class="border">';
		$this->convertNodeChildren($border->getBody());
		echo '</span>';
	}

	private function convertCommand(AST\Command $command)
	{
		switch ($command->getName()) {
			case 'kp': // konec příkladu
			case 'konecprikladu':
			case 'konecprednasky':
			case 'noindent': // no indentation for the next line begin (paragraph related)
			case 'section':
			case 'subsection':
			case 'newpage':
			case 'pagebreak':
			case 'konecdokumentu':
			case 'label':
				// ignore alltogether
				break;
			case 'tt':
				// ignore typeface
				break;
			case 'href':
				return $this->convertCommandHref($command);
			case 'url':
				return $this->convertCommandUrl($command);
			case 'dots':
				echo '...';
				break;
			case 'cite':
				return $this->convertCommandCite($command);
			case 'eqref':
			case 'ref':
				// todo
				break;
			case 'reseni':
				return $this->convertTheorem(AST\Theorem\Solution::fromCommand($command));
			case 'centerline':
			case 'resizebox':
				return $this->convertNodeChildren($command->getBody());
			case 'includegraphics':
				return $this->convertCommandIncludeGraphics($command);
			default:
				throw new NotImplementedException((string) $command);
		}
	}

	private function convertCommandHref(AST\Command $href)
	{
		/** @var AST\CommandArgument $link */
		/** @var AST\CommandArgument $title */
		list($link, $title) = $href->getArguments()->toArray();
		echo '[', $link->getFirstValue()->getValue(), ' ', $title->getFirstValue()->getValue(), ']';
	}

	private function convertCommandUrl(AST\Command $url)
	{
		/** @var AST\CommandArgument $link */
		$link = $url->getArguments()->first();
		$linkValue = $link->getFirstValue()->getValue();
		echo '[', $linkValue, ' ', $linkValue, ']';
	}

	private function convertCommandCite(AST\Command $cite)
	{
		$name = $cite->getBody()->getFirstValue()->getValue();

		/** @var \ArrayObject $level */
		$level = end($this->citationsStack);
		$level[$name] = true;

		$refTag = Html::el()
			->setName('ref', true)
			->addAttributes(['name' => $name]);
		if ($cite->getArguments()->count() === 1) {
			echo $refTag;

		} else {
			ob_start();
			$this->convertNodeChildren($cite->getFirstArgument());
			$title = ob_get_clean();
			echo '[<nowiki />', $refTag, ', ', $title, ']';
		}
	}

	private function convertCommandCaption(AST\Command $caption)
	{
		/** @var AST\CommandArgument $argument */
		$argument = $caption->getArguments()->first();
		$this->convertNodes($argument->getChildren());
	}

	private function convertCommandIncludeGraphics(AST\Command $graphic)
	{
		/** @var AST\CommandArgument $graphicName */
		$graphicName = $graphic->getArguments()
			->filter(AST\CommandArgument::filterOptional(false))
			->first() ?: null;
		echo "\n[[File:" . $graphicName->getFirstValue()->getValue() . "]]\n";
	}

	private function convertTheorem(AST\Theorem\Theorem $theorem)
	{
		$el = Html::el($theorem::NAME);

		/** @var AST\Style\Bold $boldTitle */
		$boldTitle = $theorem->getChildrenRecursive(AST\Node::filterByType(AST\Command::class))
			->filter(AST\Command::filterByName('bf'))
			->first() ?: null;
		if ($boldTitle) {
			$el->addAttributes(['title' => trim($boldTitle->getFirstArgument()->getFirstValue()->getValue())]);

		} elseif (($title = $theorem->getArguments()->get(1)) && $title->isOptional()) {
			/** @var AST\CommandArgument $title */
			$el->addAttributes(['title' => trim($title->getFirstValue()->getValue())]);
		}

		ob_start();
		$this->convertNodes($theorem->getBody());
		$theoremContents = ob_get_clean();

		echo $el->startTag(), "\n";
		echo trim($theoremContents);
		echo "\n", $el->endTag();
	}

	private function convertMathSection(AST\MathSection $section)
	{
		$outputName = $sectionName = $section->getName()->getValue();
		if (array_key_exists($sectionName, $this->mathSectionReplacement)) {
			$outputName = $this->mathSectionReplacement[$sectionName];
		}

		echo "\n:<math>\n";
		echo '\begin{' . $outputName . '}';

		if ($sectionName === 'tabular') {
			/** @var AST\Text $argumentText */
			$argumentText = $section->getFirstArgument()->getFirstValue();
			echo '{' . $argumentText->getValue() . '}';
		}

		$this->convertFormulae($section->getFormulae());

		echo '\end{' . $outputName . '}';
		echo "\n</math>\n";
	}

	private function convertSection(AST\Section $section)
	{
		switch ($section->getName()->getValue()) {
			case 'center': // no styling // todo add left padding using ":"
			case 'minipage': // no styling, means columns in document
				return $this->convertNodes($section->getBody());
			case 'itemize':
			case 'enumerate':
				return $this->convertSectionItemize($section);
			case 'figure':
				return $this->convertSectionFigure($section);
			case 'thebibliography':
				break; // no output
			default:
				throw new NotImplementedException((string) $section);
		}
	}

	private function convertSectionItemize(AST\Section $section)
	{
		foreach ($section->getBody() as $node) {
			if ($node instanceof AST\Command && $node->getName() === 'item') {
				echo "\n", ($section->getName() === 'enumerate' ? '#' : '*'), ' ';
				continue;
			}
			$this->convertNode($node);
		}
	}

	private function convertSectionFigure(AST\Section $section)
	{
		$subFigures = $section->getChildrenRecursive(AST\Node::filterByType(AST\Section::class))
			->filter(AST\Section::filterByName('subfigure'));

		foreach ($subFigures as $subfigure) {
			$this->convertSectionPspicture($subfigure);
		}

		/** @var AST\Command $caption */
		$caption = $section->getChildren(AST\Node::filterByType(AST\Command::class))
			->filter(AST\Command::filterByName('caption'))
			->first() ?: null;

		if ($caption !== null) {
			$this->convertCommandCaption($caption);
		}
	}

	private function convertSectionPspicture(AST\Section $section)
	{
		/** @var AST\MathSection $picture */
		$picture = $section->getChildrenRecursive(AST\Node::filterByType(AST\MathSection::class))
			->filter(AST\Section::filterByName('pspicture', 'pspicture*'))
			->first() ?: null;

		if ($picture !== null) {
			echo "\n<pre>" . $picture->getFormulae()->getValue() . "</pre>\n";
		}

		/** @var AST\Command[] $includeGraphics */
		$includeGraphics = $section->getChildrenRecursive(AST\Node::filterByType(AST\Command::class))
			->filter(AST\Command::filterByName('includegraphics'));

		foreach ($includeGraphics as $graphic) {
			$this->convertCommandIncludeGraphics($graphic);
		}

		/** @var AST\Command $caption */
		$caption = $section->getChildrenRecursive(AST\Node::filterByType(AST\Command::class))
			->filter(AST\Command::filterByName('caption'))
			->first() ?: null;

		if ($caption !== null) {
			$this->convertCommandCaption($caption);
		}
	}

	private function convertFormulae(AST\Text $formulae)
	{
		$rawFormulae = $formulae->getValue();
		$rawFormulae = Strings::replace($rawFormulae, '~(?<!\\\\)\\$~', '');

		foreach ($this->texCallbacks as $callback) {
			$rawFormulae = $callback($rawFormulae);
		}

		foreach ($this->texMacros as $macro => $replacement) {
			$rawFormulae = Strings::replace($rawFormulae, '~' . preg_quote($macro) . '(?![a-zA-Z0-9])~', $replacement);
		}

		echo $rawFormulae;
	}

	private function convertReferences(\Traversable $refNames)
	{
		if (!iterator_count($refNames)) {
			return;
		}

		echo '== Reference ==', "\n";
		echo '<references>', "\n";
		foreach ($refNames as $name => $_) {
			$this->convertReference($name);
		}
		echo '</references>', "\n\n";
	}

	private function convertReference($name)
	{
		/** @var AST\BibiItem $reference */
		$reference = $this->document->getBibiItems()
			->filter(AST\BibiItem::filterByName($name))
			->first() ?: null;

		if (!$reference) {
			throw new InvalidArgumentException(sprintf('Missing reference %s', $name));
		}

		$el = Html::el('ref')->addAttributes(['name' => $name]);
		$content = [];
		if ($author = $reference->getBookAuthor()) {
			ob_start();
			$this->convertNodeChildren($author);
			$content[] = ob_get_clean();
		}
		if ($publicationName = $reference->getBookName()) {
			ob_start();
			$this->convertNodeChildren($publicationName);
			$content[] = "''" . ob_get_clean() . "''";
		}
		if ($publisher = $reference->getBookPublisher()) {
			ob_start();
			$this->convertNodeChildren($publisher);
			$content[] = ob_get_clean();
		}
		if ($source = $reference->getBookSource()) {
			ob_start();
			$this->convertNodeChildren($source);
			$content[] = ob_get_clean();
		}

		echo $el->add(rtrim(implode(', ', $content), '.') . '.'), "\n";
	}

}
