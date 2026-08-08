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

namespace TheliaCMS\Storage;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\TableMap;

/**
 * Spells out, on the way to the database, the characters the connection cannot
 * carry, and reads them back on the way out — see SupplementaryCharacters for why
 * the connection cannot carry them.
 *
 * Placed on the Propel hooks rather than in the writers, because the paths that
 * store content are not one: the builder saves a draft every thirty seconds,
 * publishing promotes it, a revision snapshots it, duplicating a page copies it,
 * the importer writes it without going through the editor at all, and a visitor
 * filling in a form writes their own. A guard in each of them is a guard the next
 * caller forgets. Here it is the last thing that happens before the statement and
 * the first after the row is read, so there is nothing left to forget.
 *
 * The pair is what makes a round trip stable: an object in memory always holds
 * the character somebody typed, whatever the table holds. So a title reaches a
 * template as `📷` and is escaped into `📷`, where the stored reference would have
 * been escaped into `&amp;#128247;` and read as a bug on every screen.
 *
 * The cost is one scan per string column per read and per write, and the scan
 * stops on the first bytes of the string on any content without such a character
 * in it, which is all of it on almost every site.
 */
trait EncodesSupplementaryCharacters
{
    public function preSave(?ConnectionInterface $con = null): bool
    {
        $stylesheets = $this->stylesheetColumns();

        foreach ($this->columnNames() as $name) {
            $value = $this->getByName($name, TableMap::TYPE_PHPNAME);

            if (!\is_string($value) || !SupplementaryCharacters::areIn($value)) {
                continue;
            }

            $this->setByName(
                $name,
                \in_array($name, $stylesheets, true)
                    ? SupplementaryCharacters::toCssEscapes($value)
                    : SupplementaryCharacters::toNumericReferences($value),
                TableMap::TYPE_PHPNAME,
            );
        }

        return parent::preSave($con);
    }

    public function hydrate(array $row, int $startcol = 0, bool $rehydrate = false, string $indexType = TableMap::TYPE_NUM): int
    {
        $read = parent::hydrate($row, $startcol, $rehydrate, $indexType);
        $stylesheets = $this->stylesheetColumns();
        $changed = false;

        foreach ($this->columnNames() as $name) {
            // A stylesheet is left as it was stored: its own escape is what a
            // browser reads there, and reading it back would only serve to show
            // the character in a panel meant for CSS.
            if (\in_array($name, $stylesheets, true)) {
                continue;
            }

            $value = $this->getByName($name, TableMap::TYPE_PHPNAME);

            if (!\is_string($value)) {
                continue;
            }

            $decoded = SupplementaryCharacters::fromNumericReferences($value);

            if ($decoded !== $value) {
                $this->setByName($name, $decoded, TableMap::TYPE_PHPNAME);
                $changed = true;
            }
        }

        if ($changed) {
            // Reading a row is not changing it: without this, every object
            // carrying such a character would come out of the database claiming
            // to hold unsaved work.
            $this->resetModified();
        }

        return $read;
    }

    /**
     * Columns holding a stylesheet rather than markup or plain text.
     *
     * Named by the models that have one, because the two are not spelled the same
     * way and a stylesheet given the markup spelling shows the reference instead
     * of the character.
     *
     * @return list<string>
     */
    protected function stylesheetColumns(): array
    {
        return [];
    }

    /**
     * Every column of the table, by the name the getters and setters use.
     *
     * Not filtered by `ColumnMap::isText()`, which answers no for a `CLOB` and so
     * leaves out every `LONGTEXT` column: the HTML of a page, its stylesheet and
     * the project of the editor are all of them. What a value is gets decided by
     * looking at the value instead, which also skips a date, a number and the
     * stream a binary column hands back.
     *
     * @return list<string>
     */
    private function columnNames(): array
    {
        $tableMap = static::TABLE_MAP;

        return array_map(
            static fn (ColumnMap $column): string => $column->getPhpName(),
            array_values($tableMap::getTableMap()->getColumns()),
        );
    }
}
