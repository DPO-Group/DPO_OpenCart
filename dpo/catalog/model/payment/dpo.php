<?php
/*
 * Copyright (c) 2024 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Opencart\Catalog\Model\Extension\Dpo\Payment;

use Opencart\System\Engine\Model;

class Dpo extends Model
{

    public function getMethods($address, $total = null)
    {

        $this->load->language('extension/dpo/payment/dpo');

        if ($this->config->get('payment_dpo_title') == "") {
            $title = $this->language->get('text_title');
        } else {
            $title = $this->config->get('payment_dpo_title');
        }

        $method_data = array();

        $option_data['dpo'] = [
            'code' => 'dpo.dpo',
            'name' => $this->language->get('text_title')
        ];

        $method_data = array(
            'code' => 'dpo',
            'name' => $title,
            'sort_order' => $this->config->get('payment_dpo_sort_order'),
            'option' => $option_data,
        );


        return $method_data;
    }
}
