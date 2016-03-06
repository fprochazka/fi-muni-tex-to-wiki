<?php

namespace TexToWiki\Mediawiki;

use Nette\Iterators\CachingIterator;
use Nette\Utils\Html;
use Nette\Utils\Strings;
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
	];

	public function convert(AST\Document $document) : \Generator
	{
		foreach ($document->getSections() as $section) {
			$name = $section->getName()->getValue();
			yield $name => $this->convertTocSection($section);
		}
	}

	private function convertTocSection(AST\Toc\Section $section) : string
	{
		ob_start();

		echo '# ', $section->getName()->getValue(), "\n\n";
		$this->convertNodeChildren($section);

		$content = ob_get_clean();
		$content = Strings::replace($content, '~\n\n+~', "\n\n");

		return $content;
	}

	private function convertTocSubSection(AST\Toc\SubSection $subSection)
	{
		echo '## ', $subSection->getName()->getValue(), "\n\n";
		$this->convertNodeChildren($subSection);
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
		echo $text->getValue();
	}

	private function convertNewParagraph(AST\Style\NewParagraph $paragraph)
	{
		echo "\n\n";
	}

	private function convertMath(AST\Math $math)
	{
		if (!$math->isInline()) {
			echo "\n:";
		}
		echo '<math>';
		$this->convertFormulae($math->getFormulae());
		echo '</math>';
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
				// todo
				break;
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

	private function convertCommandHref(AST\Command $command)
	{
		/** @var AST\CommandArgument $link */
		/** @var AST\CommandArgument $title */
		list($link, $title) = $command->getArguments()->toArray();
		echo '[', $link->getFirstValue()->getValue(), ' ', $title->getFirstValue()->getValue(), ']';
	}

	private function convertCommandUrl(AST\Command $command)
	{
		/** @var AST\CommandArgument $link */
		$link = $command->getArguments()->first();
		echo '[', $link->getFirstValue()->getValue(), ']';
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
		foreach ($theorem->getArguments() as $argument) {
			foreach ($it = new CachingIterator($argument->getChildren()) as $argumentNode) {
				if ($argumentNode instanceof AST\Command && $argumentNode->getName() === 'bf') {
					/** @var AST\Text $title */
					$title = $it->getNextValue();
					$el->addAttributes(['title' => $title->getValue()]);
					$it->next(); // skip title
				}
			}
		}
		echo $el->startTag();
		$this->convertNodes($theorem->getBody());
		echo $el->endTag();
	}

	private function convertMathSection(AST\MathSection $section)
	{
		$sectionName = $section->getName()->getValue();

		echo ":<math>\n";
		echo '\begin{' . $sectionName . '}';

		if ($sectionName === 'tabular') {
			/** @var AST\Text $argumentText */
			$argumentText = $section->getFirstArgument()->getFirstValue();
			echo '{' . $argumentText->getValue() . '}';
		}

		$this->convertFormulae($section->getFormulae());

		echo '\end{' . $sectionName . '}';
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
		$rawFormulae = strtr($rawFormulae, $this->texMacros);

		echo $rawFormulae;
	}

}
