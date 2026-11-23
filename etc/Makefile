after-renovate:
	$(MAKE) update-versions generate

generate:
	$(DOCKER_RUN) php bin/generate.php

update-versions: ## Update etc/versions.json ##%GITHUB_TOKEN%##
	$(DOCKER_RUN) php bin/update-versions.php
