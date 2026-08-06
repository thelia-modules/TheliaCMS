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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use SEOne\Service\SeoDefaultModels\SeoElementInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Install\Database;
use Thelia\Model\ConfigQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Module\BaseModule;
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
            self::setConfigValue('is_initialized', 1);
        }

        $this->createSearchIndex();
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
