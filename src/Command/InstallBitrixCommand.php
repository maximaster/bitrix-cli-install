<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\Command;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Maximaster\BitrixCliInstall\BitrixInstaller\BitrixInstallConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\BitrixInstaller;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\BitrixDistributiveReady;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationConfigurationPrepared;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationFailed;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationFinished;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationStepFinished;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationStepPayloadPrepared;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationStepPrepared;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\WizardClientPrepared;
use Maximaster\BitrixCliInstall\BitrixInstaller\Exception\InstallationStepException;
use Maximaster\BitrixCliInstall\PathResolver;
use Maximaster\BitrixCliInstall\ResourceProcessor\Event\ResourceProcessorProgressed;
use Maximaster\BitrixCliInstall\ResourceProcessor\LocalZipResourceProcessor;
use Maximaster\BitrixCliInstall\BitrixInstaller\ResourceProcessor\UnpackedBitrixValidatorProcessor;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigLocator;
use Maximaster\BitrixCliInstall\ResourceProcessor\Event\ResourceProcessorFinished;
use Maximaster\BitrixCliInstall\ResourceProcessor\Event\ResourceProcessorPrepared;
use Maximaster\BitrixCliInstall\ResourceProcessor\RemoteResourceProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InstallBitrixCommand extends Command
{
    public const ARG_DISTRIBUTIVE = 'distributive';
    public const ARG_DOCUMENT_ROOT = 'document-root';

    public const OPT_WIZARD_CONFIG = 'wizard-config';
    public const OPT_REPEAT = 'repeat';

    public const WIZARD_CONFIG_DEFAULT = 'default.yaml';

    /** @var PathResolver */
    private $pathResolver;

    /** @var BitrixInstaller */
    private $bitrixInstaller;

    /** @var EventDispatcher */
    private $eventDispatcher;

    /** @var WizardConfigLocator */
    private $wizardConfigLocator;

    public static function getDefaultName(): string
    {
        return 'bitrix:install';
    }

    public function __construct(
        PathResolver $pathResolver,
        BitrixInstaller $bitrixInstaller,
        EventDispatcher $eventDispatcher,
        WizardConfigLocator $wizardConfigLocator
    ) {
        parent::__construct();

        $this->pathResolver = $pathResolver;
        $this->bitrixInstaller = $bitrixInstaller;
        $this->eventDispatcher = $eventDispatcher;
        $this->wizardConfigLocator = $wizardConfigLocator;
    }

    protected function configure(): void
    {
        $this->setDescription('Установить Битрикс из дистрибутива');

        $def = $this->getDefinition();

        $def->setArguments([
            new InputArgument(
                self::ARG_DISTRIBUTIVE,
                InputArgument::REQUIRED,
                'Ссылка на дистрибутив из которого нужно произвести установку.'
                . 'Поддерживаются http-ссылки и локальные относительные ссылки.'
                . 'Если архив был ранее загружен и распакован, можно указать "/"'
            ),
            new InputArgument(
                self::ARG_DOCUMENT_ROOT,
                InputArgument::OPTIONAL,
                sprintf(
                    'DOCUMENT_ROOT в который должна произойти установка. Можно не указывать, если указано в "%s"',
                    self::ARG_DISTRIBUTIVE
                )
            )
        ]);

        $def->setOptions([
            new InputOption(
                self::OPT_WIZARD_CONFIG,
                null,
                InputOption::VALUE_REQUIRED,
                'Файл содержащий данные для мастера установки',
                self::WIZARD_CONFIG_DEFAULT
            ),
            new InputOption(
                self::OPT_REPEAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Сколько раз повторять запрос, если произошла ошибка',
                5
            ),
        ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws Exception|GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ss = new SymfonyStyle($input, $output);
        $eventSubscriber = $this->createEventSubscriber($ss);

        try {
            $this->eventDispatcher->addSubscriber($eventSubscriber);

            $distributiveUri = $input->getArgument(self::ARG_DISTRIBUTIVE);

            try {
                $distributiveUri = $this->pathResolver->resolve($distributiveUri)->getPathname();
            } catch (Exception $e) {
                // Значит это был URL?
            }

            $documentRoot = $input->getArgument(self::ARG_DOCUMENT_ROOT);
            if (empty($documentRoot)) {
                $documentRoot = $this->pathResolver->resolve($distributiveUri);
                if (!$documentRoot->isDir() || !$documentRoot->getRealPath()) {
                    throw new Exception(sprintf(
                        'Аргумент "%s" обязателен, если в "%s" указана не директория',
                        self::ARG_DOCUMENT_ROOT,
                        self::ARG_DISTRIBUTIVE
                    ));
                }

                $documentRoot = $documentRoot->getPathname();
            }

            $documentRoot = $this->pathResolver->resolve($documentRoot);

            if (!$documentRoot->isDir() || !$documentRoot->getRealPath()) {
                throw new Exception(sprintf(
                    'Аргумент "%s" должен быть директорией, передано "%s"',
                    self::ARG_DOCUMENT_ROOT,
                    $documentRoot->getType()
                ));
            }

            $this->bitrixInstaller->install(
                new BitrixInstallConfig(
                    $distributiveUri,
                    $documentRoot,
                    $input->getOption(self::OPT_WIZARD_CONFIG),
                    (int) $input->getOption(self::OPT_REPEAT)
                )
            );
        } catch (Exception $e) {
            $ss->error(sprintf('Произошла ошибка установки: %s', $e->getMessage()));
            return -1;
        } finally {
            $this->eventDispatcher->removeSubscriber($eventSubscriber);
        }

        $ss->note('Процесс завершён');
        return 0;
    }

    private function createEventSubscriber(SymfonyStyle $ss): EventSubscriberInterface
    {
        return new class($ss) implements EventSubscriberInterface {
            /** @var SymfonyStyle */
            private $ss;

            /** @var ProgressBar|null */
            private $progress;

            public function __construct(SymfonyStyle $ss)
            {
                $this->ss = $ss;
            }

            public static function getSubscribedEvents()
            {
                return array_fill_keys([
                    InstallationConfigurationPrepared::class,
                    BitrixDistributiveReady::class,
                    WizardClientPrepared::class,
                    InstallationStepPrepared::class,
                    InstallationStepPayloadPrepared::class,
                    InstallationStepFinished::class,
                    InstallationFailed::class,
                    InstallationFinished::class,
                    ResourceProcessorPrepared::class,
                    ResourceProcessorProgressed::class,
                    ResourceProcessorFinished::class,
                ], '__invoke');
            }

            public function __invoke(object $event)
            {
                if ($event instanceof BitrixDistributiveReady) {
                    $this->ss->note('Дистрибутив готов');
                } elseif ($event instanceof ResourceProcessorPrepared) {
                    switch (get_class($event->processor)) {
                        case RemoteResourceProcessor::class:
                            $this->ss->note('Загружаем дистрибутив из сети');
                            break;
                        case LocalZipResourceProcessor::class:
                            $this->ss->note('Распаковываем дистрибутив на локальный диск');
                            break;
                        case UnpackedBitrixValidatorProcessor::class:
                            $this->ss->note('Проверяем корректность распакованных данных');
                            break;
                    }
                } elseif ($event instanceof ResourceProcessorProgressed) {
                    if (!$this->progress) {
                        $this->progress = $this->ss->createProgressBar($event->total);
                        $this->progress->setMessage('');
                    }

                    $this->progress->setProgress($event->current);
                } elseif ($event instanceof ResourceProcessorFinished) {
                    if ($this->progress) {
                        $this->ss->writeln(PHP_EOL);
                        $this->progress = null;
                    }
                } elseif ($event instanceof WizardClientPrepared) {
                    $this->ss->note('Подготовлен клиент для прохождения мастера установки');
                    $this->progress = $this->ss->createProgressBar(count($event->wizardConfig->steps()));
                    $this->progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
                } elseif ($event instanceof InstallationStepPrepared) {
                    $stepInfo = [$event->step->id()];
                    $payload = $event->step->payload();
                    foreach (['__wiz_nextStep', '__wiz_nextStepStage'] as $stepInfoPartKey) {
                        if (!empty($payload[$stepInfoPartKey])) {
                            $stepInfo[] = $payload[$stepInfoPartKey];
                        }
                    }
                    $this->progress && $this->progress->setMessage(implode(' ', $stepInfo));
                } elseif ($event instanceof InstallationStepFinished) {
                    $this->progress && $this->progress->advance();
                } elseif ($event instanceof InstallationFailed) {
                    $this->ss->writeln(PHP_EOL);
                    $this->ss->error($event->exception->getMessage());
                    if ($event->exception instanceof InstallationStepException) {
                        $this->ss->note('payload = ' . http_build_query($event->exception->payload()));
                    }

                    if ($this->ss->isDebug()) {
                        $this->ss->writeln($event->exception->getTraceAsString());
                    }

                } elseif ($event instanceof InstallationFinished) {
                    $this->ss->writeln(PHP_EOL);
                    $this->ss->success('Установка завершена');
                }
            }
        };
    }
}
