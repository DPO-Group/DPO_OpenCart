<?php
/*
 * Copyright (c) 2021 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class ModelExtensionPaymentDpo extends Model
{

    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/dpo');

        $query = $this->db->query(
            "SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get(
                'payment_dpo_geo_zone_id'
            ) . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')"
        );

        if ($this->config->get('payment_dpo_total') > 0 && $this->config->get('payment_dpo_total') > $total) {
            $status = false;
        } elseif ( ! $this->config->get('payment_dpo_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            $method_data = array(
                'code'       => 'dpo',
                'title'      => $this->language->get('text_dpo_checkout') . ' <img src="' . $this->config->get(
                        'config_ssl'
                    ) . 'catalog/view/theme/default/image/dpo.png" alt="DPO Group" title="DPO Group" style="border: 0;" />',
                'terms'      => '',
                'sort_order' => $this->config->get('payment_dpo_sort_order'),
            );
        }

        return $method_data;
    }
}
