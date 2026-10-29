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

namespace TheliaCMS\Evaluation;

/**
 * One answer of the generator to a brief, as `thelia_cms:generate --dry-run`
 * prints it: the document on a line of its own, then a line of cost.
 *
 * The output of the command is kept as it is rather than in a format of its
 * own, so a run is recorded with a plain redirection and read back here.
 */
final readonly class Run
{
    /**
     * @param array<string, mixed> $document the document as JSON gives it, `sections` a list
     */
    public function __construct(
        public string $name,
        public array $document,
        public ?int $turns = null,
        public ?float $costUsd = null,
        public ?float $durationSeconds = null,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        $sections = $this->document['sections'] ?? [];

        return \is_array($sections) ? array_values(array_filter($sections, \is_array(...))) : [];
    }

    /**
     * @throws \InvalidArgumentException when the output holds no document
     */
    public static function fromOutput(string $name, string $output): self
    {
        $document = null;
        $turns = null;
        $cost = null;
        $duration = null;

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);

            if (null === $document && str_starts_with($line, '{"version"')) {
                $decoded = json_decode($line, true);

                if (\is_array($decoded) && \is_array($decoded['sections'] ?? null)) {
                    $document = $decoded;
                }

                continue;
            }

            if (1 === preg_match('/(\d+) turn\(s\).*?([\d.]+) USD, ([\d.]+) s/', $line, $matches)) {
                $turns = (int) $matches[1];
                $cost = (float) $matches[2];
                $duration = (float) $matches[3];
            }
        }

        if (null === $document) {
            throw new \InvalidArgumentException(\sprintf('The run "%s" holds no document: it is not the output of thelia_cms:generate.', $name));
        }

        return new self($name, $document, $turns, $cost, $duration);
    }
}
