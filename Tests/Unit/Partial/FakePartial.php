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

namespace TheliaCMS\Tests\Unit\Partial;

use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;

/**
 * A partial with no database and no template behind it, so the registry, the
 * prop validation and the substitution can be tested on their own.
 */
final class FakePartial implements PartialDefinitionInterface
{
    /**
     * @param list<PartialProp> $props
     */
    public function __construct(
        private readonly string $name = 'fake',
        private readonly array $props = [],
        private readonly ?int $cacheTtl = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return ucfirst($this->name);
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/'.$this->name;
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/'.$this->name.'.html.twig';
    }

    public function props(): array
    {
        return $this->props;
    }

    public function context(array $props, string $locale): array
    {
        return ['props' => $props, 'locale' => $locale];
    }

    public function cacheTtl(): ?int
    {
        return $this->cacheTtl;
    }
}
