<?php

require_once __DIR__ . '/../vendor/autoload.php';

\Tracy\Debugger::enable(\Tracy\Debugger::DEVELOPMENT, __DIR__ . '/../tmp');
\Tracy\Debugger::$logSeverity = E_NOTICE | E_WARNING;

$input = new TexToWiki\Latex\Parser();
$document = $input->parse(file_get_contents(__DIR__ . '/../input/MB102/prednasky_MB102-c.tex'));

$serializer = new \TexToWiki\Mediawiki\Serializer();
$sections = $serializer->convert($document);
$i = 1;
foreach ($sections as $name => $section) {
	$filename = sprintf(__DIR__ . '/../output/MB102/%d. %s.txt', $i++, $name);
	\Nette\Utils\FileSystem::createDir(dirname($filename));
	file_put_contents($filename, $section);
}
