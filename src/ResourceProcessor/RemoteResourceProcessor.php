<?php declare(strict_types=1);

namespace Maximaster\BitrixCliInstall\ResourceProcessor;

use Exception;
use Maximaster\BitrixCliInstall\ResourceProcessor\Event\ResourceProcessorProgressed;
use Psr\EventDispatcher\EventDispatcherInterface;

class RemoteResourceProcessor implements ResourceProcessorInterface
{
    /** @var EventDispatcherInterface|null */
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

    public function supports(string $resourceUri): bool
    {
        $scheme = parse_url($resourceUri, PHP_URL_SCHEME);
        return $scheme && in_array($scheme, stream_get_wrappers());
    }

    /**
     * @param string $resourceUri
     * @param string|null $workingDirectory
     *
     * @return string|null
     *
     * @throws Exception
     */
    public function process(string $resourceUri, string $workingDirectory = null): ?string
    {
        if (!$workingDirectory) {
            throw new Exception(sprintf('workingDirectory должна быть указана для данного процессора'));
        }

        $filePath = parse_url($resourceUri, PHP_URL_PATH);
        $fileName = basename($filePath);
        if (!$fileName) {
            throw new Exception(sprintf('Не удалось извлечь имя файла из ссылки на ресурс "%s"', $resourceUri));
        }

        $remoteStream = fopen($resourceUri, 'rb', false, $this->buildStreamContext());

        if ($remoteStream === false) {
            throw new Exception(sprintf('Не удалось открыть удалённый ресурс "%s"', $resourceUri));
        }

        $nextPackageUrl = $workingDirectory . DIRECTORY_SEPARATOR . $fileName;

        if (!is_writable($workingDirectory)) {
            throw new Exception(sprintf('Не удалось открыть локальный ресурс "%s" для записи', $nextPackageUrl));
        }

        if (file_put_contents($nextPackageUrl, $remoteStream) === false) {
            throw new Exception(sprintf('Не удалось сохранить данные загружаемые по ссылке "%s"', $resourceUri));
        }

        return $nextPackageUrl;
    }

    /**
     * @return resource|null
     */
    private function buildStreamContext()
    {
        $streamContext = null;
        if (!$this->dispatcher) {
            return null;
        }

        $streamContext = stream_context_create();
        stream_context_set_params($streamContext, [
            'notification' => function (
                int $notificationCode,
                int $severity,
                ?string $message,
                int $messageCode,
                int $bytesTransferred,
                int $bytesMax
            ) {
                static $filesize;
                if ($notificationCode === STREAM_NOTIFY_FILE_SIZE_IS) {
                    $filesize = $bytesMax;
                    return;
                }

                if (!$bytesTransferred) {
                    return;
                }

                $this->dispatcher->dispatch(new ResourceProcessorProgressed($this, $bytesTransferred, $filesize));
            },
        ]);

        return $streamContext;
    }
}
