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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Brings several components to the catalogue at once, decided at runtime: the
 * partials of the site, the components of the active theme. A component that
 * is one class is simpler written as a SectionDefinitionInterface on its own.
 */
#[AutoconfigureTag(SectionCatalog::PROVIDER_TAG)]
interface SectionProviderInterface
{
    /**
     * @return iterable<SectionDefinitionInterface>
     */
    public function sections(): iterable;
}
