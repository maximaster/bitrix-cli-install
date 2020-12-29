<?php

namespace Maximaster\BitrixCliInstall\BitrixRestorer;

use InvalidArgumentException;

class RestoreDatabaseRequest
{
    /** @var string */
    public $dumpName;

    /** @var string */
    public $host;

    /** @var string */
    public $user;

    /** @var string */
    public $password;

    /** @var string */
    public $database;
    /** @var bool */
    public $create;

    public function __construct(
        string $dumpName,
        string $host,
        string $user,
        string $password,
        string $database,
        bool $create = true
    ) {
        if (empty($dumpName)) {
            throw new InvalidArgumentException('Аргумент dumpName не должен быть пуст');
        }

        if (empty($host)) {
            throw new InvalidArgumentException('Аргумент host не должен быть пуст');
        }

        if (empty($user)) {
            throw new InvalidArgumentException('Аргумент user не должен быть пуст');
        }

        if (empty($database)) {
            throw new InvalidArgumentException('Аргумент database не должен быть пуст');
        }

        $this->dumpName = $dumpName;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->create = $create;
    }
}