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

use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\SyntaxError;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Markup;
use Twig\Node\CheckToStringNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\ForNode;
use Twig\Node\Node;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityPolicy;
use Twig\Source;
use Twig\TwigFunction;

/**
 * Renders the template of a component of the site, written by a model and
 * kept in the database, in a Twig of its own: never the Twig of the site,
 * whose functions reach the session, the database and the file system.
 *
 * The sandbox is on for every template of this environment. A template may
 * test, loop and set a variable, print with the escaping filters and a few
 * that shape text, call a few functions of values (`min`, `max`), and
 * nothing else: no property of an object, no include. The only objects it
 * receives are the markup props, whose methods give back strings and counts. It receives the props and nothing else. Markup props
 * come as Twig\Markup, printed as they are; everything else is escaped.
 */
final class SiteTemplateRenderer
{
    /** @var list<string> */
    public const array TAGS = ['if', 'for', 'set'];

    /** @var list<string> */
    public const array FILTERS = ['escape', 'e', 'default', 'length', 'join', 'upper', 'lower', 'capitalize', 'title', 'trim', 'first', 'last', 'slice', 'nl2br', 'number_format', 'replace', 'split', 'keys'];

    /**
     * Functions of values only, `cms_icon` that draws a named icon, and
     * `cms_component` that draws a component kept for the site (ADR 15). Not
     * `range`, nor `1..3` which compiles to it: a range has no bound, and the
     * loops of a component go over its props.
     *
     * @var list<string>
     */
    public const array FUNCTIONS = ['cycle', 'min', 'max', 'cms_icon', self::CALL];

    /** The function that draws another component of the site. */
    public const string CALL = 'cms_component';

    /**
     * How many components one rendering draws with `cms_component`, a call
     * inside a loop counting each time: a list holds 24 items.
     */
    public const int MAX_CALLS = 24;

    /** @var list<string> */
    public const array TESTS = ['empty', 'defined', 'null', 'none', 'odd', 'even', 'iterable', 'same as', 'divisible by'];

    public const int MAX_LENGTH = 20000;

    /**
     * A list prop holds 24 items and an item holds no list: two loops inside
     * each other cover any props, a third one only multiplies a list by itself.
     */
    public const int MAX_LOOP_DEPTH = 2;

    private ?Environment $twig = null;

    /** 1 while a component called by another is drawn: it calls nothing. */
    private int $depth = 0;

    private int $calls = 0;

    /**
     * @param KeptSiteComponents|null $components what `cms_component` draws from; without it (a unit test) the function draws nothing
     */
    public function __construct(
        private ?SiteIcon $icon = null,
        private ?KeptSiteComponents $components = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * The problems of a template, none when it compiles under the policy.
     *
     * @return list<string>
     */
    public function problems(string $template): array
    {
        if ('' === trim($template)) {
            return ['The template is empty.'];
        }

        if (mb_strlen($template) > self::MAX_LENGTH) {
            return [\sprintf('The template is %d characters at most.', self::MAX_LENGTH)];
        }

        // The `constant` test reads a constant of PHP whatever the policy says:
        // Twig compiles it apart from the tests the sandbox checks. `extends`
        // and `use` load another template and are not checked by the policy
        // either: refused by name.
        if (1 === preg_match('/\bis\s+(?:not\s+)?constant\b/', $template)) {
            return ['The template uses what a component may not (the "constant" test).'];
        }

        if (1 === preg_match('/\{%[-~]?\s*(extends|use)\b/', $template, $match)) {
            return [\sprintf('The template uses what a component may not (the "%s" tag): a component is one template.', $match[1])];
        }

        try {
            // The sandbox checks a template when it is displayed, not when it
            // is compiled: the check is run here, without rendering anything.
            $this->twig()->createTemplate($template)->unwrap($this->twig())->checkSecurity();

            // Read in the tree, not by pattern: a comment, a `verbatim` or a
            // string holding the word `for` is not a loop.
            $tree = $this->twig()->parse($this->twig()->tokenize(new Source($template, 'component')));
            $loopLine = self::lineOfTooDeepLoop($tree);
            $callProblems = self::callProblems($tree);
        } catch (SecurityError $exception) {
            return [\sprintf('The template uses what a component may not (%s). Allowed tags: %s; filters: %s; tests: %s; functions: %s.', $exception->getRawMessage(), implode(', ', self::TAGS), implode(', ', self::FILTERS), implode(', ', self::TESTS), implode(', ', self::FUNCTIONS))];
        } catch (SyntaxError $exception) {
            return [\sprintf('The template does not compile, line %d: %s', $exception->getTemplateLine(), $exception->getRawMessage())];
        } catch (Error $exception) {
            return ['The template does not compile: '.$exception->getRawMessage()];
        }

        if (null !== $loopLine) {
            return [\sprintf('The template puts loops more than %d deep, line %d: a component loops over a list, and over the list of an item at most.', self::MAX_LOOP_DEPTH, $loopLine)];
        }

        return $callProblems;
    }

    /**
     * The components the template draws with `cms_component`, in the order
     * written; none for a template that does not compile or that writes a
     * call the sandbox refuses (problems() says why).
     *
     * @return list<SiteComponentCall>
     */
    public function calls(string $template): array
    {
        if ('' === trim($template) || !str_contains($template, self::CALL)) {
            return [];
        }

        try {
            $tree = $this->twig()->parse($this->twig()->tokenize(new Source($template, 'component')));
        } catch (Error) {
            return [];
        }

        if ([] !== self::callProblems($tree)) {
            return [];
        }

        $calls = [];

        foreach (self::callNodes($tree) as $node) {
            if (null !== $call = self::callOf($node)) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * @param array<string, mixed> $props as the template reads them (SiteComponentSection::forTemplate)
     *
     * @throws Error when the template is refused or fails while rendering
     */
    public function render(string $template, array $props): string
    {
        if ([] !== $problems = $this->problems($template)) {
            throw new SecurityError(implode(' ', $problems));
        }

        if (0 === $this->depth) {
            $this->calls = 0;
        }

        return $this->twig()->createTemplate($template)->render(['props' => $props]);
    }

    /**
     * What `cms_component` prints: the component kept for the site under
     * that code, drawn with the values given (the template form the caller
     * reads its own props in), the keys it does not declare left out, inside
     * the element its stylesheet is scoped to. Nothing for a component
     * called by another, past MAX_CALLS, for a code the site does not keep,
     * or when the component fails, which is logged.
     */
    private function draw(mixed $code, mixed $values): string
    {
        if ($this->depth > 0 || null === $this->components || !\is_string($code)) {
            return '';
        }

        if (++$this->calls > self::MAX_CALLS) {
            if (self::MAX_CALLS + 1 === $this->calls) {
                $this->logger?->warning('TheliaCMS: a component of the site draws more than {max} components; the next ones are left out.', ['max' => self::MAX_CALLS]);
            }

            return '';
        }

        $component = $this->components->kept($code);

        if (null === $component) {
            $this->logger?->warning('TheliaCMS: a component of the site draws {code}, which the site does not keep.', ['code' => $code]);

            return '';
        }

        $names = array_filter(array_map(static fn (array $prop): mixed => $prop['name'] ?? null, $component->props), \is_string(...));
        $props = array_intersect_key(\is_array($values) ? $values : [], array_flip($names));

        ++$this->depth;

        try {
            $html = $this->render($component->template, $props);
        } catch (Error $exception) {
            $this->logger?->error('TheliaCMS: the component {name} of the site cannot be drawn inside another: {message}', ['name' => $component->name(), 'message' => $exception->getMessage()]);

            return '';
        } finally {
            --$this->depth;
        }

        return '' === trim($html) ? '' : \sprintf('<div class="cms-site %s">%s</div>', $component->scopeClass(), $html);
    }

    /**
     * @return list<string> one sentence per call the template writes in a way it may not
     */
    private static function callProblems(Node $tree): array
    {
        $problems = [];

        foreach (self::callNodes($tree) as $node) {
            $line = $node->getTemplateLine();
            $arguments = iterator_to_array($node->getNode('arguments'));
            $code = $arguments[0] ?? null;

            if (!array_is_list($arguments) || \count($arguments) > 2) {
                $problems[] = \sprintf("Line %d: %s takes the code of a component, then the values it draws, and nothing else: %s('Carousel', {slides: props.photos}).", $line, self::CALL, self::CALL);
            } elseif (!$code instanceof ConstantExpression || !\is_string($code->getAttribute('value')) || 1 !== preg_match('/^[A-Z][A-Za-z0-9]{1,39}$/', $code->getAttribute('value'))) {
                $problems[] = \sprintf("Line %d: the first value of %s is the code of a component of the site, written as it is between quotes ('Carousel'), not a prop nor a variable.", $line, self::CALL);
            } elseif ([] !== self::callNodes($node->getNode('arguments'))) {
                $problems[] = \sprintf('Line %d: %s draws one component; the values it passes are props, never another %s.', $line, self::CALL, self::CALL);
            } elseif (isset($arguments[1]) && (!$arguments[1] instanceof ArrayExpression || [] !== array_filter($arguments[1]->getKeyValuePairs(), static fn (array $pair): bool => !$pair['key'] instanceof ConstantExpression || !\is_string($pair['key']->getAttribute('value'))))) {
                $problems[] = \sprintf('Line %d: the values %s draws are written as a mapping of the names of the props of that component, {slides: props.photos}.', $line, self::CALL);
            }
        }

        return $problems;
    }

    /**
     * @return list<FunctionExpression>
     */
    private static function callNodes(Node $node): array
    {
        $found = $node instanceof FunctionExpression && self::CALL === $node->getAttribute('name') ? [$node] : [];

        foreach ($node as $child) {
            $found = [...$found, ...self::callNodes($child)];
        }

        return $found;
    }

    private static function callOf(FunctionExpression $node): ?SiteComponentCall
    {
        if ([] !== self::callProblems($node)) {
            return null;
        }

        $arguments = iterator_to_array($node->getNode('arguments'));
        $keys = [];
        $props = [];

        foreach (isset($arguments[1]) && $arguments[1] instanceof ArrayExpression ? $arguments[1]->getKeyValuePairs() : [] as $pair) {
            $key = (string) $pair['key']->getAttribute('value');
            $keys[] = $key;
            // The sandbox wraps what may be printed in a check of its own.
            $value = $pair['value'] instanceof CheckToStringNode ? $pair['value']->getNode('expr') : $pair['value'];

            if ($value instanceof GetAttrExpression && $value->getNode('attribute') instanceof ConstantExpression && $value->getNode('node')->hasAttribute('name') && 'props' === $value->getNode('node')->getAttribute('name')) {
                $props[$key] = (string) $value->getNode('attribute')->getAttribute('value');
            }
        }

        return new SiteComponentCall((string) $arguments[0]->getAttribute('value'), $keys, $node->getTemplateLine(), $props);
    }

    private static function lineOfTooDeepLoop(Node $node, int $depth = 0): ?int
    {
        if ($node instanceof ForNode && ++$depth > self::MAX_LOOP_DEPTH) {
            return $node->getTemplateLine();
        }

        foreach ($node as $child) {
            if (null !== $line = self::lineOfTooDeepLoop($child, $depth)) {
                return $line;
            }
        }

        return null;
    }

    private function twig(): Environment
    {
        if (null !== $this->twig) {
            return $this->twig;
        }

        $twig = new Environment(new ArrayLoader(), [
            'autoescape' => 'html',
            'cache' => false,
            'strict_variables' => false,
        ]);

        // Printing a markup prop casts a Twig\Markup to a string: the one
        // method a template may call, and only through printing it.
        $twig->addExtension(new SandboxExtension(new SecurityPolicy(self::TAGS, self::FILTERS, [Markup::class => ['__tostring']], [], self::FUNCTIONS, self::TESTS), true));

        // Without the icon service (a unit test), the function exists and draws nothing.
        $icon = $this->icon;
        $twig->addFunction(new TwigFunction('cms_icon', static fn (mixed $reference, array $attributes = []): string => null === $icon ? '' : $icon->render($reference, $attributes), ['is_safe' => ['html']]));

        // Drawn in this sandbox, and filtered with the markup of the caller.
        $twig->addFunction(new TwigFunction(self::CALL, fn (mixed $code, mixed $values = []): string => $this->draw($code, $values), ['is_safe' => ['html']]));

        return $this->twig = $twig;
    }
}
