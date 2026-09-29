<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaCMS\Builder;

use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * The canvas declaration of the front-office theme the shop runs.
 *
 * Read at every call rather than when the container is built: the active theme
 * is a setting stored in the database, and a declaration edited while working
 * on a theme shows up at the next opening of the editor.
 */
final readonly class ActiveThemeCanvas
{
    public function __construct(
        private TemplateHelperInterface $templates,
    ) {
    }

    public function declaration(): ThemeCanvas
    {
        $theme = $this->templates->getActiveFrontTemplate();

        return ThemeCanvas::declaredIn(array_map(
            static fn (TemplateDefinition $template): string => $template->getAbsolutePath(),
            [$theme, ...array_values($theme->getParentList() ?? [])],
        ));
    }
}
