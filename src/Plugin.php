<?php

namespace INDIGIT;

class Plugin
{

    /**
     * @var null|\WC_Order
     */
    protected $order = null;

    /**
     * The single instance of the class.
     *
     * @var \WC_Payment_Gateways
     * @since 2.1.0
     */
    protected static $_instance = null;

    /**
     * Main INDIGIT_Manager Instance.
     *
     * Ensures only one instance of INDIGIT_Manager is loaded or can be loaded.
     *
     * @return static
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Register administration menu
     */
    public function registerAdminMenu()
    {
        add_action('admin_menu', function () {
            // Add INDIGIT management page menu item
            add_menu_page('CTT', 'CTT', 'manage_options', 'ctt_manage', function () {

                /** @var \WC_Payment_Gateway[] $available_gateways */
                $available_gateways = WC()->payment_gateways()->payment_gateways();
                $gateways = [];
                foreach ($available_gateways as $gateway) {
                    $gateways[$gateway->id] = $gateway->title;
                }

                $settings = apply_filters(
                    'woocommerce_general_settings',
                    array(

                        // CTT
                        array(
                            'id' => 'indigit_ctt',
                            'type' => 'title',
                            'title' => __('CTT', 'woocommerce'),
                            'desc' => __('Configurações relativas aos envios por CTT', 'woocommerce'),
                        ),
                        array(
                            'id' => INDIGIT_CTT_ENABLED,
                            'type' => 'checkbox',
                            'desc' => __('Activar', 'woocommerce'),
                            'default' => 'no',
                            'desc_tip' => false,
                            'show_if_checked' => 'yes',
                            'checkboxgroup' => 'end',
                        ),
                        array(
                            'id' => INDIGIT_CTT_PRODUCTION,
                            'type' => 'checkbox',
                            'desc' => __('Activar Modo Produção', 'woocommerce'),
                            'default' => 'no',
                            'desc_tip' => false,
                            'show_if_checked' => 'yes',
                            'checkboxgroup' => 'end',
                        ),
                        array(
                            'id' => INDIGIT_CTT_AUTHENTICATION_ID,
                            'type' => 'text',
                            'title' => __('Authentication ID', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false,
                        ),
                        array(
                            'id' => INDIGIT_CTT_CLIENT_ID,
                            'type' => 'text',
                            'title' => __('Client ID', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false,
                        ),
                        array(
                            'id' => INDIGIT_CTT_USER_ID,
                            'type' => 'text',
                            'title' => __('User ID', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false,
                        ),
                        array(
                            'id' => INDIGIT_CTT_CONTRACT_ID,
                            'type' => 'text',
                            'title' => __('Contract ID', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false,
                        ),
                        array(
                            'id' => INDIGIT_CTT_DISTRIBUTION_CHANNEL_ID,
                            'type' => 'text',
                            'title' => __('Distribution Channel ID', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false,
                        ),
                        array(
                            'id' => INDIGIT_CTT_SUB_PRODUCT_ID,
                            'type' => 'text',
                            'title' => __('Sub Product ID', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false,
                        ),
                        array(
                            'id' => INDIGIT_CTT_REFERENCE_PREFIX,
                            'type' => 'text',
                            'title' => __('Sub Reference Prefix', 'woocommerce'),
                            'desc' => __('Nome da empresa (usado para prefixos)', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false,
                        ),
                        array(
                            'id' => INDIGIT_CTT_PHONE_NUMBER,
                            'type' => 'text',
                            'title' => __('Numero Telemovel', 'woocommerce'),
                            'desc' => __('Numero de telemovel de contacto da encomenda', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => false,
                        ),
                        array(
                            'id' => 'indigit_ctt',
                            'type' => 'sectionend',
                        ),
                    )
                );

                // Update if _POST is not empty
                woocommerce_update_options($settings);

                echo '<form method="POST" action="">';
                // Show form fields
                woocommerce_admin_fields($settings);
                echo '<p class="submit">
                    <button name="save" class="button-primary" type="submit" value="Guardar">Guardar</button>
                </p>';
                echo wp_nonce_field('woocommerce-settings');
                echo '</form>';
            });

            // Add INDIGIT management page menu item
            add_submenu_page(null, 'CTT', 'CTT', 'manage_options', 'ctt_generate', function () {
                $order_id = $_GET['order_id'];

                // Fetch order from database
                $this->order = new \WC_Order((int)$order_id);

                try {
                    get_option(INDIGIT_CTT_ENABLED) === 'yes' && $this->processCTT(null);

                    wp_redirect(admin_url(sprintf('post.php?post=%s&action=edit', $order_id)));
                    exit;

                } catch (\Exception $e) {
                    // Store some log info
                    indigit_log('>> CTT', $e);

                    echo $e->getMessage();
                }
            });
        });

        return $this;
    }

    /**
     * Register order listeners
     *
     * @link https://docs.woocommerce.com/wp-content/uploads/2013/05/woocommerce-order-process-diagram.png
     */
    public function registerListeners()
    {
        // moloni connect
        add_action('moloni_order_processed', function ($info) {
            list($order_id, $pdf) = $info;

            // Fetch order from database
            $this->order = new \WC_Order((int)$order_id);

            try {
                get_option(INDIGIT_CTT_ENABLED) === 'yes' && get_option(INDIGIT_CTT_AUTOMATIC) === 'yes' && $this->processCTT($pdf);
            } catch (\Exception $e) {
                // Store some log info
                indigit_log('>> CTT', $e);
            }
        });

        add_action('woocommerce_admin_order_data_after_shipping_address', function (\WC_Order $order) {

            $totalWeight = 0;
            foreach($order->get_items() as $item_id => $product_item) {
                /** @var $product_item \WC_Order_Item_Product */
                $quantity = $product_item->get_quantity();

                $weight = null;
                $meta_data = $product_item->get_formatted_meta_data('');
                if ($meta_data) {
                    foreach ($meta_data as $meta_id => $meta) {
                        if (strpos($meta->display_key, 'peso') !== false) {
                            $weight = round(trim(strip_tags($meta->display_value)));
                        }
                    }
                }

                $product_weight = $weight ?? $product_item->get_product()->get_weight();
                $totalWeight += ($product_weight * $quantity);
            }

            echo sprintf('<p><strong>Peso Total:</strong> %s%s</p>', $totalWeight, get_option('woocommerce_weight_unit'));
        }, 10, 1);

        // Add order extra information
        add_action('add_meta_boxes', function () {
            add_meta_box('indigit_add_meta_box', 'Factura & CTT', [$this, 'showINDIGITView'], 'shop_order', 'side', 'core');
        });
    }

    /**
     * Process order and send to CTTExpresso
     *
     * @param string|null $invoicePDF
     */
    public function processCTT($invoicePDF = null)
    {
        $cttPDF = (new CTT(
            $this->order,
            get_option(INDIGIT_CTT_PRODUCTION) === 'yes',
            get_option(INDIGIT_CTT_AUTHENTICATION_ID),
            get_option(INDIGIT_CTT_CLIENT_ID),
            get_option(INDIGIT_CTT_USER_ID),
            get_option(INDIGIT_CTT_CONTRACT_ID),
            get_option(INDIGIT_CTT_DISTRIBUTION_CHANNEL_ID),
            get_option(INDIGIT_CTT_SUB_PRODUCT_ID),
            get_option(INDIGIT_CTT_REFERENCE_PREFIX),
            get_option(INDIGIT_CTT_PHONE_NUMBER)
        ))->createShipment();

        if (defined('EMAIL_SEND_INTERNAL')) {
            $subject = sprintf('Envio de Documentos (%s Venda #%s)', get_option(INDIGIT_CTT_REFERENCE_PREFIX), $this->order->get_id());

            $replace = [
                '{{nome_empresa}}' => get_option(INDIGIT_CTT_REFERENCE_PREFIX),
                '{{data_hoje}}' => date('Y-m-d'),
                '{{nome_cliente}}' => $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name(),
                '{{documento_numero}}' => $this->order->get_id(),
                '{{link}}' => esc_url(admin_url(sprintf('post.php?post=%s&action=edit', $this->order->get_id()))),
            ];
            $message = str_replace(array_keys($replace), $replace, $this->getEmailTemplate());

            indigit_log('MAIL', $this->order->get_id(), ['content' => $message, $cttPDF, $invoicePDF]); // log
            wp_mail(EMAIL_SEND_INTERNAL, $subject, $message, ['Content-Type: text/html; charset=UTF-8'], array_filter([$cttPDF, $invoicePDF]));

            $this->order->add_order_note(__('Documentos enviados para: ' . EMAIL_SEND_INTERNAL));
        }
    }

    /**
     * Show order extra info related to Invoice and CTT label
     *
     * @param \WP_Post $post
     */
    public function showINDIGITView($post)
    {
        if (in_array($post->post_status, ['wc-completed'])):
            // If we already have references for
            $cttReferences = get_post_meta($post->ID, '_ctt_references', true);
            $cttFilename = get_post_meta($post->ID, '_ctt_file', true);
            $moloniID = get_post_meta($post->ID, '_moloni_sent', true);
            $moloniFilename = get_post_meta($post->ID, '_moloni_file', true);
            // standardize
            $cttFilename = $cttFilename !== '' ? $cttFilename : null;
            $moloniID = $moloniID !== '' ? $moloniID : null;
            $moloniFilename = $moloniFilename !== '' ? $moloniFilename : null;

            $cttReference = null;
            if ($cttReferences !== ''):
                [$cttReference, $lastReference, $request_id] = explode("||##||", $cttReferences);
            endif;

            $upload_dir = wp_upload_dir();
            if(null !== $cttFilename && file_exists(sprintf('%s/%s', trailingslashit($upload_dir['basedir']), $cttFilename))): ?>
            <a type="button" class="button button-primary" target="_blank" href="<?php echo sprintf('%s/%s', $upload_dir['baseurl'], $cttFilename); ?>" style="margin-top: 10px; float:right;">CTT <?php echo $cttReference ?? 'Guia' ?></a>
            <?php else: ?>
            <a type="button" class="button button-primary" href="<?php echo admin_url('admin.php?page=ctt_generate&order_id=' . $post->ID); ?>" style="margin-top: 10px; float:right;">!! GERAR GUIA CTT !!</a>
            <?php endif;

            if(null !== $moloniFilename && file_exists(sprintf('%s/%s', trailingslashit($upload_dir['basedir']), $moloniFilename))): ?>
            <a type="button" class="button button-primary" target="_blank" href="<?php echo sprintf('%s/%s', $upload_dir['baseurl'], $moloniFilename); ?>" style="margin-top: 10px; float:right;">Factura <?php echo $moloniID ?? '' ?></a>
            <?php endif; ?>

            <div style="clear:both"></div>

        <?php endif;
    }

    /**
     * Generates a UUID
     *
     * @return string
     */
    public function generateUUID()
    {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',

            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        return strtolower($uuid);
    }

    /**
     * Get email template
     *
     * @return string
     */
    protected function getEmailTemplate()
    {
        $template =<<<EOL
<table width="800" border="0" cellspacing="0" cellpadding="0" align="center" style="color:#333333;font-family:Arial, Helvetica, sans-serif, Tahoma, Verdana, Geneva;font-size:12px;line-height:150%;">
	<tbody>
		<tr>
			<td style="text-align:left;padding:10px 0px 10px 10px;border-bottom:1px solid #dddddd;">
				<table width="100%" cellpadding="0" cellspacing="0" border="0" style="color:#333333;font-family:Arial, Helvetica, sans-serif, Tahoma, Verdana, Geneva;font-size:12px;line-height:150%;">
					<tbody>
						<tr>
							<td style="width:380px;text-align:left;vertical-align:bottom;">
								{{nome_empresa}}
							</td>
							
							<td style="text-align:right;vertical-align:bottom;font-size:34px;font-family:Arial, Helvetica, sans-serif;color:#81A824;padding:0 10px 10px 0;">
								<span style="font-size:11px;font-style:italic;color:#999999;display:block;">{{data_hoje}}</span>
								<span style="font-size:22px;">Documentos em anexo</span>
							</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
		<tr>
			<td style="text-align:left;padding:10px 0px 10px 0px;">			
				<table width="100%" border="0" cellspacing="0" cellpadding="0" style="color:#333333;font-family:Arial, Helvetica, sans-serif, Tahoma, Verdana, Geneva;font-size:12px;line-height:150%;">
					<tbody>
						<tr>
							<td style="text-align:left;vertical-align:top;padding:10px 6px 6px 6px;font-family:Arial, Helvetica, sans-serif, Tahoma, Verdana, Geneva;font-size:12px;line-height:150%;">
								Segue em anexo os documentos para a venda #{{documento_numero}} para o cliente <b>{{nome_cliente}}</b>.<br>
								<a href="{{link}}">Link directo para a compra</a></br>
								<p>Com os melhores cumprimentos,<br>{{nome_empresa}}</p>
							</td>
						</tr>
						<tr>
							<td style="height:40px;">&nbsp;</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>
EOL;

        return $template;
    }

}
