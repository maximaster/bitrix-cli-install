IMAGE_NAME=maximaster/bitrix-cli-install

ifeq ($(shell which docker | wc -l),0)
HOST_MACHINE=
else
HOST_MACHINE := $(shell docker run --rm bash -c 'ip -4 route show default | cut -d" " -f3')
endif

.PHONY: build
build:
	docker build . -t $(IMAGE_NAME)

.PHONY: run # cmd="Выполняемая команда" with="Дополнительные параметры выполнения docker run"
run:
	docker run -it --rm $(with) bitrix-cli-install $(cmd)

.PHONY: publish
publish:
	docker push $(IMAGE_NAME)

.PHONY: install # distributive="Ссылка на дистрибутив" to="Путь установки"
install:
	test -n "$(distributive)"
	test -n "$(to)"
	make run cmd="bitrix:install -- $(distributive) /tmp/bitrix-cli-install" with="--volume $(to):/tmp/bitrix-cli-install"

.PHONY: develop # document-root="Путь установки"
develop:
	test -n "$(document-root)"
	HOST_MACHINE=$(HOST_MACHINE)						\
	BITRIX_CLI_INSTALL_TARGET=$(document-root)			\
	XDEBUG_CONFIG="xdebug.remote_host=$(HOST_MACHINE)"	\
	PHP_IDE_CONFIG=serverName=bitrix-cli-install		\
		docker-compose run --rm --entrypoint=			\
			-e BITRIX_DB_HOST=$(HOST_MACHINE)			\
			-e BITRIX_ADMIN_LOGIN=admin					\
			-e BITRIX_ADMIN_PASSWORD=123456				\
			-e BITRIX_ADMIN_EMAIL=admin@example.com		\
				$(with) bitrix-cli-install sh

.PHONY: build-develop
build-develop:
	docker-compose build

#
# Команды, которые будут работать только внутри контейнера
#

.PHONY: test-container
test-container:
	test ! -n "$$(which docker)"

.PHONY: debug-on
debug-on: test-container
	cat $$XDEBUG_CONFIG_FILE--disabled > $$XDEBUG_CONFIG_FILE

.PHONY: debug-off
debug-off: test-container
	echo "" > $$XDEBUG_CONFIG_FILE
