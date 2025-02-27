<?php

namespace App\Http\Controllers;

use App\Setting;
use App\SettingGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('allowed');
    }

    /**
     * @return View
     */
    public function index(): View
    {
        $settings = SettingGroup::with([
            'settings',
        ])->orderBy('order', 'ASC')->get();

        return view('settings.list')->with([
            'groups' => $settings,
        ]);
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse|View
     */
    public function edit(int $id)
    {
        $setting = Setting::find($id);
        //die("s: ".$setting->label);

        if ((bool) $setting->system === true) {
            return abort(404);
        }

        if (! is_null($setting)) {
            return view('settings.edit')->with([
                'setting' => $setting,
            ]);
        } else {
            $route = route('settings.list', []);

            return redirect($route)
            ->with([
                'error' => __('app.alert.error.not_exist'),
            ]);
        }
    }

    /**
     * @param Request $request
     * @param int $id
     *
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $setting = Setting::find($id);
        $user = $this->user();

        if (! is_null($setting)) {
            $data = Setting::getInput($request);

            $setting_value = null;

            if ($setting->type == 'image') {
                if ($request->hasFile('value')) {
                    $path = $request->file('value')->store('backgrounds');
                    $setting_value = $path;
                }
            } else {
                $setting_value = $data->value;
            }

            $user->settings()->detach($setting->id);
            $user->settings()->save($setting, ['uservalue' => $setting_value]);

            $route = route('settings.index', []);

            return redirect($route)
            ->with([
                'success' => __('app.alert.success.setting_updated'),
            ]);
        } else {
            $route = route('settings.index', []);

            return redirect($route)
            ->with([
                'error' => __('app.alert.error.not_exist'),
            ]);
        }
    }

    /**
     * @param int $id
     *
     * @return RedirectResponse
     */
    public function clear(int $id): RedirectResponse
    {
        $user = $this->user();
        $setting = Setting::find($id);
        if ((bool) $setting->system !== true) {
            $user->settings()->detach($setting->id);
            $user->settings()->save($setting, ['uservalue' => '']);
        }
        $route = route('settings.index', []);

        return redirect($route)
        ->with([
            'success' => __('app.alert.success.setting_updated'),
        ]);
    }

    public function search(Request $request)
    {
    }
}
