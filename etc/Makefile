after-renovate:
	$(MAKE) update-versions generate

generate:
	$(DOCKER_RUN) php bin/generate.php

update-versions: install
	$(DOCKER_RUN) php bin/update-versions.php
