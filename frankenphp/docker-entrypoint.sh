#!/bin/sh
set -e

if [ "$1" = 'frankenphp' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then

	if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
		composer install --prefer-dist --no-progress --no-interaction
	fi

	# Display information about the current project
	# Or about an error in project initialization
	php bin/console -V

	if grep -q ^DATABASE_URL= .env; then
		echo 'Waiting for database to be ready...'
		ATTEMPTS_LEFT_TO_REACH_DATABASE=60
		until [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ] || DATABASE_ERROR=$(php bin/console dbal:run-sql -q "SELECT 1" 2>&1); do
			if [ $? -eq 255 ]; then
				# If the Doctrine command exits with 255, an unrecoverable error occurred
				ATTEMPTS_LEFT_TO_REACH_DATABASE=0
				break
			fi
			sleep 1
			ATTEMPTS_LEFT_TO_REACH_DATABASE=$((ATTEMPTS_LEFT_TO_REACH_DATABASE - 1))
			echo "Still waiting for database to be ready... Or maybe the database is not reachable. $ATTEMPTS_LEFT_TO_REACH_DATABASE attempts left."
		done

		if [ $ATTEMPTS_LEFT_TO_REACH_DATABASE -eq 0 ]; then
			echo 'The database is not up or not reachable:'
			echo "$DATABASE_ERROR"
			exit 1
		else
			echo 'The database is now ready and reachable'
		fi

		if find ./migrations -iname '*.php' -print -quit | grep --quiet .; then
			php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
		fi
	fi

	# Tailwind's built CSS is generated, never committed: var/ is gitignored,
	# and compose.override.yaml hides it behind an anonymous volume, so a fresh
	# checkout starts with none. base.html.twig throws rather than degrade, so
	# every page — /login included — 500s until this has run once.
	#
	# Dev only, and only when it is missing: the production image bakes the CSS
	# in at build time (Dockerfile, frankenphp_prod_builder), so the glob below
	# already matches there and this is skipped. The first dev run downloads the
	# standalone Tailwind binary, which is why compose.override.yaml gives the
	# healthcheck a longer start_period.
	#
	# Restyling later is still manual — `bin/console tailwind:build`, or
	# `--watch` during template work. This only guarantees a first paint.
	if [ -z "$(ls -A var/tailwind/*.built.css 2>/dev/null)" ]; then
		echo 'Building Tailwind CSS (first run downloads the standalone binary)...'
		php bin/console tailwind:build
	fi

	echo 'PHP app ready!'
fi

exec docker-php-entrypoint "$@"
