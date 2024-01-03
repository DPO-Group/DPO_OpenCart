<?php
/*
 * Copyright (c) 2024 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Opencart\Catalog\Controller\Extension\Dpo\Payment;

use Opencart\System\Engine\Controller;
use Opencart\Catalog\Model\Extension\Dpo\Payment\DpoPay;

class Dpo extends Controller
{

    const CHECKOUT_ORDER      = 'checkout/order';
    const INFORMATION_CONTACT = 'information/contact';
    private $tableName = DB_PREFIX . 'dpo_transaction';

    /**
     * Entry point from OC checkout
     *
     * @return void
     * @throws \Exception
     */
    public function index()
    {
        unset($this->session->data['REFERENCE']);
        $testMode     = $this->config->get('payment_dpo_testmode');
        $companyToken = $this->config->get('payment_dpo_merchant_token');
        $serviceType  = $this->config->get('payment_dpo_service_type');

        $dpopay = new DpoPay($companyToken, $serviceType, $testMode);

        $data['text_loading']   = $this->language->get('text_loading');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['continue']       = $this->language->get('payment_url');

        $this->load->model(self::CHECKOUT_ORDER);

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        if ($order_info) {
            $preAmount = number_format($order_info['total'], 2, '.', '');
            $reference = htmlspecialchars($order_info['order_id']);
            $amount    = filter_var(
                $preAmount,
                FILTER_SANITIZE_NUMBER_FLOAT,
                FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND
            );
            $currency  = $order_info['currency_code'];

            if ($currency == '' && $this->config->get('config_currency') != '') {
                $currency = filter_var($this->config->get('config_currency'), FILTER_SANITIZE_STRING);
            }

            $returnUrl = filter_var(
                $this->url->link('extension/dpo/payment/dpo|dpo_return', '', true),
                FILTER_SANITIZE_URL
            );
            $backUrl   = filter_var($this->url->link('checkout/checkout', '', true), FILTER_SANITIZE_URL);
            $email     = filter_var($order_info['email'], FILTER_SANITIZE_EMAIL);

            $data                       = [];
            $data['companyToken']       = $dpopay->getCompanyToken();
            $data['accountType']        = $dpopay->getServiceType();
            $data['paymentAmount']      = $amount;
            $data['paymentCurrency']    = $currency;
            $data['customerFirstName']  = $order_info['firstname'];
            $data['customerLastName']   = $order_info['lastname'];
            $data['customerAddress']    = !$order_info['payment_address_1']
                ? $order_info['shipping_address_1'] : $order_info['payment_address_1'];
            $data['customerAddress']    .= !$order_info['payment_address_2']
                ? $order_info['shipping_address_2'] : $order_info['payment_address_2'];
            $data['customerCity']       = !$order_info['payment_city']
                ? $order_info['shipping_city'] : $order_info['payment_city'];
            $data['customerPhone']      = str_replace(['+', '-', '(', ')'], '', $order_info['telephone']);
            $data['redirectURL']        = $returnUrl;
            $data['backUrl']            = $backUrl;
            $data['customerEmail']      = $email;
            $data['companyRef']         = $reference;
            $data['payment_country']    = $order_info['payment_iso_code_2'];
            $data['payment_country_id'] = $order_info['payment_country_id'];
            $data['payment_postcode']   = !$order_info['payment_postcode']
                ? $order_info['shipping_postcode'] : $order_info['payment_postcode'];

            $tokens = $dpopay->createToken($data);
            if ($tokens['success'] === true) {
                $data['transToken'] = $tokens['transToken'];

                $createDate = date('Y-m-d H:i:s');
                $dpoData    = serialize($order_info);
                $dpoSession = [
                    'customer' => $this->customer,
                ];
                $session    = base64_encode(serialize($dpoSession));

                // Store order data
                $query = <<<SQL
                insert into 
                    {$this->tableName} (customer_id, order_id, dpo_reference, dpo_data, dpo_session, date_created, date_modified)
                values (
                        '{$order_info['customer_id']}',
                        '{$order_info['order_id']}',
                        '{$data['transToken']}',
                        '{$dpoData}',
                        '{$session}',
                        '{$createDate}',
                        '{$createDate}'
                )
                SQL;
                $this->db->query($query);

                $verify = $dpopay->verifyToken($data);
                if ($verify != "") {
                    $verify = new \SimpleXMLElement($verify);
                    if ($verify->Result->__toString() === '900') {
                        $data['ID']     = $tokens['transToken'];
                        $data['payUrl'] = $dpopay->getDpoGateway();
                    }
                }

                return $this->load->view('extension/dpo/payment/dpo', $data);
            } else {
                return $tokens['resultExplanation'] ?? 'There was a problem making a payment with DPO Pay';
            }
            header('Location: ' . $data['backUrl']);
            exit(0);
        }
        print_r(
            'Order could not be found, order_id: ' . $this->session->data['order_id'] . '. Log support ticket to <a href="' . $this->url->link(
                self::INFORMATION_CONTACT
            ) . '">shop owner</a>'
        );
        exit(0);
    }

    /**
     * GET return from DPO payment portal
     *
     * @return void
     * @throws \Exception
     */
    public function dpo_return()
    {
        // Database
        $config = $this->config;
        if ($config->get('db_autostart')) {
            $db = new \Opencart\System\Library\DB(
                $config->get('db_engine'),
                $config->get('db_hostname'),
                $config->get('db_username'),
                $config->get('db_password'),
                $config->get('db_database'),
                $config->get('db_port')
            );
            $this->registry->set('db', $db);

            // Sync PHP and DB time zones
            $db->query("SET time_zone = '" . $db->escape(date('P')) . "'");
        }

        $this->load->language('extension/dpo/checkout/dpo');
        $statusDesc = '';
        $status     = '';

        if (isset($_GET['TransactionToken'])) {
            $transToken                      = $_GET['TransactionToken'];
            $orderId                         = (int)substr($_GET['CompanyRef'], 0, -7);
            $this->session->data['order_id'] = $orderId;

            $testMode     = $this->config->get('payment_dpo_testmode');
            $companyToken = $this->config->get('payment_dpo_merchant_token');
            $serviceType  = $this->config->get('payment_dpo_service_type');

            $dpopay = new DpoPay($companyToken, $serviceType, $testMode);

            // Retrieve order data
            $query         = "select * from {$this->tableName} where dpo_reference = '{$transToken}'";
            $result        = $this->db->query($query);
            $dpoSession    = unserialize(base64_decode($result->rows[0]['dpo_session']));
            $orderInfo     = unserialize($result->rows[0]['dpo_data']);
            $customerId    = (int)$orderInfo['customer_id'];

            $data                 = [];
            $data['transToken']   = $transToken;
            $data['companyToken'] = $dpopay->getCompanyToken();

            $status = $this->getStatus($dpopay, $data);

            $DpoResponse = $this->getDpoResponse($status, $orderInfo);
            $statusDesc  = $DpoResponse['statusDesc'];
        }

        $verifyResponseData = $dpopay->verifyToken($data);
        $parseVerifyData = new \SimpleXMLElement($verifyResponseData);

        if ($status == 1 && $_GET['CompanyRef'] == $parseVerifyData->CompanyRef->__toString()) {
            $data['heading_title'] = sprintf($this->language->get('heading_title'), $statusDesc);
            $this->document->setTitle($data['heading_title']);
        } else {
            $data['heading_title'] = sprintf(
                'Transaction status verification failed. Please contact the shop owner to confirm transaction status.'
            );
            $this->document->setTitle($data['heading_title']);
        }

        $breadcrumbs         = array();
        $data['breadcrumbs'] = $this->getBreadCrumbData($breadcrumbs);

        if ($customerId > 0) {
            $data['text_message'] = sprintf(
                $this->language->get('text_customer'),
                $this->url->link('account/account', '', 'SSL'),
                $this->url->link('account/order', '', 'SSL'),
                $this->url->link('account/download', '', 'SSL'),
                $this->url->link(self::INFORMATION_CONTACT)
            );
        } else {
            $data['text_message'] = sprintf(
                $this->language->get('text_guest'),
                $this->url->link(self::INFORMATION_CONTACT)
            );
        }

        try {
            $data['button_continue'] = $this->language->get('button_continue');
            $data['continue']        = $this->url->link('common/home');
            $data['column_left']     = $this->load->controller('common/column_left');
            $data['column_right']    = $this->load->controller('common/column_right');
            $data['content_top']     = $this->load->controller('common/content_top');
            $data['content_bottom']  = $this->load->controller('common/content_bottom');
            $data['footer']          = $this->load->controller('common/footer');
            $data['header']          = $this->load->controller('common/header');
        } catch (\Exception $exception) {
            $exception->getMessage();
        }
        if ((int)$status === 1) {
            $cartTable = DB_PREFIX . 'cart';
            $this->db->query("delete from {$cartTable} where customer_id = '$customerId'");
        }
        $this->customer = $dpoSession['customer'] ?? null;
        $this->response->setOutput($this->load->view('common/success', $data));
    }

    public function getDpoResponse($status, $orderInfo)
    {
        $this->cart->clear();
        $data       = array();
        $customerId = (int)$orderInfo['customer_id'];

        // Add to activity log
        $this->load->model('account/activity');

        if ($customerId > 0) {
            $activity_data = array(
                'customer_id' => $customerId,
                'name'        => $orderInfo['firstname'] . ' ' . $orderInfo['lastname'],
                'order_id'    => $orderInfo['order_id'],
            );
            $this->model_account_activity->addActivity('order_account', $activity_data);
        } else {
            $activity_data = array(
                'name'        => $orderInfo['firstname'] . ' ' . $orderInfo['lastname'],
                'order_id'    => $orderInfo['order_id'],
            );
            $this->model_account_activity->addActivity('order_guest', $activity_data);
        }

        unset($this->session->data['REFERENCE']);

        $data['orderStatusId'] = '7';
        $TRANSACTION_STATUS    = $status;

        if (isset($TRANSACTION_STATUS)) {
            $status = 'ok';

            if ($TRANSACTION_STATUS == 0) {
                $data['orderStatusId'] = 1;
                $data['statusDesc']    = 'pending';
            } elseif ($TRANSACTION_STATUS == 1) {
                $data['orderStatusId'] = 5;
                $data['statusDesc']    = 'approved';
            } elseif ($TRANSACTION_STATUS == 2) {
                $data['orderStatusId'] = 8;
                $data['statusDesc']    = 'declined';
            } elseif ($TRANSACTION_STATUS == 4) {
                $data['orderStatusId'] = 7;
                $data['statusDesc']    = 'cancelled';
            }

            $data['resultsComment'] = "Returned from Dpo with a status of " . $data['statusDesc'];
        } else {
            $data['orderStatusId']  = 1;
            $data['statusDesc']     = 'pending';
            $data['resultsComment'] = 'Transaction status verification failed. Please contact the shop owner to confirm transaction status.';
        }

        $this->load->model(self::CHECKOUT_ORDER);
        $this->model_checkout_order->addHistory(
            $this->session->data['order_id'],
            $data['orderStatusId'],
            $data['resultsComment'],
            true
        );
        unset($this->session->data['shipping_method']);
        unset($this->session->data['shipping_methods']);
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
        unset($this->session->data['guest']);
        unset($this->session->data['comment']);
        unset($this->session->data['order_id']);
        unset($this->session->data['coupon']);
        unset($this->session->data['reward']);
        unset($this->session->data['voucher']);
        unset($this->session->data['vouchers']);
        unset($this->session->data['totals']);

        return $data;
    }

    public function getBreadCrumbData($breadcrumbs)
    {
        $breadcrumbs[] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home'),
        );

        $breadcrumbs[] = array(
            'text' => $this->language->get('text_basket'),
            'href' => $this->url->link('checkout/cart'),
        );

        $breadcrumbs[] = array(
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', 'SSL'),
        );

        $breadcrumbs[] = array(
            'text' => $this->language->get('text_success'),
            'href' => $this->url->link('checkout/success'),
        );

        return $breadcrumbs;
    }

    public function getStatus($dpopay, $data)
    {
        $status = null;
        while ($status === null) {
            $verify = $dpopay->verifyToken($data);
            if ($verify != '') {
                $verify = new \SimpleXMLElement($verify);
                switch ($verify->Result->__toString()) {
                    case '000':
                        $status = 1;
                        break;
                    case '901':
                        $status = 2;
                        break;
                    case '904':
                    default:
                        $status = 4;
                        break;
                }
            }
        }

        return $status;
    }

    public function notify_handler()
    {
        // Notify Dpo that information has been received
        echo 'OK';
        $errors = false;
        if (isset($ERROR)) {
            $errors = true;
        }

        $checkSumData       = $this->getCheckSumParams();
        $checkSumParams     = $checkSumData['checkSumParams'];
        $notify_checksum    = $checkSumData['notify_checksum'];
        $transaction_status = $checkSumData['transaction_status'];
        $order_id           = $checkSumData['order_id'];
        $pay_method_desc    = $checkSumData['pay_method_desc'];

        $checkSumParams .= $this->config->get('payment_dpo_service_type');
        $checkSumParams = md5($checkSumParams);
        if ($checkSumParams != $notify_checksum) {
            $errors = true;
        }
        $orderStatusId = 7;

        if (!$errors) {
            if ($transaction_status == 0) {
                $orderStatusId = 1;
                $statusDesc    = 'pending';
            } elseif ($transaction_status == 1) {
                $orderStatusId = 2;
                $statusDesc    = 'approved';
            } elseif ($transaction_status == 2) {
                $orderStatusId = 8;
                $statusDesc    = 'declined';
            } elseif ($transaction_status == 4) {
                $orderStatusId = 7;
                $statusDesc    = 'cancelled';
            }

            $resultsComment = "Notify from Dpo with a status of " . $statusDesc . $pay_method_desc;
            $this->load->model(self::CHECKOUT_ORDER);
            $this->model_checkout_order->addOrderHistory($order_id, $orderStatusId, $resultsComment, true);
        }
    }


    public function getCheckSumParams()
    {
        $transaction_status = '';
        $order_id           = '';
        $pay_method_desc    = '';
        $checkSumParams     = '';
        $notify_checksum    = '';
        foreach ($_POST as $key => $val) {
            if ($key == 'DPO_ID') {
                $checkSumParams .= $this->config->get('payment_dpo_merchant_token');
            }

            if ($key != 'CHECKSUM' && $key != 'DPO_ID') {
                $checkSumParams .= $val;
            }

            if ($key == 'CHECKSUM') {
                $notify_checksum = $val;
            }

            if ($key == 'TRANSACTION_STATUS') {
                $transaction_status = $val;
            }

            if ($key == 'USER1') {
                $order_id = $val;
            }

            if ($key == 'PAY_METHOD_DETAIL') {
                $pay_method_desc = ', using a payment method of ' . $val;
            }
        }
        $data                       = array();
        $data['checkSumParams']     = $checkSumParams;
        $data['notify_checksum']    = $notify_checksum;
        $data['transaction_status'] = $transaction_status;
        $data['order_id']           = $order_id;
        $data['pay_method_desc']    = $pay_method_desc;

        return $data;
    }

    public function confirm()
    {
        if ($this->session->data['payment_method']['code'] == 'dpo') {
            $this->load->model(self::CHECKOUT_ORDER);
            $comment = 'Redirected to Dpo';
            $this->model_checkout_order->addOrderHistory(
                $this->session->data['order_id'],
                $this->config->get('payment_dpo_order_status_id'),
                $comment,
                true
            );
        }
    }

    public function before_redirect()
    {
        $json = array();

        if ($this->session->data['payment_method']['code'] == 'dpo') {
            $this->load->model(self::CHECKOUT_ORDER);
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], 1);
            $json['answer'] = 'success';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
