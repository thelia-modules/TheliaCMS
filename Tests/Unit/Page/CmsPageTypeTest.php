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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;
use TheliaCMS\Page\Admin\CmsPageType;

/**
 * The page form is built on every screen that edits a page: building it must not
 * raise a deprecation, and an address typed without its scheme is read as https.
 */
final class CmsPageTypeTest extends TestCase
{
    public function testTheFormIsBuiltWithoutADeprecation(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED | \E_DEPRECATED);

        try {
            $this->createForm();
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    public function testACanonicalAddressWithoutItsSchemeIsReadAsHttps(): void
    {
        $form = $this->createForm();
        $form->submit(['canonical' => 'www.example.com/page'], false);

        self::assertSame('https://www.example.com/page', $form->get('canonical')->getData());
    }

    public function testThePageTypeIsOneOfTheTypesOffered(): void
    {
        $form = $this->createForm(['page_type_choices' => ['default', 'recipe']]);
        $form->submit(['pageType' => 'recipe'], false);

        self::assertTrue($form->get('pageType')->isValid());
        self::assertSame('recipe', $form->get('pageType')->getData());
    }

    public function testATypeMadeOfDigitsOnlyIsKeptAsItsCode(): void
    {
        $form = $this->createForm(['page_type_choices' => ['default', '2024']]);
        $form->submit(['pageType' => '2024'], false);

        self::assertTrue($form->get('pageType')->isValid());
        self::assertSame('2024', $form->get('pageType')->getData());
    }

    public function testATypeTheSiteDoesNotHaveIsRefused(): void
    {
        $form = $this->createForm(['page_type_choices' => ['default', 'recipe']]);
        $form->submit(['pageType' => 'news'], false);

        self::assertFalse($form->get('pageType')->isValid());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createForm(array $options = []): FormInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory()
            ->create(CmsPageType::class, null, $options);
    }
}
