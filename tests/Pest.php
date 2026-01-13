<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Architecture Expectations
|--------------------------------------------------------------------------
|
| Custom Pest expectations for enforcing Laravel architecture patterns.
| Each expectation provides AI-friendly error messages with fix instructions.
|
*/

require __DIR__.'/Arch/Expectations/ToOnlyHaveCruddyMethods.php';
require __DIR__.'/Arch/Expectations/ToBeValidAction.php';
require __DIR__.'/Arch/Expectations/ToUseActionsInControllers.php';
require __DIR__.'/Arch/Expectations/ToBeValidFormRequest.php';
require __DIR__.'/Arch/Expectations/ToBeValidModel.php';
require __DIR__.'/Arch/Expectations/ToBeValidJob.php';
require __DIR__.'/Arch/Expectations/ToBeValidEvent.php';
require __DIR__.'/Arch/Expectations/ToBeValidListener.php';
require __DIR__.'/Arch/Expectations/ToBeValidMiddleware.php';
require __DIR__.'/Arch/Expectations/ToBeValidPolicy.php';
require __DIR__.'/Arch/Expectations/ToBeValidServiceProvider.php';
require __DIR__.'/Arch/Expectations/ToBeValidCommand.php';
require __DIR__.'/Arch/Expectations/ToBeValidResource.php';
require __DIR__.'/Arch/Expectations/ToBeValidObserver.php';
require __DIR__.'/Arch/Expectations/ToNotUseVolt.php';

/*
|--------------------------------------------------------------------------
| Architecture Presets
|--------------------------------------------------------------------------
|
| Custom Pest presets that bundle architecture expectations by Laravel pattern.
|
*/

require __DIR__.'/Arch/Presets/Cruddy.php';
require __DIR__.'/Arch/Presets/Actions.php';
require __DIR__.'/Arch/Presets/FormRequests.php';
require __DIR__.'/Arch/Presets/Models.php';
require __DIR__.'/Arch/Presets/Jobs.php';
require __DIR__.'/Arch/Presets/Events.php';
require __DIR__.'/Arch/Presets/Middleware.php';
require __DIR__.'/Arch/Presets/Policies.php';
require __DIR__.'/Arch/Presets/ServiceProviders.php';
require __DIR__.'/Arch/Presets/Commands.php';
require __DIR__.'/Arch/Presets/Resources.php';
require __DIR__.'/Arch/Presets/Observers.php';
require __DIR__.'/Arch/Presets/NoVolt.php';

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
