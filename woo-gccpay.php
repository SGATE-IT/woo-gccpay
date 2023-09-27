<?php
/**
 * Plugin Name: GCCPay Payment for WooCommerce
 * Description: Extends WooCommerce with GCCPay.
 * Version: 1.0.3
 * Text Domain: woo-gccpay
 * Domain Path: /languages
 * Author: alfa
 * github: https://github.com/SGATE-IT/woo-gccpay
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Loading text domain
 */
function load_woo_gccpay_textdomain() {
    load_plugin_textdomain( 'woo-gccpay', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'load_woo_gccpay_textdomain' );

/**
 * Adds plugin page links
 *
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom link (i.e., "Configure")
 */
function woo_gccpay_gateway_plugin_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woo_gccpay' ) . '">' . __( 'Configure', 'woo-gccpay' ) . '</a>'
    );
    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woo_gccpay_gateway_plugin_links' );

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + Woo GCCPay
 */
function woo_gccpay_add_to_gateways( $gateways ) {
    $gateways[] = 'WOO_GCCPAY';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'woo_gccpay_add_to_gateways' );

/**
 * GCCPay Payment for WooCommerce
 *
 * Extends WooCommerce with GCCPay.
 *
 * @class 		WOO_GCCPay
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		alfa
 */
function woo_gccpay_init() {

    /**
     * Make sure WooCommerce is active
     */
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'GCCPay requires WooCommerce to be installed and active. You can download %s here.', 'woo-gccpay' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
        return;
    }

    class WOO_GCCPAY extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->id                   = 'woo_gccpay';
            $this->gccpay_icon            = $this->get_option( 'gccpay_icon' );
            $this->icon                 = ( ! empty( $this->gccpay_icon ) ) ? $this->gccpay_icon : apply_filters( 'woo_gccpay_icon', plugins_url( 'assets/images/gccpay.png' , __FILE__ ) );
            $this->has_fields           = false;
            $this->method_title         = __( 'GCCPAY', 'woo-gccpay' );
            $this->method_description   = __( 'Allows GCCPay', 'woo-gccpay' );
            $this->supports = array("products","refunds");

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title                = $this->get_option( 'title' );
            $this->description          = $this->get_option( 'description' );
            $this->merchant_id          = $this->get_option( 'merchant_id' );
            $this->client_id            = $this->get_option( 'client_id' );
            $this->client_key           = $this->get_option( 'client_key' );
            $this->client_secret        = $this->get_option( 'client_secret' );
            $this->environment_type     = $this->get_option( 'environment_type' );
            $this->checkout_interaction = $this->get_option( 'checkout_interaction' );
            
            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
            add_action( 'woocommerce_api_'.$this->id, array( $this, 'process_response' ) );
            add_action( 'woocommerce_api_'.$this->id."_background", array( $this, 'process_response_background' ) );
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {

            $this->form_fields = apply_filters( 'woo_gccpay_form_fields', array(
                'enabled' => array(
                    'title'         => __( 'Enable/Disable', 'woo-gccpay' ),
                    'type'          => 'checkbox',
                    'label'         => __( 'Enable GCCPay Payment Module.', 'woo-gccpay' ),
                    'default'       => 'yes',
                ),
                'title' => array(
                    'title'         => __( 'Title', 'woo-gccpay' ),
                    'type'          => 'text',
                    'description'   => __( 'This controls the title for the payment method the customer sees during checkout.', 'woo-gccpay' ),
                    'default'       => __( 'GCCPay', 'woo-gccpay' ),
                    'desc_tip'      => true
                ),
                'description' => array(
                    'title'         => __( 'Description', 'woo-gccpay' ),
                    'type'          => 'textarea',
                    'description'   => __( 'Payment method description that the customer will see on your checkout.', 'woo-gccpay' ),
                    'default'       => __( 'Pay with GCCPay', 'woo-gccpay' ),
                    'desc_tip'      => true
                ),
                'gccpay_icon' => array(
                    'title'         => __( 'Icon', 'woo-gccpay' ),
                    'type'          => 'text',
                    'css'           => 'width:100%',
                    'description'   => __( 'Enter an image URL to change the icon.', 'woo-gccpay' ),
                    'desc_tip'      => true
                ),

                'merchant_id' => array(
                    'title'         => __( 'Merchant ID', 'woo-gccpay' ),
                    'type'          => 'text',
                    'description'   => __( 'Merchant ID, given by the GCCPAY', 'woo-gccpay' ),
                    'placeholder'   => __( 'Merchant ID', 'woocommerce' ),
                    'desc_tip'      => true
                ),                
                'client_id' => array(
                    'title'         => __( 'Merchant Client ID', 'woo-gccpay' ),
                    'type'          => 'text',
                    'description'   => __( 'Merchant Client ID, given by the GCCPAY', 'woo-gccpay' ),
                    'placeholder'   => __( 'Merchant Client ID', 'woocommerce' ),
                    'desc_tip'      => true
                ),
                'client_key' => array(
                    'title'         => __( 'Merchant Client Key', 'woo-gccpay' ),
                    'type'          => 'text',
                    'description'   => __( 'Merchant Client Key, given by the GCCPAY', 'woo-gccpay' ),
                    'placeholder'   => __( 'Merchant Client Key', 'woocommerce' ),
                    'desc_tip'      => true
                ),
                'client_secret' => array(
                    'title'         => __( 'Merchant Client Secret', 'woo-gccpay' ),
                    'type'          => 'text',
                    'description'   => __( 'Merchant Client Secret, given by the GCCPAY', 'woo-gccpay' ),
                    'placeholder'   => __( 'Merchant Client Secret', 'woocommerce' ),
                    'desc_tip'      => true
                ),
                'environment_type' => array(
                    'title'         => __( 'environment type', 'woo-gccpay' ),
                    'type'          => 'select',
                    'description'   => __( 'Choose environment of GCCPay type. sandbox is test environment.', 'woo-gccpay' ),
                    'options'       => array( 'sandbox' => 'Sandbox Environment', 'Product' => 'Product Environment' ),
                    'default'       => '1',
                ),
                'checkout_interaction' => array(
                    'title'         => __( 'Checkout Interaction', 'woo-gccpay' ),
                    'type'          => 'select',
                    'description'   => __( 'Choose checkout interaction type. ', 'woo-gccpay' ),
                    'options'       => array( 'lightbox' => 'Lightbox', 'paymentpage' => 'Payment Page' ),
                    'default'       => '1',
                )

            ) );
        }

        /**
         *
         * @param string $uri
         * @param string $method
         * @param string $post
         * @param array $params
         * @return array[]
         */
        private function submitToGCCPay($uri="",$method="",$post="get",$params=[])
        {
            $signArr = [];
            $signArr["uri"] = $uri;
            $signArr["key"] = $this->client_key;
            $signArr["timestamp"] = time();
            $signArr["signMethod"] = "HmacSHA256";
            $signArr["signVersion"] = 1;
            $signArr["method"] = $method;
            
            ksort($signArr);
            $signStr = http_build_query($signArr);
            $sign = base64_encode(hash_hmac('sha256',$signStr, $this->client_secret, true));
            
            $headers = [];
            $headers["Content-Type"] = "application/json";
            $headers["x-auth-signature"] =  $sign;
            $headers["x-auth-key"] =  $this->client_key;
            $headers["x-auth-timestamp"] = $signArr["timestamp"];
            $headers["x-auth-sign-method"] = "HmacSHA256";
            $headers["x-auth-sign-version"] = "1";
            
            if($this->environment_type == "Product")
            {
                $url = "https://gateway.gcc-pay.com/api_v1" . $uri;
            }
            else
            {
                $url = "https://sandbox.gcc-pay.com/api_v1" . $uri;
            }
            
            $data = json_encode($params);
            error_log("woo-gccpay:request[url/header/type/params]=>:".$url."/".json_encode($headers)."/".$post."/".$data);
            
            if($post == "post")
            {
                $result =  wp_remote_post($url,array("headers"=>$headers,"body"=>$data));
            }
            else
            {
                $result =  wp_remote_get($url,array("headers"=>$headers));
            }
            error_log("woo-gccpay:response=>".json_encode($result));
            
            if ( is_wp_error( $result ) )
            {
                wc_add_notice( __( 'Payment error: Failed to communicate with GCCPay server. ', 'woo-gccpay' ), 'error' );
                return array();
                
            }
            
            $ret = json_decode($result["body"],true);
            return $ret;
            
//             $curl = curl_init();
//             curl_setopt($curl, CURLOPT_URL, $url);
//             curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
            
//             $data = json_encode($params);
//             error_log("woo-gccpay:request[url/type/params]=>:".$url."/".$post."/".$data);
//             if(strtolower($post) == "post")
//             {
//                 curl_setopt($curl, CURLOPT_POST, true);
//                 curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
//             }
//             curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//             $result = curl_exec($curl);
//             curl_close($curl);
//             error_log("woo-gccpay:response=>".$result);
            
//             $ret = json_decode($result,true);
//             return $ret;
        }
        
        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            // Prepare session request
            $session_request = array();
            $session_request["merchantOrderId"] = $order_id."_".time();
            $session_request["amount"] = $order->get_total();;
            $session_request["currency"] = get_woocommerce_currency();;
            $session_request["name"] = "User: ".$order->get_user_id().",Order:".$order_id;
            $session_request["notificationURL"] = add_query_arg( array( 'order_id' => $order_id, 'wc-api' => 'woo_gccpay_background' ), $order->get_checkout_payment_url() );;
            $session_request["expiredAt"] = strftime('%Y-%m-%dT%H:%M:%S.000Z',time()+3600*24);
            
            $productList = [];
            foreach ($order->get_items() as $item_id=>$item)
            {
                $productInfo =  $item->get_product();//wc_get_product($item->get_product_id());//       $product = wc_get_product($product_id);$item->get_product();
                $productList[] = [                    
                    "name"=>$item->get_name(),//"light line",
                    "type"=>"physical",//$item->get_type(),//"physical",//"physical","digital"
                    "quantity"=>$item->get_quantity(),//2,
                    "isPreSale"=>false,
                    "estimatedDeliveryAt"=>"2023-07-01T10:11:12Z",
                    "location"=>WC()->countries->get_base_country(),//"HongKong",
                    "price"=>$productInfo->get_price(),
                    "sku"=>$item->get_variation_id(),
                    "productId"=> $item->get_product_id(),
                    "amount"=>$item->get_total(),
                    "avatar"=>wp_get_attachment_url($productInfo->get_image_id(),"full"),//$productInfo->get_image(),
                    "description"=>$productInfo->get_description(),
                    "showURL"=>get_permalink(),//"http://www.baidu.com",
                ];
            }
            $session_request["products"] = $productList;
            $userInfo = $order->get_user();
            $session_request["customer"] = [

                "mobile"=> $order->get_billing_phone(),//"966512345678",
                "email"=> empty($userInfo)?"noemail":$userInfo->user_email,//"alfa@gccpay.com",
                "nickname"=> empty($userInfo)?"nouser":$userInfo->user_nicename,//"gartional",
                "uuid"=> $order->get_user_id(),//"2",
                "level"=> "1",
                "address"=> $order->get_formatted_shipping_address(),//get_shipping_address(),//"noaddress",
                "registeredAt"=> empty($userInfo)?"1970-01-01 00:00:00":$userInfo->user_registered,//"2012-12-01 10:11:12",
            ];
            $session_request["referenceURL"]= rawurlencode( $this->get_return_url( $order ) );
            
            
            $uri = "/merchants/" . $this->merchant_id . "/orders" ;
            $response_json = $this->submitToGCCPay($uri,"merchant.addOrder","post",$session_request);
            
    
            if ( !isset( $response_json["id"] ) ) {

                wc_add_notice( __( 'Payment error:Fail create session.', 'woo-gccpay' )."(1".json_encode($response_json)."2)", 'error' );

                return array(
                    'result'	=> 'fail',
                    'redirect'	=> '',
                );
            }

            update_post_meta( $order_id,'woo_gccpay_successSession', $response_json['id'] );
            update_post_meta( $order_id,'woo_gccpay_successTickey', $response_json['ticket'] );
            
            if($this->environment_type == "Product")
            {
                $baseurl = "https://gateway.gcc-pay.com/";
            }
            else
            {
                $baseurl = "https://sandbox.gcc-pay.com/";
            }
            
            $pay_url = add_query_arg( array(
                'payorderId'    => $response_json['id'],
                'ticket'        => $response_json['ticket'],
                'key'           => $order->get_order_key(),
                'pay_for_order' => false,
            ), $order->get_checkout_payment_url() );
            
            return array(
                'result'	=> 'success',
                'redirect'	=> $pay_url
            );
//             $pay_url = add_query_arg( array(
//                 'orderId'     => $response_json['id'],
//                 'ticket'           => $response_json['ticket'],
//                 'returnURL' => $this->get_return_url( $order ),
//             ), $baseurl);
            
//             error_log("woo-gccpay: payurl/get_checkout_payment_url =>".$pay_url."/".$order->get_checkout_payment_url());
            
//             return array(
//                 'result'	=> 'success',
//                 'redirect'	=> $pay_url
//             );

        }

        /**
         * Print payment buttons in the receipt page
         *
         * @param int $order_id
         */
        public function receipt_page( $order_id ) {

            error_log("woo-gccpay: receipt_page in");

            $payorderyid = sanitize_text_field($_REQUEST['payorderId']);
            $ticketid = sanitize_text_field($_REQUEST['ticket']);
            
            if( ! empty( $payorderyid ) ) {
                $order = wc_get_order( $order_id );
                if($this->environment_type == "Product")
                {
                    $baseurl = "https://gateway.gcc-pay.com/";
                }
                else
                {
                    $baseurl = "https://sandbox.gcc-pay.com/";
                }

                
                ?>
                <?php  
                if( $this->checkout_interaction === 'paymentpage' )
                {
                    $pay_url = add_query_arg( array(
                        'orderId'     => $payorderyid,
                        'ticket'           => $ticketid,
                        'returnURL' => rawurlencode(add_query_arg( array( 'order_id' => $order_id, 'wc-api' => 'woo_gccpay' ), home_url('/') )),
                        
//                         'returnURL' => rawurlencode( $this->get_return_url( $order ) ),
                    ), $baseurl);
                    
                    error_log("woo-gccpay: pay_url:".$pay_url);
                    wp_redirect( $pay_url );
                }
                else
                {
                    
                    $pay_url = add_query_arg( array(
                        'orderId'     => $payorderyid,
                        'ticket'           => $ticketid,
                        'payerInfo'         =>'required',
                        'language' => 'en',
                        'returnURL' => rawurlencode(add_query_arg( array( 'order_id' => $order_id, 'wc-api' => 'woo_gccpay' ), home_url('/') )),
                        // https://wc-eshop.com/checkout/order-pay/8180/?key=wc_order_Hc5epTvinJ4It&payorderId=M448726T2023082511434989286992&ticket=ecOTa2UHhXkVOqhmIX2fRNhK1MjcVpVSWJe841f9UzpWVpFGP38Sf3mts34ymWr2
                        
//                         'returnURL' => rawurlencode( $this->get_return_url( $order ) ),
                    ), $baseurl."embed/mastercard/");
                    ?>
                    <div style="z-index: 9998; display: flex; justify-content: center; align-items: center; background-color: rgba(0,0,0,0.5); border: 0px none transparent; overflow: hidden auto; visibility: visible; margin: 0px; padding: 0px; position: fixed; left: 0px; top: 0px; width: 100%; height: 100%;">
                    <script>
                    function gccpaysuccess()
                    {
                        window.location = "<?php echo esc_url($this->get_return_url( $order ));?>";
                    }
                    </script>
				<iframe title="GCCPay Checkout" src="<?php echo esc_url($pay_url);?>" style="z-index: 9999; display: block; background-color: white; border: 0px none transparent; overflow: hidden auto; visibility: visible;   width: 618px; height: 530px;boder-radius: 10px;"></iframe>
</div>
                    <?php 
                } ?>
                
                <?php
            } else {
                wc_add_notice( __( 'Payment error: Session not found.', 'woo-gccpay' ), 'error' );
                wp_redirect( wc_get_checkout_url() );
                exit;
            }
        }

        /**
         * Handle GCCPay response
         */
        public function process_response () {

            global $woocommerce;
            $order_id = sanitize_text_field($_REQUEST['order_id']);
            
            $order = wc_get_order( $order_id );
            $gccpaySessionid = get_post_meta( $order_id, "woo_gccpay_successSession", true );
            
            error_log("woo-gccpay: process_response in web:orderid =>".$order_id);
            

            if( $gccpaySessionid ) {

                $uri = "/orders/" . $gccpaySessionid;
                $orderinfo =  $this->submitToGCCPay($uri,"order.detail");   
                if(isset($orderinfo["status"]) && $orderinfo["status"] == "paid")
                {
                    $woocommerce->cart->empty_cart();
                    $order->add_order_note( sprintf( __( 'GCCPay Payment completed with Transaction Session(web): %s.', 'woo-gccpay' ), $gccpaySessionid ) );
                    $order->payment_complete( $gccpaySessionid );
                    error_log("woo-gccpay: process_response redrict =>".$this->get_return_url( $order ));
                    if( $this->checkout_interaction === 'paymentpage' )
                    {
                        wp_redirect( $this->get_return_url( $order ) );
                    }
                    else 
                    {
                        ?>
                        <script>
                        parent.gccpaysuccess();
                        </script>
                        <?php 
    //                     wp_redirect( $this->get_return_url( $order ) );
                        exit;
                    }
                } else {
                    $order->add_order_note( __('Payment error: Something went wrong.', 'woo-gccpay') );
                    wc_add_notice( __('Payment error: Something went wrong.', 'woo-gccpay'), 'error' );
                }
                
            } else {
                $order->add_order_note( __('Payment error: Invalid transaction.', 'woo-gccpay') );
                wc_add_notice( __('Payment error: Invalid transaction.', 'woo-gccpay'), 'error' );
            }
            // reaching this line means there is an error, redirect back to checkout page
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        public function process_refund($order_id, $amount = null, $reason = '')
        {
            error_log("woo-gccpay: process_refund :orderid =>".$order_id.";amount=>".$amount.";reason=>".$reason);
           
            $order = wc_get_order($order_id);            
            $gccpayOrderId = get_post_meta( $order_id, "woo_gccpay_successSession", true );

            $refundParams = [];
            $uri = "/orders/".$gccpayOrderId."/refunds";
            $refundParams = [];
            $refundParams["amount"] = $amount;
            $refundParams["reason"] = $reason;
            $wooRefundId = $order_id."_refund_".time();
            $refundParams["merchantRefundId"] = $wooRefundId;

            $refundInfo =  $this->submitToGCCPay($uri,"order.refund","post",$refundParams);
            if(!isset($refundInfo["id"]))
            {
                return false;
            }
            
            update_post_meta($order_id,'woo_gccpay_refunded_'.$wooRefundId, $refundInfo['id'] );
            update_post_meta($order_id, 'woo_gccpay_refunded_'.$wooRefundId."_status", $refundInfo["status"]);

            $order->save();
            return true;
                        
        }
        /**
         * Handle GCCPay notification
         */
        public function process_response_background () {
            
            global $woocommerce;
            $order_id = sanitize_text_field($_REQUEST['order_id']);
            
            $order = wc_get_order( $order_id );
            $gccpaySessionid = get_post_meta( $order_id, "woo_gccpay_successSession", true );
            
            error_log("woo-gccpay: process_response_background in background:orderid =>".$order_id);
            
            
            if( $gccpaySessionid ) {
                
                $uri = "/orders/" . $gccpaySessionid;
                $orderinfo =  $this->submitToGCCPay($uri,"order.detail");
                if(isset($orderinfo["status"]) && $orderinfo["status"] == "paid")
                {
                    $woocommerce->cart->empty_cart();
                    $order->add_order_note( sprintf( __( 'GCCPay Payment completed with Transaction Session(background) : %s.', 'woo-gccpay' ), $gccpaySessionid ) );
                    $order->payment_complete( $gccpaySessionid );
                    $ret = "COMPLETED::".$gccpaySessionid;
                    error_log("woo-gccpay: process_response_background return:".$ret);
                    echo esc_html($ret);
                    exit;
                } else {
                    $order->add_order_note( __('Payment error: Something went wrong.', 'woo-gccpay') );
                    wc_add_notice( __('Payment error: Something went wrong.', 'woo-gccpay'), 'error' );
                }
                
            } else {
                $order->add_order_note( __('Payment error: Invalid transaction.', 'woo-gccpay') );
                wc_add_notice( __('Payment error: Invalid transaction.', 'woo-gccpay'), 'error' );
            }
            // reaching this line means there is an error, redirect back to checkout page
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        /**
         * Converts the WooCommerce country codes to 3-letter ISO codes
         * https://en.wikipedia.org/wiki/ISO_3166-1_alpha-3
         * @param string WooCommerce's 2 letter country code
         * @return string ISO 3-letter country code
         */
        function kia_convert_country_code( $country ) {
            $countries = array(
                'AF' => 'AFG', //Afghanistan
                'AX' => 'ALA', //&#197;land Islands
                'AL' => 'ALB', //Albania
                'DZ' => 'DZA', //Algeria
                'AS' => 'ASM', //American Samoa
                'AD' => 'AND', //Andorra
                'AO' => 'AGO', //Angola
                'AI' => 'AIA', //Anguilla
                'AQ' => 'ATA', //Antarctica
                'AG' => 'ATG', //Antigua and Barbuda
                'AR' => 'ARG', //Argentina
                'AM' => 'ARM', //Armenia
                'AW' => 'ABW', //Aruba
                'AU' => 'AUS', //Australia
                'AT' => 'AUT', //Austria
                'AZ' => 'AZE', //Azerbaijan
                'BS' => 'BHS', //Bahamas
                'BH' => 'BHR', //Bahrain
                'BD' => 'BGD', //Bangladesh
                'BB' => 'BRB', //Barbados
                'BY' => 'BLR', //Belarus
                'BE' => 'BEL', //Belgium
                'BZ' => 'BLZ', //Belize
                'BJ' => 'BEN', //Benin
                'BM' => 'BMU', //Bermuda
                'BT' => 'BTN', //Bhutan
                'BO' => 'BOL', //Bolivia
                'BQ' => 'BES', //Bonaire, Saint Estatius and Saba
                'BA' => 'BIH', //Bosnia and Herzegovina
                'BW' => 'BWA', //Botswana
                'BV' => 'BVT', //Bouvet Islands
                'BR' => 'BRA', //Brazil
                'IO' => 'IOT', //British Indian Ocean Territory
                'BN' => 'BRN', //Brunei
                'BG' => 'BGR', //Bulgaria
                'BF' => 'BFA', //Burkina Faso
                'BI' => 'BDI', //Burundi
                'KH' => 'KHM', //Cambodia
                'CM' => 'CMR', //Cameroon
                'CA' => 'CAN', //Canada
                'CV' => 'CPV', //Cape Verde
                'KY' => 'CYM', //Cayman Islands
                'CF' => 'CAF', //Central African Republic
                'TD' => 'TCD', //Chad
                'CL' => 'CHL', //Chile
                'CN' => 'CHN', //China
                'CX' => 'CXR', //Christmas Island
                'CC' => 'CCK', //Cocos (Keeling) Islands
                'CO' => 'COL', //Colombia
                'KM' => 'COM', //Comoros
                'CG' => 'COG', //Congo
                'CD' => 'COD', //Congo, Democratic Republic of the
                'CK' => 'COK', //Cook Islands
                'CR' => 'CRI', //Costa Rica
                'CI' => 'CIV', //Côte d\'Ivoire
                'HR' => 'HRV', //Croatia
                'CU' => 'CUB', //Cuba
                'CW' => 'CUW', //Curaçao
                'CY' => 'CYP', //Cyprus
                'CZ' => 'CZE', //Czech Republic
                'DK' => 'DNK', //Denmark
                'DJ' => 'DJI', //Djibouti
                'DM' => 'DMA', //Dominica
                'DO' => 'DOM', //Dominican Republic
                'EC' => 'ECU', //Ecuador
                'EG' => 'EGY', //Egypt
                'SV' => 'SLV', //El Salvador
                'GQ' => 'GNQ', //Equatorial Guinea
                'ER' => 'ERI', //Eritrea
                'EE' => 'EST', //Estonia
                'ET' => 'ETH', //Ethiopia
                'FK' => 'FLK', //Falkland Islands
                'FO' => 'FRO', //Faroe Islands
                'FJ' => 'FIJ', //Fiji
                'FI' => 'FIN', //Finland
                'FR' => 'FRA', //France
                'GF' => 'GUF', //French Guiana
                'PF' => 'PYF', //French Polynesia
                'TF' => 'ATF', //French Southern Territories
                'GA' => 'GAB', //Gabon
                'GM' => 'GMB', //Gambia
                'GE' => 'GEO', //Georgia
                'DE' => 'DEU', //Germany
                'GH' => 'GHA', //Ghana
                'GI' => 'GIB', //Gibraltar
                'GR' => 'GRC', //Greece
                'GL' => 'GRL', //Greenland
                'GD' => 'GRD', //Grenada
                'GP' => 'GLP', //Guadeloupe
                'GU' => 'GUM', //Guam
                'GT' => 'GTM', //Guatemala
                'GG' => 'GGY', //Guernsey
                'GN' => 'GIN', //Guinea
                'GW' => 'GNB', //Guinea-Bissau
                'GY' => 'GUY', //Guyana
                'HT' => 'HTI', //Haiti
                'HM' => 'HMD', //Heard Island and McDonald Islands
                'VA' => 'VAT', //Holy See (Vatican City State)
                'HN' => 'HND', //Honduras
                'HK' => 'HKG', //Hong Kong
                'HU' => 'HUN', //Hungary
                'IS' => 'ISL', //Iceland
                'IN' => 'IND', //India
                'ID' => 'IDN', //Indonesia
                'IR' => 'IRN', //Iran
                'IQ' => 'IRQ', //Iraq
                'IE' => 'IRL', //Republic of Ireland
                'IM' => 'IMN', //Isle of Man
                'IL' => 'ISR', //Israel
                'IT' => 'ITA', //Italy
                'JM' => 'JAM', //Jamaica
                'JP' => 'JPN', //Japan
                'JE' => 'JEY', //Jersey
                'JO' => 'JOR', //Jordan
                'KZ' => 'KAZ', //Kazakhstan
                'KE' => 'KEN', //Kenya
                'KI' => 'KIR', //Kiribati
                'KP' => 'PRK', //Korea, Democratic People\'s Republic of
                'KR' => 'KOR', //Korea, Republic of (South)
                'KW' => 'KWT', //Kuwait
                'KG' => 'KGZ', //Kyrgyzstan
                'LA' => 'LAO', //Laos
                'LV' => 'LVA', //Latvia
                'LB' => 'LBN', //Lebanon
                'LS' => 'LSO', //Lesotho
                'LR' => 'LBR', //Liberia
                'LY' => 'LBY', //Libya
                'LI' => 'LIE', //Liechtenstein
                'LT' => 'LTU', //Lithuania
                'LU' => 'LUX', //Luxembourg
                'MO' => 'MAC', //Macao S.A.R., China
                'MK' => 'MKD', //Macedonia
                'MG' => 'MDG', //Madagascar
                'MW' => 'MWI', //Malawi
                'MY' => 'MYS', //Malaysia
                'MV' => 'MDV', //Maldives
                'ML' => 'MLI', //Mali
                'MT' => 'MLT', //Malta
                'MH' => 'MHL', //Marshall Islands
                'MQ' => 'MTQ', //Martinique
                'MR' => 'MRT', //Mauritania
                'MU' => 'MUS', //Mauritius
                'YT' => 'MYT', //Mayotte
                'MX' => 'MEX', //Mexico
                'FM' => 'FSM', //Micronesia
                'MD' => 'MDA', //Moldova
                'MC' => 'MCO', //Monaco
                'MN' => 'MNG', //Mongolia
                'ME' => 'MNE', //Montenegro
                'MS' => 'MSR', //Montserrat
                'MA' => 'MAR', //Morocco
                'MZ' => 'MOZ', //Mozambique
                'MM' => 'MMR', //Myanmar
                'NA' => 'NAM', //Namibia
                'NR' => 'NRU', //Nauru
                'NP' => 'NPL', //Nepal
                'NL' => 'NLD', //Netherlands
                'AN' => 'ANT', //Netherlands Antilles
                'NC' => 'NCL', //New Caledonia
                'NZ' => 'NZL', //New Zealand
                'NI' => 'NIC', //Nicaragua
                'NE' => 'NER', //Niger
                'NG' => 'NGA', //Nigeria
                'NU' => 'NIU', //Niue
                'NF' => 'NFK', //Norfolk Island
                'MP' => 'MNP', //Northern Mariana Islands
                'NO' => 'NOR', //Norway
                'OM' => 'OMN', //Oman
                'PK' => 'PAK', //Pakistan
                'PW' => 'PLW', //Palau
                'PS' => 'PSE', //Palestinian Territory
                'PA' => 'PAN', //Panama
                'PG' => 'PNG', //Papua New Guinea
                'PY' => 'PRY', //Paraguay
                'PE' => 'PER', //Peru
                'PH' => 'PHL', //Philippines
                'PN' => 'PCN', //Pitcairn
                'PL' => 'POL', //Poland
                'PT' => 'PRT', //Portugal
                'PR' => 'PRI', //Puerto Rico
                'QA' => 'QAT', //Qatar
                'RE' => 'REU', //Reunion
                'RO' => 'ROU', //Romania
                'RU' => 'RUS', //Russia
                'RW' => 'RWA', //Rwanda
                'BL' => 'BLM', //Saint Barth&eacute;lemy
                'SH' => 'SHN', //Saint Helena
                'KN' => 'KNA', //Saint Kitts and Nevis
                'LC' => 'LCA', //Saint Lucia
                'MF' => 'MAF', //Saint Martin (French part)
                'SX' => 'SXM', //Sint Maarten / Saint Matin (Dutch part)
                'PM' => 'SPM', //Saint Pierre and Miquelon
                'VC' => 'VCT', //Saint Vincent and the Grenadines
                'WS' => 'WSM', //Samoa
                'SM' => 'SMR', //San Marino
                'ST' => 'STP', //S&atilde;o Tom&eacute; and Pr&iacute;ncipe
                'SA' => 'SAU', //Saudi Arabia
                'SN' => 'SEN', //Senegal
                'RS' => 'SRB', //Serbia
                'SC' => 'SYC', //Seychelles
                'SL' => 'SLE', //Sierra Leone
                'SG' => 'SGP', //Singapore
                'SK' => 'SVK', //Slovakia
                'SI' => 'SVN', //Slovenia
                'SB' => 'SLB', //Solomon Islands
                'SO' => 'SOM', //Somalia
                'ZA' => 'ZAF', //South Africa
                'GS' => 'SGS', //South Georgia/Sandwich Islands
                'SS' => 'SSD', //South Sudan
                'ES' => 'ESP', //Spain
                'LK' => 'LKA', //Sri Lanka
                'SD' => 'SDN', //Sudan
                'SR' => 'SUR', //Suriname
                'SJ' => 'SJM', //Svalbard and Jan Mayen
                'SZ' => 'SWZ', //Swaziland
                'SE' => 'SWE', //Sweden
                'CH' => 'CHE', //Switzerland
                'SY' => 'SYR', //Syria
                'TW' => 'TWN', //Taiwan
                'TJ' => 'TJK', //Tajikistan
                'TZ' => 'TZA', //Tanzania
                'TH' => 'THA', //Thailand
                'TL' => 'TLS', //Timor-Leste
                'TG' => 'TGO', //Togo
                'TK' => 'TKL', //Tokelau
                'TO' => 'TON', //Tonga
                'TT' => 'TTO', //Trinidad and Tobago
                'TN' => 'TUN', //Tunisia
                'TR' => 'TUR', //Turkey
                'TM' => 'TKM', //Turkmenistan
                'TC' => 'TCA', //Turks and Caicos Islands
                'TV' => 'TUV', //Tuvalu
                'UG' => 'UGA', //Uganda
                'UA' => 'UKR', //Ukraine
                'AE' => 'ARE', //United Arab Emirates
                'GB' => 'GBR', //United Kingdom
                'US' => 'USA', //United States
                'UM' => 'UMI', //United States Minor Outlying Islands
                'UY' => 'URY', //Uruguay
                'UZ' => 'UZB', //Uzbekistan
                'VU' => 'VUT', //Vanuatu
                'VE' => 'VEN', //Venezuela
                'VN' => 'VNM', //Vietnam
                'VG' => 'VGB', //Virgin Islands, British
                'VI' => 'VIR', //Virgin Island, U.S.
                'WF' => 'WLF', //Wallis and Futuna
                'EH' => 'ESH', //Western Sahara
                'YE' => 'YEM', //Yemen
                'ZM' => 'ZMB', //Zambia
                'ZW' => 'ZWE', //Zimbabwe

            );

            $iso_code = isset( $countries[$country] ) ? $countries[$country] : $country;
            return $iso_code;

        }
    }
}
add_action( 'plugins_loaded', 'woo_gccpay_init' );