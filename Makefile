.PHONY: check lint stan sync

check: lint phpcs stan

vendor/autoload.php: composer.json
	rm -rf composer.lock vendor/
	composer install

lint:
	find . -name '*.php' '!' -path './vendor/*' -exec php -l {} >/dev/null \;

stan: vendor/autoload.php
	vendor/bin/phpstan analyze

phpcs: vendor/autoload.php
	vendor/bin/phpcs

deploy: check
	rm -rf var/cache/*
	# @todo: use specific composer command for production:
	#   composer install --no-dev  --optimize-autoloader --classmap-authoritative --prefer-dist
	rsync --verbose -e 'ssh -p222' --exclude .git --exclude token --exclude logs --exclude 'var/log/*' --delete -az ./ qbusio@qbus.de:public_html/slack/
