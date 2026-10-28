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

namespace TheliaCMS\Tests\Integration\Settings;

use Symfony\Component\DomCrawler\Form;
use Thelia\Model\ModuleConfigQuery;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The "Generator" card of CMS > Settings: the model, the effort and the
 * length of an answer are saved from the screen, and the key never is.
 */
final class GeneratorSettingsScreenTest extends AdminScreenTestCase
{
    private const string SCREEN = '/admin/cms/settings';

    public function testTheCardShowsTheSettingsAndNoKey(): void
    {
        $card = $this->screen(self::SCREEN)->filter('[data-testid="cms-settings-generator"]');

        self::assertSame(1, $card->count(), 'The card is rendered.');
        self::assertSame(1, $card->filter('input[name="cms_settings[generatorModel]"]')->count());
        self::assertSame(1, $card->filter('select[name="cms_settings[generatorEffort]"]')->count());
        self::assertSame(1, $card->filter('input[name="cms_settings[generatorMaxTokens]"]')->count());
        self::assertSame(1, $card->filter('input[type="checkbox"][name="cms_settings[generatorWebFetch]"]')->count());
        self::assertStringContainsString('billed as input tokens', $card->filter('[data-testid="cms-settings-web-fetch-help"]')->text(''), 'The box says what reading a page costs, which the theme of the back office leaves out of a checkbox.');
        self::assertSame(0, $card->filter('input[type="password"], input[name*="Key"], input[name*="key"]')->count(), 'No field takes the API key.');
        self::assertStringContainsString('ANTHROPIC_API_KEY', $card->text(''));
    }

    public function testPostingTheFormChangesWhatTheGeneratorReads(): void
    {
        $form = $this->settingsForm();
        $form['cms_settings[generatorModel]'] = 'claude-sonnet-5';
        $form['cms_settings[generatorEffort]'] = 'low';
        $form['cms_settings[generatorMaxTokens]'] = '4096';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        ModuleConfigQuery::resetConfigCache();
        $settings = $this->settings();
        self::assertSame('claude-sonnet-5', $settings->model());
        self::assertSame('low', $settings->effort());
        self::assertSame(4096, $settings->maxTokens());
    }

    public function testReadingTheWebPagesABriefCitesIsOnUntilTheSiteTurnsItOff(): void
    {
        self::assertTrue($this->settings()->webFetch(), 'On by default.');

        $form = $this->settingsForm();
        self::assertTrue($form['cms_settings[generatorWebFetch]']->hasValue(), 'The box shows the setting ticked.');
        $form['cms_settings[generatorWebFetch]']->untick();

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        ModuleConfigQuery::resetConfigCache();
        self::assertFalse($this->settings()->webFetch());

        $form = $this->settingsForm();
        self::assertFalse($form['cms_settings[generatorWebFetch]']->hasValue(), 'The box shows the setting as saved.');
        $form['cms_settings[generatorWebFetch]']->tick();
        $this->send('POST', $form->getUri(), $form->getPhpValues());

        ModuleConfigQuery::resetConfigCache();
        self::assertTrue($this->settings()->webFetch());
    }

    /**
     * A short text written from a brief has settings of its own: a paragraph
     * does not need the effort, nor the length, of a whole page. Left empty,
     * the model is the one of the pages.
     */
    public function testTheTextsWrittenFromABriefHaveTheirOwnSettings(): void
    {
        $card = $this->screen(self::SCREEN)->filter('[data-testid="cms-settings-generator-text"]');

        self::assertSame(1, $card->filter('input[name="cms_settings[textModel]"]')->count());
        self::assertSame(1, $card->filter('select[name="cms_settings[textEffort]"]')->count());
        self::assertSame(1, $card->filter('input[name="cms_settings[textMaxTokens]"]')->count());

        $settings = $this->settings();
        self::assertSame($settings->model(), $settings->textModel(), 'By default, the model of the pages.');
        self::assertSame('low', $settings->textEffort());
        self::assertSame(GeneratorSettings::DEFAULT_TEXT_MAX_TOKENS, $settings->textMaxTokens());

        $form = $this->settingsForm();
        $form['cms_settings[textModel]'] = 'claude-haiku-5-5';
        $form['cms_settings[textEffort]'] = 'medium';
        $form['cms_settings[textMaxTokens]'] = '3000';

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        ModuleConfigQuery::resetConfigCache();
        $settings = $this->settings();
        self::assertSame('claude-haiku-5-5', $settings->textModel());
        self::assertSame('medium', $settings->textEffort());
        self::assertSame(3000, $settings->textMaxTokens());
        self::assertNotSame('claude-haiku-5-5', $settings->model(), 'The pages keep their own model.');

        $form = $this->settingsForm();
        $form['cms_settings[textModel]'] = '';
        $form['cms_settings[textMaxTokens]'] = '10';
        $this->send('POST', $form->getUri(), $form->getPhpValues());

        ModuleConfigQuery::resetConfigCache();
        $settings = $this->settings();
        self::assertSame($settings->model(), $settings->textModel(), 'Emptied, the model follows the pages again.');
        self::assertSame(GeneratorSettings::DEFAULT_TEXT_MAX_TOKENS, $settings->textMaxTokens(), 'A length too short for a paragraph comes back to the default.');
    }

    public function testAnEffortTheApiDoesNotKnowIsRefused(): void
    {
        $before = $this->settings()->effort();
        $form = $this->settingsForm();
        $values = $form->getPhpValues();
        $values['cms_settings']['generatorEffort'] = 'infinite';

        self::assertSame(200, $this->send('POST', $form->getUri(), $values));

        self::assertGreaterThan(0, $this->client->getCrawler()->filter('.invalid-feedback, .form-error-message')->count(), 'The refusal is shown.');
        ModuleConfigQuery::resetConfigCache();
        self::assertSame($before, $this->settings()->effort());
    }

    private function settingsForm(): Form
    {
        return $this->screen(self::SCREEN)->filter('form[name="cms_settings"]')->form();
    }

    private function settings(): GeneratorSettings
    {
        $settings = $this->getService(GeneratorSettings::class);
        \assert($settings instanceof GeneratorSettings);

        return $settings;
    }
}
