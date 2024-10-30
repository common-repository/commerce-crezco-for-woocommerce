<?php

class WC_Crezco_Catalog {
    public function filterPayments($available_gateways) {
        $settings = WC_Admin_Settings::get_option('woocommerce_crezco_settings');

        global $woocommerce;

        if ($woocommerce->cart) {
            if ( $woocommerce->cart->total < (int)$settings['min_price'] ) {
                unset($available_gateways['crezco']);
            }
        }

        return $available_gateways;
    }
    public function process($order_id, $return_url) {
        $data = array();
        global $woocommerce;
        $order = wc_get_order( $order_id );

        // Mark as pending-payment as the user has not attempted to pay yet
        $order->update_status('pending', __( 'Awaiting payment initiation', 'crezco-payment-gateway' ));

        $order_total = $woocommerce->cart->total;

        $site_url = site_url();

        $currency_code = $order->get_currency();

        $settings = WC_Admin_Settings::get_option('woocommerce_crezco_settings');

        $api_key = $settings['api_key'];
        $partner_id = $settings['partner_id'];
        $user_id = get_option('wc_crezco_user_id');
        $environment = $settings['environment'];

        $crezco_info = array(
            'api_key' => $api_key,
            'partner_id' => $partner_id,
            'environment' => $environment
        );
        
        $crezco = new Crezco($crezco_info);

        $pay_demand_info = array(
            'useDefaultBeneficiaryAccount' => true,
            'dueDate' => date('Y-m-d'),
            'currency' => $currency_code,
            'amount' => $order_total,
            'reference' => 'Order ' . $order_id
        );

        $result = $crezco->createPayDemand($user_id, $pay_demand_info);

        if ($crezco->hasErrors()) {
            $error_details = array();
                
            $errors = $crezco->getErrors();
                                
            foreach ($errors as $error) {
                if (isset($error['title']) && ($error['code'] == 504)) {
                    $error['detail'] =  __('Sorry, Pay by Bank is currently busy. Please try again later!', 'crezco-payment-gateway');
                }
                    
                if (isset($error['detail'])) {
                    $error_details[] = $error['detail'];
                }
                    
                WC_Crezco_Log::add($error, $error['detail']);
            }
                
            wc_add_notice( implode(' ', $error_details), 'error' );
            $this->error['warning'] = implode(' ', $error_details);
        } else {
            $pay_demand_id = $result;
        }

        if (!empty($pay_demand_id)) {
            $payment_info = array(
                'initialScreen' => 'BankSelection',
                'finalScreen' => 'PaymentStatus',
                'amount' => $order_total,
                'countryIso2Code' => 'GB',
                'successCallbackUri' => $return_url,
                'failureCallbackUri' => $return_url
            );
            
            if (!empty($order->get_billing_email())) {
                $payment_info['payerEmail'] = $order->get_billing_email();
            }
                                    
            $result = $crezco->createPayment($user_id, $pay_demand_id, $payment_info);

            if ($crezco->hasErrors()) {
                $error_details = array();
                
                $errors = $crezco->getErrors();
                                
                foreach ($errors as $error) {
                    if (isset($error['title']) && ($error['code'] == 504)) {
                        $error['detail'] =  __('Sorry, Pay by Bank is currently busy. Please try again later!', 'crezco-payment-gateway');
                    }
                    
                    if (isset($error['detail'])) {
                        $error_details[] = $error['detail'];
                    }

                    WC_Crezco_Log::add($error, $error['detail']);
                }
                wc_add_notice(implode(' ', $error_details), 'error');
            } elseif (!empty($result['redirect'])) {
                $data['redirect'] = $result['redirect'];

                return array(
                    'redirect' => $result['redirect'],
                    'result' => 'success'
                );
            }
        }
    }

    public function webhook() {
        $webhooks_info = json_decode(html_entity_decode(file_get_contents('php://input')), true);

        foreach ($webhooks_info as $webhook_info) {
            WC_Crezco_Log::add($webhook_info, 'Webhook');
        
            if (!empty($webhook_info['id']) && !empty($webhook_info['metadata']['payDemandId']) && !empty($webhook_info['eventType'])) {
                $payment_id = $webhook_info['id'];
                $pay_demand_id = $webhook_info['metadata']['payDemandId'];
                $event_type = $webhook_info['eventType'];
            
                $settings = WC_Admin_Settings::get_option('woocommerce_crezco_settings');
    
                $api_key = $settings['api_key'];
                $partner_id = $settings['partner_id'];
                $user_id = get_option('wc_crezco_user_id');
                $environment = $settings['environment'];
        
                $crezco_info = array(
                    'api_key' => $api_key,
                    'partner_id' => $partner_id,
                    'environment' => $environment
                );
        
                $crezco = new Crezco($crezco_info);
            
                $result = $crezco->getPayDemand($pay_demand_id);
                        
                if ($crezco->hasErrors()) {
                    $errors = $crezco->getErrors();
                                
                    foreach ($errors as $error) {
                        if (isset($error['title']) && ($error['code'] == 504)) {
                            $error['detail'] = __('Sorry, Pay by Bank is currently busy. Please try again later!', 'crezco-payment-gateway');
                        }
                        
                        WC_Crezco_Log::add($error, $error['detail']);
                    }
                } elseif (!empty($result['reference'])) {
                    $order_id = str_replace('Order ', '', $result['reference']);
                }
                
                $result = $crezco->getPaymentStatus($payment_id);
        
                if ($crezco->hasErrors()) {
                    $errors = $crezco->getErrors();
                                
                    foreach ($errors as $error) {
                        if (isset($error['title']) && ($error['code'] == 504)) {
                            $error['detail'] = __('Sorry, Pay by Bank is currently busy. Please try again later!', 'crezco-payment-gateway');
                        }
                                
                        WC_Crezco_Log::add($error, $error['detail']);
                    }
                } elseif (!empty($result['code'])) {
                    $payment_status = $result['code'];
                }
                                        
                if (!empty($order_id) && !empty($payment_status)) {
                    global $woocommerce;
                    $order = wc_get_order( $order_id );
                    # See best practices: https://woocommerce.com/document/payment-gateway-api/#section-5
                    if ($event_type == 'PaymentPending') {
                        $order->update_status('pending', __( 'Waiting for payment to complete', 'crezco-payment-gateway' ));
                    }
        
                    if ($event_type == 'PaymentFailed') {
                        $order->update_status('failed', __( 'Failed', 'crezco-payment-gateway' ));
                    }
            
                    if ($event_type == 'PaymentCompleted') {
                        $order->payment_complete();
                    }
                
                    if ($payment_status == 'Cancelled') {
                        $order->update_status('cancelled', __( 'Cancelled', 'crezco-payment-gateway' ));
                    }
                
                    if ($payment_status == 'Failed') {
                        $order->update_status('failed', __( 'Failed', 'crezco-payment-gateway' ));
                    }
                
                    if ($payment_status == 'Denied') {
                        $order->update_status('failed', __( 'Denied', 'crezco-payment-gateway' ));
                    }
                
                    if ($payment_status == 'Declined') {
                        $order->update_status('failed', __( 'Declined', 'crezco-payment-gateway' ));
                    }
                }
            }
        }
    }
}