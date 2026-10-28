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

namespace TheliaCMS\Generation\Text\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\LangQuery;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\GenerationException;
use TheliaCMS\Generation\GenerationRefusedException;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Generation\GenerationUnavailableException;
use TheliaCMS\Generation\InvalidGenerationException;
use TheliaCMS\Generation\Text\TextField;
use TheliaCMS\Generation\Text\TextGenerator;
use TheliaCMS\Generation\Text\TextRequest;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;

/**
 * Writes the text of one rich-text field from a brief, for the script beside
 * the field: the text comes back as JSON and nothing is saved but the row of
 * the ledger. The field holds it once the editor chose to put it there, and
 * the form saves it as if they had typed it.
 */
#[Route('/admin/cms/text', name: 'admin.cms.text.')]
final readonly class TextBriefController
{
    /** The objects whose fields the button sits beside. */
    public const array TYPES = ['page', 'product', 'category', 'brand', 'folder', 'content'];

    private const int BRIEF_LENGTH = 20000;

    private const int CURRENT_LENGTH = 100000;

    public function __construct(
        private TextGenerator $generator,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::GENERATE], [], [AccessManager::UPDATE])) {
            throw new AccessDeniedHttpException($this->translate('You are not allowed to use the generator.'));
        }

        $type = (string) $request->request->get('type', '');
        $field = TextField::tryFrom((string) $request->request->get('field', ''));
        $locale = (string) $request->request->get('locale', '');
        $brief = trim((string) $request->request->get('brief', ''));
        $current = (string) $request->request->get('current', '');

        if (!\in_array($type, self::TYPES, true) || !$field instanceof TextField || !LangQuery::create()->filterByActive(1)->filterByLocale($locale)->exists()) {
            return $this->error($this->translate('This field cannot be written from a brief.'), Response::HTTP_BAD_REQUEST);
        }

        if ('' === $brief) {
            return $this->error($this->translate('Say in the brief what the text should say.'), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (mb_strlen($brief) > self::BRIEF_LENGTH || \strlen($current) > self::CURRENT_LENGTH) {
            return $this->error($this->translate('The brief or the text of the field is too long.'), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $target = GenerationTarget::field($type, max(0, $request->request->getInt('id')), (string) $request->request->get('title', ''), $locale);
        $generation = GenerationRequest::text($target, $locale, new Brief(
            $brief,
            self::optional($request->request->get('tone')),
            self::optional($request->request->get('audience')),
        ));

        try {
            $result = $this->generator->generate(new TextRequest($generation, $field, $current));
        } catch (GenerationUnavailableException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (GenerationRefusedException|InvalidGenerationException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (GenerationException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'html' => $result->html,
            'cost' => number_format($result->costUsd, 3, '.', ''),
        ]);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    private static function optional(mixed $value): ?string
    {
        $value = \is_string($value) ? trim($value) : '';

        return '' === $value ? null : mb_substr($value, 0, 200);
    }

    private function translate(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
