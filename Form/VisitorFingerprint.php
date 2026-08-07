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

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Turns the address a request came from into something that can be stored.
 *
 * An IP address is personal data, and a form that keeps one for a year keeps a
 * year of personal data it never needed. What the site actually needs is to
 * recognise the same sender twice, which a keyed hash does.
 *
 * Keyed, not plain: a bare SHA-256 of an IPv4 address is reversed by hashing
 * the four billion of them, which is minutes of work.
 */
final readonly class VisitorFingerprint
{
    /**
     * The core `form_firewall.ip_address` column is VARCHAR(15) — it was sized
     * for a dotted IPv4 and holds no IPv6 address at all. Sixty bits of hash
     * fit, identify a sender as reliably as the address did, and stop the table
     * from being a list of who visited.
     */
    private const int BUCKET_LENGTH = 15;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $applicationSecret,
    ) {
    }

    /**
     * Stored with a submission.
     */
    public function of(?string $ipAddress): string
    {
        return hash_hmac(
            'sha256',
            (string) $ipAddress,
            hash_hmac('sha256', 'thelia-cms.visitor', $this->applicationSecret),
        );
    }

    /**
     * Stored in the rate-limiting table.
     */
    public function bucket(?string $ipAddress): string
    {
        return substr($this->of($ipAddress), 0, self::BUCKET_LENGTH);
    }
}
