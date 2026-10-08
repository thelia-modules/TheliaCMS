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

namespace TheliaCMS\Page;

/**
 * What the front office is allowed to see of a CMS page: published columns
 * only, never a draft. Also the payload handed to the `cmspage.*` theme hooks,
 * so third-party modules get a stable contract instead of a Propel object.
 */
final readonly class PublishedPage
{
    /**
     * @deprecated since 1.2.0, removed in 2.0.0: read $pageType. Kept for the
     *             hooks written against 1.x, derived from the type — a type
     *             that is not one of the former layouts reads as Default.
     */
    public PageLayout $layout;

    /**
     * @param string $pageType the code of the type of the page, always a valid one
     */
    public function __construct(
        public int $id,
        public string $locale,
        public string $title,
        public string $pageType,
        public string $html,
        public string $css,
        public ?string $chapo = null,
        public ?string $description = null,
        public ?PageImage $image = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public bool $noindex = false,
        public bool $nofollow = false,
        public ?\DateTimeInterface $publishedAt = null,
    ) {
        $this->layout = PageLayout::fromStorage($pageType);
    }

    /**
     * The same page with its dynamic blocks resolved. Nothing else may change:
     * this is the object the theme hooks receive.
     */
    public function withHtml(string $html): self
    {
        return new self(
            id: $this->id,
            locale: $this->locale,
            title: $this->title,
            pageType: $this->pageType,
            html: $html,
            css: $this->css,
            chapo: $this->chapo,
            description: $this->description,
            image: $this->image,
            metaTitle: $this->metaTitle,
            metaDescription: $this->metaDescription,
            noindex: $this->noindex,
            nofollow: $this->nofollow,
            publishedAt: $this->publishedAt,
        );
    }
}
