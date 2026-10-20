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

use TheliaCMS\TheliaCMS;

/**
 * The components of the catalogue the site no longer offers to the
 * generator, chosen in CMS > Components.
 *
 * A component put away is not removed: the pages that use it keep
 * rendering, and their sections keep being edited by hand. It is only left
 * out of what the generator reads and may write. Free text cannot be put
 * away: it is what the generator falls back on, and a catalogue left with
 * nothing in it gives a schema no page matches.
 *
 * Stored in `module_config` by name, so a name the catalogue no longer
 * holds (a component the theme stopped declaring) stays put away if it
 * comes back.
 */
final readonly class SectionAvailability
{
    public const string DISABLED_SECTIONS = 'disabled_sections';

    /**
     * @return list<string>
     */
    public function disabled(): array
    {
        $decoded = json_decode((string) TheliaCMS::getConfigValue(self::DISABLED_SECTIONS, '[]'), true);

        return \is_array($decoded) ? array_values(array_filter($decoded, \is_string(...))) : [];
    }

    public function isOffered(string $name): bool
    {
        return !\in_array($name, $this->disabled(), true);
    }

    public function canBeDisabled(string $name): bool
    {
        return RichTextSection::NAME !== $name;
    }

    public function setOffered(string $name, bool $offered): void
    {
        $disabled = array_values(array_diff($this->disabled(), [$name]));

        if (!$offered && $this->canBeDisabled($name)) {
            $disabled[] = $name;
            sort($disabled);
        }

        TheliaCMS::setConfigValue(self::DISABLED_SECTIONS, json_encode($disabled, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
    }
}
