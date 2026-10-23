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

namespace TheliaCMS\Generation\Admin;

use TheliaCMS\Content\Document;
use TheliaCMS\Generation\GenerationTarget;

/**
 * What the content screen shows and where its forms post, for the page it
 * writes.
 */
final readonly class ContentSubject
{
    /**
     * @param array<string, string>                      $routes              the screen, generate, revise, section, update, remove, translate and publish routes, with their parameters
     * @param list<array{locale: string, label: string}> $translationSources  the languages the target holds a draft in, to write this one from
     * @param bool                                       $publishable         false for the model of a page type, which is never published
     * @param string|null                                $draftPagePreviewUrl the page of a catalogue item showing the draft, when the item has one online
     */
    public function __construct(
        public GenerationTarget $target,
        public string $locale,
        public int $languageId,
        public string $statusLabel,
        public string $statusBadgeClass,
        public string $backUrl,
        public string $backLabel,
        public string $draftPreviewUrl,
        public ?string $previewUrl,
        public ?Document $document,
        public string $publishHint,
        public array $routes,
        public string $testId,
        public array $translationSources = [],
        public bool $publishable = true,
        public ?string $draftPagePreviewUrl = null,
    ) {
    }

    /**
     * Whether there is something to show: a document with at least one
     * section. A document emptied by hand is as blank as none.
     */
    public function hasContent(): bool
    {
        return $this->document instanceof Document && !$this->document->isEmpty();
    }

    public function url(string $action): string
    {
        return $this->routes[$action] ?? throw new \LogicException(\sprintf('The content screen has no "%s" route.', $action));
    }
}
