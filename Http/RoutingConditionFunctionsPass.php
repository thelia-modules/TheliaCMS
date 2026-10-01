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

namespace TheliaCMS\Http;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the routing context of Thelia the functions route conditions call.
 *
 * Thelia matches a request through a router chain built on its own
 * `request.context`, and the chain hands that context to every router in it,
 * the Symfony one included. Symfony stores the functions of route conditions,
 * `service()` among them, as the `_functions` parameter of the context it builds
 * for itself, so on the chain a condition calling `service()` fails with a
 * fatal error on the first request it is evaluated for.
 *
 * Does nothing once the context carries the parameter already, so a core that
 * sets it makes this pass a no-op rather than a second writer.
 */
final class RoutingConditionFunctionsPass implements CompilerPassInterface
{
    private const string THELIA_CONTEXT = 'request.context';

    private const string FUNCTIONS = 'router.expression_language_provider';

    private const string PARAMETER = '_functions';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::THELIA_CONTEXT) || !$container->has(self::FUNCTIONS)) {
            return;
        }

        $context = $container->getDefinition(self::THELIA_CONTEXT);

        foreach ($context->getMethodCalls() as [$method, $arguments]) {
            if ('setParameter' === $method && self::PARAMETER === ($arguments[0] ?? null)) {
                return;
            }

            if ('setParameters' === $method && \is_array($arguments[0] ?? null) && \array_key_exists(self::PARAMETER, $arguments[0])) {
                return;
            }
        }

        $context->addMethodCall('setParameter', [
            self::PARAMETER,
            new Reference(self::FUNCTIONS, ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
        ]);
    }
}
