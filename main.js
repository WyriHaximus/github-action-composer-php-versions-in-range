const semver = require('semver');
const fs = require('fs');
const composerJsonPath = (
    process.env.INPUT_WORKINGDIRECTORY.toString().length > 0 ?  (
        (process.env.INPUT_WORKINGDIRECTORY.endsWith('/')  ? process.env.INPUT_WORKINGDIRECTORY.slice(0, -1) : process.env.INPUT_WORKINGDIRECTORY) + '/'
    ) : ''
) + 'composer.json';

let composerJson = JSON.parse(fs.readFileSync(composerJsonPath));
let supportedVersionsRange = composerJson['require']['php'].toString().replaceAll('||', 'PIPEPIPEPLACEHOLDER').replaceAll('|', '||').replaceAll('PIPEPIPEPLACEHOLDER', '||');

let versions = [];
let upcomingVersion = '';

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
    '8.2',
    '8.3',
].forEach(function (version) {
    if (semver.satisfies(version + '.0', supportedVersionsRange)) {
        versions.push(version);
    }
});

if (process.env.INPUT_UPCOMINGRELEASES === 'true') {
    [
        '8.4'
    ].forEach(function (version) {
        if (semver.satisfies(version + '.0', supportedVersionsRange)) {
            versions.push(version);
            upcomingVersion = version;
        }
    });
}

console.log(`Versions found: ${JSON.stringify(versions)}`);
console.log(`Lowest version found: ${versions[0]}`);
console.log(`Highest version found: ${versions[versions.length - 1]}`);

fs.appendFileSync(process.env.GITHUB_OUTPUT, `version=${JSON.stringify(versions)}\n`);
fs.appendFileSync(process.env.GITHUB_OUTPUT, `lowest=${versions[0]}\n`);
fs.appendFileSync(process.env.GITHUB_OUTPUT, `highest=${versions[versions.length - 1]}\n`);
fs.appendFileSync(process.env.GITHUB_OUTPUT, `upcoming=${upcomingVersion}\n`);

// Extensions handling
function getExtensionsFrom(section, composer) {
    if (!composer.hasOwnProperty(section)) {
        return [];
    }

    return Object.entries(composer[section])
        .map(function (dependency) {
            return dependency[0];
        })
        .filter(function(dependency) {
            return dependency.toString().startsWith("ext-");
        })
        .map(function (dependency) {
            return dependency.toString().substring(4);
        });
}

let requiredExtensions = getExtensionsFrom('require', composerJson);
let requiredDevExtensions = getExtensionsFrom('require-dev', composerJson);
let allExtensions = [...requiredExtensions, ...requiredDevExtensions];

console.log(`All required extensions: ${JSON.stringify(allExtensions)}`);
console.log(`Required extensions: ${JSON.stringify(requiredExtensions)}`);
console.log(`Required dev extensions: ${JSON.stringify(requiredDevExtensions)}`);

fs.appendFileSync(process.env.GITHUB_OUTPUT, `extensions=${JSON.stringify(allExtensions)}\n`);
fs.appendFileSync(process.env.GITHUB_OUTPUT, `requiredExtensions=${JSON.stringify(requiredExtensions)}\n`);
fs.appendFileSync(process.env.GITHUB_OUTPUT, `requiredDevExtensions=${JSON.stringify(requiredDevExtensions)}\n`);
