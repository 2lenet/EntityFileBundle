lint:
	./vendor/bin/phpcs
	./vendor/bin/phpstan analyse

format:
	./vendor/bin/phpcbf
