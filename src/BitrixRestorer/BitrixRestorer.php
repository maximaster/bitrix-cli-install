<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer;

use DOMNode;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Enum\WizardConfigStepIdEnum;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigFactory;
use Maximaster\BitrixCliInstall\BitrixRestorer\Enum\BackupLocationTypeEnum;
use Maximaster\BitrixCliInstall\BitrixRestorer\Enum\BitrixRestoreStageEnum;
use Maximaster\BitrixCliInstall\BitrixRestorer\Event\RestoreStepFinished;
use Maximaster\BitrixCliInstall\BitrixRestorer\Event\RestoreStepPrepared;
use Maximaster\BitrixCliInstall\ResourceProcessor\ResourceProcessorInterface;
use Maximaster\CliEnt\CliEntFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

class BitrixRestorer
{
    public const RESTORE_URI = '/restore.php';

    /** @var CliEntFactory */
    private $cliEntFactory;

    /** @var ResourceProcessorInterface */
    private $restoreScriptProcessor;

    /** @var ResourceProcessorInterface */
    private $backupProcessor;

    /** @var WizardConfigFactory */
    private $wizardConfigFactory;

    /** @var EventDispatcherInterface|null */
    private $dispatcher;

    public function __construct(
        CliEntFactory $cliEntFactory,
        ResourceProcessorInterface $restoreScriptProcessor,
        ResourceProcessorInterface $backupProcessor,
        WizardConfigFactory $wizardConfigFactory,
        EventDispatcherInterface $dispatcher = null
    ) {
        $this->cliEntFactory = $cliEntFactory;
        $this->restoreScriptProcessor = $restoreScriptProcessor;
        $this->backupProcessor = $backupProcessor;
        $this->wizardConfigFactory = $wizardConfigFactory;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param BitrixRestoreConfig $config
     *
     * @throws Exception
     */
    public function restore(BitrixRestoreConfig $config)
    {
        if (!$this->restoreScriptProcessor->supports($config->restoreScriptUri)) {
            throw new Exception(sprintf('Формат ссылки на скрипт восстановления не поддерживается: %s', $config->restoreScriptUri));
        }

        $this->restoreScriptProcessor->process($config->restoreScriptUri, $config->documentRoot->getPathname());

        $client = $this->cliEntFactory->build($config->documentRoot->getPathname());

        $this->patchRestore($config);
        $this->initRestore($config, $client);

        $backupFormat = pathinfo($config->backupUri, PATHINFO_EXTENSION);

        $backupLocationType = parse_url($config->backupUri, PHP_URL_SCHEME)
            ? BackupLocationTypeEnum::REMOTE()
            : BackupLocationTypeEnum::LOCAL();

        $backupLocationType->isRemote()
            ? $this->downloadBackup($config, $client)
            : $this->copyBackup($config->backupUri, $config->documentRoot->getPathname());

        if ($backupFormat !== 'sql') {
            $unpackResponse = $this->unpackBackup($config, $client);
        }

        if ($config->skipDbRestore) {
            return;
        }

        // Приоритетно берём подключение конфига установки, если указано
        // Иначе из ответа от распаковки, если она делалась
        // Иначе делаем запрос на автоматически определённые параметры подключения
        if ($config->wizardConfig) {
            $restoreDatabaseRequest = $this->createRestoreDatabaseRequestFromWizardConfig($config);
        } elseif (!empty($unpackResponse)) {
            $restoreDatabaseRequest = $this->createRestoreDatabaseRequestFromRestoreResponse($config, $unpackResponse);
        } else {
            $restoreDatabaseRequest = $this->createRestoreDatabaseRequestFromRestoreResponse(
                $config,
                $this->requestConnectionParameters($config, $client)
            );
        }

        $this->restoreDatabase($config, $client, $restoreDatabaseRequest);
    }

    /**
     * @param BitrixRestoreConfig $config
     * @param BitrixRestoreStageEnum $stage
     * @param ClientInterface $client
     * @param array $payload
     * @param callable|null $handler
     *
     * @return mixed ResponseInterface или то что вернёт $handler()
     *
     * @throws Exception
     */
    private function step(
        BitrixRestoreConfig $config,
        BitrixRestoreStageEnum $stage,
        ClientInterface $client,
        array $payload = [],
        callable $handler = null
    ) {
        $this->dispatcher && $this->dispatcher->dispatch(new RestoreStepPrepared($config, $stage, $payload));

        $response = $client->post(self::RESTORE_URI, [ RequestOptions::FORM_PARAMS => $payload ]);
        $restoreResponse = $this->parseRestoreResponse($response);

        $this->assertResponse($restoreResponse);

        $this->dispatcher && $this->dispatcher->dispatch(new RestoreStepFinished($config, $stage, $payload, $restoreResponse));

        if ($handler) {
            $handlerResult = $handler($restoreResponse);
            return $handlerResult === null ? $response : $handlerResult;
        }

        return $response;
    }

    private function parseRestoreResponse(ResponseInterface $response): RestoreResponse
    {
        $responseBody = $response->getBody()->getContents();
        $crawler = new Crawler($responseBody);

        $progressBars = $crawler->filter('.progressbar-counter');

        return new RestoreResponse(
            $response,
            $responseBody,
            $progressBars->count() ? (int) $progressBars->first()->text() : null
        );
    }

    /**
     * @param ResponseInterface $response
     *
     * @throws Exception
     */
    private function assertResponse(RestoreResponse $response): void
    {
        $statusCode = $response->httpResponse->getStatusCode();
        if ($statusCode !== 200) {
            throw new Exception(sprintf('Ожидался код ответа 200, получен %d', $statusCode));
        }
    }

    /**
     * Вносит ряд исправлений в restore.php
     *
     * @param BitrixRestoreConfig $config
     *
     * @throws Exception
     */
    private function patchRestore(BitrixRestoreConfig $config)
    {
        // Заменить ВСЮ строку значением, если в сроке есть вхождение данного ключа
        $replaces = [
            // Под PHP 7.4 сыпется в огромный количеством E_DEPRECATED на стадии распаковки,
            // поэтому добавляем их в список исключений
            'error_reporting(' => 'error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);',
            // Чтобы чаще обновлялся прогресс восстановления
            'define("STEP_TIME"' => 'define("STEP_TIME", 5);',
        ];

        $restoreFile = $config->documentRoot->getPathname() . DIRECTORY_SEPARATOR . self::RESTORE_URI;
        $restoreFileContent = file_get_contents($restoreFile);

        foreach (file($restoreFile) as $restoreFileLine) {
            foreach ($replaces as $replaceMarker => $replaceValue) {
                if (strpos($restoreFileLine, $replaceMarker) !== 0) {
                    continue;
                }

                $restoreFileContent = str_replace($restoreFileLine, $replaceValue, $restoreFileContent);
            }
        }

        if (!file_put_contents($restoreFile, $restoreFileContent)) {
            throw new Exception('Не удалось сохранить изменения в restore.php');
        }
    }

    /**
     * Пустой запрос в котором Битрикс инициализирует IP_LIMIT
     *
     * @param BitrixRestoreConfig $config
     * @param ClientInterface $client
     *
     * @throws Exception
     */
    private function initRestore(BitrixRestoreConfig $config, ClientInterface $client)
    {
        $this->step($config, BitrixRestoreStageEnum::INIT_RESTORE(), $client);
    }

    /**
     * @param BitrixRestoreConfig $config
     * @param ClientInterface $client
     *
     * @throws Exception
     */
    private function downloadBackup(BitrixRestoreConfig $config, ClientInterface $client): void
    {
        $payload = [
            'Step'          => 2,
            'source'        => 'download',
            'arc_down_url'  => $config->backupUri,
            'continue'      => 'Y',
        ];

        while (true) {
            $payload = $this->step(
                $config,
                BitrixRestoreStageEnum::DOWNLOAD_BACKUP(),
                $client,
                $payload,
                [$this, 'collectNextPayload']
            );

            if ($payload['source'] === 'download') {
                continue;
            }

            break;
        }
    }

    /**
     * @param BitrixRestoreConfig $config
     * @param ClientInterface $client
     *
     * @return array
     *
     * @throws Exception
     */
    private function unpackBackup(BitrixRestoreConfig $config, ClientInterface $client): array
    {
        $backupUrlUri = parse_url($config->backupUri, PHP_URL_PATH);
        if (!$backupUrlUri) {
            throw new Exception(sprintf('Не удалось определить имя файла по URI: "%s"', $config->backupUri));
        }

        $fixedPayload = $payload = [
            'Step' => 2,
            'arc_name' => basename($backupUrlUri),
        ];

        while (true) {
            /** @var array $payload */
            $payload = $this->step(
                $config,
                BitrixRestoreStageEnum::UNPACK_BACKUP(),
                $client,
                $fixedPayload + $payload,
                [$this, 'collectNextPayload']
            );

            // Прогресс распаковки
            if (!empty($payload['DataSize'])) {
                if (((int) $payload['Block'] < (int) $payload['DataSize'])) {
                    continue;
                }
            }

            // Прогресс очистки результатов
            if (!empty($payload['clear'])) {
                continue;
            }

            $hasProgressFields = array_key_exists('Block', $payload) && array_key_exists('DataSize', $payload);
            if (($hasProgressFields && ((int) $payload['Block'] < (int) $payload['DataSize']))
                || !empty($payload['clear'])
            ) {
                continue;
            }

            break;
        }

        return $payload;
    }

    private function collectNextPayload(RestoreResponse $response): array
    {
        $crawler = new Crawler($response->responseBody);

        $payload = [];
        foreach ($crawler->filter('form[name="restore"] input') as $input) {
            $nameAttr = $input->attributes->getNamedItem('name');
            $valueAttr = $input->attributes->getNamedItem('value');
            if ($nameAttr && $valueAttr && $nameAttr instanceof DOMNode && $valueAttr instanceof DOMNode) {
                $payload[ $nameAttr->nodeValue ] = $valueAttr->nodeValue;
            }
        }

        return $payload;
    }

    private function createRestoreDatabaseRequestFromRestoreResponse(
        BitrixRestoreConfig $config,
        array $unpackResponsePayload
    ): RestoreDatabaseRequest {
        $connectionParams = [];
        foreach ($unpackResponsePayload as $field => $value) {
            if (strpos($field, 'DB') === 0) {
                $connectionParams[$field] = $value;
            }
        }

        return new RestoreDatabaseRequest(
            $this->createDumpNameFromBackupUri($config->backupUri),
            $connectionParams['DBHost'],
            $connectionParams['DBLogin'],
            $connectionParams['DBPassword'],
            $connectionParams['DBName']
        );
    }

    /**
     * @param BitrixRestoreConfig $config
     *
     * @return RestoreDatabaseRequest
     *
     * @throws Exception
     */
    private function createRestoreDatabaseRequestFromWizardConfig(BitrixRestoreConfig $config): RestoreDatabaseRequest
    {
        $wizardConfig = $this->wizardConfigFactory->createFromPath(
            $config->wizardConfig,
            [getcwd(), $config->documentRoot->getPathname()]
        );

        $connectionParams = $wizardConfig->getFirstStepOfId(WizardConfigStepIdEnum::CREATE_DATABASE())->payload();

        return new RestoreDatabaseRequest(
            $this->createDumpNameFromBackupUri($config->backupUri),
            $connectionParams['__wiz_host'],
            $connectionParams['__wiz_user'],
            $connectionParams['__wiz_password'],
            $connectionParams['__wiz_database']
        );
    }

    private function createDumpNameFromBackupUri(string $backupUri): string
    {
        if (pathinfo($backupUri, PATHINFO_EXTENSION) === 'sql') {
            return $backupUri;
        }

        $fileName = basename(pathinfo($backupUri, PATHINFO_BASENAME), '.tar.gz');
        return "{$fileName}.sql";
    }

    /**
     * @param BitrixRestoreConfig $config
     * @param ClientInterface $client
     * @param RestoreDatabaseRequest $request
     *
     * @throws Exception
     */
    private function restoreDatabase(BitrixRestoreConfig $config, ClientInterface $client, RestoreDatabaseRequest $request): void
    {
        $fixedPayload = $payload = [
            'Step' => 3,
            'dump_name' => $request->dumpName,
            'DBHost' => $request->host,
            'DBLogin' => $request->user,
            'DBPassword' => $request->password,
            'DBName' => $request->database,
        ];

        // $payload += ['d_pos' => 0];

        while (true) {
            $payload = $this->step(
                $config,
                BitrixRestoreStageEnum::RESTORE_DATABASE(),
                $client,
                $fixedPayload + $payload,
                [$this, 'collectNextPayload']
            );

            if (!array_key_exists('d_pos', $payload)) {
                break;
            }
        }
    }

    /**
     * @param BitrixRestoreConfig $config
     * @param ClientInterface $client
     *
     * @return array
     *
     * @throws Exception
     */
    private function requestConnectionParameters(BitrixRestoreConfig $config, ClientInterface $client): array
    {
        return $this->step(
            $config,
            BitrixRestoreStageEnum::REQUEST_CONNECTION_PARAMETERS(),
            $client,
            ['Step' => 2, 'source' => 'dump'],
            [$this, 'collectNextPayload']
        );
    }

    /**
     * @param string $backupUri
     * @param string $targetDirectory
     *
     * @throws Exception
     */
    private function copyBackup(string $backupUri, string $targetDirectory): void
    {
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . basename($backupUri);

        // Случай когда бекап уже находится в публичной директории и переносить для распаковки его не нужно
        if ($backupUri === $targetPath) {
            return;
        }

        if (copy($backupUri, $targetPath) === false) {
            throw new Exception(sprintf('Не удалось скопировать бекап "%s" в директорию "%s"', $backupUri, $targetDirectory));
        }
    }
}
