<?php

namespace TexToWiki\Mediawiki;

use Nette\Iterators\CachingIterator;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use TexToWiki\InvalidArgumentException;
use TexToWiki\InvalidStateException;
use TexToWiki\Latex\AST;
use TexToWiki\NotImplementedException;

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Serializer
{

	/** @var LatexMacroExpansion */
	public $macrosExpansion;

	/** @var array  */
	public $mathSectionReplacement = [
		'tabular' => 'array',
	];

	/** @var AST\Document */
	private $document;

	/** @var \ArrayObject[]|string[][]  */
	private $citationsStack = [];

	public function __construct()
	{
		$this->macrosExpansion = Configurator::configureMB102();
		$this->macrosExpansion->addMacroHandler('ref', 1, $refHandler = function (string ...$args) : string {
			list($wikiLink, $label) = $this->commandRefToWikiLink($args[0]);
			if (!$label) {
				return '';
			}

			return sprintf('\\wikiref{%s}', $wikiLink);
		});
		$this->macrosExpansion->addMacroHandler('eqref', 1, $refHandler);
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

	private function convertNodeChildren(AST\Node $node = null)
	{
		if ($node === null) {
			return;
		}

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
			case AST\Theorem\Solution::class:
			case AST\Theorem\Theorem::class:
				return $this->convertTheorem($node);
			case AST\MathSection::class:
				return $this->convertMathSection($node);
			case AST\Section::class:
				return $this->convertSection($node);
			case AST\Label::class:
				break; // ignore
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
			case 'rm':
				// typeface
				$this->convertNodeChildren($command->getBody());
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
				return $this->convertCommandRef($command);
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

	private function convertCommandRef(AST\Command $caption)
	{
		list($wikiLink, $label) = $this->commandRefToWikiLink($caption->getFirstArgument()->getFirstValue()->getValue());
		if (!$label) {
			return; // ignore?
		}
		echo sprintf('[[%s|#]]', $wikiLink);
	}

	private function commandRefToWikiLink(string $caption) : array
	{
		/** @var AST\Label $relevantLabel */
		$relevantLabel = $this->document->getLabels()
			->filter(AST\Label::filterByValue($caption))
			->first() ?: null;

		if (!$relevantLabel) {
			return [NULL, NULL]; // ignore?
		}

		$section = $relevantLabel->getTocSection();
		$subSection = $relevantLabel->getTocSubSection();
		if (!$section && !$subSection) {
			throw new InvalidStateException('TOC not found');
		}

		$toUrl = function (string $s) : string {
			return strtr($s, [
				' ' => '_',
				'\'' => '’',
			]);
		};

		$page = ':MB102';
		if ($section) {
			$page .= '/' . $toUrl($section->getName()->getValue());
		}
		if ($subSection) {
			$page .= '/' . $toUrl($subSection->getName()->getValue());
		}

		if (in_array($relevantLabel->getLabelType(), ['S', 'SS'], true)) { // toc
			$wikiLink = $page;

		} else {
			$target = $relevantLabel->getCompleteLabelId();
			$wikiLink = sprintf('%s#%s', $page, $target);
		}

		return [
			$wikiLink,
			$relevantLabel
		];
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

		if ($title = $theorem->getTitle()) {
			$el->addAttributes(['title' => trim($title->getValue())]);
		}

		if ($label = $theorem->getLabel()) {
			$el->addAttributes([
				'id' => $label->getLabelId(),
			]);
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

		$el = Html::el('math');

		/** @var AST\Label $label */
		$label = $section->getChildren(AST\Node::filterByType(AST\Label::class))
			->first() ?: null;
		if ($label) {
			$el->addAttributes([
				'id' => $label->getLabelId(),
			]);
		}

		echo "\n:", $el->startTag(), "\n";
		echo '\begin{' . $outputName . '}';

		if ($sectionName === 'tabular') {
			/** @var AST\Text $argumentText */
			$argumentText = $section->getFirstArgument()->getFirstValue();
			echo '{' . $argumentText->getValue() . '}';
		}

		$this->convertFormulae($section->getFormulae());

		echo '\end{' . $outputName . '}';
		echo "\n", $el->endTag(), "\n";
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
		/** @var AST\EnumerationItem[] $items */
		$items = $section->getChildren(AST\Node::filterByType(AST\EnumerationItem::class));

		$list = Html::el('ul');
		if ($section->getName() && $section->getName()->getValue() === 'enumerate') {
			$list->setName('ol');
			if ($specialItem = $items[0]->getFirstArgument()) {
				/** @var AST\Text $style */
				$style = $specialItem->getChildrenRecursive(AST\Node::filterByType(AST\Text::class))
					->first() ?: null;
				if ($style) {
					switch ($style->getValue()) {
						case '(i)':
							$list->setName('ul')->addClass('roman');
							break;
						case '(a)':
							$list->setName('ul')->addClass('letters');
							break;
					}
				}
			}
		}

		echo $list->startTag(), "\n";

		foreach ($items as $item) {
			ob_start();
			$this->convertNodeChildren($item->getBody());
			$rawContent = ob_get_clean();

			echo '<li>', trim($rawContent), '</li>', "\n";
		}

		echo $list->endTag(), "\n";
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
//		$rawFormulae = Strings::replace($rawFormulae, '~(?<!\\\\)\\$~', '');
		$rawFormulae = $this->macrosExpansion->expand($rawFormulae);
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
