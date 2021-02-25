<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\Command;

use AppendIterator;
use ArrayIterator;
use Exception;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Enum\CreateDatabaseFieldEnum;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfig;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigFactory;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Enum\WizardConfigPresetEnum;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Enum\WizardConfigStepIdEnum;
use Maximaster\BitrixCliInstall\PathResolver;
use mysqli;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UninstallBitrixCommand extends Command
{
    public const ARG_DOCUMENT_ROOT = 'document-root';
    public const OPT_WIZARD_CONFIG = 'wizard-config';

    /** @var PathResolver */
    private $pathResolver;

    /** @var WizardConfigFactory */
    private $configFactory;

    /** @var array */
    private $bitrixFiles;

    public static function getDefaultName()
    {
        return 'bitrix:uninstall';
    }

    public function __construct(
        PathResolver $pathResolver,
        WizardConfigFactory $configFactory,
        array $bitrixFiles
    ) {
        parent::__construct();

        $this->pathResolver = $pathResolver;
        $this->configFactory = $configFactory;
        $this->bitrixFiles = $bitrixFiles;
    }

    protected function configure()
    {
        $this->setDescription('Удалить Битрикс');

        $def = $this->getDefinition();

        $def->setArguments([
            new InputArgument(
                self::ARG_DOCUMENT_ROOT,
                InputArgument::REQUIRED,
                'DOCUMENT_ROOT в котором установлен Битрикс'
            ),
        ]);

        $def->setOptions([
            new InputOption(
                self::OPT_WIZARD_CONFIG,
                null,
                InputOption::VALUE_REQUIRED,
                'Файл содержащий данные для мастера установки',
                WizardConfigPresetEnum::DEFAULT
            ),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ss = new SymfonyStyle($input, $output);

        try {
            $documentRoot = $this->pathResolver->resolve($input->getArgument(self::ARG_DOCUMENT_ROOT))->getPathname();
            $wizardConfig = $this->configFactory->createFromPath(
                $input->getOption(self::OPT_WIZARD_CONFIG),
                [getcwd(), $documentRoot]
            );

            $this->uninstallFiles($ss, $documentRoot);
            $this->uninstallDatabase($ss, $wizardConfig);
        } catch (Exception $e) {
            $ss->error($e->getMessage());
            return -1;
        }

        return 0;
    }

    /**
     * @param SymfonyStyle $ss
     * @param string $documentRoot
     *
     * @throws Exception
     */
    private function uninstallFiles(SymfonyStyle $ss, string $documentRoot): void
    {
        /** @var SplFileInfo[]|AppendIterator $bitrixFilesIterator */
        $bitrixFilesIterator = new AppendIterator();
        foreach ($this->bitrixFiles as $distributiveFile) {
            $distributiveFileInfo = new SplFileInfo($documentRoot . DIRECTORY_SEPARATOR . $distributiveFile);

            // !file_exists()
            if (!$distributiveFileInfo->getRealPath()) {
                continue;
            }

            if ($distributiveFileInfo->isDir()) {
                $bitrixFilesIterator->append(
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator(
                            $distributiveFileInfo->getPathname(),
                            RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::CURRENT_AS_FILEINFO
                        ),
                        RecursiveIteratorIterator::CHILD_FIRST
                    )
                );
            }

            $bitrixFilesIterator->append(new ArrayIterator([$distributiveFileInfo]));
        }

        $ss->note('Считаем количество файлов и директорий к удалению');

        $entriesCnt = 0;
        while ($bitrixFilesIterator->valid()) {
            $bitrixFilesIterator->next();
            $entriesCnt++;
        }

        $ss->section('Удаляем файлы и директории');

        $progress = $ss->createProgressBar($entriesCnt);

        foreach ($bitrixFilesIterator as $directoryEntry) {
            $pathToDelete = $directoryEntry->getPathname();
            if ($directoryEntry->isFile()) {
                if (!unlink($pathToDelete)) {
                    throw new Exception(sprintf('Не удалось удалить файл: %s', $pathToDelete));
                }
            } else {
                if (!rmdir($pathToDelete)) {
                    throw new Exception(sprintf('Не удалось удалить директорию: %s', $pathToDelete));
                }
            }

            $progress->advance();
        }

        $ss->writeln(PHP_EOL);
    }

    /**
     * @param SymfonyStyle $ss
     * @param WizardConfig $wizardConfig
     *
     * @throws Exception
     */
    private function uninstallDatabase(SymfonyStyle $ss, WizardConfig $wizardConfig): void
    {
        $createDatabaseStep = $wizardConfig->getFirstStepOfId(WizardConfigStepIdEnum::CREATE_DATABASE());
        $payload = $createDatabaseStep->payload();

        $connection = new mysqli(
            $payload[CreateDatabaseFieldEnum::HOST],
            $payload[CreateDatabaseFieldEnum::USER],
            $payload[CreateDatabaseFieldEnum::PASSWORD],
            $payload[CreateDatabaseFieldEnum::DATABASE]
        );

        $res = $connection->query('SHOW TABLES LIKE "b_%"');
        if (!$res) {
            throw new Exception(sprintf('Не удалось получить список таблиц: %s', $connection->error));
        }

        $bitrixTables = $res->fetch_all(MYSQLI_NUM);

        $ss->section('Удаляем таблицы Битрикса');

        $progress = $ss->createProgressBar(count($bitrixTables));
        foreach ($bitrixTables as [$table]) {
            if (!$connection->query('DROP TABLE ' . $table)) {
                throw new Exception(sprintf('Не удалось таблицу "%s"', $table));
            }

            $progress->advance();
        }

        $ss->writeln(PHP_EOL);
    }
}
