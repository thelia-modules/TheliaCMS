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

namespace TheliaCMS\Content\Site;

use Twig\Environment;
use Twig\Node\CheckToStringNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Gives each `cms_component('<Name>', {key: props.<name>})` a third value the
 * template does not write: the name of the prop of the caller behind each
 * key. The function then hands the called component the value the page
 * stores, not the one the caller's template reads (ADR 16).
 */
final class ComponentCallVisitor implements NodeVisitorInterface
{
    /** Set on a call once given its third value: its own values are the first two. */
    public const string MARK = 'cms_component_props';

    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof FunctionExpression || SiteTemplateRenderer::CALL !== $node->getAttribute('name') || $node->hasAttribute(self::MARK)) {
            return $node;
        }

        $arguments = $node->getNode('arguments');
        $written = iterator_to_array($arguments);

        // Written another way than a name and its values: left as it is, for
        // the renderer to refuse.
        if (\count($written) > 2 || !array_is_list($written)) {
            return $node;
        }

        $line = $node->getTemplateLine();
        $props = new ArrayExpression([], $line);

        if ($arguments->hasNode('1') && $arguments->getNode('1') instanceof ArrayExpression) {
            foreach ($arguments->getNode('1')->getKeyValuePairs() as $pair) {
                $prop = self::propOf($pair['value']);

                if ($pair['key'] instanceof ConstantExpression && null !== $prop) {
                    $props->addElement(new ConstantExpression($prop, $line), new ConstantExpression($pair['key']->getAttribute('value'), $line));
                }
            }
        }

        if (!$arguments->hasNode('1')) {
            $arguments->setNode('1', new ArrayExpression([], $line));
        }

        $arguments->setNode('2', $props);
        $node->setAttribute(self::MARK, true);

        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * The name of the prop of the caller a value is, `props.<name>`; null for
     * anything else.
     */
    public static function propOf(Node $value): ?string
    {
        // The sandbox wraps what may be printed in a check of its own.
        $value = $value instanceof CheckToStringNode ? $value->getNode('expr') : $value;

        if ($value instanceof GetAttrExpression && $value->getNode('attribute') instanceof ConstantExpression && $value->getNode('node')->hasAttribute('name') && 'props' === $value->getNode('node')->getAttribute('name')) {
            return (string) $value->getNode('attribute')->getAttribute('value');
        }

        return null;
    }
}
