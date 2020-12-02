const semver = require('semver');
const fs = require('fs');

let composerJson = JSON.parse(fs.readFileSync('composer.json'));

let versions = [];

[
    '5.3',
    '5.4',
    '5.5',
    '5.6',
    '7.0',
    '7.1',
    '7.2',
    '7.3',
    '7.4',
    '8.0',
].forEach(function (version) {
    if (semver.satisfies(version + '.0', composerJson['require']['php'])) {
        versions.push(version);
    }
});

console.log(`::set-output name=version::${JSON.stringify(versions)}`);
