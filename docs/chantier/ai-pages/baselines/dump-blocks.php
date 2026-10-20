<?php

declare(strict_types=1);

/*
 * Baseline tool: writes the markup of the ten core page blocks (1.5.0) to
 * blocks-catalogue-avant.html, next to this script. Run from the host project:
 *
 *   ddev exec php local/modules/TheliaCMS/docs/chantier/ai-pages/baselines/dump-blocks.php
 *
 * The file is the reference phase 2 compares the Cms components against: same
 * cms-* class names, same structure, so blocks.css and the theme's cms.css
 * keep applying.
 */

require __DIR__.'/../../../../../../../vendor/autoload.php';

use Symfony\Component\Translation\IdentityTranslator;
use TheliaCMS\Builder\CoreBlockCatalog;

$catalog = new CoreBlockCatalog(new IdentityTranslator());
$blocks = $catalog->blocks('fr_FR');

$out = "<!doctype html>\n<html lang=\"fr\">\n<head>\n<meta charset=\"utf-8\">\n<title>Blocs de page TheliaCMS 1.5.0 (référence)</title>\n<link rel=\"stylesheet\" href=\"/cms/blocks.css\">\n</head>\n<body>\n<div class=\"cms-page-content\">\n";
foreach ($blocks as $block) {
    $out .= \sprintf("\n<!-- %s : %s (%s) -->\n%s\n", $block->id, $block->label, $block->category, $block->content);
}
$out .= "</div>\n</body>\n</html>\n";

file_put_contents(__DIR__.'/blocks-catalogue-avant.html', $out);
printf("%d blocs écrits dans blocks-catalogue-avant.html\n", \count($blocks));
