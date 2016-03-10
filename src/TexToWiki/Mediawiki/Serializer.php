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
				return $this->convertCommandRef($command);
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

		/** @var AST\Command $label */
		$label = $theorem->getChildren(AST\Node::filterByType(AST\Command::class))
			->filter(AST\Command::filterByName('label'))
			->first() ?: null;
		if ($label) {
			$labelValue = Strings::replace($label->getBody()->getFirstValue()->getValue(), '~^[a-z]+\\:~i');
			$el->addAttributes([
				'id' => trim(Strings::webalize($labelValue), '-'),
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
