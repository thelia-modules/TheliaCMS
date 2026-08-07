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

namespace TheliaCMS\Form;

/**
 * Turns the answers of a submission into what is stored, and reads them back.
 *
 * Only what the form declared goes in — a name, a type, the question as it was
 * asked, and the answer. Nothing of the request itself: no headers, no
 * referrer, no browser, none of which was ever asked for.
 *
 * A consent carries the moment it was given as well. An agreement whose wording
 * and date cannot be shown is not an agreement anyone can rely on.
 */
final readonly class SubmissionData
{
    /**
     * @param list<Answer> $answers
     */
    public static function encode(array $answers, \DateTimeImmutable $at): string
    {
        $stored = [];

        foreach ($answers as $answer) {
            $entry = [
                'name' => $answer->name,
                'type' => $answer->type->value,
                'label' => $answer->label,
                'value' => $answer->value,
            ];

            if (FieldType::Consent === $answer->type) {
                $entry['granted_at'] = $at->format(\DATE_ATOM);
            }

            $stored[] = $entry;
        }

        return json_encode($stored, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<array{name: string, type: string, label: string, value: string|bool, granted_at?: string}>
     */
    public static function decode(?string $json): array
    {
        $decoded = json_decode((string) $json, true);

        if (!\is_array($decoded)) {
            return [];
        }

        $answers = [];

        foreach ($decoded as $entry) {
            if (!\is_array($entry) || !isset($entry['name'])) {
                continue;
            }

            $answers[] = [
                'name' => (string) $entry['name'],
                'type' => (string) ($entry['type'] ?? FieldType::Text->value),
                'label' => (string) ($entry['label'] ?? $entry['name']),
                'value' => \is_bool($entry['value'] ?? null) ? $entry['value'] : (string) ($entry['value'] ?? ''),
                ...(isset($entry['granted_at']) ? ['granted_at' => (string) $entry['granted_at']] : []),
            ];
        }

        return $answers;
    }
}
