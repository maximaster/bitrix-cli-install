# Bitrix CLI Install

Позволит вам установить Битрикс через консоль из дистрибутива или бекапа.
Наличие запущенного веб-сервера не требуется.

## Использование

```bash
composer require --dev maximaster/bitrix-cli-install

# Экспортируйте нужные переменные окружения, например из env-файла.
set -a && source .env && set +a
./vendor/bin/bitrix-cli-install bitrix:install start_encode.zip www
```

### Переопределение конфигурации

Порядок установки определяется конфигурационным YAML-файлом, который по сути
описывает какие данные на каких шагах отправляются на форме. По умолчанию
используется файл `config/default.yaml`, но можно указать собственный, используя
опцию `--wizard-config`. При использовании стандартного конфигурационного файла
можно переопределить ряд параметров через переменные окружения:

* BITRIX_DB_HOST
* BITRIX_DB_NAME
* BITRIX_DB_LOGIN
* BITRIX_DB_PASSWORD
* BITRIX_ADMIN_LOGIN
* BITRIX_ADMIN_PASSWORD
* BITRIX_ADMIN_EMAIL
