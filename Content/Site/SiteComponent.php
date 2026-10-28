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

namespace TheliaCMS\Content\Site;

/**
 * A component the site made in the back office, as written: by the model from
 * a prompt, or read back from its row.
 *
 * The stylesheet is kept as written, unscoped: it is scoped to the component
 * when it is served (SiteCssScoper), so a change asked later sends the model
 * what it wrote, not what the site made of it.
 */
final readonly class SiteComponent
{
    /** What a document names a component of the site by, before its code. */
    public const string PREFIX = 'Site:';

    /**
     * @param list<array<string, mixed>> $props  the JSON form of the props (SitePropReader)
     * @param array<string, mixed>       $sample
     * @param string|null                $provenance the JSON of the mark of the core (AI Act, article 50(2)), null for a component written by hand
     */
    public function __construct(
        public string $code,
        public string $label,
        public string $description,
        public array $props,
        public array $sample,
        public string $template,
        public string $css = '',
        public ?string $provenance = null,
    ) {
    }

    /**
     * The same component with the mark of provenance of the call that wrote
     * it (ProvenanceMark), or none.
     */
    public function withProvenance(?string $provenance): self
    {
        return new self($this->code, $this->label, $this->description, $this->props, $this->sample, $this->template, $this->css, $provenance);
    }

    public function name(): string
    {
        return self::PREFIX.$this->code;
    }

    /**
     * The class the component is drawn inside, which its stylesheet is
     * scoped to: `PriceTable` is `cms-site--price-table`.
     */
    public function scopeClass(): string
    {
        return 'cms-site--'.strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $this->code));
    }
}
