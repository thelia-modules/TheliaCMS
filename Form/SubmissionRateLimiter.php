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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\ConfigQuery;
use Thelia\Model\FormFirewall;
use Thelia\Model\FormFirewallQuery;

/**
 * Caps how often the same sender may post the same form.
 *
 * The core has this logic, but it lives in `FirewallForm`, which a form of this
 * module is not: it is tied to `BaseForm`, it buckets by PHP class name, and it
 * does nothing at all outside the production environment — a rate limit that is
 * off while you are testing it is a rate limit nobody has ever seen work. The
 * table is the core one all the same, under a name of our own, so a site keeps
 * one place to look and the existing `form_firewall_*` settings still apply.
 */
final readonly class SubmissionRateLimiter
{
    /** Prefix of the bucket name, so CMS forms are recognisable in the table. */
    public const string NAME_PREFIX = 'cms_form_';

    private const int DEFAULT_ATTEMPTS = 6;

    private const int DEFAULT_MINUTES = 60;

    public function __construct(
        private VisitorFingerprint $fingerprints,
    ) {
    }

    public static function nameFor(string $formCode): string
    {
        return self::NAME_PREFIX.$formCode;
    }

    /**
     * Records one attempt and says whether it may go through.
     */
    public function allows(string $formCode, ?string $ipAddress): bool
    {
        if (!$this->isActive()) {
            return true;
        }

        $name = self::nameFor($formCode);
        $bucket = $this->fingerprints->bucket($ipAddress);

        $this->forgetOldAttempts($name);

        $attempt = FormFirewallQuery::create()
            ->filterByFormName($name)
            ->filterByIpAddress($bucket)
            ->findOne();

        if (null === $attempt) {
            (new FormFirewall())
                ->setFormName($name)
                ->setIpAddress($bucket)
                ->save();

            return true;
        }

        if ((int) $attempt->getAttempts() < $this->attempts()) {
            $attempt->incrementAttempts();

            return true;
        }

        // Saved even though nothing changed: it moves `updated_at` forward, so
        // a sender hammering the form keeps their own window open rather than
        // waiting it out while still sending.
        $attempt->save();

        return false;
    }

    /**
     * Minutes a sender who went over the limit has to wait.
     */
    public function waitingMinutes(): int
    {
        return $this->minutes();
    }

    private function forgetOldAttempts(string $name): void
    {
        FormFirewallQuery::create()
            ->filterByFormName($name)
            ->filterByUpdatedAt(new \DateTimeImmutable(\sprintf('-%d minutes', $this->minutes())), Criteria::LESS_THAN)
            ->delete();
    }

    private function isActive(): bool
    {
        return (bool) ConfigQuery::read('form_firewall_active', '1');
    }

    private function attempts(): int
    {
        return max(1, (int) ConfigQuery::read('form_firewall_attempts', (string) self::DEFAULT_ATTEMPTS));
    }

    private function minutes(): int
    {
        return max(1, (int) ConfigQuery::read('form_firewall_time_to_wait', (string) self::DEFAULT_MINUTES));
    }
}
