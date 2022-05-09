const semver = require('semver');
const fs = require('fs');

let composerJson = JSON.parse(fs.readFileSync('composer.json'));
let supportedVersionsRange = composerJson['require']['php'].toString().replace('||', 'PIPEPIPEPLACEHOLDER').replace('|', '||').replace('PIPEPIPEPLACEHOLDER', '||');

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
    '8.1',
].forEach(function (version) {
    if (semver.satisfies(version + '.0', supportedVersionsRange)) {
        versions.push(version);
    }
});

if (process.env.INPUT_UPCOMINGRELEASES === 'true') {
    [
        '8.2'
    ].forEach(function (version) {
        if (semver.satisfies(version + '.0', supportedVersionsRange)) {
            versions.push(version);
        }
    });
}

console.log(`Versions found: ${JSON.stringify(versions)}`);
console.log(`::set-output name=version::${JSON.stringify(versions)}`);
