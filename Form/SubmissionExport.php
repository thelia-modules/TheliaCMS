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
 * Writes submissions out, so answering someone who asks for a copy of what the
 * site holds about them is a download rather than an afternoon.
 *
 * Two formats on purpose: the spreadsheet for whoever has to read the answers,
 * and JSON for whoever has to hand them over — a CSV flattens a submission into
 * the columns of a form as it is today, and loses the questions that were
 * removed since.
 */
final readonly class SubmissionExport
{
    /**
     * @param list<array{id: int, locale: string, email: ?string, created_at: string, answers: list<array<string, mixed>>}> $submissions
     */
    public static function toJson(array $submissions): string
    {
        return json_encode($submissions, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }

    /**
     * The columns are the questions actually answered, in the order they were
     * first met — a question dropped from the form months ago still has its
     * column, because the answers to it are still there.
     *
     * @param list<array{id: int, locale: string, email: ?string, created_at: string, answers: list<array<string, mixed>>}> $submissions
     * @param array<string, string>                                                                                         $headings    the first four column titles, translated
     */
    public static function toCsv(array $submissions, array $headings, string $yes, string $no): string
    {
        $questions = [];

        foreach ($submissions as $submission) {
            foreach ($submission['answers'] as $answer) {
                $questions[(string) $answer['name']] ??= (string) ($answer['label'] ?? $answer['name']);
            }
        }

        $rows = [array_merge(array_values($headings), array_values($questions))];

        foreach ($submissions as $submission) {
            $byName = [];

            foreach ($submission['answers'] as $answer) {
                $value = $answer['value'] ?? '';
                $byName[(string) $answer['name']] = \is_bool($value) ? ($value ? $yes : $no) : (string) $value;
            }

            $row = [
                (string) $submission['id'],
                $submission['created_at'],
                (string) ($submission['email'] ?? ''),
                $submission['locale'],
            ];

            foreach (array_keys($questions) as $name) {
                $row[] = $byName[$name] ?? '';
            }

            $rows[] = $row;
        }

        $handle = fopen('php://temp', 'r+');

        if (false === $handle) {
            return '';
        }

        // A spreadsheet opening a UTF-8 file without this shows Déjà as DÃ©jÃ .
        fwrite($handle, "\u{FEFF}");

        foreach ($rows as $row) {
            fputcsv($handle, self::defused($row), ',', '"', '\\');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * A cell starting with `=`, `+`, `-` or `@` is a formula to a spreadsheet,
     * and a formula in a file somebody was sent is how a contact form turns
     * into remote code execution on the machine of whoever opens it.
     *
     * @param list<string> $row
     *
     * @return list<string>
     */
    private static function defused(array $row): array
    {
        return array_map(
            static fn (string $cell): string => 1 === preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell,
            $row,
        );
    }
}
