<?php
/*
Plugin Name: The Captcha - Simple Google reCAPTCHA v3 Plugin
Description: Adds Google reCAPTCHA v3 verification to your site’s comment, login, and registration forms. Provides an admin settings page to configure the reCAPTCHA keys and choose which forms to protect. Automatically refreshes tokens to prevent expiration.
Version: 1.1
Author: Mirza
*/

// =============================
// 1. ADMIN SETTINGS PAGE
// =============================

/**
 * Register the plugin settings, sections, and fields.
 */
function grv3_register_settings() {
    register_setting( 'grv3_options_group', 'grv3_options' );

    add_settings_section(
        'grv3_main_section',
        'reCAPTCHA Settings',
        'grv3_section_text',
        'grv3'
    );

    add_settings_field(
        'grv3_site_key',
        'Site Key',
        'grv3_site_key_input',
        'grv3',
        'grv3_main_section'
    );

    add_settings_field(
        'grv3_secret_key',
        'Secret Key',
        'grv3_secret_key_input',
        'grv3',
        'grv3_main_section'
    );

    add_settings_field(
        'grv3_protect_comments',
        'Protect Comments Form',
        'grv3_protect_comments_input',
        'grv3',
        'grv3_main_section'
    );

    add_settings_field(
        'grv3_protect_login',
        'Protect Login Form',
        'grv3_protect_login_input',
        'grv3',
        'grv3_main_section'
    );

    add_settings_field(
        'grv3_protect_registration',
        'Protect Registration Form',
        'grv3_protect_registration_input',
        'grv3',
        'grv3_main_section'
    );
}
add_action( 'admin_init', 'grv3_register_settings' );

/**
 * Add a settings page under Settings in the admin menu.
 */
function grv3_add_admin_menu() {
    add_options_page(
        'Google reCAPTCHA v3 Settings',
        'reCAPTCHA v3',
        'manage_options',
        'grv3',
        'grv3_options_page'
    );
}
add_action( 'admin_menu', 'grv3_add_admin_menu' );

/**
 * Section text for the settings page.
 */
function grv3_section_text() {
    echo '<p>Enter your Google reCAPTCHA v3 credentials and choose which forms to protect.</p>';
}

/**
 * Site key field.
 */
function grv3_site_key_input() {
    $options = get_option( 'grv3_options' );
    $site_key = isset( $options['site_key'] ) ? esc_attr( $options['site_key'] ) : '';
    echo "<input id='grv3_site_key' name='grv3_options[site_key]' size='50' type='text' value='{$site_key}' />";
}

/**
 * Secret key field.
 */
function grv3_secret_key_input() {
    $options = get_option( 'grv3_options' );
    $secret_key = isset( $options['secret_key'] ) ? esc_attr( $options['secret_key'] ) : '';
    echo "<input id='grv3_secret_key' name='grv3_options[secret_key]' size='50' type='text' value='{$secret_key}' />";
}

/**
 * Checkbox for protecting the comments form.
 */
function grv3_protect_comments_input() {
    $options = get_option( 'grv3_options' );
    $checked = ! empty( $options['protect_comments'] ) ? 'checked' : '';
    echo "<input id='grv3_protect_comments' name='grv3_options[protect_comments]' type='checkbox' value='1' {$checked} />";
}

/**
 * Checkbox for protecting the login form.
 */
function grv3_protect_login_input() {
    $options = get_option( 'grv3_options' );
    $checked = ! empty( $options['protect_login'] ) ? 'checked' : '';
    echo "<input id='grv3_protect_login' name='grv3_options[protect_login]' type='checkbox' value='1' {$checked} />";
}

/**
 * Checkbox for protecting the registration form.
 */
function grv3_protect_registration_input() {
    $options = get_option( 'grv3_options' );
    $checked = ! empty( $options['protect_registration'] ) ? 'checked' : '';
    echo "<input id='grv3_protect_registration' name='grv3_options[protect_registration]' type='checkbox' value='1' {$checked} />";
}

/**
 * Render the options page.
 */
function grv3_options_page() {
    ?>
    <div class="wrap">
        <h1>Google reCAPTCHA v3 Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'grv3_options_group' ); ?>
            <?php do_settings_sections( 'grv3' ); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// =============================
// 2. ENQUEUE reCAPTCHA SCRIPT
// =============================

/**
 * Enqueue the reCAPTCHA script on the front end (for comments) if a site key is provided.
 */
function grv3_enqueue_recaptcha_script() {
    $options = get_option( 'grv3_options' );
    if ( ! empty( $options['site_key'] ) ) {
        wp_enqueue_script(
            'google-recaptcha',
            'https://www.google.com/recaptcha/api.js?render=' . esc_js( $options['site_key'] ),
            array(),
            null,
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'grv3_enqueue_recaptcha_script' );

/**
 * Enqueue the reCAPTCHA script on the login page if login protection is enabled.
 */
function grv3_enqueue_recaptcha_script_login() {
    $options = get_option( 'grv3_options' );
    if ( ! empty( $options['site_key'] ) && ! empty( $options['protect_login'] ) ) {
        wp_enqueue_script(
            'google-recaptcha',
            'https://www.google.com/recaptcha/api.js?render=' . esc_js( $options['site_key'] ),
            array(),
            null,
            true
        );
    }
}
add_action( 'login_enqueue_scripts', 'grv3_enqueue_recaptcha_script_login' );

// =============================
// 3. TOKEN INJECTION & REFRESH
// =============================

/**
 * Add a hidden reCAPTCHA field and JavaScript (with auto-refresh) to the comment form.
 */
function grv3_add_recaptcha_field_comments() {
    $options = get_option( 'grv3_options' );
    if ( empty( $options['protect_comments'] ) || empty( $options['site_key'] ) ) {
        return;
    }
    ?>
    <input type="hidden" id="g-recaptcha-response-comment" name="g-recaptcha-response">
    <script type="text/javascript">
        function refreshRecaptchaToken(action, elementId) {
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.execute('<?php echo esc_js( $options['site_key'] ); ?>', {action: action}).then(function(token) {
                    document.getElementById(elementId).value = token;
                });
            }
        }
        grecaptcha.ready(function() {
            refreshRecaptchaToken('comment', 'g-recaptcha-response-comment');
            // Refresh token every 110 seconds
            setInterval(function(){
                refreshRecaptchaToken('comment', 'g-recaptcha-response-comment');
            }, 110000);
        });
    </script>
    <?php
}
add_action( 'comment_form_after_fields', 'grv3_add_recaptcha_field_comments' );
add_action( 'comment_form_logged_in_after', 'grv3_add_recaptcha_field_comments' );

/**
 * Add a hidden reCAPTCHA field and JavaScript to the login form.
 */
function grv3_add_recaptcha_field_login() {
    $options = get_option( 'grv3_options' );
    if ( empty( $options['protect_login'] ) || empty( $options['site_key'] ) ) {
        return;
    }
    ?>
    <p>
        <input type="hidden" id="g-recaptcha-response-login" name="g-recaptcha-response-login">
    </p>
    <script type="text/javascript">
        function refreshRecaptchaTokenLogin(action, elementId) {
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.execute('<?php echo esc_js( $options['site_key'] ); ?>', {action: action}).then(function(token) {
                    document.getElementById(elementId).value = token;
                });
            }
        }
        grecaptcha.ready(function() {
            refreshRecaptchaTokenLogin('login', 'g-recaptcha-response-login');
            setInterval(function(){
                refreshRecaptchaTokenLogin('login', 'g-recaptcha-response-login');
            }, 110000);
        });
    </script>
    <?php
}
add_action( 'login_form', 'grv3_add_recaptcha_field_login' );

/**
 * Add a hidden reCAPTCHA field and JavaScript to the registration form.
 */
function grv3_add_recaptcha_field_registration() {
    $options = get_option( 'grv3_options' );
    if ( empty( $options['protect_registration'] ) || empty( $options['site_key'] ) ) {
        return;
    }
    ?>
    <p>
        <input type="hidden" id="g-recaptcha-response-registration" name="g-recaptcha-response-registration">
    </p>
    <script type="text/javascript">
        function refreshRecaptchaTokenRegistration(action, elementId) {
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.execute('<?php echo esc_js( $options['site_key'] ); ?>', {action: action}).then(function(token) {
                    document.getElementById(elementId).value = token;
                });
            }
        }
        grecaptcha.ready(function() {
            refreshRecaptchaTokenRegistration('register', 'g-recaptcha-response-registration');
            setInterval(function(){
                refreshRecaptchaTokenRegistration('register', 'g-recaptcha-response-registration');
            }, 110000);
        });
    </script>
    <?php
}
add_action( 'register_form', 'grv3_add_recaptcha_field_registration' );

// =============================
// 4. TOKEN VERIFICATION
// =============================

/**
 * Verify the reCAPTCHA token when a comment is submitted.
 */
function grv3_verify_recaptcha_on_comment( $commentdata ) {
    if ( empty( $_POST['g-recaptcha-response'] ) ) {
        wp_die( __( 'reCAPTCHA token not found. Please try again.', 'grv3' ) );
    }
    $options = get_option( 'grv3_options' );
    if ( empty( $options['secret_key'] ) ) {
        // If secret key is not set, bypass verification.
        return $commentdata;
    }
    $token = sanitize_text_field( $_POST['g-recaptcha-response'] );
    $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
        'body' => array(
            'secret'   => $options['secret_key'],
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        )
    ) );
    if ( is_wp_error( $response ) ) {
        wp_die( __( 'reCAPTCHA verification failed. Please try again.', 'grv3' ) );
    }
    $response_body = wp_remote_retrieve_body( $response );
    $result = json_decode( $response_body, true );
    if ( ! isset( $result['success'] ) || ! $result['success'] || $result['score'] < 0.5 ) {
        wp_die( __( 'reCAPTCHA verification failed, please try again.', 'grv3' ) );
    }
    return $commentdata;
}
add_filter( 'preprocess_comment', 'grv3_verify_recaptcha_on_comment' );

/**
 * Verify the reCAPTCHA token during login.
 */
function grv3_verify_recaptcha_on_login( $user, $username, $password ) {
    $options = get_option( 'grv3_options' );
    if ( empty( $options['protect_login'] ) || empty( $options['secret_key'] ) ) {
        return $user;
    }
    if ( isset( $_POST['g-recaptcha-response-login'] ) ) {
        $token = sanitize_text_field( $_POST['g-recaptcha-response-login'] );
        $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret'   => $options['secret_key'],
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            )
        ) );
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'recaptcha_error', __( 'reCAPTCHA verification failed. Please try again.', 'grv3' ) );
        }
        $response_body = wp_remote_retrieve_body( $response );
        $result = json_decode( $response_body, true );
        if ( ! isset( $result['success'] ) || ! $result['success'] || $result['score'] < 0.5 ) {
            return new WP_Error( 'recaptcha_error', __( 'reCAPTCHA verification failed, please try again.', 'grv3' ) );
        }
    } else {
        return new WP_Error( 'recaptcha_error', __( 'reCAPTCHA token not found, please try again.', 'grv3' ) );
    }
    return $user;
}
add_filter( 'authenticate', 'grv3_verify_recaptcha_on_login', 21, 3 );

/**
 * Verify the reCAPTCHA token during registration.
 */
function grv3_verify_recaptcha_on_registration( $errors, $sanitized_user_login, $user_email ) {
    $options = get_option( 'grv3_options' );
    if ( empty( $options['protect_registration'] ) || empty( $options['secret_key'] ) ) {
        return $errors;
    }
    if ( isset( $_POST['g-recaptcha-response-registration'] ) ) {
        $token = sanitize_text_field( $_POST['g-recaptcha-response-registration'] );
        $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret'   => $options['secret_key'],
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            )
        ) );
        if ( is_wp_error( $response ) ) {
            $errors->add( 'recaptcha_error', __( 'reCAPTCHA verification failed. Please try again.', 'grv3' ) );
            return $errors;
        }
        $response_body = wp_remote_retrieve_body( $response );
        $result = json_decode( $response_body, true );
        if ( ! isset( $result['success'] ) || ! $result['success'] || $result['score'] < 0.5 ) {
            $errors->add( 'recaptcha_error', __( 'reCAPTCHA verification failed, please try again.', 'grv3' ) );
        }
    } else {
        $errors->add( 'recaptcha_error', __( 'reCAPTCHA token not found, please try again.', 'grv3' ) );
    }
    return $errors;
}
add_filter( 'registration_errors', 'grv3_verify_recaptcha_on_registration', 10, 3 );