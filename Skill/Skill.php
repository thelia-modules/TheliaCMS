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

namespace TheliaCMS\Skill;

/**
 * A skill: a piece of instructions the generator reads before writing a
 * page. Shipped as a file by the module or the theme, stored in `cms_skill`
 * and edited there by the site.
 */
final readonly class Skill
{
    public const string ORIGIN_MODULE = 'module';
    public const string ORIGIN_THEME = 'theme';
    public const string ORIGIN_SITE = 'site';

    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public string $body,
        public string $origin,
        public int $position = 0,
        public bool $enabled = true,
        public bool $always = false,
        public ?string $shippedBody = null,
    ) {
    }

    /**
     * Whether the site rewrote the shipped text.
     */
    public function isEdited(): bool
    {
        return null !== $this->shippedBody && $this->shippedBody !== $this->body;
    }
}
