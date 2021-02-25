# Bitrix CLI Install

Позволит вам установить Битрикс через консоль из дистрибутива или бекапа. Наличие запущенного веб-сервера не требуется.

## Использование

Документацию по командам следует смотреть вызывая их с --help. Список доступных команд доступен при вызове команды list.

Принципиально, использование поддерживается либо через глобальную установку Composer, либо через Docker. 
В обоих примерах используется .env файл где расположен ряд переменных окружения (см. Переопределение конфигурации)

### Через глобальную установку Composer

```bash
composer global require maximaster/bitrix-cli-install
source .env && bitrix-cli-install bitrix:install https://www.1c-bitrix.ru/download/start_encode.zip ~/projects/bitrix/public
```

Для работы требуется установка ext-runkit(7)

### Через docker

```bash
BITRIX_DB_HOST=$(docker run --rm bash ip -4 route show default | cut -d" " -f3)   \
docker run --rm -it                                                               \
  -v $HOME/projects/bitrix/public:/tmp/bitrix-cli-install                         \
  --env-file=.env -e BITRIX_DB_HOST                                               \
  maximaster/bitrix-cli-install bitrix:install                                    \
    https://www.1c-bitrix.ru/download/start_encode.zip /tmp/bitrix-cli-install
```

### Переопределение конфигурации

Порядок установки определяется конфигурационным YAML-файлом, который по сути описывает какие данные на каких шагах
отправляются на форме. По умолчанию используется файл `config/default.yaml`, но можно указать собственный, используя
опцию `--wizard-config`. При использовании стандартного конфигурационного файла можно переопределить ряд параметров
через переменные окружения:

* BITRIX_DB_HOST
* BITRIX_DB_NAME
* BITRIX_DB_LOGIN
* BITRIX_DB_PASSWORD
* BITRIX_ADMIN_LOGIN
* BITRIX_ADMIN_PASSWORD
* BITRIX_ADMIN_EMAIL

## Разработка

Для начала надо запустить образ с пробросом текущего кода, чтобы его можно было менять налету, также пробросом
директории установки и доступа к базе данных:

```bash
make develop document-root=$PWD/tmp
```

Опционально можно указать параметр `with` который позволит передать дополнительные данные для `docker-compose run`,
например переменные окружения, через `-e ENV_VAR=value`.

Произойдёт вход в консоль контейнера. В нём будет находиться тот же код, что в хостовой системе, поэтому в первый раз
там не будет директории `vendor` и её надо создать:

```bash
composer install
```

После, можно работать с консольной утилитой согласно её документации:

```bash
bitrix-cli-install --help
```

Установку нужно производить в `$BITRIX_DIR`, т.к. именно эта директория пробрасывается в хостовую систему через аргумент
**document-root** команды `make develop`. Пример запуска установки внутри контейнера:

```bash
bitrix-cli-install bitrix:install https://www.1c-bitrix.ru/download/start_encode.zip $BITRIX_DIR
```

Подключение к базе по умолчанию осуществляется по адресу хостовой машины, остальные параметры согласно переменным
окружения.

В случае необходимости отладки через XDebug нужно его включить:

```bash
make debug-on
```

При использовании PHPStorm минимальное количество подготовительных действий для начала отладки будет следующим:

* [Создать сервер](jetbrains://PhpStorm/settings?name=Languages+%26+Frameworks--PHP--Servers) с именем
  bitrix-cli-install, в маппинге директорий указать `/var/www`
* Убедиться, что в [настройках](jetbrains://PhpStorm/settings?name=Languages+%26+Frameworks--PHP--Debug) установлен порт
  `9000`
* Включить прослушивание XDebug-подключений (трубка)
