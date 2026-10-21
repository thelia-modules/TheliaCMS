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

    public const string DEFAULT_MODEL = 'claude-opus-5-5';
    public const string DEFAULT_EFFORT = 'high';
    public const int DEFAULT_MAX_TOKENS = 16000;

    /** Below this, no page fits in the answer. */
    public const int MIN_MAX_TOKENS = 1024;

    public const array EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];

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
     * On unless the site turned it off: the organisation of the key may not
     * allow the tool, or the site may not want pages read for it.
     */
    public function webFetch(): bool
    {
        return '0' !== (string) TheliaCMS::getConfigValue(self::WEB_FETCH, '1');
    }

    /**
     * Writes the settings, each brought back to its default when what
     * arrives makes no sense: an empty model, an effort the API does not know,
     * a length too short for a page.
     */
    public function save(string $model, string $effort, int $maxTokens, bool $webFetch = true): void
    {
        $model = trim($model);

        TheliaCMS::setConfigValue(self::MODEL, '' === $model ? self::DEFAULT_MODEL : $model);
        TheliaCMS::setConfigValue(self::EFFORT, \in_array($effort, self::EFFORTS, true) ? $effort : self::DEFAULT_EFFORT);
        TheliaCMS::setConfigValue(self::MAX_TOKENS, (string) ($maxTokens >= self::MIN_MAX_TOKENS ? $maxTokens : self::DEFAULT_MAX_TOKENS));
        TheliaCMS::setConfigValue(self::WEB_FETCH, $webFetch ? '1' : '0');
    }
}
