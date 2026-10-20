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
 * What the content screen shows and where its forms post: the same screen
 * writes a page and a block, and this is everything that differs between
 * the two.
 */
final readonly class ContentSubject
{
    /**
     * @param array<string, string> $routes the screen, generate, revise, section and publish routes, with their parameters
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
    ) {
    }

    public function url(string $action): string
    {
        return $this->routes[$action] ?? throw new \LogicException(\sprintf('The content screen has no "%s" route.', $action));
    }
}
