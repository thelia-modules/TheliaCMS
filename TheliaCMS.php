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

namespace TheliaCMS;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use SEOne\Service\SeoDefaultModels\SeoElementInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ProfileQuery;
use Thelia\Model\ProfileResource;
use Thelia\Model\Resource;
use Thelia\Model\ResourceQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Module\BaseModule;
use TheliaCMS\Install\LegalPagesSeeder;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Page\CmsUrlService;
use TheliaCMS\Security\CmsAdminResourcesCompiler;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Seo\CmsPageHreflangListener;
use TheliaCMS\Seo\CmsPageSeoElement;

class TheliaCMS extends BaseModule
{
    public const string DOMAIN_NAME = 'theliacms';

    /** Value written in `rewriting_url.view` for every CMS page URL. */
    public const string PAGE_VIEW = 'cmspage';

    public function preActivation(?ConnectionInterface $con = null): bool
    {
        // Every CMS page is served through a rewritten URL. With URL rewriting
        // off, activating the module would publish pages that can never be
        // reached, so refuse loudly instead of failing later on the front.
        if ('1' !== ConfigQuery::read('rewriting_enable', '0')) {
            throw new \RuntimeException(
                'Thelia CMS requires URL rewriting. Enable "rewriting_enable" in the store configuration, then activate the module again.'
            );
        }

        $this->assertFullTextSearchIsAvailable();

        return true;
    }

    public function postActivation(?ConnectionInterface $con = null): void
    {
        if (!self::getConfigValue('is_initialized')) {
            (new Database($con))->insertSql(null, [__DIR__.'/Config/TheliaMain.sql']);
            (new LegalPagesSeeder())->seed();
            self::setConfigValue('is_initialized', 1);
        }

        $this->createSearchIndex();
        $this->seedAdminResources();
        $this->regenerateRewrittenUrls();
    }

    /**
     * Rebuilds the page URLs dropped by preDeactivation, so deactivating and
     * reactivating the module leaves the site exactly as it was rather than
     * silently 404ing every page.
     */
    private function regenerateRewrittenUrls(): void
    {
        $urls = new CmsUrlService();

        $pages = CmsPageQuery::create()
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->find();

        foreach ($pages as $page) {
            $locales = CmsPageContentQuery::create()
                ->filterByPageId($page->getId())
                ->select(['Locale'])
                ->find()
                ->toArray();

            foreach (array_unique($locales) as $locale) {
                $urls->refresh($page, (string) $locale);
            }
        }
    }

    /**
     * Declaring a resource to the container is not enough: permissions are read
     * from `profile_resource`, so a resource with no row there is denied to
     * every profile but the super administrator, and the back office offers no
     * way to grant it.
     */
    private function seedAdminResources(): void
    {
        foreach (CmsResources::all() as $code) {
            if (null !== ResourceQuery::create()->findOneByCode($code)) {
                continue;
            }

            $resource = new Resource();
            $resource->setCode($code);

            foreach (LangQuery::create()->find() as $lang) {
                $resource->setLocale($lang->getLocale())->setTitle($this->resourceTitle($code, $lang->getLocale()));
            }

            $resource->save();

            // Existing profiles start with no access at all: an administrator
            // opens them deliberately from the profile screen.
            foreach (ProfileQuery::create()->find() as $profile) {
                (new ProfileResource())
                    ->setProfileId($profile->getId())
                    ->setResourceId($resource->getId())
                    ->setAccess(0)
                    ->save();
            }
        }
    }

    private function resourceTitle(string $code, string $locale): string
    {
        $labels = [
            CmsResources::PAGE => ['en_US' => 'CMS: pages', 'fr_FR' => 'CMS : pages'],
            CmsResources::MENU => ['en_US' => 'CMS: menus', 'fr_FR' => 'CMS : menus'],
            CmsResources::FORM => ['en_US' => 'CMS: forms', 'fr_FR' => 'CMS : formulaires'],
            CmsResources::MEDIA => ['en_US' => 'CMS: media', 'fr_FR' => 'CMS : médias'],
            CmsResources::SETTINGS => ['en_US' => 'CMS: settings', 'fr_FR' => 'CMS : réglages'],
            CmsResources::CUSTOM_CODE => ['en_US' => 'CMS: custom code', 'fr_FR' => 'CMS : code libre'],
        ];

        return $labels[$code][$locale] ?? $labels[$code]['en_US'] ?? $code;
    }

    /**
     * Rewritten URLs outlive a deactivated module: left behind, they keep
     * routing visitors to a `cmspage` view nothing can render any more, which
     * is a 500 and not a 404. They are dropped here and regenerated from the
     * pages on the next activation.
     *
     * `markRewrittenUrlObsolete()` is deliberately not used: it points the URL
     * at an `obsolete-rewritten-url` view no shipped theme provides a template
     * for.
     */
    public function preDeactivation(?ConnectionInterface $con = null): bool
    {
        RewritingUrlQuery::create()
            ->filterByView(self::PAGE_VIEW)
            ->delete($con);

        return true;
    }

    public static function getCompilers(): array
    {
        return [new CmsAdminResourcesCompiler()];
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n/*',
                __DIR__.'/Config/*',
                __DIR__.'/Model/*',
                __DIR__.'/templates/*',
                // Value objects: instantiated by the services that build them,
                // never wired by the container.
                __DIR__.'/Page/PublishedPage.php',
                // Registered below, guarded: it implements a SEOne interface.
                __DIR__.'/Seo/*',
            ])
            ->autowire(true)
            ->autoconfigure(true);

        // SEOne is a soft dependency. Autodiscovering CmsPageSeoElement would
        // reflect a class implementing an interface that does not exist on a
        // site running without SEOne, which is a fatal error, not a skip.
        if (interface_exists(SeoElementInterface::class)) {
            $servicesConfigurator->set(CmsPageSeoElement::class)
                ->autowire(true)
                ->autoconfigure(true);

            $servicesConfigurator->set(CmsPageHreflangListener::class)
                ->autowire(true)
                ->autoconfigure(true);
        }
    }

    /**
     * The front search (§2.11) runs on a native FULLTEXT index with no LIKE
     * fallback, so the storage engine has to support one before any page is
     * published.
     */
    private function assertFullTextSearchIsAvailable(): void
    {
        $connection = Propel::getConnection('TheliaMain');

        if ('mysql' !== $connection->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            throw new \RuntimeException('Thelia CMS requires a MySQL or MariaDB database: its front-office search relies on a native FULLTEXT index.');
        }

        $version = (string) $connection->getAttribute(\PDO::ATTR_SERVER_VERSION);

        if (str_contains(strtolower($version), 'mariadb')) {
            $supported = version_compare($this->normalizeVersion($version), '10.0.5', '>=');
        } else {
            $supported = version_compare($this->normalizeVersion($version), '5.6.0', '>=');
        }

        if (!$supported) {
            throw new \RuntimeException(\sprintf('Thelia CMS requires InnoDB FULLTEXT support (MySQL 5.6+ or MariaDB 10.0.5+), server reports "%s".', $version));
        }
    }

    private function normalizeVersion(string $version): string
    {
        preg_match('/\d+(\.\d+)*/', $version, $matches);

        return $matches[0] ?? '0';
    }

    /**
     * Kept out of TheliaMain.sql on purpose: that file is only replayed on the
     * very first activation and Propel has no portable FULLTEXT declaration.
     */
    private function createSearchIndex(): void
    {
        $connection = Propel::getConnection('TheliaMain');

        $exists = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index'
        );
        $exists->execute(['table' => 'cms_page_search', 'index' => 'ft_cms_page_search_content']);

        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        $connection->exec('ALTER TABLE `cms_page_search` ADD FULLTEXT INDEX `ft_cms_page_search_content` (`content`)');
    }
}
