# SPDX-FileCopyrightText: 2026 Nexeno contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
app_name=nexeno_freeup
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
package_dir=$(appstore_dir)/$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates

OCC?=php ../../occ

.PHONY: default help install-deps install-deps-nodev install-deps-js build build-js watch \
	lint lint-js lint-php psalm cs-fix test-e2e appstore sign clean clean-dist

default: build

help:
	@echo "make install-deps   Install npm and composer packages"
	@echo "make build          Install packages and build the front end (js/, css/)"
	@echo "make watch          Rebuild the front end on every change"
	@echo "make lint           ESLint, PHP syntax, code style and Psalm checks"
	@echo "make cs-fix         Fix PHP code style"
	@echo "make test-e2e       Run the Playwright tests"
	@echo "make appstore       Build build/artifacts/appstore/$(app_name).tar.gz"
	@echo "make sign           Sign the package (needs the app certificate in $(cert_dir))"
	@echo "make clean          Remove build/"

install-deps: install-deps-js
	composer install

install-deps-nodev: install-deps-js
	composer install --no-dev

install-deps-js:
	npm ci

build: install-deps build-js

build-js:
	npm run build

watch:
	npm run dev

lint: lint-js lint-php psalm

lint-js:
	npm run lint

lint-php:
	composer run lint 1>/dev/null
	composer run cs:check

psalm:
	composer run psalm

cs-fix:
	composer run cs:fix

test-e2e:
	npm run test:e2e

# The package holds only what runs on the server; .nextcloudignore lists everything else.
appstore: clean install-deps-nodev build-js
	mkdir -p $(package_dir)
	rsync -a --exclude-from=.nextcloudignore $(if $(wildcard .git/info/exclude),--exclude-from=.git/info/exclude) ./ $(package_dir)/
	tar -czf $(appstore_dir)/$(app_name).tar.gz -C $(appstore_dir) $(app_name)
	@echo "Package: $(appstore_dir)/$(app_name).tar.gz"

sign:
	$(OCC) integrity:sign-app \
		--privateKey=$(cert_dir)/$(app_name).key \
		--certificate=$(cert_dir)/$(app_name).crt \
		--path=$(package_dir)
	tar -czf $(appstore_dir)/$(app_name).tar.gz -C $(appstore_dir) $(app_name)
	@echo "App Store signature:"
	@openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(appstore_dir)/$(app_name).tar.gz | openssl base64

clean:
	rm -rf build/

clean-dist: clean
	rm -rf node_modules/ vendor/ vendor-bin/*/vendor/
