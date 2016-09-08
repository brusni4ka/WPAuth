<?php

/*
Plugin Name: Profile Update/ DIPLOMA
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
Author: kate
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/
require_once 'helpers.php';
require('protectimus-php-sdk-master/src/bootstrap.php');
use Exception\ProtectimusApiException;

class  Profile
{

    protected static $instance = null;
    // Some plugin info
    protected $name = 'Two-Factor Authentication';

    // Parsed settings
    private $settings = null;

    // Is API ready, should plugin act?
    protected $ready = false;

    // Auth API
    protected $api = null;
    protected $api_key = null;
    protected $api_url = null;
    protected $api_username = null;


    // Interface keys
    protected $settings_page = 'auth-user';
    protected $users_page = 'auth-user';

    // Data storage keys
    protected $settings_key = 'auth-user';
    protected $users_meta_key = 'auth_user';


    // Settings field placeholders
    protected $settings_fields = array();

    protected $settings_field_defaults = array(
        'label' => null,
        'type' => 'text',
        'sanitizer' => 'sanitize_text_field',
        'section' => 'default',
        'class' => null,
    );

    // Default Auth data
    protected $user_defaults = array(
        'login' => null,
        'email' => null,
        'token_id' => null,
        'resource_id' => '100',
        'enable_own' => null,
        'force_by_admin' => null
    );


    public static function instance()
    {
        if (!is_a(self::$instance, __CLASS__)) {
            self::$instance = new self;
            self::$instance->setup();
        }
        return self::$instance;
    }

    public function setup()
    {

        $this->register_settings_fields();
        $this->prepare_api();



        // Plugin settings
        add_action('admin_init', array($this, 'action_admin_init'));
        add_action('admin_menu', array($this, 'action_admin_menu'));
        add_filter('plugin_action_links', array($this, 'filter_plugin_action_links'), 10, 2);

        add_action( 'delete_user', array($this, 'delete_auth_data') );


        if ( $this->ready ) {
            //Allow admin to add 2fa to users
            add_action('edit_user_profile', array($this, 'action_edit_user_profile'));
            add_action('edit_user_profile_update', array($this, 'action_edit_user_profile_update'));

            //user settings
            add_action('show_user_profile', array($this, 'action_show_user_profile'));
            add_action('personal_options_update', array($this, 'action_personal_options_update'));

            //modal
            add_action('admin_enqueue_scripts', array($this, 'action_admin_enqueue_scripts'));
            add_action('wp_ajax_' . $this->users_page, array($this, 'get_user_modal_via_ajax'));

            // Authentication
            add_filter('authenticate', array($this, 'authenticate_user'), 10, 3);

            // Disable XML-RPC
            if ($this->get_setting('disable_xmlrpc') == "true") {
                add_filter('xmlrpc_enabled', '__return_false');
            }
        }
    }


    protected function register_settings_fields()
    {
        $this->settings_fields = array(
            array(
                'name' => 'api_key_production',
                'label' => 'Production API Key',
                'type' => 'text',
                'sanitizer' => 'alphanumeric',
            ),
            array(
                'name' => 'disable_xmlrpc',
                'label' => __("Disable external apps that don't support Two-factor Authentication", 'authy'),
                'type' => 'checkbox',
                'sanitizer' => null,
            ),
        );
    }

    public function get_setting($key)
    {
        $value = false;

        if (is_null($this->settings) || !is_array($this->settings)) {
            $this->settings = get_option($this->settings_key);
            $this->settings = wp_parse_args($this->settings, array(
                'api_key_production' => '',
                'disable_xmlrpc' => "true",
            ));
        }

        if (isset($this->settings[$key])) {
            $value = $this->settings[$key];
        }

        return $value;
    }

    protected function prepare_api()
    {

        $this->api_url = 'https://api.protectimus.com/';
        $this->api_key = $this->get_setting('api_key_production');
        $this->api_username = get_option('admin_email');

        // Only prepare the API endpoint if we have all information needed.
        if ($this->api_key) {
            $this->ready = true;
        }

        // Instantiate the API class
        try{
            $this->api = new ProtectimusApi($this->api_username, $this->api_key, $this->api_url);
           //just to be sure that's everything work properly
            $this->api->getBalance();

        }catch (ProtectimusApiException $e){
            $this->ready = false;
        }


    }


    //PLAGIN SETTINGS

    public function action_admin_init()
    {
        register_setting($this->settings_page, $this->settings_key, array($this, 'validate_plugin_settings'));
    }

    public function validate_plugin_settings($settings)
    {


        check_admin_referer($this->settings_page . '-options');

        $settings_validated = array();

        foreach ($this->settings_fields as $field) {
            $field = wp_parse_args($field, $this->settings_field_defaults);


            if (!isset($settings[$field['name']]) && $field['type'] != 'checkbox') {
                continue;
            }

            if ($field['type'] === "text" && $field['sanitizer'] === 'alphanumeric') {
                $value = preg_replace('#[^a-z0-9]#i', '', $settings[$field['name']]);

            } elseif ($field['type'] == "checkbox") {
                $value = $settings[$field['name']];

                if ($value != "true") {
                    $value = "false";
                }
            } else {
                $value = sanitize_text_field($settings[$field['name']]);
            }

            if (isset($value) && !empty($value)) {
                $settings_validated[$field['name']] = $value;
            }
        }
        return $settings_validated;
    }

    public function action_admin_menu()
    {
        $show_settings = false;
        $can_admin_network = is_plugin_active_for_network('TokenPage/Token-page.php') && current_user_can('network_admin');

        if ($can_admin_network || current_user_can('manage_options')) {
            $show_settings = true;
        }

        if ($show_settings) {
            add_options_page($this->name, 'auth-user', 'manage_options', $this->settings_page, array($this, 'plugin_settings_page'));
            add_settings_section('default', '', array($this, 'register_settings_page_sections'), $this->settings_page);
        }
    }

    public function plugin_settings_page()
    {
        $plugin_name = esc_html(get_admin_page_title());
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo esc_attr($plugin_name); ?></h2>

            <?php if ($this->ready === true) :
                $response = $this->api->getBalance();
                $balance = $response->response->balance;
                ?>
                <p> Enter your Protectimus API key.</p>

            <?php else : ?>
                <p><?php 'To use the 2FA service, you must register an account at <a href="%1$s"><strong>https://www.authy.com </strong></a> and create an application for access to the Authy API. ' ?></p>

                <p><?php "Once you've created your application, enter your API keys in the fields below." ?></p>
                <p><?php 'Until your API keys are entered, the %s plugin cannot function.' ?></p>
            <?php endif; ?>

            <form action="options.php" method="post">
                <?php settings_fields($this->settings_page); ?>
                <?php do_settings_sections($this->settings_page); ?>

                <p class="submit">
                    <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>"
                           class="button-primary">
                </p>
            </form>

            <?php if (!empty($response)) { ?>
                <h2><?php 'Application Details'; ?></h2>

                <table class='widefat' style="width:400px;">
                    <tbody>
                    <tr>
                        <th>Your Balance</th>
                        <td><?= $balance; ?></td>
                    </tr>
                    </tbody>
                </table>
            <?php } ?>
        </div>
        <?php
    }

    public function register_settings_page_sections()
    {
        add_settings_field('api_key_production', 'Protectimus Production API Key', array($this, 'add_settings_api_key'), $this->settings_page, 'default');
        add_settings_field('disable_xmlrpc', "Disable external apps that don't support Two-factor Authentication", array($this, 'add_settings_disable_xmlrpc'), $this->settings_page, 'default');

    }

    public function add_settings_api_key()
    {
        $value = $this->get_setting('api_key_production');
        ?>
        <input type="text" name="<?php echo esc_attr($this->settings_key); ?>[api_key_production]"
               class="regular-text" id="field-api_key_production" value="<?php echo esc_attr($value); ?>"/>
        <?php
    }

    public function add_settings_disable_xmlrpc()
    {
        if ($this->get_setting('disable_xmlrpc') == "false") {
            $value = false;
        } else {
            $value = true;
        }

        ?>
        <label for='<?php echo esc_attr($this->settings_key); ?>[disable_xmlrpc]'>
            <input name="<?php echo esc_attr($this->settings_key); ?>[disable_xmlrpc]" type="checkbox"
                   value="true" <?php if ($value) echo 'checked="checked"'; ?> >
            <span
                style='color: #bc0b0b;'><?php _e('Ensure Two-factor authentication is always respected.', 'authy'); ?></span>
        </label>
        <p class='description'><?php _e("WordPress mobile app's don't support Two-Factor authentication. If you disable this option you will be able to use the apps but it will bypass Two-Factor Authentication.", 'authy'); ?></p>
        <?php
    }

    public function filter_plugin_action_links($links, $plugin_file)
    {
        if (strpos($plugin_file, pathinfo(__FILE__, PATHINFO_FILENAME)) !== false) {
            $links['settings'] = '<a href="options-general.php?page=' . $this->settings_page . '">' . __('Settings') . '</a>';
        }

        return $links;
    }

    //USER SETTINGS

    public function action_edit_user_profile($user)
    {
        if (!current_user_can('administrator')) {
            return;
        }
        $meta = get_user_meta($user->ID, $this->users_meta_key, true);
        if (empty($meta)) {
            $meta = array();
        }
        //var_dump($meta);
        ?>
        <h3>Two-factor Authentication</h3>

        <table class="form-table">
            <?php
            checkbox_for_admin_disable_auth($this->users_meta_key, $meta);
            ?>
        </table>
        <?php
    }

    public function action_edit_user_profile_update($user_id)
    {

        $data = $_POST[$this->users_meta_key];
        $auth_data = $this->get_auth_data($user_id);
        $enable_auth = false;
        if (isset($_POST["_{$this->users_meta_key}_wpnonce"]) &&
            wp_verify_nonce($_POST["_{$this->users_meta_key}_wpnonce"], $this->users_meta_key . '_force_by_admin')
        ) {
            $enable_auth = $data['force_by_admin'] = !empty($data['force_by_admin']) ?: false;
        }

        if ($enable_auth) {
            if (!$this->is_auth_user($user_id)) {
                $data['token_id'] = $this->create_token($auth_data);
                update_user_meta($user_id, $this->users_meta_key, $data);
             }
        } else {
            if ($this->is_auth_user($user_id)) {
                 $this->delete_token($auth_data['token_id']);
             }
            delete_user_meta($user_id, $this->users_meta_key);
            return;
        }



    }

    public function action_show_user_profile($user)
    {
        //delete_user_meta($user->ID, $this->users_meta_key, false);

        $meta = get_user_meta($user->ID, $this->users_meta_key, true);
        if (empty($meta)) {
            $meta = array();
        }
        //var_dump($meta);
        enable_form_on_profile($this->users_meta_key, $meta);
    }

    public function action_personal_options_update($user_id)
    {

        // Check if we have data to work with
        $auth_data = isset($_POST[$this->users_meta_key]) ? $_POST[$this->users_meta_key] : false;
        // Parse for nonce
        if (!is_array($auth_data) || !array_key_exists('nonce', $auth_data)) {
            return;
        }

        $is_disabling = wp_verify_nonce($auth_data['nonce'], $this->users_meta_key . 'enable_own') &&
            empty($auth_data['enable_own']);
        $is_enabling = wp_verify_nonce($auth_data['nonce'], $this->users_meta_key . 'enable_own') &&
            !empty($auth_data['enable_own']);
        // die(var_dump($is_disabling));

        $user_data = $this->get_auth_data($user_id);


        if ($is_disabling) {
            // Delete Auth usermeta if requested

            if ($this->is_auth_user($user_id)) {
                $this->delete_token($user_data['token_id']);
            }
            delete_user_meta($user_id, $this->users_meta_key);
            return;
        } else {

            if (!$this->is_auth_user($user_id)) {
                $data['token_id'] = $this->create_token($user_data);
                update_user_meta($user_id, $this->users_meta_key, $data);
            }
            //$this->register_auth_fields();
            //$this->register_auth_user($this->auth_fields);
            // $this->set_auth_data($new_data);
        }
    }


    public function action_admin_enqueue_scripts()
    {
        if (!$this->ready) {
            return;
        }

        global $current_screen;

        if ($current_screen->base === 'profile') {
            wp_enqueue_script('profile', plugins_url('assets/profile.js', __FILE__), array('jquery', 'thickbox'), 1.01, true);
            wp_localize_script('profile', __CLASS__, array(
                'ajax' => $this->get_ajax_url(),
                'th_text' => 'Two-Factor Authentication',
                'button_text' => 'Enable/Disable ',
            ));

            wp_enqueue_style('thickbox');
        }
    }

    public function get_user_modal_via_ajax()
    {
        // If nonce isn't set, bail
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], $this->users_meta_key . '_ajax')) {
            ?>
            <script type="text/javascript">self.parent.tb_remove();</script><?php
            exit;
        }

        // User data
        $user_id = get_current_user_id();
        $auth_data = $this->get_auth_data($user_id);
        $forced =  isset($auth_data['force_by_admin']);

        //die(var_dump($user_data));


        $errors = array();
        // $this->set_meta($user_id);

        $data = $_POST[$this->users_meta_key];

        //iframe head
//
        // $is_enabling = isset($_POST['nonce']) && wp_verify_nonce($data['nonce'], $this->users_meta_key . '_ajax_auth_enabled') && !$data['enable_own'];
        //$is_enabling = isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], $this->users_meta_key . '_ajax_auth_enabled');
        $is_disabling = 'disable' && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], $this->users_meta_key . '_ajax_auth_disable');

        // iframe body
        $this->ajax_head()
        ;?>

        <body <?php body_class('wp-admin wp-core-ui auth-user-modal'); ?>>
        <div class="wrap">

            <h2> Two-Factor Authentication</h2>
            <?php //var_dump($auth_data) ?>
            <form action="<?php echo esc_url($this->get_ajax_url()); ?>" method="post">
                <?php

                if($forced){
                    render_force_auth();
                    exit();
                }


                //If OTP was sent
                if ($_POST['auth_token']) {
                    $response = $this->api->authenticateToken($auth_data['resource_id'], $auth_data['token_id'], $_POST['auth_token'], "192.168.15.1");
                    $result = $response->response->result;
                    if ($result) {
                        $this->clear_auth_meta($user_id);
                        // die(var_dump(get_user_meta($user_id,$this->users_meta_key)));
                        $this->delete_token($auth_data['token_id']);
                        render_confirmation_authy_disabled();
                        exit();
                    }
                }


                //if user choose "disable 2fa"
                if ($is_disabling) {
                    //sending mail
                    $this->api->prepareAuthentication($auth_data['resource_id'], $auth_data['token_id'], null, null);
                    render_otp_page($auth_data);
                    exit();
                }

                if ($this->is_auth_user($user_id)) {
                    render_disable_auth_on_modal($this->users_meta_key, $auth_data['login']);
                    exit();
                } else {
                    //Create_token
                    $data['token_id'] = $this->create_token($auth_data);
                    update_user_meta($user_id, $this->users_meta_key, $data);
                    form_enable_on_modal($this->users_meta_key, $auth_data['login']);
                    exit();
                }


                ?>
            </form>
        </div>
        </body>
        <?php
    }

    protected function get_ajax_url()
    {
        return add_query_arg(array(
            'action' => $this->users_page,
            'nonce' => wp_create_nonce($this->users_meta_key . '_ajax'),
        ), admin_url('admin-ajax.php'));
    }


    public function ajax_head() {
        ?>
        <head>
            <?php
            wp_print_scripts( array( 'jquery', 'authy' ) );
            wp_print_styles( array( 'colors', 'authy' ) );
            ?>
            <link href="https://www.authy.com/form.authy.min.css" media="screen" rel="stylesheet" type="text/css">
            <script src="https://www.authy.com/form.authy.min.js" type="text/javascript"></script>

            <style type="text/css">
                body {
                    width: 450px;
                    height: 300px;
                    overflow: hidden;
                    padding: 0 10px 10px 10px;
                }


                .message{
                    width: 75%;
                }


                .sadness,.angry, .happiness, .scare{
                    width: 175px;
                    right: 0;
                    bottom: 0;
                    position: absolute;
                }
                .happiness{
                    top: 0;
                }



                div.wrap {
                    width: 450px;
                    height: 380px;
                    overflow: hidden;
                }

                table th label {
                    font-size: 12px;
                }
            </style>
        </head>
        <?php
    }


    //AUTH DATA
    public function get_auth_data($user_id)
    {
        // Bail without a valid user ID
        if (!$user_id) {
            return $this->user_defaults;
        }

        $user_data = get_userdata($user_id);
        $this->user_defaults['login'] = isset($this->user_defaults['login']) ?
            $this->user_defaults['login'] : $user_data->data->user_login;
        $this->user_defaults['email'] = isset($this->user_defaults['email']) ?
            $this->user_defaults['email'] : $user_data->data->user_email;

        // Get meta, which holds all Auth data
        $meta_data = get_user_meta($user_id, $this->users_meta_key, true);
        if (!is_array($meta_data)) {
            $meta_data = array();
        }


        return wp_parse_args($meta_data,$this->user_defaults);
    }

    public function set_auth_data(array $params)
    {
        return update_user_meta(get_current_user_id(), $this->users_meta_key, $params);
    }

    function clear_auth_meta($user_id)
    {
        delete_user_meta($user_id, $this->users_meta_key);
    }

    public function is_auth_user($user_id)
    {
        $meta = get_user_meta($user_id, $this->users_meta_key)[0];
        $tokenId = $meta['token_id'];
        try {
            if ($tokenId && $this->api->getToken($tokenId)) {
                return true;
            };
        } catch (ProtectimusApiException $e) {
            return false;
        }
    }


    //TOKEN

    public function create_token($auth_data)
    {
        $response = $this->api->addSoftwareToken(null, null, "MAIL", $auth_data['email'], "Mail token", null, null, 6, null, null, null);
        $tokenId = $response->response->id;

        $this->api->assignTokenToResource($auth_data['resource_id'], null, $tokenId);
        return $tokenId;
    }

    public function delete_token($token_id)
    {
        $response = $this->api->deleteToken($token_id);
        return true;
    }


    // AUTHENTICATION

    public function authenticate_user($user = '', $username = '', $password = '')
    {
        // If XMLRPC_REQUEST is disabled stop
        if ((defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) || (defined('APP_REQUEST') && APP_REQUEST)) {
            return $user;
        }

        $remember_me = isset($_POST['rememberme']) ? $_POST['rememberme'] : null;

        if (!empty($username)) {
            return $this->verify_password_and_redirect($user, $username, $password, $_POST['redirect_to'], $remember_me);
        }

        $auth_token = isset($_POST['auth_token']) ? $_POST['auth_token'] : null;

        if ($auth_token) {
            $user = get_user_by('login', $_POST['username']);
            // This line prevents WordPress from setting the authentication cookie and display errors.
            remove_action('authenticate', 'wp_authenticate_username_password', 20);

            $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : null;
            return $this->login_with_2FA($user, $auth_token, $redirect_to, $remember_me);
        }

        var_dump($_POST);
    }

    public function verify_password_and_redirect($user, $username, $password, $redirect_to, $remember_me)
    {

        $userWP = get_user_by('login', $username);
        // Don't bother if WP can't provide a user object.
        if (!is_object($userWP) || !property_exists($userWP, 'ID')) {
            return $userWP;
        }

        if (!$this->is_auth_user($userWP->ID)) {
            return $user; // wordpress will continue authentication.
        }

        // from here we take care of the authentication.
        remove_action('authenticate', 'wp_authenticate_username_password', 20);

        $ret = wp_authenticate_username_password($user, $username, $password);
        if (is_wp_error($ret)) {
            return $ret; // there was an error
        }

        $user = $ret;
        $auth_data = $this->get_auth_data($user->ID);
        // Sending email
        $this->api->prepareAuthentication($auth_data['resource_id'], $auth_data['token_id'], null, null);
        $this->render_auth_token_page($user, $redirect_to, $remember_me); // Show the auth token page
        exit();
    }

    public function render_auth_token_page($user, $redirect, $remember_me)
    {
        $username = $user->user_login;
        $auth_data = $this->get_auth_data($user->ID);
        auth_token_form($username, $auth_data, $redirect, $remember_me);
    }

    public function login_with_2FA($user, $auth_token, $redirect_to, $remember_me)
    {
        $auth_data = $this->get_auth_data($user->ID);
        $response = $this->api->authenticateToken($auth_data['resource_id'], $auth_data['token_id'], $auth_token, "192.168.15.1");
        $result = $response->response->result;
        if ($result === true) {
            $remember_me = ($remember_me == 'forever') ? true : false;
            wp_set_auth_cookie($user->ID, $remember_me); // token was checked so go ahead.
            wp_safe_redirect($redirect_to);
            exit(); // redirect without returning anything.
        }
        return new WP_Error('authentication_failed', __('<strong>ERROR</strong> Authentication timed out. Please try again.', 'authy'));
    }


    //Delete auth data

    function delete_auth_data($user_id){
        if($this->is_auth_user($user_id)){
            $auth_data = $this->get_auth_data($user_id);
            $this->delete_token($auth_data['token_id']);
        }
    }




}

Profile::instance()->setup();







