# Composer PHP Versions in range

GitHub Action that gets the PHP versions in range from composer.json

## Options

This action supports the following option.

### upcomingReleases

Will include the next upcoming major or minor PHP release. For example, if enabled at the time of writing (May 2022) 
that will be `8.2`.

* *Required*: `No`
* *Type*: `Boolean`
* *Default*: `false`
* *Example*: `true` for including upcoming new major or minor releases

## Output

The action comes with 7 outputs, most importantly `version` which contains a JSON list with versions to be used in
follow up steps:

```json
["7.3","7.4","8.0","8.1","8.2"]
```

And the `highest` and `lowest` outputs that provide the highest PHP version (`8.1` in the `version` output example)
and the lowest PHP version (`7.3` in the `version` output example) from the `version` list. The 4rth output is 
`upcoming` and will be populated with the upcoming but unreleased next minor or major version of PHP.

On top of that this action will also give you 3 lists of extensions. The extensions from `require` in 
`requiredExtensions`, dev extensions in `requiredDevExtensions`, and a combined list in `extensions`.

## Example

This example will get the supported PHP version from `composer.json` and run tests of all those version:

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

## License ##

Copyright 2022 [Cees-Jan Kiewiet](http://wyrihaximus.net/)

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
