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

namespace TheliaCMS\Content;

/**
 * A provider that also puts sections of the catalogue away: a theme that
 * opens a component of its own for a row of products has no use for the
 * module's, and the model is not to choose between the two.
 */
interface SectionHidingInterface
{
    /**
     * @return list<string> the names of the sections to leave out of the catalogue
     */
    public function hidden(): array;
}
