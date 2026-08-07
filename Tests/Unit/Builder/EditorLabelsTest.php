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

namespace TheliaCMS\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;

/**
 * The wording GrapesJS ships in English only travels from the server to the
 * editor through three files, and a break anywhere on that path is silent: the
 * editor simply keeps showing English.
 *
 * Read here rather than exercised through a booted editor, because what breaks
 * is the agreement between the three: a key renamed on one side, or a screen
 * that forgets to hand the labels over.
 */
final class EditorLabelsTest extends TestCase
{
    private const string CONFIG_FILE = __DIR__.'/../../../Builder/CmsBuilderConfig.php';
    private const string CONTROLLER_FILE = __DIR__.'/../../../assets/src/cms_page_builder_controller.js';

    /** Every screen holding the editor, each with its own set of values. */
    private const array BUILDER_TEMPLATES = [
        __DIR__.'/../../../templates/backOffice/default-twig/pages/builder.html.twig',
        __DIR__.'/../../../templates/backOffice/default-twig/blocks/builder.html.twig',
    ];

    public function testTheEditorUsesEveryGroupOfLabelsTheServerSends(): void
    {
        $controller = $this->read(self::CONTROLLER_FILE);

        foreach ($this->serverLabelGroups() as $group) {
            self::assertStringContainsString(
                'labels.'.$group,
                $controller,
                \sprintf('The server sends "%s" and the editor never reads it, so it stays in English.', $group),
            );
        }
    }

    public function testEveryBuilderScreenHandsTheLabelsOver(): void
    {
        foreach (self::BUILDER_TEMPLATES as $template) {
            self::assertStringContainsString(
                'editor-labels-value',
                $this->read($template),
                \sprintf('%s opens the editor without the labels, which reverts it to English.', basename($template)),
            );
        }
    }

    /**
     * The rich-text buttons are named by GrapesJS, not by us: a name that does
     * not match one of its actions is looked up, not found, and skipped.
     */
    public function testTheRichTextButtonsAreNamedAsGrapesJsNamesThem(): void
    {
        self::assertSame(
            ['bold', 'italic', 'underline', 'strikethrough', 'link', 'wrap'],
            $this->serverRichTextActions(),
        );
    }

    /** @return list<string> */
    private function serverLabelGroups(): array
    {
        $body = $this->labelsMethodBody();

        self::assertSame(
            1,
            preg_match_all("#^\\s{12}'([a-zA-Z]+)' =>#m", $body, $matches) > 0 ? 1 : 0,
            'The labels are expected to be a literal array, so they can be read.',
        );

        return $matches[1];
    }

    /** @return list<string> */
    private function serverRichTextActions(): array
    {
        self::assertSame(
            1,
            preg_match("#'richText' => \[(.*?)\n {12}\],#s", $this->labelsMethodBody(), $group),
            'The rich-text labels are expected to be a literal array.',
        );

        preg_match_all("#'([a-zA-Z]+)' =>#", $group[1], $matches);

        return $matches[1];
    }

    private function labelsMethodBody(): string
    {
        self::assertSame(
            1,
            preg_match('#public function editorLabels\(\): array\s*\{(.*?)\n {4}\}#s', $this->read(self::CONFIG_FILE), $matches),
            'editorLabels() is expected to be readable as a whole.',
        );

        return $matches[1];
    }

    private function read(string $file): string
    {
        $source = file_get_contents($file);

        self::assertIsString($source, \sprintf('%s is expected to be readable.', $file));

        return $source;
    }
}
