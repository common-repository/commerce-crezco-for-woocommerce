<?php
require_once  plugin_dir_path(__FILE__) . 'crezco/crezco.php';

class WC_Crezco_Admin
{
    private $webhook_id = '';
    private $user_id = '';


    private $error = array();


    private $api_key = '';
    private $environment = '';

    private $settings = array();

    function __construct()
    {
        $this->settings = WC_Admin_Settings::get_option('woocommerce_crezco_settings');
    }

    public function getEnvironment()
    {
        if (!empty($_GET['user-id']) && !empty($_COOKIE['environment'])) {
            return sanitize_text_field($_COOKIE['environment']);
        } else if (!empty($this->settings)) {
            return $this->settings['environment'];
        }
    }

    public function getApiKey()
    {
        if (!empty($_GET['user-id']) && !empty($_COOKIE['api_key'])) {
            return sanitize_text_field($_COOKIE['api_key']);
        } else if (!empty($this->settings)) {
            return sanitize_text_field($this->settings['api_key']);
        }
    }

    public function getUserId()
    {
        if (!empty($_GET['user-id'])) {
            return sanitize_text_field($_GET['user-id']);
        } else {
            return get_option('wc_crezco_user_id');
        }
    }

    public function getWebhookId()
    {
        return get_option('wc_crezco_webhook_id');
    }

    public function getPartnerId()
    {
        if (!empty($_GET['user-id']) && !empty($_COOKIE['partner_id'])) {
            return sanitize_text_field($_COOKIE['partner_id']);
        } else if (!empty($this->settings)) {
            return $this->settings['partner_id'];
        }
    }

    public function createWebhook()
    {
        if (!empty($_GET['user-id']) && !empty($_COOKIE['environment'])) {
            $api_key = $this->getApiKey();
            $partner_id = $this->getPartnerId();
            $environment = $this->getEnvironment();
            $this->user_id = sanitize_text_field($_GET['user-id']);


            $crezco_info = array(
                'api_key' => $api_key,
                'partner_id' => $partner_id,
                'environment' => $environment
            );

            $crezco = new Crezco($crezco_info);

            $webhook_info = array(
                'type' => 'payment',
                'eventType' => 'PaymentAll',
                'callback' => get_rest_url(null, '/wc_crezco/v1/webhook')
            );

            $result = $crezco->createWebhook($webhook_info);

            if ($crezco->hasErrors()) {
                $error_details = array();

                $errors = $crezco->getErrors();

                foreach ($errors as $error) {
                    if (isset($error['title']) && ($error['code'] == 504)) {
                        $error['detail'] =__('Sorry, Pay by Bank is currently busy. Please try again later!', 'crezco-payment-gateway');
                    }

                    if (isset($error['detail'])) {
                        $error_details[] = $error['detail'];
                    }

                    WC_Crezco_Log::add($error, $error['detail']);
                }

                $this->error['warning'] = implode(' ', $error_details);
            } else {
                update_option('wc_crezco_user_id', sanitize_text_field($this->user_id));
                update_option('wc_crezco_webhook_id', sanitize_text_field($this->webhook_id));
            }
            unset($_COOKIE['environment']);
            setcookie('environment', null, -1, '/');
        }
    }

    public function getUser()
    {
        if ($this->getUserId()) {
            $crezco_info = array(
                'api_key' => $this->getApiKey(),
                'partner_id' => $this->getPartnerId(),
                'environment' => $this->getEnvironment()
            );

            $crezco = new Crezco($crezco_info);

            $result = $crezco->getUser($this->getUserId());

            if ($crezco->hasErrors()) {
                $error_details = array();

                $errors = $crezco->getErrors();

                foreach ($errors as $error) {
                    if (isset($error['title']) && ($error['code'] == 504)) {
                        $error['detail'] = __('Sorry, Pay by Bank is currently busy. Please try again later!', 'crezco-payment-gateway');
                    }

                    if (isset($error['detail'])) {
                        $error_details[] = $error['detail'];
                    }

                    WC_Crezco_Log::add($error, $error['detail']);
                }

                $this->error['warning'] = implode(' ', $error_details);
                return;
            }

            return $result;
        }
    }

    public function removeWebhook()
    {
        $data = array();
        if ($this->getWebhookId()) {
            $crezco_info = array(
                'api_key' => $this->getApiKey(),
                'partner_id' => $this->getPartnerId(),
                'environment' => $this->getEnvironment()
            );

            $crezco = new Crezco($crezco_info);

            $crezco->deleteWebhook($this->getWebhookId());

            if ($crezco->hasErrors()) {
                $error_details = array();

                $errors = $crezco->getErrors();

                foreach ($errors as $error) {
                    if (isset($error['title']) && ($error['code'] == 504)) {
                        $error['detail'] = __('Sorry, Pay by Bank is currently busy. Please try again later!', 'crezco-payment-gateway');
                    }

                    if (isset($error['detail'])) {
                        $error_details[] = $error['detail'];
                    }

                    WC_Crezco_Log::add($error, $error['detail']);
                }

                $this->error['warning'] = implode(' ', $error_details);
            }
        }

        delete_option('wc_crezco_webhook_id');
        delete_option('wc_crezco_user_id');

        $data['error'] = $this->error;

        echo json_encode($data);
        exit();
    }

    public function prepareData()
    {
        if (empty($this->webhook_id)) {
            $this->webhook_id = get_option('wc_crezco_webhook_id', '');
        }
        if (empty($this->user_id)) {
            $this->user_id = get_option('wc_crezco_user_id', '');
        }
    }
    public function renderTemplate()
    {
        $crezcoTemplate = new WC_Crezco_Template();

        $user = $this->getUser();

        if ($user) {
            $text_connect = sprintf(__('Your seller account has been connected.<br />User ID = %s<br />E-Mail = %s<br />Display Name = %s<br />If you would like to connect another account, please, disconnect.', 'crezco-payment-gateway'), $this->getUserId(), $user['eMail'], $user['displayName']);
            $crezcoTemplate->set('text_connect', $text_connect);
        }

        $crezcoTemplate->set('user_id', $this->user_id);

        $crezcoTemplate->set('error', $this->error);

        echo $crezcoTemplate->render('setting');
    }

    public function connect() {
        $data = array();
        $partner_url = admin_url().'admin.php?page=wc-settings&amp;tab=checkout&amp;section=crezco';
        $partner_url = str_replace('&amp;', '%26', $partner_url);
        $partner_url = str_replace('?', '%3F', $partner_url);
        if (sanitize_text_field($_POST['woocommerce_crezco_environment']) == 'production') {
            $data['redirect'] = 'https://app.crezco.com/onboarding?partner_id=' . sanitize_text_field($_POST['woocommerce_crezco_partner_id']) . '&redirect_uri=' . $partner_url;
        } else {
            $data['redirect'] = 'https://app.sandbox.crezco.com/onboarding?partner_id=' . sanitize_text_field($_POST['woocommerce_crezco_partner_id']) . '-sandbox&redirect_uri=' . $partner_url;
        }

        setcookie( 'api_key', sanitize_text_field($_POST['woocommerce_crezco_api_key']), 0 , "/" );
        setcookie( 'partner_id', sanitize_text_field($_POST['woocommerce_crezco_partner_id']), 0 , "/" );
        setcookie( 'environment', sanitize_text_field($_POST['woocommerce_crezco_environment']), 0 , "/" );
        echo json_encode($data);
        exit();
    }
}
