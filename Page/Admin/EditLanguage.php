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

namespace TheliaCMS\Page\Admin;

use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Language whose translation is being edited — independent from the language
 * the back office itself is displayed in.
 *
 * `edit_language_id` is the parameter the back-office language switcher already
 * writes, and the choice is stored in the session the rest of the back office
 * reads, so switching language on a CMS screen carries over to the product and
 * folder screens, and the other way round.
 */
final readonly class EditLanguage
{
    public const string PARAMETER = 'edit_language_id';

    public function resolve(Request $request): Lang
    {
        $active = $this->activeLanguages();
        $session = $request->hasSession() ? $request->getSession() : null;
        $requested = $request->query->getInt(self::PARAMETER);

        foreach ($active as $lang) {
            if ($lang->getId() === $requested) {
                if ($session instanceof TheliaSession) {
                    $session->setAdminEditionLang($lang);
                }

                return $lang;
            }
        }

        $current = $session instanceof TheliaSession ? $session->getAdminEditionLang() : Lang::getDefaultLanguage();

        foreach ($active as $lang) {
            if ($lang->getId() === $current->getId()) {
                return $lang;
            }
        }

        // The stored language has since been switched off.
        return $active[0] ?? Lang::getDefaultLanguage();
    }

    /**
     * Only languages the shop has switched on: offering a translation tab for a
     * disabled language invites work that is never published.
     *
     * @return list<Lang>
     */
    private function activeLanguages(): array
    {
        return array_values(iterator_to_array(
            LangQuery::create()->filterByActive(1)->orderByPosition()->find(),
            false
        ));
    }
}
