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

namespace TheliaCMS\Generation;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Page\Admin\CmsPageWriter;

/**
 * Writes the draft of a page from a brief, from the command line: what a
 * script, a demo or a test of the real API uses. Publishing stays a separate
 * step, through `thelia_cms:publish`.
 */
#[AsCommand(
    name: 'thelia_cms:generate',
    description: 'Writes the draft of a CMS page from a brief, with the generator.',
)]
final class GenerateCommand extends Command
{
    public function __construct(
        private readonly PageGenerator $generator,
        private readonly CmsPageWriter $writer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'The page to write, by id.')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'The language of the page, such as fr_FR.')
            ->addOption('brief', null, InputOption::VALUE_REQUIRED, 'The brief, or a path to a file holding it.')
            ->addOption('revise', null, InputOption::VALUE_NONE, 'Reads the brief as a change asked on the document the page holds.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Prints the document and saves nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pageId = (int) $input->getOption('page');
        $locale = (string) $input->getOption('locale');
        $briefOption = (string) $input->getOption('brief');

        if ($pageId <= 0 || '' === $locale || '' === $briefOption) {
            $io->error('Name the page with --page, the language with --locale and the brief with --brief.');

            return Command::INVALID;
        }

        $page = CmsPageQuery::create()->findPk($pageId);

        if (null === $page || null !== $page->getDeletedAt()) {
            $io->error(\sprintf('No page #%d.', $pageId));

            return Command::FAILURE;
        }

        $briefText = is_file($briefOption) ? (string) file_get_contents($briefOption) : $briefOption;
        $brief = new Brief($briefText);
        $target = GenerationTarget::page($page, $locale);

        if ($input->getOption('revise')) {
            $content = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($locale)->findOne();
            $current = Document::fromJson($content?->getDraftDocument());

            if (null === $current) {
                $io->error('The page holds no document to revise in this language.');

                return Command::FAILURE;
            }

            $request = GenerationRequest::revision($target, $locale, $current, $brief);
        } else {
            $request = GenerationRequest::fromBrief($target, $locale, $brief);
        }

        try {
            $result = $this->generator->generate($request);
        } catch (GenerationException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->writeln($result->document->toJson());
        $io->newLine();
        $io->comment(\sprintf(
            '%s, %d turn(s), %d in / %d out tokens (%d cached), %.4f USD, %.1f s',
            $result->model,
            $result->turns,
            $result->usage->inputTokens,
            $result->usage->outputTokens,
            $result->usage->cacheReadTokens,
            $result->costUsd,
            $result->durationMs / 1000,
        ));

        if ($input->getOption('dry-run')) {
            return Command::SUCCESS;
        }

        $this->writer->saveContent($page, $locale, $result->document);
        $io->success(\sprintf('Draft of page #%d saved in %s. Publish it with thelia_cms:publish --page %d.', $pageId, $locale, $pageId));

        return Command::SUCCESS;
    }
}
