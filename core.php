<?php

/**
 * Plugin Name:       11Sight – Video, Audio calls and text chat
 * Plugin URI:        https://11sight.com/11sight-wordpress-plugin/
 * Description:       Handle the basics with this plugin.
 * Version:           1.4
 * Requires at least: 5.3
 * Requires PHP:      7.2
 * Author:            11Sight
 * Author URI:        https://11sight.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       11sight-video-audio-calls-and-text-chat
 */

function iisight_settings_init()
{
    // register a new setting for "iisight" page
    register_setting('iisight', 'iisight_options');
    register_setting('iisight', 'iisight_button_options');

    // register a new section in the "iisight" page
    add_settings_section(
        'iisight_section_developers',
        __('', 'iisight'),
        'iisight_section_developers_cb', 'iisight');

    // register a new field in the "iisight_section_developers" section, inside the "iisight" page
    add_settings_field(
        'iisight_field_html', // as of WP 4.6 this value is used only internally
        // use $args' label_for to populate the id inside the callback
        __('', 'iisight'),
        'iisight_field_html_cb',
        'iisight',
        'iisight_section_developers',
        [
            'label_for' => 'iisight_field_html',
            'class' => 'iisight_row',
        ]
    );
}

/**
 * register our iisight_settings_init to the admin_init action hook
 */
add_action('admin_init', 'iisight_settings_init');

/**
 * custom option and settings:
 * callback functions
 */

// developers section cb

// section callbacks can accept an $args parameter, which is an array.
// $args have the following keys defined: title, id, callback.
// the values are defined at the add_settings_section() function.
function iisight_section_developers_cb($args)
{


}


// field callbacks can accept an $args parameter, which is an array.
// $args is defined at the add_settings_field() function.
// wordpress has magic interaction with the following keys: label_for, class.
// the "label_for" key value is used for the "for" attribute of the <label>.
// the "class" key value is used for the "class" attribute of the <tr> containing the field.
// you can add custom key value pairs to be used inside your callbacks.
function iisight_field_html_cb($args)
{
    // get the value of the setting we've registered with register_setting()

}

/**
 * top level menu
 */
function iisight_options_page()
{
    // add top level menu page
    add_menu_page(
        '11Sight',
        '11Sight Options',
        'manage_options',
        'iisight',
        'iisight_options_page_html',
        plugins_url( '11Sight-icon.png', __FILE__ )
    );
}

/**
 * register our iisight_options_page to the admin_menu action hook
 */
add_action('admin_menu', 'iisight_options_page');

/**
 * top level menu:
 * callback functions
 */
function iisight_options_page_html()
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // add error/update messages

    // check if the user have submitted the settings
    // wordpress will add the "settings-updated" $_GET parameter to the url
    if (isset($_GET['settings-updated'])) {
        // add settings saved message with the class of "updated" --> Commented bcs of the flow of
        //add_settings_error('iisight_messages', 'iisight_message', __('Settings Saved', 'iisight'), 'updated');
    }

    // show error/update messages
    settings_errors('iisight_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html("Welcome to 11Sight wordpress plug-in!"); ?></h1>
        <form action="options.php" autocomplete="off" method="post">


            <?php
            $options = get_option('iisight_options');


            function blankRequest()
            {
                $response = wp_remote_get('https://app.11sight.com/user/blank');
                $auth_token = $response['headers']['x-Auth-token'];
                $cookie = explode(";", $response['headers']['set-cookie']);
                return array('auth_token' => $auth_token, 'cookie' => $cookie[0]);
            }

            function registerAppRequest()
            {
                $params = array('app' => 'ios', 'app_version' => '1.4', 'device_info' => json_encode(array('device' => 'wp_plugin', 'model' => 'wp_plugin', 'idfv' => '1000', 'os' => 'wp_plugin', 'os_version' => '1.4')));
                $appinstallId_response = wp_remote_post('https://app.11sight.com/register_app.json', array(
                        'body' => $params
                    )
                );
                if (is_wp_error($appinstallId_response)) {
                     // There was an error making the request
                    if (is_array($appinstallId_response)) {
                        $error_message = $appinstallId_response->get_error_message();    
                    } else {
                        $error_message = "Couldn't connect to the server, something went wrong!";
                    }
                    
                    echo $error_message . "<br>";
                    return "";
                }
                if (!is_array($appinstallId_response)) {
                    echo  "Something went wrong! <br>";
                    return "";
                }
                $appinstallId = json_decode($appinstallId_response['body'])->app_install_id;
                $options['app_install_id'] = $appinstallId;
                update_option('iisight_options', $options);

                return $appinstallId;
            }

            function signInRequest($email, $pass)
            {
                $options = get_option('iisight_options');
                if (!array_key_exists('app_install_id',$options)) {
                    $appinstallId = registerAppRequest();
                    if ($appinstallId == "") {
                        return "";
                    }
                } else {
                    $appinstallId = $options['app_install_id'];
                }

                $response = blankRequest();

                $auth_token = $response['auth_token'];
                $cookies = $response['cookie'];
                if ($appinstallId) {
                    $json = json_encode(array(
                        'app_install_id' => $appinstallId,
                        'user' => array(
                            'email' => $email,
                            'password' => $pass
                        )
                    ));
                    $response_sign_in = wp_remote_post('https://app.11sight.com/user/sign_in.json', array(
                        'headers' => array(
                            'Cookie' => $cookies,
                            'X-CSRF-Token' => $auth_token,
                            'Content-Type' => 'application/json; charset=utf-8',
                        ),
                        'body' => $json,
                    ));

                    if (!is_wp_error($response_sign_in)) {
                        // The request went through successfully, check the response code against
                        // what we're expecting
                        if (201 == wp_remote_retrieve_response_code($response_sign_in)) {
                            // Do something with the response
                            //ignore body nothing useful for plugin
                            //$body = wp_remote_retrieve_body( $response );
                            $cookies = explode(";", $response_sign_in['headers']['set-cookie']);
                            $options = get_option('iisight_options');
                            $options['login_cookie'] = $cookies[0];
                            update_option('iisight_options', $options);
                            return $cookies;

                        } else {
                            echo "<p class='submit' style='font-weight: bold;color: red;'>".json_decode($response_sign_in['body'], true)['error']."</p>";
                            return "";      
                            // The response code was not what we were expecting, record the message
                        }
                    } else {
                        // There was an error making the request
                        if (is_array($response_sign_in)) {
                            $error_message = $response_sign_in->get_error_message();    
                        } else {
                            $error_message = "Couldn't connect to the server to login, something went wrong!";
                        }
                        
                        echo $error_message . "<br>";
                        return "";
                    }
                } else {
                    //there is no app install id!
                    echo "Something went wrong!";
                }
            }

            function getButtons($login_cookie)
            {
                $response = blankRequest();
                $auth_token = $response['auth_token'];
                $button_request_response = wp_remote_get('https://app.11sight.com/mobile/button_styles.json', array(
                    'headers' => array(
                        'X-CSRF-Token' => $auth_token,
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Cookie' => $login_cookie,
                    ),
                ));
                return $button_request_response;
            }

            function saveButtonResult($button_request_response)
            {
                if (401 == wp_remote_retrieve_response_code($button_request_response)) {
                    $error_message = json_decode($button_request_response['body'])->error;
                    echo $error_message . '<br><button type="submit" class="btn btn-primary">Logout</button>';
                } else if (200 == wp_remote_retrieve_response_code($button_request_response)) {
                    $buttons = json_decode($button_request_response['body']);
                    $options = get_option('iisight_options');
                    $options['buttons'] = $buttons->data;
                    update_option('iisight_options', $options);
                    //echo "<pre>";
                    //print_r($buttons->data);
                    //echo "</pre>";
                    ?>
                    <p class="submit">
                        <input type="submit" name="submit" id="refresh-button" class="button button-primary" value="Refresh button list">
                    </p>
                    <style>
                        .iisight-button-table {
                            font-family: arial, sans-serif;
                            border-collapse: collapse;
                            width: 80%;
                        }

                        .iisight-td, .iisight-th {
                            border: 1px solid #dddddd;
                            text-align: left;
                            padding: 8px;
                        }

                        tr:nth-child(even) {
                            background-color: #dddddd;
                        }
                        .iisight-login-area {
                            display: none;
                        }
                    </style>
                    <table class="iisight-button-table">

                        <tr>
                            <th class="iisight-th">Name</th>
                            <th class="iisight-th">Icon</th>
                            <th class="iisight-th">Position</th>
                            <th class="iisight-th">Tracker</th>
                            <th class="iisight-th">Expandable?</th>
                            <th class="iisight-th">Shortcode</th>
                            <th class="iisight-th">Active on all pages?</th>

                        </tr>

                        <?php foreach ($buttons->data as $button) { ?>

                            <tr>
                                <td class="iisight-td"><?php echo $button->name; ?></td>

                                <td class="iisight-td"><?php echo $button->btn_style->label; ?></td>

                                <td class="iisight-td"><?php echo $button->btn_position->label; ?></td>

                                <td class="intellacall-td"><?php echo ($button->tracker) ? $button->tracker->label : ""; ?></td>

                                <td class="iisight-td"><?php echo ($button->is_expandable) ? "Yes" : "No"; ?></td>

                                <td class="iisight-td"><code>[11sight_button id="<?php echo $button->id; ?>"]</code></td>
                                <td class="iisight-td">
                                    <?php if ($button->btn_position->value == 1) {
                                        echo "Not available for inline buttons";
                                    } else {
                                       if ($options['active_button'] == $button->id) {
                                            echo "<button type='submit' value='".$button->id."' class='deactivate-button' name='iisight_options[active_button]'>Deactivate </button>";
                                        } else {
                                            echo "<button type='submit' value='".$button->id."' class='activate-button' name='iisight_options[active_button]'>Activate </button>";
                                        }
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                    <p class="submit">To activate a button on all pages click <span style="font-weight: bold;">"Activate"</span>. Only one button can be active at a time.<br>
                        To embed a button on a specific location, simply copy the short code and paste it on the page.</p>
                    <a target="_blank" href="https://app.11sight.com">Click here to edit your buttons</a>
                    <p>Note: Editing an active button may result in errors.</p>

                    <button type="submit" class="btn btn-primary">Logout</button>
                    <p>Logging out will deactivate all your active buttons.</p>
                    <script>
                        (function($) {
                            $( ".activate-button" ).click(function(e) {
                                var button_id = $(this).val();
                                $("form").append('<input type="hidden" value="<?php echo $options["app_install_id"] ?>" name="iisight_options[app_install_id]">');
                                $("form").append('<input type="hidden" value="<?php echo $options["login_cookie"] ?>" name="iisight_options[login_cookie]">');
                                $("form").append('<input type="hidden" value="<?php echo $options["buttons"] ?>" name="iisight_options[buttons]">');
                                $("form").append('<input type="hidden" value='+button_id+' name="iisight_options[active_button]">');
                            });

                            $( ".deactivate-button" ).click(function(e) {
                                $("form").append('<input type="hidden" value="<?php echo $options["app_install_id"] ?>" name="iisight_options[app_install_id]">');
                                $("form").append('<input type="hidden" value="<?php echo $options["login_cookie"] ?>" name="iisight_options[login_cookie]">');
                                $("form").append('<input type="hidden" value="<?php echo $options["buttons"] ?>" name="iisight_options[buttons]">');
                                $("form").append('<input type="hidden" value="0" name="iisight_options[active_button]">');
                            });

                            $("#refresh-button").click(function (e) {
                                e.preventDefault();
                                location.reload();
                            });
                        })(jQuery);

                    </script>

                    <?php
                } else {
                    //something else happened
                    if (is_array($button_request_response)) {
                        $error_message = $button_request_response->get_error_message();    
                    } else {
                        $error_message = "Couldn't retrieve button details, please try again later.";
                    }
                    
                    echo $error_message . "<br>";
                }
            }
            if (!isset($options['login_cookie']) || ! isset($options['app_install_id'])) {
                //show login form
                ?>
                <div class="iisight-login-area">
                    <p id="iisight-login-subtext"><?php esc_html_e('To continue, please login to your 11Sight account:', 'iisight'); ?></p>

                    <label for="email"><?php esc_html_e('Email: ', 'iisight'); ?></label>
                    <input style="margin-left: 25px;" type="text"  placeholder="11Sight email" value="<?php echo isset($options['email']) ? $options['email'] : (''); ?>" autocomplete="off" id="email" name="iisight_options[email]"><br><br>
                    <label for="password"><?php esc_html_e('Password: ', 'iisight'); ?></label>
                    <input type="password" id="pass" placeholder="11Sight password" value="<?php echo isset($options['pass']) ? $options['pass'] : (''); ?>" name="iisight_options[pass]"><br><br>
                    <?php
                    if (is_array($options)) {
                        if (array_key_exists('email',$options) && array_key_exists('pass',$options)) {
                            if ($options['email'] != null && $options['pass'] != null) {
                                //password and email removing after loged-in to the system
                                $login_cookie = signInRequest($options['email'], $options['pass']);
                                if ($login_cookie != "") {
                                    //If login successful get user's buttons from server and show in dashboard
                                    $buttons = getButtons($login_cookie);
                                    //save buttons for future usage and show to the db
                                    saveButtonResult($buttons);
                                }
                            }
                        }
                    }
                    
                    submit_button('Login');
                    ?>
                    <a style="cursor: pointer;" href="https://app.11sight.com/user/account/password/new" target="_blank" >Forgot your password?</a><br><br>
                    <a style="cursor: pointer;" href="https://11sight.com/wordpress-pricing/" target="_blank">Don't have an account? Click here to create your account with a 1 month free trial!</a>
                </div>
                <?php
            } else {
                //show button list
            }

            // output security fields for the registered setting "iisight"
            settings_fields('iisight');

            //get updated options
            $options = get_option('iisight_options');
            // check if login_cookie and app install id exist in the system, if so get the buttons and save to the system for future usage
            if (is_array($options)) {
                if (array_key_exists('login_cookie',$options) && array_key_exists('app_install_id',$options)) {
                    $cookie = $options['login_cookie'];
                    $button_request_response = getButtons($cookie);
                    saveButtonResult($button_request_response);
                }    
            }
            
            ?>
        </form>

    </div>
    <?php
}

function iisight_button_shortcode( $attributes ) {

    $options = get_option('iisight_options');
    $buttons = $options["buttons"];
    foreach ($buttons as $button) {
        if ($button->id == $attributes["id"]) {
            return $button->embed_codes[0]->code;
        }
    }
}

function iisight_shortcodes_init()
{
    add_shortcode('11sight_button', 'iisight_button_shortcode');
}

add_action('init', 'iisight_shortcodes_init');


function hook_iisight_js()
{
    ?>
    <script>!function (d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) return;
            js = d.createElement(s);
            js.id = id;
            js.src = "https://app.11sight.com/button_loader.js";
            fjs.parentNode.insertBefore(js, fjs);
        }(document, "script", "elevensight-11buttonjs"); </script>
    <?php
}

add_action('wp_head', 'hook_iisight_js');


function append_iisight_html_to_body()
{
    $options = get_option('iisight_options');
    $button_id = $options['active_button'];
    if ($button_id != 0) {
        $buttons = $options['buttons'];
        foreach ($buttons as $button) {
            if ($button->id == $button_id && $button->btn_position->value != 1) {
                echo $button->embed_codes[0]->code;
                break;
            }
        }
    }
}

add_action('wp_footer', 'append_iisight_html_to_body');