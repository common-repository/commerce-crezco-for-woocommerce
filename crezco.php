<?php
/**
 * Plugin Name: Crezco Payment Gateway
 * Author: Crezco
 * Author URI: https://www.crezco.com/
 * Description: Account-to-account payment powered by open banking.
 * Version: 1.0.8
 * License: GPL v2 or later
 * Stable tag: 1.0.8
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crezco-payment-gateway
 * Tags: commerce crezco, payment, payment gateway, commerce, product
 * @author Crezco
 * @url https://www.crezco.com/
 * @version 1.0.8
 */

if ( ! defined( 'ABSPATH' ) ) exit;


if ( !function_exists( 'init_crezco_wc' ) )
{
    function init_crezco_wc()
    {
        if( ! class_exists( 'CrezcoWC' ) ) {
            class CrezcoWC extends WC_Payment_Gateway
            {
                /**
                 * CrezcoWC constructor.
                 * @since 1.0
                 * @version 1.0.0
                 */
                public function __construct()
                {
                    $this->run();
                    $this->id = 'crezco';
                    $this->title = $this->get_option( 'title' );
                    $this->icon = plugin_dir_url( __FILE__ ) . 'assets/images/icon.png';
                    $this->has_fields = false;
                    $this->method_title = __( 'Crezco', 'crezco-payment-gateway' );
                    $this->method_description = __( 'Crezco', 'crezco-payment-gateway' );
                    $this->init_form_fields();

                    $this->init_settings();
                    $this->enabled = $this->get_option( 'enabled' );
                    $this->api_key = $this->get_option( 'api_key' );

                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                    add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );   
                }

                /**
                 * Runs Plugins
                 * @since 1.0
                 * @version 1.0
                 */
                public function run()
                {
                    $this->constants();
                    $this->add_actions();
                }

                /**
                 * @param $name Name of constant
                 * @param $value Value of constant
                 * @since 1.0
                 * @version 1.0
                 */
                public function define($name, $value)
                {
                    if (!defined($name))
                        define($name, $value);
                }

                /**
                 * Defines Constants
                 * @since 1.0
                 * @version 1.0
                 */
                public function constants()
                {
                    $this->define('CREZCOWC_VERSION', '1.0.0');
                    $this->define('CREZCOWC_PREFIX', 'crezcowc_');
                    $this->define('CREZCOWC_TEXT_DOMAIN', 'crezco-payment-gateway');
                    $this->define('CREZCOWC_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
                    $this->define('CREZCOWC_PLUGIN_DIR_URL', plugin_dir_url(__FILE__));
                }

                /**
                 * Prints the admin options for the gateway.
                 * Inserts an empty placeholder div feature flag is enabled.
                 */
                public function admin_options() {
                    wp_enqueue_style(CREZCOWC_TEXT_DOMAIN . '-css', CREZCOWC_PLUGIN_DIR_URL . 'assets/css/style.css', '', CREZCOWC_VERSION);
                    wp_enqueue_script(CREZCOWC_TEXT_DOMAIN . '-custom-js', CREZCOWC_PLUGIN_DIR_URL . 'assets/js/custom.js', '', CREZCOWC_VERSION);
                    
                    $crezcoAdmin = new WC_Crezco_Admin();
                    $crezcoAdmin->createWebhook();
                    $crezcoAdmin->prepareData();
                    parent::admin_options();
                    $crezcoAdmin->renderTemplate();
                    
                }
                /**
                 * Add Actions
                 * @since 1.0
                 * @version 1.0
                 */
                public function add_actions()
                {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                }

                /**
                 * Fields
                 * @since 1.0
                 * @since 1.4 added `Webhook URL`
                 * @version 1.0.1
                 */
                public function init_form_fields()
                {
                    $this->form_fields = array(
                        'enabled'   =>  array(
                             'title'     =>  __( 'Enabled/ Disabled', 'crezco-payment-gateway' ),
                             'type'      =>  'checkbox',
                             'label'     =>  __( 'Enable or Disable Crezco Payments', 'crezco-payment-gateway' ),
                             'default'   =>  'no'
                        ),
                        'title' =>  array(
                            'title'         =>  __( 'Payment method name', 'crezco-payment-gateway' ),
                            'type'          =>  'text',
                            'default'       =>  __( 'Pay by bank', 'crezco-payment-gateway' ),
                            'desc_tip'      => true,
                            'description'   =>  __( 'Name of payment method that customers will see at checkout', 'crezco-payment-gateway' ),
                        ),
                        'environment'         => array(
                            'title'   => __( 'Environment', 'crezco-payment-gateway' ),
                            'type'    => 'select',
                            'class'   => 'wc-enhanced-select',
                            'default' => 'sandbox',
                            'options' => array(
                                'production'     => __( 'Production', 'crezco-payment-gateway' ),
                                'sandbox' => __( 'Sandbox', 'crezco-payment-gateway' )
                            ),
                        ),
                        'debug'         => array(
                            'title'   => __( 'Debug Logging', 'crezco-payment-gateway' ),
                            'type'    => 'select',
                            'class'   => 'wc-enhanced-select',
                            'default' => '0',
                            'options' => array(
                                '1'     => __( 'Enabled', 'crezco-payment-gateway' ),
                                '0'     => __( 'Disabled', 'crezco-payment-gateway' )
                            ),
                        ),
                        'min_price' => array(
                            'title'       => __('Total', 'crezco-payment-gateway'),
                            'type'        => 'text',
                            'desc_tip'    => true,
                            "description" => __('The checkout total the order must reach before this payment method becomes active.', 'crezco-payment-gateway')
                        ),
                        'api_key' => array(
                            'title'       => __('API Key', 'crezco-payment-gateway'),
                            'type'        => 'text'
                        ),
                        'partner_id' => array(
                            'title'       => __('Partner ID', 'crezco-payment-gateway'),
                            'type'        => 'text'
                        )
                    );
                }

                public function payment_scripts()
                {
                    if ( empty( $this->api_key ) ) {
                        return;
                    }
                }

                public function process_payment( $order_id )
                {
                    $crezcoCatalog = new WC_Crezco_Catalog();

                    $order = wc_get_order( $order_id );

                    $return_url = wp_sanitize_redirect(
                        esc_url_raw(
                            add_query_arg(
                                [
                                    'order_id'            => $order_id,
                                    'wc_payment_method'   => $this->id,
                                    '_wpnonce'            => wp_create_nonce('wcpay_process_redirect_order_nonce'),
                                ],
                                $this->get_return_url( $order )
                            )
                        )
                    );

                    return $crezcoCatalog->process($order_id, $return_url);
                }

                /**
                 * @return bool|void
                 */
                public function process_admin_options()
                {
                    parent::process_admin_options();

                    if ( empty( $_POST['woocommerce_crezco_api_key'] ) ) {
                        WC_Admin_Settings::add_error( 'Error: API Key is required.' );
                        return false;
                    }

                    if ( empty( $_POST['woocommerce_crezco_min_price'] ) ) {
                        WC_Admin_Settings::add_error( 'Error: Total is required.' );
                        return false;
                    }

                    if ( empty( $_POST['woocommerce_crezco_partner_id'] ) ) {
                        WC_Admin_Settings::add_error( 'Error: Partner ID is required.' );
                        return false;
                    }
                }
       
            }
        }
    }
}
/**
 * Runs on Plugin's activation
 * @since 1.1
 * @version 1.0
 */
if ( !function_exists( 'crezco_woocommerce_requirements' ) ) {
    function crezco_woocommerce_requirements() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_attr_e( 'Please activate', 'crezco-payment-gateway' );?> <a href="https://wordpress.org/plugins/woocommerce/"><?php esc_attr_e( 'Woocommerce', 'crezco-payment-gateway' ); ?></a> <?php esc_attr_e( 'to use this plugin.', 'crezco-payment-gateway' ); ?></p>
        </div>
        <?php
    }
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'plugins_loaded', 'init_crezco_wc' );
} else {
    add_action( 'admin_notices', 'om_woocommerce_requirements' );
}

if ( !function_exists( 'add_crezco_to_wc' ) ):
    function add_crezco_to_wc( $gateways )
    {
        $gateways[] = 'CrezcoWC';
        return $gateways;
    }
endif;

add_filter( 'woocommerce_payment_gateways', 'add_crezco_to_wc' );

require_once plugin_dir_path(__FILE__) . 'includes/crezco-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/crezco-catalog.php';
require_once plugin_dir_path(__FILE__) . 'includes/crezco-log.php';
require_once plugin_dir_path(__FILE__) . 'includes/crezco-template.php';


add_action('wp_ajax_crezco_connect', 'crezco_connect_action');
add_action('wp_ajax_crezco_disconnect', 'crezco_disconnect_action');

function crezco_connect_action() {

    $crezcoAdmin = new WC_Crezco_Admin();
    $crezcoAdmin->connect();
}

function crezco_disconnect_action() {

    $crezcoAdmin = new WC_Crezco_Admin();
    $crezcoAdmin->removeWebhook();
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'wc_crezco/v1', '/webhook', array(
      'methods' => 'POST',
      'callback' => 'wc_crezco_webhook',
      'permission_callback' => '__return_true'
    ) );
});

function wc_crezco_webhook( ) {
    $catalogCrezco = new WC_Crezco_Catalog();
    $catalogCrezco->webhook();
}

function wc_crezco_payment_gateway_enable_check( $available_gateways ) {
   $crezcoCatalog = new WC_Crezco_Catalog();

   return $crezcoCatalog->filterPayments($available_gateways);
}
add_filter( 'woocommerce_available_payment_gateways', 'wc_crezco_payment_gateway_enable_check' );