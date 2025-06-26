<?php
/*
 * Copyright (c) 2025 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Opencart\Catalog\Model\Extension\Dpo\Payment;

use Dpo\Common\Dpo as DpoCommon;
use Opencart\System\Engine\Model;

class Dpo extends Model
{

    public function getMethods($address, $total = null): array
    {
        $this->load->language('extension/dpo/payment/dpo');

        if ($this->config->get('payment_dpo_title') == "") {
            $title = $this->language->get('text_title');
        } else {
            $title = $this->config->get('payment_dpo_title');
        }

        $option_data['dpo'] = [
            'code' => 'dpo.dpo',
            'name' => $this->language->get('text_title')
        ];

        return array(
            'code'       => 'dpo',
            'name'       => $title,
            'sort_order' => $this->config->get('payment_dpo_sort_order'),
            'option'     => $option_data,
        );
    }

    /**
     * @param array|string $tokens
     * @param array $data
     * @param $order_info
     * @param string $tableName
     *
     * @return array
     * @throws \Exception
     */
    public function createTransaction(
        array|string $tokens,
        array $data,
        $order_info,
        string $tableName,
        DpoCommon $dpopay
    ): array {
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
        INSERT INTO {$tableName} (
            customer_id, order_id, dpo_reference, dpo_data, dpo_session, date_created, date_modified
        ) VALUES (
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

            // Verify the token
            $verify = $this->verifyData($dpopay, $data);

            if ($verify != "") {
                $verify = new \SimpleXMLElement($verify);
                if ($verify->Result->__toString() === '900') {
                    return [
                        'transToken' => $tokens['transToken'],
                        'payUrl'     => $dpopay->getPayUrl(),
                    ];
                }
            }
        }

        return [
            'resultExplanation' => $tokens['resultExplanation']
                                   ?? 'There was a problem making a payment with DPO Pay'
        ];
    }


    /**
     * @param $currency
     *
     * @return mixed
     */
    public function getCurrency($currency): mixed
    {
        if ($currency == '' && $this->config->get('config_currency') != '') {
            $currency = trim(htmlspecialchars($this->config->get('config_currency'), ENT_QUOTES, 'UTF-8'));
        }

        return $currency;
    }

    public function getStatus($dpopay, $data): int
    {
        $status = null;
        while ($status === null) {
            $verify = $this->verifyData($dpopay, $data);
            if ($verify != '') {
                $verify = new \SimpleXMLElement($verify);
                $status = match ($verify->Result->__toString()) {
                    '000' => 1,
                    '901' => 2,
                    default => 4,
                };
            }
        }

        return $status;
    }

    /**
     * @param $dpopay
     * @param $data
     *
     * @return mixed
     */
    public function verifyData($dpopay, $data): mixed
    {
        return $dpopay->verifyToken(
            [
                'companyToken' => $data['companyToken'],
                'transToken'   => $data['transToken']
            ]
        );
    }

    public function restoreCart($products, $statusDesc, $orderId): void
    {
        if ($statusDesc !== 'approved' && is_array($products)) {
            // Restore the cart which has already been cleared
            foreach ($products as $product) {
                $options = $this->model_checkout_order->getOptions($orderId, $product['order_product_id']);
                $option  = [];
                if (is_array($options) && count($options) > 0) {
                    $option = $options;
                }
                $this->cart->add($product['product_id'], $product['quantity'], $option);
            }
        }
    }

    /**
     * Cleans up session data after successful transaction
     * This method removes various checkout-related session data
     * to prevent issues with subsequent purchases
     *
     * @return void
     */
    /**
     * Cleans up session data after successful transaction
     * This method removes various checkout-related session data
     * to prevent issues with subsequent purchases
     *
     * @return void
     */
    public function cleanupSession(): void
    {
        $sessionKeys = [
            'shipping_method',
            'shipping_methods',
            'payment_method',
            'payment_methods',
            'guest',
            'comment',
            'order_id',
            'coupon',
            'reward',
            'voucher',
            'vouchers',
            'totals',
        ];

        foreach ($sessionKeys as $key) {
            unset($this->session->data[$key]);
        }
    }
}
