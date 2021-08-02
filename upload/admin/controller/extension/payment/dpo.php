<?php
/*
 * Copyright (c) 2021 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class ControllerExtensionPaymentDpo extends Controller
{

    const PAYMENT_METHOD        = 'extension/payment/dpo';
    const USER_TOKEN_TEXT       = 'user_token=';
    const TYPE_PAYMENT          = '&type=payment';
    const MARKETPALCE_EXTENSION = 'marketplace/extension';
    private $error = array();

    public function index()
    {
        $this->load->language(self::PAYMENT_METHOD);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_dpo', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect(
                $this->url->link(
                    self::MARKETPALCE_EXTENSION,
                    self::USER_TOKEN_TEXT . $this->session->data['user_token'] . self::TYPE_PAYMENT,
                    true
                )
            );
        }

        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_edit']      = $this->language->get('text_edit');
        $data['text_enabled']   = $this->language->get('text_enabled');
        $data['text_disabled']  = $this->language->get('text_disabled');
        $data['text_all_zones'] = $this->language->get('text_all_zones');

        $data['entry_order_status']     = $this->language->get('entry_order_status');
        $data['entry_success_status']   = $this->language->get('entry_success_status');
        $data['entry_failed_status']    = $this->language->get('entry_failed_status');
        $data['entry_cancelled_status'] = $this->language->get('entry_cancelled_status');
        $data['entry_total']            = $this->language->get('entry_total');
        $data['entry_geo_zone']         = $this->language->get('entry_geo_zone');
        $data['entry_status']           = $this->language->get('entry_status');
        $data['entry_sort_order']       = $this->language->get('entry_sort_order');

        $data['tab_general']      = $this->language->get('tab_general');
        $data['tab_order_status'] = $this->language->get('tab_order_status');

        $data['entry_merchant_token'] = $this->language->get('entry_merchant_token');
        $data['entry_service_type']   = $this->language->get('entry_service_type');

        $data['help_total'] = $this->language->get('help_total');

        $data['button_save']   = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link(
                'common/dashboard',
                self::USER_TOKEN_TEXT . $this->session->data['user_token'],
                true
            ),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link(
                self::MARKETPALCE_EXTENSION,
                self::USER_TOKEN_TEXT . $this->session->data['user_token'] . self::TYPE_PAYMENT,
                true
            ),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                self::PAYMENT_METHOD,
                self::USER_TOKEN_TEXT . $this->session->data['user_token'],
                true
            ),
        );

        $data['action'] = $this->url->link(
            self::PAYMENT_METHOD,
            self::USER_TOKEN_TEXT . $this->session->data['user_token'],
            true
        );
        $data['cancel'] = $this->url->link(
            self::MARKETPALCE_EXTENSION,
            self::USER_TOKEN_TEXT . $this->session->data['user_token'] . self::TYPE_PAYMENT,
            true
        );

        if (isset($this->request->post['payment_dpo_total'])) {
            $data['payment_dpo_total'] = $this->request->post['payment_dpo_total'];
        } else {
            $data['payment_dpo_total'] = $this->config->get('payment_dpo_total');
        }

        if (isset($this->request->post['payment_dpo_order_status_id'])) {
            $data['payment_dpo_order_status_id'] = $this->request->post['payment_dpo_order_status_id'];
        } else {
            $data['payment_dpo_order_status_id'] = $this->config->get('payment_dpo_order_status_id');
        }

        if (isset($this->request->post['payment_dpo_success_order_status_id'])) {
            $data['payment_dpo_success_order_status_id'] = $this->request->post['payment_dpo_success_order_status_id'];
        } else {
            $data['payment_dpo_success_order_status_id'] = $this->config->get('payment_dpo_success_order_status_id');
        }

        if (isset($this->request->post['payment_dpo_failed_order_status_id'])) {
            $data['payment_dpo_failed_order_status_id'] = $this->request->post['payment_dpo_failed_order_status_id'];
        } else {
            $data['payment_dpo_failed_order_status_id'] = $this->config->get('payment_dpo_failed_order_status_id');
        }

        if (isset($this->request->post['payment_dpo_cancelled_order_status_id'])) {
            $data['payment_dpo_cancelled_order_status_id'] = $this->request->post['payment_dpo_cancelled_order_status_id'];
        } else {
            $data['payment_dpo_cancelled_order_status_id'] = $this->config->get(
                'payment_dpo_cancelled_order_status_id'
            );
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_dpo_geo_zone_id'])) {
            $data['payment_dpo_geo_zone_id'] = $this->request->post['payment_dpo_geo_zone_id'];
        } else {
            $data['payment_dpo_geo_zone_id'] = $this->config->get('payment_dpo_geo_zone_id');
        }

        $this->load->model('localisation/geo_zone');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        if (isset($this->request->post['payment_dpo_status'])) {
            $data['payment_dpo_status'] = $this->request->post['payment_dpo_status'];
        } else {
            $data['payment_dpo_status'] = $this->config->get('payment_dpo_status');
        }

        if (isset($this->request->post['payment_dpo_sort_order'])) {
            $data['payment_dpo_sort_order'] = $this->request->post['payment_dpo_sort_order'];
        } else {
            $data['payment_dpo_sort_order'] = $this->config->get('payment_dpo_sort_order');
        }

        if (isset($this->request->post['payment_dpo_merchant_token'])) {
            $data['payment_dpo_merchant_token'] = $this->request->post['payment_dpo_merchant_token'];
        } else {
            $data['payment_dpo_merchant_token'] = $this->config->get('payment_dpo_merchant_token');
        }

        if (isset($this->request->post['payment_dpo_service_type'])) {
            $data['payment_dpo_service_type'] = $this->request->post['payment_dpo_service_type'];
        } else {
            $data['payment_dpo_service_type'] = $this->config->get('payment_dpo_service_type');
        }

        if (isset($this->request->post['payment_dpo_testmode'])) {
            $data['payment_dpo_testmode'] = $this->request->post['payment_dpo_testmode'];
        } else {
            $data['payment_dpo_testmode'] = $this->config->get('payment_dpo_testmode');
        }

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view(self::PAYMENT_METHOD, $data));
    }

    protected function validate()
    {
        if ( ! $this->user->hasPermission('modify', self::PAYMENT_METHOD)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return ! $this->error;
    }
}
