<?php

namespace Modules\Manufacturing\Http\Controllers;

use App\System;
use Composer\Semver\Comparator;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class InstallController extends Controller
{
    public function __construct()
    {
        $this->module_name = 'manufacturing';
        $this->appVersion = config('manufacturing.module_version');
        $this->module_display_name = 'Manufacturing';
    }

    /**
     * Install
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '512M');

        $this->installSettings();

        //Check if installed or not.
        $is_installed = System::getProperty($this->module_name.'_version');
        if (! empty($is_installed)) {
            abort(404);
        }

        $action_url = action([\Modules\Manufacturing\Http\Controllers\InstallController::class, 'install']);

        $intruction_type = 'uf';
        $action_type = 'install';
        $module_display_name = $this->module_display_name;
        return view('install.install-module')
            ->with(compact('action_url', 'intruction_type', 'action_type', 'module_display_name'));

    }

    public function install()
    {
        request()->validate(
            ['license_code' => 'required',
                'login_username' => 'required', ],
            ['license_code.required' => 'License code is required',
                'login_username.required' => 'Username is required', ]
        );

        $license_code = request()->license_code;
        $login_username = request()->login_username;
        $email = request()->email;
        $pid = config('manufacturing.pid');

        //Validate
        $response = pos_boot(url('/'), __DIR__, $license_code, $email, $login_username, $type = 1, $pid);
        if ( empty($response)) {
            return $response;
        }

        $is_installed = System::getProperty($this->module_name.'_version');
        if (! empty($is_installed)) {
            abort(404);
        }

        DB::statement('SET default_storage_engine=INNODB;');
        Artisan::call('module:migrate', ['module' => 'Manufacturing', '--force' => true]);
        System::addProperty($this->module_name.'_version', $this->appVersion);

        $output = ['success' => 1,
            'msg' => 'Manufacturing module installed succesfully',
        ];

        return redirect()
            ->action([\App\Http\Controllers\Install\ModulesController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Initialize all install functions
     */
    private function installSettings()
    {
        config(['app.debug' => true]);
        Artisan::call('config:clear');
    }

    //Updating
    public function update()
    {
        //Check if manufacturing_version is same as appVersion then 404
        //If appVersion > manufacturing_version - run update script.
        //Else there is some problem.
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            //DB::beginTransaction();

            ini_set('max_execution_time', 0);
            ini_set('memory_limit', '512M');

            $manufacturing_version = System::getProperty($this->module_name.'_version');

            if (Comparator::greaterThan($this->appVersion, $manufacturing_version)) {
                ini_set('max_execution_time', 0);
                ini_set('memory_limit', '512M');
                $this->installSettings();

                DB::statement('SET default_storage_engine=INNODB;');
                Artisan::call('module:migrate', ['module' => 'Manufacturing', '--force' => true]);

                System::setProperty($this->module_name.'_version', $this->appVersion);
            } else {
                abort(404);
            }

            //DB::commit();

            $output = ['success' => 1,
                'msg' => 'Manufacturing module updated Succesfully to version '.$this->appVersion.' !!',
            ];

            return redirect()->back()->with(['status' => $output]);
        } catch (Exception $e) {
            //DB::rollBack();
            exit($e->getMessage());
        }
    }

    /**
     * Uninstall
     *
     * @return Response
     */
    public function uninstall()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            System::removeProperty($this->module_name.'_version');

            $output = ['success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }
}
