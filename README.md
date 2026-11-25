# Composer PHP Versions in range

GitHub Action that gets the PHP versions in range from composer.json

## Options

This action supports the following option.

### `upcomingReleases`

Will include the next upcoming major or minor PHP release. For earlier builds, use `nightly`.
The upcoming release list is empty.

* *Required*: `No`
* *Type*: `Boolean`
* *Default*: `false`
* *Example*: `true` for including upcoming new major or minor releases

### nightly

Will include the nightly of the next new major or minor PHP release. The current version is `8.7`.
* *Required*: `No`
* *Type*: `Boolean`
* *Default*: `false`
* *Example*: `true` for including the nightly of the next new major or minor releases

### `workingDirectory`

Set a different than the root directory to look for `composer.json`

* *Required*: `No`
* *Type*: `String`
* *Default*: `""`
* *Example*: `clients/GitHub`

The `nightly` and `upcomingReleases` change depending on how close we are to the next release.

Renovate's `make after-renovate` task refreshes version data. The system retains released versions from PHP
upstream tags. A development version enters `nightly` when the `setup-php` builder publishes a Linux binary.
Once the system observes the `nts-alpine-dev` image from `ghcr.io/wyrihaximusnet/php` for at least
24 hours, it moves to `upcoming`; the system removes released versions from both lists. The system records
image first-observed times in `etc/versions.json`, so a later refresh promotes versions after that
observation. Run `php bin/update-versions.php` followed by `make generate` to refresh locally; Renovate
refreshes on dependency updates, not on a fixed schedule.

| Input              | Current value    | Gets updated                                                             |
|--------------------|------------------|--------------------------------------------------------------------------|
| `nightly`          | `8.7`  | When the first nightly of the next major/minor PHP version is out        |
| `upcomingReleases` |  | At least 24 hours after [docker-php](https://github.com/WyriHaximusNet/docker-php) first observes an image |

## Output

The action provides 7 outputs, most importantly `version` which contains a JSON list of versions for
follow up steps:

```json
["7.3","7.4","8.0","8.1","8.2","8.3","8.4"]
```

And the `highest` and `lowest` outputs that provide the highest PHP version (`8.4` in the `version` output example)
and the lowest PHP version (`7.3` in the `version` output example) from the `version` list. The fourth output is
`upcoming` and holds the upcoming but in alpha/beta/rc next minor or major version of PHP. The
`nightly` contains the current in development but not alpha/beta/rc tag next major or minor version.

On top of that this action also provides 3 lists of extensions. The extensions from `require` in
`requiredExtensions`, dev extensions in `requiredDevExtensions`, and a combined list in `extensions`.

## Example

This example gets the supported PHP version from `composer.json` and runs tests for all those versions:

```yaml
name: CI

on:
  push:
  pull_request:

jobs:
  supported-versions-matrix:
    name: Supported Versions Matrix
    runs-on: ubuntu-latest
    outputs:
      extensions: ${{ steps.supported-versions-matrix.outputs.extensions }}
      version: ${{ steps.supported-versions-matrix.outputs.version }}
    steps:
      - uses: actions/checkout@v3
      - id: supported-versions-matrix
        uses: WyriHaximus/github-action-composer-php-versions-in-range@v1
  tests:
    name: PHP ${{ matrix.php }} Latest
    runs-on: ubuntu-latest
    needs:
      - supported-versions-matrix
    strategy:
      matrix:
        php: ${{ fromJson(needs.supported-versions-matrix.outputs.version) }}
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          tools: composer
          coverage: none
          extensions: ${{ join(fromJson(needs.supported-versions-matrix.outputs.extensions), ',') }}
      - name: Install dependencies
        uses: ramsey/composer-install@v2
      - name: Execute tests
        run: composer test
```

<!-- vale off -->
## License

Copyright © 2026 [Cees-Jan Kiewiet](http://wyrihaximus.net/)

<!-- vale write-good = NO -->
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

<!-- vale neighbor.AllCapsProse = NO -->
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
<!-- vale neighbor.AllCapsProse = yes -->
<!-- vale write-good = yes -->
<!-- vale on -->
