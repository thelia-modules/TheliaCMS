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

namespace TheliaCMS\Generation;

use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsPage;

/**
 * One call to the generator: a page, a language, and either a brief for a
 * new document, a change asked on the document the page holds, or one
 * section of it to write again.
 */
final readonly class GenerationRequest
{
    public const string KIND_BRIEF = 'brief';
    public const string KIND_REVISION = 'revision';
    public const string KIND_SECTION = 'section';

    private function __construct(
        public CmsPage $page,
        public string $locale,
        public string $kind,
        public Brief $brief,
        public ?Document $current = null,
        public ?string $sectionId = null,
    ) {
    }

    public static function fromBrief(CmsPage $page, string $locale, Brief $brief): self
    {
        return new self($page, $locale, self::KIND_BRIEF, $brief);
    }

    /**
     * A change on the whole document: "make the hero shorter", "add a FAQ".
     */
    public static function revision(CmsPage $page, string $locale, Document $current, Brief $change): self
    {
        return new self($page, $locale, self::KIND_REVISION, $change, $current);
    }

    /**
     * One section written again, the others kept as they are.
     *
     * @throws \InvalidArgumentException when the document has no such section
     */
    public static function section(CmsPage $page, string $locale, Document $current, string $sectionId, Brief $change): self
    {
        if (null === $current->section($sectionId)) {
            throw new \InvalidArgumentException(\sprintf('The page has no section "%s".', $sectionId));
        }

        return new self($page, $locale, self::KIND_SECTION, $change, $current, $sectionId);
    }
}
