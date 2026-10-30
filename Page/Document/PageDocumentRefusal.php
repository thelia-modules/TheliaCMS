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

namespace TheliaCMS\Page\Document;

/**
 * Why a file sent to a page was not attached to it.
 */
enum PageDocumentRefusal: string
{
    case Format = 'format';
    case TooLarge = 'too-large';
    case Failed = 'failed';

    /** What the editor is told, a key of the translations of the module. */
    public function message(): string
    {
        return match ($this) {
            self::Format => 'Only PDF, Word, Excel, PowerPoint, OpenDocument text, ZIP and plain text files can be attached.',
            self::TooLarge => 'The file is larger than the server accepts.',
            self::Failed => 'The file did not arrive whole. Send it again.',
        };
    }
}
