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
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Prop;
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

    /** A name `cms_component` takes: the code of a component of the site, or the catalogue name of one of the theme. */
    public const string NAME = '/^(?:[A-Z][A-Za-z0-9]{1,39}|[A-Za-z][A-Za-z0-9_-]*(?::[A-Za-z0-9_-]+)+)$/';

    /** 1 while a component called by another is drawn: it calls nothing. */
    private int $depth = 0;

    private int $calls = 0;

    /** @var array<string, mixed> the props the caller being drawn stores */
    private array $stored = [];

    private string $locale = 'en_US';

    /** @var array<string, string> the markup of the components of the theme drawn, by the placeholder printed in their place */
    private array $placed = [];

    /**
     * @param DrawableSections|null $sections what `cms_component` draws; without it (a unit test) the function draws nothing
     */
    public function __construct(
        private ?SiteIcon $icon = null,
        private ?DrawableSections $sections = null,
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
     * @param array<string, mixed> $props  as the template reads them (SiteComponentSection::forTemplate)
     * @param array<string, mixed> $stored as the page stores them: what `cms_component` hands on
     *
     * @throws Error when the template is refused or fails while rendering
     */
    public function render(string $template, array $props, array $stored = [], string $locale = 'en_US'): string
    {
        if ([] !== $problems = $this->problems($template)) {
            throw new SecurityError(implode(' ', $problems));
        }

        if (0 === $this->depth) {
            $this->calls = 0;
        }

        [$callerStored, $callerLocale] = [$this->stored, $this->locale];
        [$this->stored, $this->locale] = [$stored, $locale];

        try {
            return $this->twig()->createTemplate($template)->render(['props' => $props]);
        } finally {
            [$this->stored, $this->locale] = [$callerStored, $callerLocale];
        }
    }

    /**
     * Puts the markup of the components of the theme a rendering drew in
     * place of their placeholders, once the caller's markup is filtered: the
     * theme draws them as it draws its sections, with the attributes its
     * controllers read, which the filter of a component would take out.
     */
    public function placeTheme(string $html): string
    {
        foreach ($this->placed as $placeholder => $markup) {
            if (str_contains($html, $placeholder)) {
                $html = str_replace($placeholder, $markup, $html);
                unset($this->placed[$placeholder]);
            }
        }

        return $html;
    }

    /**
     * What `cms_component` prints: the section of that name the catalogue
     * offers, given the values the caller stores for the props it passes and
     * the values written in the call (ADR 16), normalised by its own schema
     * and drawn by its own section. A component of the theme prints a
     * placeholder, replaced by placeTheme(). Nothing for a component called
     * by another, past MAX_CALLS, for a name the catalogue does not offer,
     * or for values its schema refuses or a drawing that fails, which is
     * logged.
     *
     * @param mixed $written the values of the call, as the template reads them
     * @param mixed $props   the prop of the caller behind each key, which ComponentCallVisitor adds
     */
    private function draw(mixed $name, mixed $written, mixed $props): string
    {
        if ($this->depth > 0 || null === $this->sections || !\is_string($name)) {
            return '';
        }

        if (++$this->calls > self::MAX_CALLS) {
            if (self::MAX_CALLS + 1 === $this->calls) {
                $this->logger?->warning('TheliaCMS: a component of the site draws more than {max} components; the next ones are left out.', ['max' => self::MAX_CALLS]);
            }

            return '';
        }

        $catalogueName = SiteComponentCall::catalogueName($name);
        $section = $this->sections->find($catalogueName);

        if (null === $section) {
            $this->logger?->warning('TheliaCMS: a component of the site draws {code}, which the catalogue does not offer.', ['code' => $name]);

            return '';
        }

        $values = \is_array($written) ? $written : [];

        foreach (\is_array($props) ? $props : [] as $key => $prop) {
            $values[$key] = $this->stored[$prop] ?? null;
        }

        $declared = array_map(static fn (Prop $prop): string => $prop->name, $section->schema()->props);
        $values = array_filter(array_intersect_key($values, array_flip($declared)), static fn (mixed $value): bool => null !== $value);

        ++$this->depth;

        try {
            $html = $section->render($section->normalizeProps($values), $this->locale);
        } catch (InvalidDocumentException|Error $exception) {
            $this->logger?->error('TheliaCMS: the component {name} cannot be drawn inside another: {message}', ['name' => $catalogueName, 'message' => $exception->getMessage()]);

            return '';
        } finally {
            --$this->depth;
        }

        if ($section instanceof SiteComponentSection || '' === trim($html)) {
            return $html;
        }

        $placeholder = 'cms-theme-call-'.bin2hex(random_bytes(8));
        $this->placed[$placeholder] = $html;

        return $placeholder;
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

            $written = $node->hasAttribute(ComponentCallVisitor::MARK) ? 2 : \count($arguments);

            if (!array_is_list($arguments) || $written > 2) {
                $problems[] = \sprintf("Line %d: %s takes the code of a component, then the values it draws, and nothing else: %s('Carousel', {slides: props.photos}).", $line, self::CALL, self::CALL);
            } elseif (!$code instanceof ConstantExpression || !\is_string($code->getAttribute('value')) || 1 !== preg_match(self::NAME, $code->getAttribute('value'))) {
                $problems[] = \sprintf("Line %d: the first value of %s is the code of a component of the site or the name of a component of the theme, written as it is between quotes ('Carousel'), not a prop nor a variable.", $line, self::CALL);
            } elseif ([] !== self::callNodes($node->getNode('arguments'))) {
                $problems[] = \sprintf('Line %d: %s draws one component; the values it passes are props, never another %s.', $line, self::CALL, self::CALL);
            } elseif (isset($arguments[1]) && (!$arguments[1] instanceof ArrayExpression || [] !== array_filter($arguments[1]->getKeyValuePairs(), static fn (array $pair): bool => !$pair['key'] instanceof ConstantExpression || !\is_string($pair['key']->getAttribute('value'))))) {
                $problems[] = \sprintf('Line %d: the values %s draws are written as a mapping of the names of the props of that component, {slides: props.photos}.', $line, self::CALL);
            } elseif (isset($arguments[1]) && $arguments[1] instanceof ArrayExpression && [] !== $refused = array_filter($arguments[1]->getKeyValuePairs(), static fn (array $pair): bool => null === ComponentCallVisitor::propOf($pair['value']) && !self::isWritten($pair['value']))) {
                $problems[] = \sprintf('Line %d: %s passes "%s" something else than a prop of the component (props.photos) or a value written as it is (a text, a number, true or false): it hands on what the page stores, never a field of a row nor a computed value.', $line, self::CALL, implode('", "', array_map(static fn (array $pair): string => (string) $pair['key']->getAttribute('value'), $refused)));
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

            if (null !== $prop = ComponentCallVisitor::propOf($pair['value'])) {
                $props[$key] = $prop;
            }
        }

        return new SiteComponentCall((string) $arguments[0]->getAttribute('value'), $keys, $node->getTemplateLine(), $props);
    }

    /**
     * A value written as it is: a text, a number, true, false or null.
     */
    private static function isWritten(Node $value): bool
    {
        $value = $value instanceof CheckToStringNode ? $value->getNode('expr') : $value;

        return $value instanceof ConstantExpression && (null === $value->getAttribute('value') || \is_scalar($value->getAttribute('value')));
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
        $twig->addFunction(new TwigFunction(self::CALL, fn (mixed $code, mixed $written = [], mixed $props = []): string => $this->draw($code, $written, $props), ['is_safe' => ['html']]));
        $twig->addNodeVisitor(new ComponentCallVisitor());

        return $this->twig = $twig;
    }
}
