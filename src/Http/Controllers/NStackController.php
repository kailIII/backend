<?php

namespace Nodes\Backend\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Class NStackController.
 */
class NStackController extends Controller
{
    /**
     * getConfig.
     *
     * This function can be overridden for changing configs in runtime
     *
     * @author Casper Rasmussen <cr@nodes.dk>
     * @return array|null
     */
    protected function getConfig()
    {
        return config('nodes.backend.nstack');
    }

    /**
     * guardUserPermissions.
     *
     * This function can be overridden for changing user permissions
     *
     * @author Casper Rasmussen <cr@nodes.dk>
     * @return void
     */
    protected function guardUserPermissions()
    {
        if (Gate::denies('backend-super-admin')) {
            abort(403);
        }
    }

    /**
     * NStack hook.
     *
     * @author Casper Rasmussen <cr@nodes.dk>
     * @return \Illuminate\Http\RedirectResponse
     */
    public function hook()
    {
        $this->guardUserPermissions();

        // Retrieve NStack config
        $config = $this->getConfig();

        $default = ! empty($config['defaults']['application']) ? $config['defaults']['application'] : 'default';

        $application = \Request::get('application', $default);

        $credentials = ! empty($config['credentials'][$application]) ? $config['credentials'][$application] : $config['credentials']; // For backwards compatibility

        // Validate NStack credentials
        if (empty($config['url']) || empty($credentials['appId']) || empty($credentials['masterKey']) ||
            empty($config['role'])
        ) {
            return redirect()->back()->with('error', 'NStack hook is not configured, setup keys in (config/nodes/backend/nstack.php)');
        }

        // Retrieve backend user
        $backendUser = backend_user();

        // Create message
        $encryptedMessage = $this->encrypt(json_encode([
            'appId'           => $credentials['appId'],
            'masterKey'       => $credentials['masterKey'],
            'role'            => $config['role'],
            'accessToAllApps' => isset($config['accessToAllApps']) ? filter_var($config['accessToAllApps'], FILTER_VALIDATE_BOOLEAN) : true, // For backwards compatibility
            'url'             => url('/'),
            'user'            => [
                'name'  => $backendUser->name,
                'email' => $backendUser->email,
            ],
        ]));

        return redirect()->away($config['url'].'?message='.urlencode($encryptedMessage));
    }

    /**
     * Encrypt message.
     *
     * @author Casper Rasmussen <cr@nodes.dk>
     * @param  string $text
     * @return string
     */
    private function encrypt($text)
    {
        return trim(base64_encode(mcrypt_encrypt(
            MCRYPT_RIJNDAEL_256,
            env('NODES_SALT'),
            $text,
            MCRYPT_MODE_ECB,
            mcrypt_create_iv(mcrypt_get_iv_size(
                MCRYPT_RIJNDAEL_256,
                MCRYPT_MODE_ECB
            ), MCRYPT_RAND)
        )));
    }
}
