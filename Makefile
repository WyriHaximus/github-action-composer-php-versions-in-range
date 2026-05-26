# set all to phony
SHELL=bash

.PHONY: *

#PHP_VERSION:=$(shell docker run --rm -v "`pwd`:`pwd`" jess/jq jq -r -c '.config.platform.php' "`pwd`/composer.json" | php -r "echo str_replace('|', '.', explode('.', implode('|', explode('.', stream_get_contents(STDIN), 2)), 2)[0]);")
PHP_VERSION="8.5"
CONTAINER_NAME=$(shell echo "ghcr.io/wyrihaximusnet/php:${PHP_VERSION}-nts-alpine-dev")

ifneq ("$(wildcard /.you-are-in-a-wyrihaximus.net-php-docker-image)","")
    IN_DOCKER=TRUE
else
    IN_DOCKER=FALSE
endif

ifeq ("$(IN_DOCKER)","TRUE")
	DOCKER_RUN:=
else
	DOCKER_RUN:=docker run --rm -t \
		-v "`pwd`:`pwd`" \
		-w "`pwd`" \
		${CONTAINER_NAME}
endif

generate: install
	$(DOCKER_RUN) php etc/generate.php

shell: ## Provides Shell access in the expected environment ####
	$(DOCKER_RUN) bash

install: ## Install dependencies ####
	$(DOCKER_RUN) composer install

update: ## Update dependencies ####
	$(DOCKER_RUN) composer update -W

outdated: ## Show outdated dependencies ####
	$(DOCKER_RUN) composer outdated

