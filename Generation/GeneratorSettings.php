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

use TheliaCMS\TheliaCMS;

/**
 * What the site decided about the generator: the model, how hard it thinks,
 * how long an answer may be, whether it reads the web pages a brief cites. The key is never here: it is read from the
 * environment, where a secret belongs, and never written to a table an
 * export would carry.
 */
final readonly class GeneratorSettings
{
    public const string MODEL = 'generator_model';
    public const string EFFORT = 'generator_effort';
    public const string MAX_TOKENS = 'generator_max_tokens';
    public const string WEB_FETCH = 'generator_web_fetch';

    // A text written from a brief in a rich-text field: a paragraph does not
    // need the effort, nor the length, of a whole page.
    public const string TEXT_MODEL = 'generator_text_model';
    public const string TEXT_EFFORT = 'generator_text_effort';
    public const string TEXT_MAX_TOKENS = 'generator_text_max_tokens';

    public const string DEFAULT_MODEL = 'claude-opus-5-5';
    public const string DEFAULT_EFFORT = 'high';
    public const int DEFAULT_MAX_TOKENS = 16000;

    /** Below this, no page fits in the answer. */
    public const int MIN_MAX_TOKENS = 1024;

    public const array EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];

    public const string DEFAULT_TEXT_EFFORT = 'low';
    public const int DEFAULT_TEXT_MAX_TOKENS = 2000;

    /** Below this, no paragraph fits in the answer, with its envelope. */
    public const int MIN_TEXT_MAX_TOKENS = 256;

    public function model(): string
    {
        $model = trim((string) TheliaCMS::getConfigValue(self::MODEL, self::DEFAULT_MODEL));

        return '' === $model ? self::DEFAULT_MODEL : $model;
    }

    public function effort(): string
    {
        $effort = (string) TheliaCMS::getConfigValue(self::EFFORT, self::DEFAULT_EFFORT);

        return \in_array($effort, self::EFFORTS, true) ? $effort : self::DEFAULT_EFFORT;
    }

    public function maxTokens(): int
    {
        $maxTokens = (int) TheliaCMS::getConfigValue(self::MAX_TOKENS, (string) self::DEFAULT_MAX_TOKENS);

        return $maxTokens >= self::MIN_MAX_TOKENS ? $maxTokens : self::DEFAULT_MAX_TOKENS;
    }

    /**
     * Off unless the site turned it on: a page read leaves this server for
     * the provider of the AI range, which the site may not want.
     */
    public function webFetch(): bool
    {
        return '1' === (string) TheliaCMS::getConfigValue(self::WEB_FETCH, '0');
    }

    /**
     * The model of the texts written from a brief; the one of the pages until
     * the site names another.
     */
    public function textModel(): string
    {
        $model = $this->textModelAsSet();

        return '' === $model ? $this->model() : $model;
    }

    /**
     * The model of the texts as the site set it: empty while it follows the
     * model of the pages, which is what the settings screen shows.
     */
    public function textModelAsSet(): string
    {
        return trim((string) TheliaCMS::getConfigValue(self::TEXT_MODEL, ''));
    }

    public function textEffort(): string
    {
        $effort = (string) TheliaCMS::getConfigValue(self::TEXT_EFFORT, self::DEFAULT_TEXT_EFFORT);

        return \in_array($effort, self::EFFORTS, true) ? $effort : self::DEFAULT_TEXT_EFFORT;
    }

    public function textMaxTokens(): int
    {
        $maxTokens = (int) TheliaCMS::getConfigValue(self::TEXT_MAX_TOKENS, (string) self::DEFAULT_TEXT_MAX_TOKENS);

        return $maxTokens >= self::MIN_TEXT_MAX_TOKENS ? $maxTokens : self::DEFAULT_TEXT_MAX_TOKENS;
    }

    /**
     * Writes the settings of the texts. An empty model is kept empty: it
     * means "the model of the pages", and follows it when that one changes.
     */
    public function saveText(string $model, string $effort, int $maxTokens): void
    {
        TheliaCMS::setConfigValue(self::TEXT_MODEL, trim($model));
        TheliaCMS::setConfigValue(self::TEXT_EFFORT, \in_array($effort, self::EFFORTS, true) ? $effort : self::DEFAULT_TEXT_EFFORT);
        TheliaCMS::setConfigValue(self::TEXT_MAX_TOKENS, (string) ($maxTokens >= self::MIN_TEXT_MAX_TOKENS ? $maxTokens : self::DEFAULT_TEXT_MAX_TOKENS));
    }

    /**
     * Writes the settings, each brought back to its default when what
     * arrives makes no sense: an empty model, an effort the API does not know,
     * a length too short for a page.
     */
    public function save(string $model, string $effort, int $maxTokens, bool $webFetch = false): void
    {
        $model = trim($model);

        TheliaCMS::setConfigValue(self::MODEL, '' === $model ? self::DEFAULT_MODEL : $model);
        TheliaCMS::setConfigValue(self::EFFORT, \in_array($effort, self::EFFORTS, true) ? $effort : self::DEFAULT_EFFORT);
        TheliaCMS::setConfigValue(self::MAX_TOKENS, (string) ($maxTokens >= self::MIN_MAX_TOKENS ? $maxTokens : self::DEFAULT_MAX_TOKENS));
        TheliaCMS::setConfigValue(self::WEB_FETCH, $webFetch ? '1' : '0');
    }
}
