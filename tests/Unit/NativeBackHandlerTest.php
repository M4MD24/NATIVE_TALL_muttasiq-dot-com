<?php

test('native back action climbs the view tree instead of browser history', function () {
    $path = dirname(__DIR__, 2).'/resources/js/packages/alpine/hash-actions.js';

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);
    $nativeBackSection = str($contents)
        ->after('window.__nativeBackAction = () => {')
        ->before("window.Alpine.magic('hashAction'")
        ->toString();

    expect($contents)->toContain('window.__nativeBackAction');
    expect($nativeBackSection)->toContain('const parentView = getParentView(currentView);');
    expect($nativeBackSection)->toContain('applyHash(`#${parentView}`');
    expect($nativeBackSection)->toContain("return currentView === viewIndex.rootView ? 'exit' : false;");
    expect($nativeBackSection)->toContain("return 'exit';");
    expect($nativeBackSection)->not()->toContain('window.history.back();');
});
