const semver = require('semver');
const fs = require('fs');

let composerJson = JSON.parse(fs.readFileSync('composer.json'));
let supportedVersionsRange = composerJson['require']['php'].toString().replace('||', 'PIPEPIPEPLACEHOLDER').replace('|', '||').replace('PIPEPIPEPLACEHOLDER', '||');

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
            upcomingVersion = version;
        }
    });
}

console.log(`Versions found: ${JSON.stringify(versions)}`);
console.log(`Lowest version found: ${versions[0]}`);
console.log(`Highest version found: ${versions[versions.length - 1]}`);
console.log(`::set-output name=version::${JSON.stringify(versions)}`);
console.log(`::set-output name=lowest::${versions[0]}`);
console.log(`::set-output name=highest::${versions[versions.length - 1]}`);
console.log(`::set-output name=upcoming::${upcomingVersion}`);

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

console.log(`Required extensions: ${JSON.stringify(requiredExtensions)}`);
console.log(`Required dev extensions: ${JSON.stringify(requiredDevExtensions)}`);
console.log(`All extensions: ${JSON.stringify(allExtensions)}`);
console.log(`::set-output name=extensions::${JSON.stringify(allExtensions)}`);
console.log(`::set-output name=requiredExtensions::${JSON.stringify(requiredExtensions)}`);
console.log(`::set-output name=requiredDevExtensions::${JSON.stringify(requiredDevExtensions)}`);
