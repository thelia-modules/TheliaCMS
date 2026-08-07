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

namespace TheliaCMS\Form\Admin;

/**
 * What the settings form of a form carries, once read off the request.
 */
final readonly class FormSettings
{
    public function __construct(
        public string $code,
        public bool $active,
        public string $recipients,
        public bool $storeSubmissions,
        public int $retentionDays,
        public bool $sendReceipt,
        public ?int $privacyPolicyPageId,
        public bool $leadEvent,
        public string $title,
        public string $description,
        public string $submitLabel,
        public string $successMessage,
        public string $legalNotice,
        public string $receiptSubject,
        public string $receiptBody,
    ) {
    }
}
