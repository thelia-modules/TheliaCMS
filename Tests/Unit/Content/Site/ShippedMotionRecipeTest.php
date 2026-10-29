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

namespace TheliaCMS\Tests\Unit\Content\Site;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\Pipeline\PublishedContentSanitizer;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentValidator;
use TheliaCMS\Content\Site\SiteCssScoper;
use TheliaCMS\Generation\Component\ComponentPrompt;

/**
 * What the model is told about movement holds against what the site does:
 * the recipe of the skill on motion is a component the site keeps, and the
 * elements the prompt says a page takes out are taken out.
 */
final class ShippedMotionRecipeTest extends TestCase
{
    private const string SKILL = __DIR__.'/../../../../skills/motion/SKILL.md';

    public function testThePictureThatScrollsOfTheSkillIsAComponentTheSiteKeeps(): void
    {
        $skill = (string) file_get_contents(self::SKILL);

        foreach (['tabindex="0"', 'aria-hidden="true"', 'translateX(-50%)', 'animation-play-state: paused', ':focus-within', 'prefers-reduced-motion: no-preference'] as $piece) {
            self::assertStringContainsString($piece, $skill, 'The recipe below is the one the skill gives.');
        }

        $component = new SiteComponent(
            'PictureScroll',
            'Image défilante',
            'One wide or tall picture that pans slowly in a frame, horizontally or vertically.',
            [
                ['name' => 'image', 'type' => 'image', 'label' => 'Picture'],
                ['name' => 'alt', 'type' => 'text', 'label' => 'Alternative text', 'max' => 200],
                ['name' => 'direction', 'type' => 'choice', 'label' => 'Direction', 'choices' => ['horizontal' => 'Horizontal', 'vertical' => 'Vertical'], 'default' => 'horizontal'],
            ],
            ['image' => null, 'alt' => 'Les étals de la boutique.', 'direction' => 'horizontal'],
            '<div class="scroll scroll--{{ props.direction }}">{% if props.image %}<div class="scroll__frame" tabindex="0"><div class="scroll__track"><img class="scroll__picture" src="{{ props.image }}" alt="{{ props.alt }}"><img class="scroll__picture" src="{{ props.image }}" alt="" aria-hidden="true"></div></div>{% endif %}</div>',
            ".scroll__frame { overflow: hidden; height: 20rem; }\n.scroll__track { display: flex; width: 200%; height: 100%; }\n.scroll--vertical .scroll__track { flex-direction: column; width: 100%; height: 200%; }\n.scroll__picture { flex: 1; min-width: 0; object-fit: cover; }\n@keyframes pan { from { transform: translateX(0); } to { transform: translateX(-50%); } }\n@keyframes rise { from { transform: translateY(0); } to { transform: translateY(-50%); } }\n@media (prefers-reduced-motion: no-preference) { .scroll__track { animation: pan 60s linear infinite; } .scroll--vertical .scroll__track { animation-name: rise; } .scroll__frame:hover .scroll__track, .scroll__frame:focus-within .scroll__track { animation-play-state: paused; } }",
        );

        $kept = (new SiteComponentValidator())->validate($component);
        $css = (new SiteCssScoper())->scope($kept->css, $kept->scopeClass());

        self::assertStringContainsString('animation: cms-site--picture-scroll--pan 60s linear infinite;', $css);
        self::assertStringContainsString('animation-name: cms-site--picture-scroll--rise;', $css);
    }

    public function testTheElementsThePromptSaysAPageTakesOutAreTakenOut(): void
    {
        $sanitizer = new PublishedContentSanitizer();

        foreach (ComponentPrompt::ELEMENTS_TAKEN_OUT as $element) {
            self::assertStringNotContainsString('<'.$element, (string) $sanitizer->html(\sprintf('<div><%s class="x">a</%1$s></div>', $element)), $element);
        }
    }
}
