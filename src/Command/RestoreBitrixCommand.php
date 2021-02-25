<?php

namespace Maximaster\BitrixCliInstall\Command;

use Exception;
use Maximaster\BitrixCliInstall\BitrixRestorer\BitrixRestoreConfig;
use Maximaster\BitrixCliInstall\BitrixRestorer\BitrixRestorer;
use Maximaster\BitrixCliInstall\BitrixRestorer\Enum\BitrixRestoreSkippableStageEnum;
use Maximaster\BitrixCliInstall\BitrixRestorer\Enum\BitrixRestoreStageEnum;
use Maximaster\BitrixCliInstall\BitrixRestorer\Event\RestoreStepFinished;
use Maximaster\BitrixCliInstall\BitrixRestorer\Event\RestoreStepPrepared;
use Maximaster\BitrixCliInstall\PathResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RestoreBitrixCommand extends Command
{
    public const DEFAULT_RESTORE_SCRIPT = 'https://www.1c-bitrix.ru/download/files/scripts/restore.php';

    public const ARG_DOCUMENT_ROOT = 'document-root';
    public const ARG_BACKUP = 'backup';

    public const OPT_SCRIPT = 'script';
    public const OPT_WIZARD_CONFIG = 'wizard-config';
    public const OPT_SKIPS = 'skip';

    /** @var PathResolver */
    private $pathResolver;

    /** @var BitrixRestorer */
    private $bitrixRestorer;

    /** @var EventDispatcherInterface */
    private $dispatcher;

    public static function getDefaultName()
    {
        return 'bitrix:restore';
    }

    public function __construct(
        PathResolver $pathResolver,
        BitrixRestorer $bitrixRestorer,
        EventDispatcherInterface $dispatcher
    ) {
        parent::__construct();

        $this->pathResolver = $pathResolver;
        $this->bitrixRestorer = $bitrixRestorer;
        $this->dispatcher = $dispatcher;
    }

    protected function configure()
    {
        $this->setDescription('Восстановить Битрикс из резервной копии');

        $def = $this->getDefinition();

        $def->setArguments([
            new InputArgument(self::ARG_BACKUP, InputArgument::REQUIRED, 'Путь к файлу бекапа. Можно сетевой'),
            new InputArgument(
                self::ARG_DOCUMENT_ROOT,
                InputArgument::REQUIRED,
                sprintf(
                    'DOCUMENT_ROOT в который должна произойти восстановление. Можно не указывать, если аргумент "%s" содержит локальный путь',
                    self::ARG_BACKUP
                )
            )
        ]);

        $def->setOptions([
            new InputOption(
                self::OPT_SCRIPT,
                's',
                InputOption::VALUE_REQUIRED,
                'Файл установщика',
                self::DEFAULT_RESTORE_SCRIPT
            ),
            new InputOption(
                self::OPT_WIZARD_CONFIG,
                'w',
                InputOption::VALUE_REQUIRED,
                'Файл настройки установки, для получения данных о подключении'
            ),
            new InputOption(
                self::OPT_SKIPS,
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                'Пропустить определённый шаг: ' . implode(', ', BitrixRestoreSkippableStageEnum::toArray())
            )
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ss = new SymfonyStyle($input, $output);
        $eventSubscriber = $this->createEventSubscriber($ss);

        try {
            $this->dispatcher->addSubscriber($eventSubscriber);

            $backupFilepath = $input->getArgument(self::ARG_BACKUP);

            $documentRoot = $input->getArgument(self::ARG_DOCUMENT_ROOT);
            if (empty($documentRoot)) {
                $documentRoot = dirname($backupFilepath);
            }

            $documentRoot = $this->pathResolver->resolve($documentRoot);
            if (!$documentRoot->isDir()) {
                $ss->error(sprintf('Ожидался путь к директории. Получено "%s"', $documentRoot->getPathname()));
            }

            $this->bitrixRestorer->restore(
                new BitrixRestoreConfig(
                    $documentRoot,
                    $input->getOption(self::OPT_SCRIPT),
                    $input->getArgument(self::ARG_BACKUP),
                    $input->getOption(self::OPT_SKIPS),
                    $input->getOption(self::OPT_WIZARD_CONFIG) ?: null
                )
            );
        } catch (Exception $e) {
            $ss->error($e->getMessage());

            if ($ss->isDebug()) {
                $ss->writeln($e->getTraceAsString());
            }

            return -1;
        } finally {
            $this->dispatcher->removeSubscriber($eventSubscriber);
        }

        return 0;
    }

    private function createEventSubscriber(SymfonyStyle $ss): EventSubscriberInterface
    {
        return new class($ss) implements EventSubscriberInterface {
            /** @var SymfonyStyle */
            private $ss;

            /** @var BitrixRestoreStageEnum|null */
            private $activeStage;

            /** @var ProgressBar|null */
            private $activeProgress;

            public function __construct(SymfonyStyle $ss)
            {
                $this->ss = $ss;
            }

            public static function getSubscribedEvents()
            {
                return array_fill_keys(
                    [
                        RestoreStepPrepared::class,
                        RestoreStepFinished::class,
                    ],
                    '__invoke'
                );
            }

            public function __invoke(object $event)
            {
                if ($event instanceof RestoreStepPrepared) {
                    // Смена стадии
                    if ($this->activeStage === null || !$this->activeStage->equals($event->stage)) {
                        if ($this->activeProgress) {
                            $this->ss->writeln(PHP_EOL);

                            if (in_array(
                                $event->stage->getValue(),
                                [BitrixRestoreStageEnum::DOWNLOAD_BACKUP, BitrixRestoreStageEnum::RESTORE_DATABASE]
                            )) {
                                if ($this->activeProgress) {
                                    $this->activeProgress->setProgress(100);
                                    $this->activeProgress = null;
                                }
                            }
                        }

                        $this->ss->section(sprintf('Стадия "%s"', $event->stage->getValue()));

                        $this->activeStage = $event->stage;
                    }


                } elseif ($event instanceof RestoreStepFinished) {
                    if ($event->response->progress) {
                        $this
                            ->progressBar(100)
                            ->setProgress($event->response->progress);
                    }
                }
            }

            private function progressBar(int $max): ProgressBar
            {
                return ($this->activeProgress = $this->activeProgress ?: $this->ss->createProgressBar($max));
            }
        };
    }
}
