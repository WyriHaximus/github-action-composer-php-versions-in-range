<?php declare(strict_types = 1);


(static function (): void {
    $versionData = json_decode(
        file_get_contents(
            __DIR__ . DIRECTORY_SEPARATOR . 'versions.json',
        ),
        true,
    );

    $highest = $versionData['released'][(count($versionData['released']) - 1)];
    $nightly = $highest;
    $highestUpcoming = $highest;

    if (count($versionData['nightly']) > 0) {
        $nightly = $versionData['nightly'][count($versionData['nightly']) - 1];
    }

    if (count($versionData['upcoming']) > 0) {
        $nightly = $versionData['upcoming'][count($versionData['upcoming']) - 1];
    }

    file_put_contents(getenv('GITHUB_OUTPUT'), 'highestUpcoming=' . $highestUpcoming . "\n", FILE_APPEND);
    file_put_contents(getenv('GITHUB_OUTPUT'), 'nightly=' . $nightly . "\n", FILE_APPEND);
    file_put_contents(getenv('GITHUB_OUTPUT'), 'highest=' . $highest . "\n", FILE_APPEND);
})();
