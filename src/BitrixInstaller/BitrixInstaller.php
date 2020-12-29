<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\BitrixInstaller;

use Exception;
use Guzzle\Parser\Cookie\CookieParser;
use Guzzle\Parser\Message\MessageParser;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\BitrixDistributiveReady;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationConfigurationPrepared;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationFailed;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationFinished;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationStepFinished;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationStepPayloadPrepared;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\InstallationStepPrepared;
use Maximaster\BitrixCliInstall\BitrixInstaller\Event\WizardClientPrepared;
use Maximaster\BitrixCliInstall\BitrixInstaller\Exception\InstallationStepException;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\Parser\WizardConfigBuilder;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigFactory;
use Maximaster\BitrixCliInstall\BitrixInstaller\WizardConfig\WizardConfigStep;
use Maximaster\BitrixCliInstall\ResourceProcessor\ResourceProcessorInterface;
use Maximaster\CliEnt\CliEntHandler;
use Maximaster\CliEnt\GlobalsParser;

class BitrixInstaller
{
    /** @var WizardConfigFactory */
    private $wizardConfigFactory;

    /** @var ResourceProcessorInterface */
    private $distributivePackageProcessor;

    /** @var EventDispatcher|null */
    private $eventDispatcher;

    public function __construct(
        WizardConfigFactory $wizardConfigFactory,
        ResourceProcessorInterface $distributivePackageProcessor,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->wizardConfigFactory = $wizardConfigFactory;
        $this->distributivePackageProcessor = $distributivePackageProcessor;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param BitrixInstallConfig $installConfig
     *
     * @throws Exception
     */
    public function install(BitrixInstallConfig $installConfig): void
    {
        $documentRoot = $installConfig->documentRoot();
        $wizardConfig = $this->wizardConfigFactory->createFromPath(
            $installConfig->wizardConfig(),
            [getcwd(), $documentRoot->getPathname()]
        );

        $this->eventDispatcher && $this->eventDispatcher->dispatch(
            new InstallationConfigurationPrepared($installConfig, $wizardConfig)
        );

        if (!$this->distributivePackageProcessor->supports($installConfig->distributivePackageUri())) {
            throw new Exception('Формат ссылки на дистрибутив не поддерживается');
        }

        $this->distributivePackageProcessor->process($installConfig->distributivePackageUri(), $installConfig->documentRoot()->getPathname());

        $this->eventDispatcher && $this->eventDispatcher->dispatch(new BitrixDistributiveReady($installConfig));

        $hoardedPayload = [];
        $client = $this->client($documentRoot->getPathname());

        $this->eventDispatcher && $this->eventDispatcher->dispatch(
            new WizardClientPrepared($installConfig, $wizardConfig, $client)
        );

        /** @var WizardConfigStep|null $previousStep */
        $previousStep = null;
        foreach ($wizardConfig->steps() as $idx => $step) {
            $this->eventDispatcher && $this->eventDispatcher->dispatch(
                new InstallationStepPrepared($installConfig, $wizardConfig, $step)
            );

            unset($hoardedPayload['PreviousStepID'], $hoardedPayload['NextStepID']);

            $defaultPayload = [WizardConfigBuilder::ID_KEY => $step->id()];

            if ($previousStep) {
                $defaultPayload['PreviousStepID'] = $previousStep->id();
            }

            $nextStep = $wizardConfig->step($idx + 1);
            if ($nextStep) {
                $defaultPayload['NextStepID'] = $nextStep->id();
            }

            $hoardedPayload = $step->payload() + $hoardedPayload + $defaultPayload;

            $this->eventDispatcher && $this->eventDispatcher->dispatch(
                new InstallationStepPayloadPrepared($installConfig, $wizardConfig, $step, $hoardedPayload)
            );

            try {
                $this->runStep($client, $hoardedPayload);
            } catch (Exception $e) {
                $this->eventDispatcher && $this->eventDispatcher->dispatch(
                    new InstallationFailed($installConfig, $wizardConfig, $step, $e)
                );
                return;
            }

            $this->eventDispatcher && $this->eventDispatcher->dispatch(
                new InstallationStepFinished($installConfig, $wizardConfig, $step)
            );

            $previousStep = $step;
        }

        $this->eventDispatcher && $this->eventDispatcher->dispatch(
            new InstallationFinished($installConfig, $wizardConfig)
        );
    }

    /**
     * @param Client $client
     * @param array $payload
     * @param int $repeat
     *
     * @throws InstallationStepException
     */
    private function runStep(Client $client, array $payload, int $repeat = 5): void
    {
        $response = $client->post('/index.php', [ RequestOptions::FORM_PARAMS => $payload ]);

        $body = $response->getBody()->getContents();

        $responseCode = $response->getStatusCode();

        if ($responseCode !== 200) {
            if ($repeat) {
                $this->runStep($client, $payload, $repeat - 1);
                return;
            }

            throw new InstallationStepException(sprintf('Неожиданный код ответа: %d', $responseCode), $payload, $body);
        }

        $this->validateResponse($payload, $body);
    }

    /**
     * @param array $payload
     * @param string $response
     *
     * @throws InstallationStepException
     */
    private function validateResponse(array $payload, string $response): void
    {
        try {
            if (strpos($response, '<!DOCTYPE html>') === 0) {
                $this->validateHtmlResponse($payload, $response);
            } else {
                $this->validateAjaxResponse($response);
            }
        } catch (Exception $e) {
            throw new InstallationStepException(
                sprintf('На шаге "%s" возникла проблема: "%s"', $payload[WizardConfigBuilder::ID_KEY], $e->getMessage()),
                $payload,
                $response,
                $e
            );
        }
    }

    /**
     * @param array $payload
     * @param string $response
     *
     * @throws Exception
     */
    private function validateHtmlResponse(array $payload, string $response): void
    {
        $crawler = new Crawler($response);

        $formCrawler = $crawler->filter('#__wizard_form');
        if ($formCrawler->count() === 0) {
            throw new Exception('Не удалось найти форму установки');
        }

        foreach ($formCrawler->filter('.inst-note-block-red') as $errorBlock) {
            // Такой блок есть на страницах с AJAX'ом и показывается он когда возникает ошибка на AJAX'е
            if ($errorBlock->parentNode->attributes->getNamedItem('id')->nodeValue === 'error_notice') {
                continue;
            }

            $errorCrawler = (new Crawler($errorBlock))->filter('.inst-note-block-text');
            if ($errorCrawler->count() && trim($errorCrawler->text())) {
                throw new Exception(sprintf('%s (ошибка на форме установки)', $errorCrawler->text()));
            }
        }

        $stepNode = $formCrawler->filter('input[name="CurrentStepID"]')->getNode(0);
        if (!$stepNode) {
            throw new Exception('Не удалось найти на форме данные о имени текущего шага');
        }

        $actualStepId = $stepNode->getAttribute('value');

        if (!empty($payload['NextStepID']) && $payload['NextStepID'] !== $actualStepId) {
            throw new Exception(sprintf('В ответе ожидался шаг %s, на форме указан %s', $payload['NextStepID'], $actualStepId));
        }
    }

    /**
     * @param string $response
     *
     * @throws Exception
     */
    private function validateAjaxResponse(string $response): void
    {
        if ($response && strpos($response, 'window.ajaxForm.Post') === false) {
            throw new Exception(sprintf('Неожиданный AJAX-ответ: %s', $response));
        }
    }

    /**
     * Было бы хорошо передавать клиент в конструкторе, но его построение зависит от documentRoot, который нам известен
     * только на момент конкретного вызова
     *
     * @param string $documentRoot
     *
     * @return Client
     *
     * @throws Exception
     */
    private function client(string $documentRoot): Client
    {
        return new Client([
            'base_uri' => 'http://localhost',
            'handler' => new CliEntHandler(
                new GlobalsParser(new CookieParser()),
                new MessageParser(),
                $documentRoot,
                function (array &$globals) use ($documentRoot) {
                    $globals['_SERVER'] += [
                        'DOCUMENT_ROOT' =>  $documentRoot,
                    ];
                }
            ),
            'cookie' => new CookieJar(),
        ]);
    }
}
