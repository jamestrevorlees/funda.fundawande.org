<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // security check, don't load file outside WP
}


/**
 * FundaWande Login
 *
 * All functionality pertaining to the login functionality of Funda Wande.
 *
 * @package Core
 * @author Pango
 *
 * @since 1.0.00
 */

class FundaWande_Login {

    /**
     * Constructor
     */
    public function __construct() {
        //Display the login form, so it can be added to the login page template.
        add_action('show_login_form', array($this,'setup_login_form'));
        //Check for wrong login information and add 'login=failed' to URL
        add_action('wp_login_failed', array($this, 'custom_login_failed'));
        //Check for blank fields and add 'login=blank-field' to URL
        add_action('authenticate', array($this, 'custom_login_blank_field'));
    }
    //Set up login form options
    public function setup_login_form() {
        $args = array(
            'echo'           => true,
            //TODO: redirect to either the dashboard or modules page on login
            'redirect'       => site_url('login'),
            'form_id'        => 'form-login',
            'label_username' => __( 'ID Number' ),
            'label_password' => __( 'Password' ),
            'label_remember' => __( 'Remember Me' ),
            'label_log_in'   => __( 'Sign In' ),
            'id_username'    => 'user_login',
            'id_password'    => 'user_pass',
            'id_remember'    => 'rememberme',
            'id_submit'      => 'wp-submit',
            'remember'       => true,
            'value_username' => NULL,
            'value_remember' => true );

        return wp_login_form( $args );
    } // end setup_login_form();

    /**
     * Refreshes the page and adds 'login=failed' if the info added failed to authenticate the user
     *
     * @param $user
     *
     * @author jtame
     */
    public function custom_login_failed ($user) {
        //If the login language is set to english, add it to the URL after stripping out other GET variables
        if( strstr($_SERVER['HTTP_REFERER'], 'eng') ) {
            $referrer = strtok($_SERVER['HTTP_REFERER'], '?');
            $referrer .= '?login-lang=eng';
        }
        else {
            //No need to check for Xhosa, as that is the default language
            $referrer = strtok($_SERVER['HTTP_REFERER'], '?');
        }
        if (!empty($referrer) && !strstr($referrer, 'wp-login') && !strstr($referrer, 'wp-admin') && $user!=null )
        {
            if (!strstr($referrer, '?login=failed'))
            {
                if(!strstr($referrer, '?'))
                {
                    $referrer .= '?';
                }
                else
                {
                    $referrer .= '&';
                }

                wp_redirect($referrer . 'login=failed');
            }
            else
            {
                wp_redirect($referrer);
            }

            exit;
        }
    } // end custom_login_failed();

    /**
     * Refreshes the page and adds 'login=failed' to the URL if either login field is blank
     *
     * @author jtame
     */
    public function custom_login_blank_field( ) {
        //If the login language is set to english, add it to the URL after stripping out other GET variables
        if( strstr($_SERVER['HTTP_REFERER'], 'eng') ) {
            $referrer = strtok($_SERVER['HTTP_REFERER'], '?');
            $referrer .= '?login-lang=eng';
        }
        else {
            //No need to check for Xhosa, as that is the default language
            $referrer = strtok($_SERVER['HTTP_REFERER'], '?');
        }

        $error = false;
        if(empty($_POST) || $_POST['log'] == '' || $_POST['pwd'] == '')
        {
            $error = true;
        }

        if (!empty($referrer) && !strstr($referrer,'wp-login') && !strstr($referrer,'wp-admin') && $error)
        {
            if (!strstr($referrer, '?login=blank') )
            {
                if(!strstr($referrer, '?'))
                {
                    $referrer .= '?';
                }
                else
                {
                    $referrer .= '&';
                }

                wp_redirect( $referrer . 'login=blank' );
            }
            else
            {
                wp_redirect( $referrer );
            }

            exit;
        }
    } // end custom_login_blank_field();

} // end FundaWande_Login
