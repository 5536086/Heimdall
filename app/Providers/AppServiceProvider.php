<?php

namespace App\Providers;

use App\Application;
use App\Jobs\ProcessApps;
use App\Jobs\UpdateApps;
use App\Setting;
use App\User;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! class_exists('ZipArchive')) {
            die('You are missing php-zip');
        }

        $this->createEnvFile();

        $this->setupDatabase();

        if (! is_file(public_path('storage/.gitignore'))) {
            Artisan::call('storage:link');
            \Session::put('current_user', null);
        }

        $applications = Application::all();

        if ($applications->count() <= 0) {
            ProcessApps::dispatch();
        }

        // User specific settings need to go here as session isn't available at this point in the app
        view()->composer('*', function ($view) {
            if (isset($_SERVER['HTTP_AUTHORIZATION']) && ! empty($_SERVER['HTTP_AUTHORIZATION'])) {
                list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) =
                explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
            }
            if (! \Auth::check()) {
                if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])
                        && ! empty($_SERVER['PHP_AUTH_USER']) && ! empty($_SERVER['PHP_AUTH_PW'])) {
                    $credentials = ['username' => $_SERVER['PHP_AUTH_USER'], 'password' => $_SERVER['PHP_AUTH_PW']];

                    if (\Auth::attempt($credentials, true)) {
                        // Authentication passed...
                        $user = \Auth::user();
                        //\Session::put('current_user', $user);
                        session(['current_user' => $user]);
                    }
                } elseif (isset($_SERVER['REMOTE_USER']) && ! empty($_SERVER['REMOTE_USER'])) {
                    $user = User::where('username', $_SERVER['REMOTE_USER'])->first();
                    if ($user) {
                        \Auth::login($user, true);
                        session(['current_user' => $user]);
                    }
                }
            }

            $alt_bg = '';
            if ($bg_image = Setting::fetch('background_image')) {
                $alt_bg = ' style="background-image: url(storage/'.$bg_image.')"';
            }

            $allusers = User::all();
            $current_user = User::currentUser();

            $lang = Setting::fetch('language');
            \App::setLocale($lang);

            $view->with('alt_bg', $alt_bg);
            $view->with('allusers', $allusers);
            $view->with('current_user', $current_user);
        });

        $this->app['view']->addNamespace('SupportedApps', app_path('SupportedApps'));

        if (env('FORCE_HTTPS') === true) {
            \URL::forceScheme('https');
        }

        if (env('APP_URL') != 'http://localhost') {
            \URL::forceRootUrl(env('APP_URL'));
        }
    }

    /**
     * Generate app key if missing and .env exists
     *
     * @return void
     */
    public function genKey()
    {
        if (is_file(base_path('.env'))) {
            if (empty(env('APP_KEY'))) {
                Artisan::call('key:generate', ['--force' => true, '--no-interaction' => true]);
            }
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        $this->app->singleton('settings', function () {
            return new Setting();
        });
    }

    /**
     * Check if database needs an update or do first time database setup
     *
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setupDatabase(): void
    {
        $db_type = config()->get('database.default');

        if ($db_type == 'sqlite' && ! is_file(database_path('app.sqlite'))) {
            touch(database_path('app.sqlite'));
        }

        if ($this->needsDBUpdate()) {
            Artisan::call('migrate', ['--path' => 'database/migrations', '--force' => true, '--seed' => true]);
            ProcessApps::dispatchSync();
            $this->updateApps();
        }
    }

    /**
     * @return void
     */
    public function createEnvFile(): void
    {
        if (!is_file(base_path('.env'))) {
            copy(base_path('.env.example'), base_path('.env'));
        }

        $this->genKey();
    }

    /**
     * @return bool
     */
    private function needsDBUpdate(): bool
    {
        if (!Schema::hasTable('settings')) {
            return true;
        }

        $db_version = Setting::_fetch('version');
        $app_version = config('app.version');

        return version_compare($app_version, $db_version) === 1;
    }

    /**
     * @return void
     */
    private function updateApps()
    {
        // This lock ensures that the job is not invoked multiple times.
        // In 5 minutes all app updates should be finished.
        $lock = Cache::lock('updateApps', 5*60);

        if ($lock->get()) {
            UpdateApps::dispatchAfterResponse();
        }
    }
}
