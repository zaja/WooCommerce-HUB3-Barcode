<?php
/**
 * Plugin Name: WooCommerce HUB3 Barcode
 * Plugin URI: https://svejedobro.hr
 * Description: Generira PDF417 2D barkod prema HUB3 standardu za BACS plaćanje - prikazuje se na stranici zahvale, u narudžbi i emailu kupcu.
 * Version: 1.0.0
 * Author: Goran Zajec
 * Author URI: https://svejedobro.hr
 * Text Domain: woo-hub3-barcode
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WOO_HUB3_VERSION', '1.0.0');
define('WOO_HUB3_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_HUB3_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

class WooHUB3Barcode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        
        // Add settings link in plugins list
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=hub3_barcode') . '">' . __('Postavke', 'woo-hub3-barcode') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load includes
        $this->includes();
        
        // Admin hooks
        if (is_admin()) {
            add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
            add_action('woocommerce_settings_tabs_hub3_barcode', array($this, 'settings_tab_content'));
            add_action('woocommerce_update_options_hub3_barcode', array($this, 'update_settings'));
            add_action('admin_notices', array($this, 'check_bacs_enabled'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        }
        
        // Frontend hooks - Thank you page
        add_action('woocommerce_thankyou_bacs', array($this, 'display_barcode_thankyou'), 20);
        
        // Order details page (customer account)
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_barcode_order_details'), 10);
        
        // Admin order page
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_barcode_admin_order'), 10);
        
        // Email hooks
        add_action('woocommerce_email_after_order_table', array($this, 'display_barcode_email'), 10, 4);
        
        // AJAX for barcode generation
        add_action('wp_ajax_generate_hub3_barcode', array($this, 'ajax_generate_barcode'));
        add_action('wp_ajax_nopriv_generate_hub3_barcode', array($this, 'ajax_generate_barcode'));
        
        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
    }
    
    private function includes() {
        require_once WOO_HUB3_PLUGIN_DIR . 'includes/class-pdf417-generator.php';
        require_once WOO_HUB3_PLUGIN_DIR . 'includes/class-hub3-data.php';
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WooCommerce HUB3 Barcode zahtijeva instaliran i aktiviran WooCommerce plugin.', 'woo-hub3-barcode'); ?></p>
        </div>
        <?php
    }
    
    public function check_bacs_enabled() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'hub3_barcode') {
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (!isset($gateways['bacs']) || $gateways['bacs']->enabled !== 'yes') {
                ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('Upozorenje:', 'woo-hub3-barcode'); ?></strong> <?php _e('BACS (Izravno bankovni prijenos) metoda plaćanja nije uključena. Da bi HUB3 barkod radio, morate prvo omogućiti BACS plaćanje u', 'woo-hub3-barcode'); ?> <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=bacs'); ?>"><?php _e('WooCommerce postavkama plaćanja', 'woo-hub3-barcode'); ?></a>.</p>
                </div>
                <?php
            }
        }
    }
    
    public function add_settings_tab($tabs) {
        $tabs['hub3_barcode'] = __('HUB3 Barcode', 'woo-hub3-barcode');
        return $tabs;
    }
    
    public function update_settings() {
        $options = array();
        
        $options['text_above_barcode'] = sanitize_textarea_field($_POST['woo_hub3_text_above_barcode'] ?? '');
        $options['show_in_admin'] = isset($_POST['woo_hub3_show_in_admin']) ? '1' : '0';
        $options['recipient_name'] = sanitize_text_field($_POST['woo_hub3_recipient_name'] ?? '');
        $options['recipient_address'] = sanitize_text_field($_POST['woo_hub3_recipient_address'] ?? '');
        $options['recipient_postal'] = sanitize_text_field($_POST['woo_hub3_recipient_postal'] ?? '');
        $options['recipient_city'] = sanitize_text_field($_POST['woo_hub3_recipient_city'] ?? '');
        $options['recipient_iban'] = sanitize_text_field($_POST['woo_hub3_recipient_iban'] ?? '');
        $options['payment_model'] = sanitize_text_field($_POST['woo_hub3_payment_model'] ?? 'HR99');
        $options['reference_format'] = sanitize_text_field($_POST['woo_hub3_reference_format'] ?? 'order_number');
        $options['date_format'] = sanitize_text_field($_POST['woo_hub3_date_format'] ?? 'dmY');
        $options['reference_prefix'] = sanitize_text_field($_POST['woo_hub3_reference_prefix'] ?? '');
        $options['reference_suffix'] = sanitize_text_field($_POST['woo_hub3_reference_suffix'] ?? '');
        $options['purpose_code'] = sanitize_text_field($_POST['woo_hub3_purpose_code'] ?? 'OTHR');
        $options['purpose_code_custom'] = strtoupper(preg_replace('/[^A-Za-z]/', '', sanitize_text_field($_POST['woo_hub3_purpose_code_custom'] ?? '')));
        $options['payment_description'] = sanitize_text_field($_POST['woo_hub3_payment_description'] ?? 'Plaćanje narudžbe #{order_number}');
        
        update_option('woo_hub3_options', $options);
    }
    
    public function admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'hub3_barcode') {
            return;
        }
        wp_enqueue_style('woo-hub3-admin', WOO_HUB3_PLUGIN_URL . 'assets/css/admin.css', array(), WOO_HUB3_VERSION);
    }
    
    public function frontend_scripts() {
        if (is_checkout() || is_wc_endpoint_url('order-received') || is_wc_endpoint_url('view-order')) {
            wp_enqueue_style('woo-hub3-frontend', WOO_HUB3_PLUGIN_URL . 'assets/css/frontend.css', array(), WOO_HUB3_VERSION);
        }
    }
    
    public function settings_tab_content() {
        $options = get_option('woo_hub3_options', array());
        ?>
        <div class="woo-hub3-settings">
            
            <h2><?php _e('Opće postavke', 'woo-hub3-barcode'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_text_above_barcode"><?php _e('Tekst iznad barkoda', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <textarea name="woo_hub3_text_above_barcode" id="woo_hub3_text_above_barcode" rows="3" class="large-text"><?php echo esc_textarea($options['text_above_barcode'] ?? 'Skenirajte ovaj kod mobilnom aplikacijom vaše banke za brzo plaćanje:'); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php _e('Prikaži barkod u admin narudžbi', 'woo-hub3-barcode'); ?>
                    </th>
                    <td>
                        <label for="woo_hub3_show_in_admin">
                            <input type="checkbox" name="woo_hub3_show_in_admin" id="woo_hub3_show_in_admin" value="1" <?php checked($options['show_in_admin'] ?? '1', '1'); ?> />
                            <?php _e('Prikaži barkod na stranici narudžbe u admin panelu', 'woo-hub3-barcode'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <h2><?php _e('Podaci o primatelju', 'woo-hub3-barcode'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_recipient_name"><?php _e('Primatelj', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="woo_hub3_recipient_name" id="woo_hub3_recipient_name" value="<?php echo esc_attr($options['recipient_name'] ?? ''); ?>" class="regular-text" />
                        <p class="description"><?php _e('Naziv tvrtke ili ime primatelja', 'woo-hub3-barcode'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_recipient_address"><?php _e('Adresa', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="woo_hub3_recipient_address" id="woo_hub3_recipient_address" value="<?php echo esc_attr($options['recipient_address'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_recipient_postal"><?php _e('Poštanski broj', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="woo_hub3_recipient_postal" id="woo_hub3_recipient_postal" value="<?php echo esc_attr($options['recipient_postal'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_recipient_city"><?php _e('Mjesto', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="woo_hub3_recipient_city" id="woo_hub3_recipient_city" value="<?php echo esc_attr($options['recipient_city'] ?? ''); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_recipient_iban"><?php _e('IBAN', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="woo_hub3_recipient_iban" id="woo_hub3_recipient_iban" value="<?php echo esc_attr($options['recipient_iban'] ?? ''); ?>" class="regular-text" placeholder="HR1234567890123456789" />
                        <p class="description"><?php _e('IBAN račun primatelja (bez razmaka)', 'woo-hub3-barcode'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h2><?php _e('Postavke plaćanja', 'woo-hub3-barcode'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_payment_model"><?php _e('Model plaćanja', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <select name="woo_hub3_payment_model" id="woo_hub3_payment_model">
                            <option value="HR99" <?php selected($options['payment_model'] ?? '', 'HR99'); ?>>HR99 - Bez kontrole</option>
                            <option value="HR00" <?php selected($options['payment_model'] ?? '', 'HR00'); ?>>HR00 - Bez poziva na broj</option>
                            <option value="HR01" <?php selected($options['payment_model'] ?? '', 'HR01'); ?>>HR01 - Jedan broj (P1)</option>
                            <option value="HR02" <?php selected($options['payment_model'] ?? '', 'HR02'); ?>>HR02 - Dva broja (P1-P2)</option>
                            <option value="HR03" <?php selected($options['payment_model'] ?? '', 'HR03'); ?>>HR03 - Tri broja (P1-P2-P3)</option>
                            <option value="HR04" <?php selected($options['payment_model'] ?? '', 'HR04'); ?>>HR04 - Jedan broj (P1)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_reference_format"><?php _e('Poziv na broj - format', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <select name="woo_hub3_reference_format" id="woo_hub3_reference_format">
                            <option value="order_number" <?php selected($options['reference_format'] ?? '', 'order_number'); ?>><?php _e('Samo broj narudžbe', 'woo-hub3-barcode'); ?></option>
                            <option value="order_date" <?php selected($options['reference_format'] ?? '', 'order_date'); ?>><?php _e('Broj narudžbe - Datum', 'woo-hub3-barcode'); ?></option>
                            <option value="date_order" <?php selected($options['reference_format'] ?? '', 'date_order'); ?>><?php _e('Datum - Broj narudžbe', 'woo-hub3-barcode'); ?></option>
                            <option value="only_date" <?php selected($options['reference_format'] ?? '', 'only_date'); ?>><?php _e('Samo datum', 'woo-hub3-barcode'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_date_format"><?php _e('Oblik datuma u pozivu na broj', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <select name="woo_hub3_date_format" id="woo_hub3_date_format">
                            <option value="dmY" <?php selected($options['date_format'] ?? '', 'dmY'); ?>>DDMMGGGG (npr. 31012026)</option>
                            <option value="Ymd" <?php selected($options['date_format'] ?? '', 'Ymd'); ?>>GGGGMMDD (npr. 20260131)</option>
                            <option value="dmy" <?php selected($options['date_format'] ?? '', 'dmy'); ?>>DDMMGG (npr. 310126)</option>
                            <option value="ymd" <?php selected($options['date_format'] ?? '', 'ymd'); ?>>GGMMDD (npr. 260131)</option>
                            <option value="Y" <?php selected($options['date_format'] ?? '', 'Y'); ?>>GGGG - samo godina (npr. 2026)</option>
                            <option value="y" <?php selected($options['date_format'] ?? '', 'y'); ?>>GG - samo godina kratko (npr. 26)</option>
                            <option value="dm" <?php selected($options['date_format'] ?? '', 'dm'); ?>>DDMM (npr. 3101)</option>
                            <option value="md" <?php selected($options['date_format'] ?? '', 'md'); ?>>MMDD (npr. 0131)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_reference_prefix"><?php _e('Prefiks poziva na broj', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="woo_hub3_reference_prefix" id="woo_hub3_reference_prefix" value="<?php echo esc_attr($options['reference_prefix'] ?? ''); ?>" class="regular-text" />
                        <p class="description"><?php _e('Tekst koji će se dodati ispred poziva na broj', 'woo-hub3-barcode'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_reference_suffix"><?php _e('Sufiks poziva na broj', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="woo_hub3_reference_suffix" id="woo_hub3_reference_suffix" value="<?php echo esc_attr($options['reference_suffix'] ?? ''); ?>" class="regular-text" />
                        <p class="description"><?php _e('Tekst koji će se dodati iza poziva na broj', 'woo-hub3-barcode'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_purpose_code"><?php _e('Šifra namjene', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <select name="woo_hub3_purpose_code" id="woo_hub3_purpose_code">
                            <option value="OTHR" <?php selected($options['purpose_code'] ?? '', 'OTHR'); ?>>OTHR - Ostalo</option>
                            <option value="ADVA" <?php selected($options['purpose_code'] ?? '', 'ADVA'); ?>>ADVA - Unaprijed plaćeno</option>
                            <option value="COST" <?php selected($options['purpose_code'] ?? '', 'COST'); ?>>COST - Troškovi</option>
                            <option value="GDDS" <?php selected($options['purpose_code'] ?? '', 'GDDS'); ?>>GDDS - Kupnja robe</option>
                            <option value="GDSV" <?php selected($options['purpose_code'] ?? '', 'GDSV'); ?>>GDSV - Kupnja robe i usluga</option>
                            <option value="SCVE" <?php selected($options['purpose_code'] ?? '', 'SCVE'); ?>>SCVE - Kupnja usluga</option>
                            <option value="SUPP" <?php selected($options['purpose_code'] ?? '', 'SUPP'); ?>>SUPP - Plaćanje dobavljaču</option>
                            <option value="custom" <?php selected($options['purpose_code'] ?? '', 'custom'); ?>><?php _e('Vlastita šifra...', 'woo-hub3-barcode'); ?></option>
                        </select>
                        <input type="text" name="woo_hub3_purpose_code_custom" id="woo_hub3_purpose_code_custom" value="<?php echo esc_attr($options['purpose_code_custom'] ?? ''); ?>" class="regular-text" placeholder="<?php _e('Unesite vlastitu šifru (4 slova)', 'woo-hub3-barcode'); ?>" style="margin-left: 10px; <?php echo ($options['purpose_code'] ?? '') !== 'custom' ? 'display:none;' : ''; ?>" maxlength="4" />
                        <script>
                        jQuery(function($) {
                            $('#woo_hub3_purpose_code').on('change', function() {
                                if ($(this).val() === 'custom') {
                                    $('#woo_hub3_purpose_code_custom').show().focus();
                                } else {
                                    $('#woo_hub3_purpose_code_custom').hide();
                                }
                            });
                        });
                        </script>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="woo_hub3_payment_description"><?php _e('Opis plaćanja', 'woo-hub3-barcode'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="woo_hub3_payment_description" id="woo_hub3_payment_description" value="<?php echo esc_attr($options['payment_description'] ?? 'Plaćanje narudžbe #{order_number}'); ?>" class="large-text" />
                        <p class="description"><?php _e('Dostupne varijable: {order_number}, {order_date}', 'woo-hub3-barcode'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h2><?php _e('Pregled barkoda', 'woo-hub3-barcode'); ?></h2>
            <p><?php _e('Primjer kako će izgledati barkod s testnim podacima:', 'woo-hub3-barcode'); ?></p>
            <div id="hub3-preview">
                <?php 
                $test_order_data = array(
                    'order_id' => 12345,
                    'order_number' => '12345',
                    'order_date' => date('Y-m-d'),
                    'total' => '100.00',
                    'currency' => 'EUR',
                    'payer_name' => 'Ivan Horvat',
                    'payer_address' => 'Ilica 1',
                    'payer_city' => '10000 Zagreb',
                );
                echo $this->generate_barcode_html($test_order_data, true);
                ?>
            </div>
        </div>
        <?php
    }
    
    public function display_barcode_thankyou($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== 'bacs') {
            return;
        }
        
        // Skip if already paid
        if ($order->is_paid()) {
            return;
        }
        
        $order_data = $this->get_order_data($order);
        echo $this->generate_barcode_html($order_data);
    }
    
    public function display_barcode_order_details($order) {
        if (!$order || $order->get_payment_method() !== 'bacs') {
            return;
        }
        
        // Skip if on thank you page (already shown via woocommerce_thankyou_bacs)
        if (is_wc_endpoint_url('order-received')) {
            return;
        }
        
        // Skip if already paid
        if ($order->is_paid()) {
            return;
        }
        
        $order_data = $this->get_order_data($order);
        echo $this->generate_barcode_html($order_data);
    }
    
    public function display_barcode_admin_order($order) {
        if (!$order || $order->get_payment_method() !== 'bacs') {
            return;
        }
        
        // Check if admin display is enabled
        $options = get_option('woo_hub3_options', array());
        if (($options['show_in_admin'] ?? '1') !== '1') {
            return;
        }
        
        $order_data = $this->get_order_data($order);
        echo '<div class="hub3-admin-barcode">';
        echo '<h3>' . __('HUB3 Barcode', 'woo-hub3-barcode') . '</h3>';
        echo $this->generate_barcode_html($order_data);
        echo '</div>';
    }
    
    public function display_barcode_email($order, $sent_to_admin, $plain_text, $email) {
        // Only show in customer emails for BACS orders
        if ($sent_to_admin || $plain_text) {
            return;
        }
        
        if (!$order || $order->get_payment_method() !== 'bacs') {
            return;
        }
        
        // Skip if already paid
        if ($order->is_paid()) {
            return;
        }
        
        // Only show in specific email types
        $allowed_emails = array('customer_on_hold_order', 'customer_processing_order', 'customer_invoice');
        if (!in_array($email->id, $allowed_emails)) {
            return;
        }
        
        $order_data = $this->get_order_data($order);
        echo $this->generate_barcode_html($order_data, false, true);
    }
    
    private function get_order_data($order) {
        $billing_address = $order->get_billing_address_1();
        if ($order->get_billing_address_2()) {
            $billing_address .= ' ' . $order->get_billing_address_2();
        }
        
        return array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_date' => $order->get_date_created()->format('Y-m-d'),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'payer_address' => $billing_address,
            'payer_city' => $order->get_billing_postcode() . ' ' . $order->get_billing_city(),
        );
    }
    
    public function generate_barcode_html($order_data, $is_preview = false, $is_email = false) {
        $options = get_option('woo_hub3_options', array());
        
        // Check if all required settings are filled
        if (empty($options['recipient_name']) || empty($options['recipient_iban'])) {
            if (!$is_preview) {
                return '';
            }
            $options = array_merge(array(
                'recipient_name' => 'Test Tvrtka d.o.o.',
                'recipient_address' => 'Testna ulica 1',
                'recipient_postal' => '10000',
                'recipient_city' => 'Zagreb',
                'recipient_iban' => 'HR1234567890123456789',
                'payment_model' => 'HR99',
                'reference_format' => 'order_number',
                'date_format' => 'dmY',
                'reference_prefix' => '',
                'reference_suffix' => '',
                'purpose_code' => 'OTHR',
                'payment_description' => 'Plaćanje narudžbe #{order_number}',
                'text_above_barcode' => 'Skenirajte ovaj kod mobilnom aplikacijom vaše banke za brzo plaćanje:',
            ), $options);
        }
        
        // Generate HUB3 data
        $hub3 = new WooHUB3Data($options, $order_data);
        $hub3_string = $hub3->generate();
        
        // Generate barcode image
        $generator = new WooPDF417Generator();
        $barcode_data = $generator->generate($hub3_string);
        
        $text_above = $options['text_above_barcode'] ?? 'Skenirajte ovaj kod mobilnom aplikacijom vaše banke za brzo plaćanje:';
        
        $style = $is_email ? 'style="text-align: center; margin: 20px 0; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;"' : '';
        $img_style = $is_email ? 'style="max-width: 100%; height: auto;"' : '';
        
        $html = '<div class="hub3-barcode-wrapper" ' . $style . '>';
        if (!empty($text_above)) {
            $html .= '<p class="hub3-text-above">' . esc_html($text_above) . '</p>';
        }
        $html .= '<div class="hub3-barcode">';
        $html .= '<img src="' . $barcode_data . '" alt="HUB3 Barcode" class="hub3-barcode-image" ' . $img_style . ' />';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    public function ajax_generate_barcode() {
        check_ajax_referer('hub3_barcode_nonce', 'nonce');
        
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
        }
        
        $order_data = $this->get_order_data($order);
        $html = $this->generate_barcode_html($order_data);
        
        wp_send_json_success(array('html' => $html));
    }
}

// Initialize plugin
WooHUB3Barcode::get_instance();
