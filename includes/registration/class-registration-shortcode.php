<?php
/**
 * ZipPicks Registration Shortcode
 *
 * @package ZipPicks\Core
 * @subpackage Registration
 * @since 1.0.0
 */

namespace ZipPicks\Core\Registration;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Registration Shortcode Handler
 *
 * Renders the ZipPicks registration form using the [zippicks_registration_form] shortcode.
 * Follows enterprise standards with proper security, asset management, and extensibility.
 *
 * @since 1.0.0
 */
class RegistrationShortcode {
    
    /**
     * Shortcode tag
     *
     * @var string
     */
    const SHORTCODE_TAG = 'zippicks_registration_form';
    
    /**
     * Asset handle prefix
     *
     * @var string
     */
    const ASSET_PREFIX = 'zippicks-registration';
    
    /**
     * Nonce action
     *
     * @var string
     */
    const NONCE_ACTION = 'zippicks_user_register';
    
    /**
     * Nonce field name
     *
     * @var string
     */
    const NONCE_FIELD = 'zippicks_register_nonce';
    
    /**
     * Whether assets have been enqueued
     *
     * @var bool
     */
    private static $assets_enqueued = false;
    
    /**
     * Initialize the shortcode
     *
     * @since 1.0.0
     */
    public static function init() {
        add_shortcode(self::SHORTCODE_TAG, [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_assets']);
    }
    
    /**
     * Render the registration form
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string The rendered form HTML
     */
    public static function render($atts = []) {
        // Parse attributes with defaults
        $atts = shortcode_atts([
            'redirect_url' => home_url('/dashboard/'),
            'show_title' => true,
            'title' => 'Create Your Account',
            'subtitle' => 'Join ZipPicks and discover top-rated restaurants, bars, and hidden gems curated by experts and tastemakers nationwide.',
            'class' => 'zip-registration-form-wrapper'
        ], $atts, self::SHORTCODE_TAG);
        
        // Enqueue assets if not already done
        self::enqueue_assets();
        
        // Start output buffering
        ob_start();
        
        // Process form submission if POST request
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[self::NONCE_FIELD])) {
            $errors = self::process_submission();
        }
        
        // Render the form
        self::render_form($atts, $errors);
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Render the registration form HTML
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @param array $errors Validation errors
     */
    private static function render_form($atts, $errors) {
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <div class="zip-container zip-main-column">
                <?php if ($atts['show_title']) : ?>
                    <h1 class="zip-section-title"><?php echo esc_html($atts['title']); ?></h1>
                    <p class="zip-muted"><?php echo esc_html($atts['subtitle']); ?></p>
                <?php endif; ?>

                <?php if (!empty($errors)) : ?>
                    <div class="zip-error-messages" role="alert">
                        <ul>
                            <?php foreach ($errors as $error) : ?>
                                <li><?php echo esc_html($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="zip-registration-form" novalidate>
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                    
                    <input type="hidden" name="redirect_url" value="<?php echo esc_url($atts['redirect_url']); ?>">

                    <input type="text" 
                           name="first_name" 
                           placeholder="First Name" 
                           value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>"
                           required 
                           autofocus>
                           
                    <input type="text" 
                           name="last_name" 
                           placeholder="Last Name" 
                           value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>"
                           required>

                    <select name="user_role" required>
                        <option value="">Select Role</option>
                        <option value="zipper" <?php selected(isset($_POST['user_role']) && $_POST['user_role'] === 'zipper'); ?>>Zipper</option>
                        <option value="critic" <?php selected(isset($_POST['user_role']) && $_POST['user_role'] === 'critic'); ?>>Critic</option>
                        <option value="business_owner" <?php selected(isset($_POST['user_role']) && $_POST['user_role'] === 'business_owner'); ?>>Business Owner</option>
                    </select>
                    <small>Zippers are approved instantly. Critics & Businesses require review and approval.</small>

                    <input type="text" 
                           name="zip_code" 
                           placeholder="ZIP Code" 
                           value="<?php echo isset($_POST['zip_code']) ? esc_attr($_POST['zip_code']) : ''; ?>"
                           pattern="[0-9]{5}"
                           maxlength="5"
                           required>
                    <small>Used to personalize your local experience.</small>

                    <input type="email" 
                           name="user_email" 
                           id="user_email" 
                           placeholder="Email" 
                           value="<?php echo isset($_POST['user_email']) ? esc_attr($_POST['user_email']) : ''; ?>"
                           required>
                    <small>Your login email and verification link will be sent here.</small>
                    <small id="emailSuggestion" style="color: #1e40af; display:none; font-weight: 500;"></small>

                    <input type="password" 
                           name="user_password" 
                           id="user_password" 
                           placeholder="Password" 
                           required>
                    <small>Use at least 8 characters with upper/lowercase, numbers, and symbols.</small>
                    <div class="password-meter">
                        <div id="passwordStrength" class="password-meter-fill"></div>
                    </div>

                    <input type="password" 
                           name="confirm_password" 
                           placeholder="Confirm Password" 
                           required>

                    <div class="zip-terms-row">
                        <label for="terms_agree">
                            <input type="checkbox" 
                                   id="terms_agree" 
                                   name="terms_agree" 
                                   <?php checked(isset($_POST['terms_agree']) && $_POST['terms_agree'] === 'on'); ?>
                                   required>
                            I agree to the <a href="/terms-of-use" target="_blank">Terms</a> and <a href="/privacy-policy" target="_blank">Privacy Policy</a>
                        </label>
                    </div>

                    <?php
                    // Allow filtering of reCAPTCHA site key
                    $recaptcha_site_key = apply_filters('zippicks_recaptcha_site_key', '6Lc8sUYrAAAAAGY813swPcwC-uJy5ckmhtTECZXm');
                    if ($recaptcha_site_key) :
                    ?>
                        <div class="g-recaptcha"
                             data-sitekey="<?php echo esc_attr($recaptcha_site_key); ?>"
                             data-callback="onRecaptchaSuccessCallback"
                             data-expired-callback="onRecaptchaExpiredCallback">
                        </div>
                    <?php endif; ?>

                    <div class="zip-form-actions">
                        <button type="submit" 
                                class="zip-follow-button zip-btn-primary" 
                                id="submitBtn" 
                                <?php echo $recaptcha_site_key ? 'disabled' : ''; ?>>
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Process form submission
     *
     * @since 1.0.0
     * @return array Validation errors
     */
    private static function process_submission() {
        $errors = [];
        
        // Verify nonce
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            $errors[] = 'Security verification failed. Please try again.';
            return $errors;
        }
        
        // Check if registration handler exists
        if (function_exists('zippicks_handle_registration')) {
            $errors = zippicks_handle_registration();
        } else {
            // Placeholder validation for demonstration
            $errors[] = 'Registration handler not yet implemented. Form submission captured successfully.';
        }
        
        return $errors;
    }
    
    /**
     * Maybe enqueue assets based on page content
     *
     * @since 1.0.0
     */
    public static function maybe_enqueue_assets() {
        global $post;
        
        // Check if shortcode exists in post content
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, self::SHORTCODE_TAG)) {
            self::enqueue_assets();
        }
    }
    
    /**
     * Enqueue required assets
     *
     * @since 1.0.0
     */
    public static function enqueue_assets() {
        if (self::$assets_enqueued) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            self::ASSET_PREFIX,
            ZIPPICKS_CORE_PLUGIN_URL . 'assets/css/registration.css',
            [],
            ZIPPICKS_CORE_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            self::ASSET_PREFIX,
            ZIPPICKS_CORE_PLUGIN_URL . 'assets/js/registration.js',
            ['jquery'],
            ZIPPICKS_CORE_VERSION,
            true
        );
        
        // Enqueue Google reCAPTCHA if site key is available
        $recaptcha_site_key = apply_filters('zippicks_recaptcha_site_key', '6Lc8sUYrAAAAAGY813swPcwC-uJy5ckmhtTECZXm');
        if ($recaptcha_site_key) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js',
                [],
                null,
                true
            );
        }
        
        // Localize script with data
        wp_localize_script(self::ASSET_PREFIX, 'zippicks_registration', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_registration_ajax'),
            'strings' => [
                'email_suggestion' => 'Did you mean %s?',
                'password_weak' => 'Weak password',
                'password_medium' => 'Medium strength',
                'password_strong' => 'Strong password'
            ]
        ]);
        
        self::$assets_enqueued = true;
    }
}

// Initialize the shortcode
add_action('init', ['\ZipPicks\Core\Registration\RegistrationShortcode', 'init']);