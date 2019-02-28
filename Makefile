.PHONY: check lint stan sync

check: lint phpcs stan

vendor/autoload.php: composer.json
	rm -rf composer.lock vendor/
	composer install

lint:
	find . -name '*.php' '!' -path './vendor/*' '!' -path './.deploy/*' -exec php -l {} >/dev/null \;

stan: vendor/autoload.php
	vendor/bin/phpstan analyze

phpcs: vendor/autoload.php
	vendor/bin/phpcs

deploy: check public/active-collab_light.png
	rsync --verbose --exclude .git --exclude token --exclude logs --exclude vendor/ --exclude 'var/log/*' --exclude '/.deploy' --delete -az ./ .deploy/
	cd .deploy/ && composer install --no-dev --optimize-autoloader --classmap-authoritative --prefer-dist
	.deploy/bin/warmup-caches
	rsync --verbose -e 'ssh -p222' --exclude .git --exclude token --exclude logs --exclude 'var/log/*' --delete -az .deploy/ qbusio@qbus.de:public_html/slack/

# Asset generation
public/active-collab_light.png: public/active-collab_light.svg
	rsvg-convert -w 129 -h 129 -f png $< > $@
