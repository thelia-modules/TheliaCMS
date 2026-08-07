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
 * The conversion a showcase site actually measures.
 *
 * A sent form is what an advertising campaign is optimised against, so it is
 * reported to the data layer under the name every tag manager already expects.
 *
 * What is reported is the code of the form and nothing else. No name, no email,
 * no phone number: the data layer is read by whatever tag the site loads, and
 * a contact form has no business handing those to a third party. (The Thelia
 * GoogleTagManager module pushes email addresses in the clear today — that is
 * upstream, and it is a reason to be careful here rather than an example.)
 */
final readonly class LeadEvent
{
    public const string NAME = 'generate_lead';
}
