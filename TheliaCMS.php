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

use OpenStudio\PageBuilderBundle\Contract\ImageLibraryPortInterface;
use OpenStudio\PageBuilderBundle\Contract\ImageUploadPortInterface;
use OpenStudio\PageBuilderBundle\Contract\PageBuilderConfigProviderInterface;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use SEOne\Service\SeoDefaultModels\SeoElementInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
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
use TheliaCMS\Builder\CmsBuilderConfig;
use TheliaCMS\Install\LegalPagesSeeder;
use TheliaCMS\Install\MenuSeeder;
use TheliaCMS\Media\LibraryImageCatalog;
use TheliaCMS\Media\LibraryImageUploader;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Page\CmsUrlService;
use TheliaCMS\Partial\PartialFragmentRenderer;
use TheliaCMS\Partial\PartialFragmentRendererInterface;
use TheliaCMS\Search\Tnt\CmsPageIndex;
use TheliaCMS\Security\CmsAdminResourcesCompiler;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Seo\CmsPageHreflangListener;
use TheliaCMS\Seo\CmsPageSeoElement;
use TheliaCMS\Seo\StandInPageCanonicalListener;
// The class CmsPageIndex extends. Importing it costs nothing when TntSearch is
// absent, and without the import the guard below would name TheliaCMS\BaseIndex.
use TntSearch\Index\BaseIndex;

class TheliaCMS extends BaseModule
{
    public const string DOMAIN_NAME = 'theliacms';

    /** Value written in `rewriting_url.view` for every CMS page URL. */
    public const string PAGE_VIEW = 'cmspage';

    /**
     * Request attribute holding the id of a page served in place of what the
     * address designates — the page shown on an address that does not exist.
     *
     * Whatever describes a response reads the request, not the render, so this
     * is how those readers are told the two do not agree.
     */
    public const string STAND_IN_PAGE_ATTRIBUTE = '_cms_stand_in_page_id';

    public function preActivation(?ConnectionInterface $con = null): bool
    {
        // Every CMS page is served through a rewritten URL. With URL rewriting
        // off, activating the module would publish pages that can never be
        // reached, so refuse loudly instead of failing later on the front.
        if ('1' !== ConfigQuery::read('rewriting_enable', '0')) {
            throw new \RuntimeException('Thelia CMS requires URL rewriting. Enable "rewriting_enable" in the store configuration, then activate the module again.');
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
        (new MenuSeeder())->seed();
        $this->regenerateRewrittenUrls();
    }

    /**
     * Applies the schema changes of every version between the one installed and
     * the one shipped.
     *
     * The core replays `Config/update/*.sql` on a fresh install and through
     * `module:schema:apply`, but a site that merely updates the module goes
     * through here — without it, new tables would only ever appear on new sites.
     * The files are written to tolerate being applied twice.
     *
     * Only SQL here, no model: the Propel runtime map of this process was built
     * before the statements below ran, so the tables they create have no table
     * map yet and any query against them fails. That is why the seed rows of a
     * new table are part of its update file rather than of a PHP seeder.
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        foreach (self::migrationsBetween((string) $currentVersion, (string) $newVersion) as $file) {
            (new Database($con))->insertSql(null, [$file]);
        }
    }

    /**
     * The migration files to apply to move a site from one version to another,
     * oldest first.
     *
     * Ordered by version rather than by name: a plain sort puts 0.10.0 before
     * 0.9.0, and a table would then be altered before it is created.
     *
     * @return list<string> absolute paths
     */
    public static function migrationsBetween(string $currentVersion, string $newVersion): array
    {
        $files = glob(__DIR__.'/Config/update/*.sql') ?: [];
        usort($files, static fn (string $a, string $b): int => version_compare(basename($a, '.sql'), basename($b, '.sql')));

        return array_values(array_filter($files, static function (string $file) use ($currentVersion, $newVersion): bool {
            $version = basename($file, '.sql');

            return version_compare($version, $currentVersion, '>') && version_compare($version, $newVersion, '<=');
        }));
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

    /**
     * The page builder bundle registers its GrapesJS sources with the asset
     * mapper. On Thelia the asset mapper belongs to the front-office theme, and
     * the bundle's bare imports (`grapesjs`, `grapesjs/dist/css/grapes.min.css`)
     * are absent from that importmap: in `strict` mode every front asset then
     * fails, the theme stylesheet included.
     *
     * Excluding those sources is not a loss — a published page must ship no
     * builder JavaScript at all, and the editor is bundled by this module's own
     * npm build and served with `module_asset()` on the admin route only.
     */
    public static function configureContainer(ContainerConfigurator $containerConfigurator): void
    {
        $containerConfigurator->extension('framework', [
            'asset_mapper' => [
                'excluded_patterns' => [
                    '*/openstudio/page-builder-bundle/assets/*',
                ],
            ],
        ]);
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n/*',
                __DIR__.'/Config/*',
                __DIR__.'/Model/*',
                __DIR__.'/templates/*',
                // Test doubles implement the module's own interfaces, so
                // autoconfiguration would tag them and the editor would offer
                // a "Fake" block in production.
                __DIR__.'/Tests/*',
                // Value objects: instantiated by the services that build them,
                // never wired by the container.
                __DIR__.'/Page/PublishedPage.php',
                __DIR__.'/Partial/PartialProp.php',
                // Registered below, guarded: it implements a SEOne interface.
                __DIR__.'/Seo/*',
                // Same, for TntSearch: the class extends one of its own, so
                // reflecting it on a site without the module is a fatal error.
                __DIR__.'/Search/Tnt/*',
            ])
            ->autowire(true)
            ->autoconfigure(true);

        // The bundle ships a static provider aliased to the interface; the
        // editor has to follow the site instead — the theme stylesheet, the
        // project palette, the theme breakpoints.
        $servicesConfigurator->alias(PageBuilderConfigProviderInterface::class, CmsBuilderConfig::class);

        // Same for the image ports: the bundle aliases them to null objects
        // that accept nothing, waiting for the host to say where images live.
        $servicesConfigurator->alias(ImageUploadPortInterface::class, LibraryImageUploader::class);
        $servicesConfigurator->alias(ImageLibraryPortInterface::class, LibraryImageCatalog::class);

        // Dynamic blocks are rendered through an interface so the substitution
        // of a page can be unit-tested without a theme behind it.
        $servicesConfigurator->alias(PartialFragmentRendererInterface::class, PartialFragmentRenderer::class);

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

            $servicesConfigurator->set(StandInPageCanonicalListener::class)
                ->autowire(true)
                ->autoconfigure(true);
        }

        // TntSearch is the other soft dependency. Its compiler pass collects
        // the `tntsearch.index` tag, so declaring the service is all it takes;
        // without the module, the built-in FULLTEXT search answers alone.
        if (class_exists(BaseIndex::class)) {
            $servicesConfigurator->set(CmsPageIndex::class)
                ->autowire(true)
                ->tag('tntsearch.index');
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
