<?php

/**
  Name: Payoneer
  Class: wpi_payoneer
  Internal Slug: wpi_payoneer
  JS Slug: wpi_payoneer
  Version: 1.0
  Description: Provides Payoneer integration. No recurring payments support.
 */
class wpi_payoneer extends wpi_gateway_base {

  /**
   * Construct
   */
  public function __construct() {
    parent::__construct();

    $this->options = array(
        'name' => 'Payoneer',
        'allow' => '',
        'default_option' => '',
        'settings' => array(
            'sign_up_text' => array(
                'type' => "static",
                'data' => __("Don't have a Payoneer account? <a href='http://register.payoneer.com/wp-invoice/' target='_blank'>Sign up now</a>", ud_get_wp_invoice()->domain)
            ),
            'usd_details' => array(
                'type' => "static",
                'label' => __("USD Payment service details", ud_get_wp_invoice()->domain )
            ),
            'usd_bank_name' => array(
                'label' => __("Bank Name", ud_get_wp_invoice()->domain),
                'value' => '',
                'description' => __( 'The name of the bank of your US collection account (i.e.: Bank of America)', ud_get_wp_invoice()->domain )
            ),
            'usd_account_number' => array(
                'label' => __("Account Number", ud_get_wp_invoice()->domain),
                'value' => '',
                'description' => __( 'The 13 digit number of your US collection account', ud_get_wp_invoice()->domain )
            ),
            'usd_bank_routing_number' => array(
                'label' => __("ABA (Bank Routing Number)", ud_get_wp_invoice()->domain),
                'value' => '',
                'description' => __( 'The 9 digit bank routing number', ud_get_wp_invoice()->domain )
            ),
            'euro_details' => array(
                'type' => "static",
                'label' => __("EURO Payment service details", ud_get_wp_invoice()->domain )
            ),
            'euro_bank_name' => array(
                'label' => __("Bank Name", ud_get_wp_invoice()->domain),
                'value' => '',
                'description' => __( 'The name of the bank of your EUR collection account (i.e.: WireCard)', ud_get_wp_invoice()->domain )
            ),
            'euro_bic' => array(
                'label' => __("BIC", ud_get_wp_invoice()->domain),
                'value' => '',
                'description' => __( 'Business Identifier Code (I.E WIREDEMM)', ud_get_wp_invoice()->domain )
            ),
            'euro_iban' => array(
                'label' => __("IBAN", ud_get_wp_invoice()->domain),
                'value' => '',
                'description' => __( 'The 22 digit International Bank Account Number (I.E DExxxxxxxxxxxxx)', ud_get_wp_invoice()->domain )
            )
        )
    );

    //** Fields for front-end. */
    $this->front_end_fields = array(
        'customer_information' => array(
            'first_name' => array(
                'type' => 'text',
                'class' => 'text-input',
                'name' => 'first_name',
                'label' => __('First Name', ud_get_wp_invoice()->domain)
            ),
            'last_name' => array(
                'type' => 'text',
                'class' => 'text-input',
                'name' => 'last_name',
                'label' => __('Last Name', ud_get_wp_invoice()->domain)
            ),
            'user_email' => array(
                'type' => 'text',
                'class' => 'text-input',
                'name' => 'email_address',
                'label' => __('Email Address', ud_get_wp_invoice()->domain)
            ),
            'phonenumber' => array(
                'type' => 'text',
                'class' => 'text-input',
                'name' => 'phonenumber',
                'label' => __('Phone', ud_get_wp_invoice()->domain)
            ),
            'streetaddress' => array(
                'type' => 'text',
                'class' => 'text-input',
                'name' => 'address1',
                'label' => __('Address', ud_get_wp_invoice()->domain)
            ),
            'city' => array(
                'type' => 'text',
                'class' => 'text-input',
                'name' => 'city',
                'label' => __('City', ud_get_wp_invoice()->domain)
            ),
            'state' => array(
                'type' => 'text',
                'class' => 'text-input',
                'name' => 'state',
                'label' => __('State/Province', ud_get_wp_invoice()->domain)
            ),
            'zip' => array(
                'type' => 'text',
                'class' => 'text-input',
                'name' => 'zip',
                'label' => __('Zip/Postal Code', ud_get_wp_invoice()->domain)
            )
        )
    );
  }

  /**
   * Show settings for RB.
   * @param type $invoice
   */
  function recurring_settings($invoice) {
    ?>
    <h4><?php _e('Payoneer Recurring Billing', ud_get_wp_invoice()->domain); ?></h4>
    <p><?php _e('Currently Payoneer integration is limited and does not support recurring billing', ud_get_wp_invoice()->domain); ?></p>
    <?php
  }

  /**
   * Fields renderer
   * @param type $invoice
   */
  function wpi_payment_fields($invoice) {

    $this->front_end_fields = apply_filters('wpi_crm_custom_fields', $this->front_end_fields, 'crm_data');

    if (!empty($this->front_end_fields)) {
      
      //** For each section */
      foreach ($this->front_end_fields as $key => $value) {
        
        //** If section is not empty */
        if (!empty($this->front_end_fields[$key])) {
          ob_start();
          ?>
          <ul class="wpi_checkout_block">
            <li class="section_title"><?php _e(ucwords(str_replace('_', ' ', $key)), ud_get_wp_invoice()->domain); ?></li>
            <?php
            $html = ob_get_clean();
            echo $html;
            
            //** For each field */
            foreach ($value as $field_slug => $field_data) {
              
              //** Change field properties if we need */
              $field_data = apply_filters('wpi_payment_form_styles', $field_data, $field_slug, 'wpi_interkassa');
              $html = '';

              ob_start();

              switch ($field_data['type']) {
                case self::TEXT_INPUT_TYPE:
                  ?>

                  <li class="wpi_checkout_row">
                    <div class="control-group">
                      <label class="control-label" for="<?php echo esc_attr($field_slug); ?>"><?php _e($field_data['label'], ud_get_wp_invoice()->domain); ?></label>
                      <div class="controls">
                        <input type="<?php echo esc_attr($field_data['type']); ?>" class="<?php echo esc_attr($field_data['class']); ?>"  name="<?php echo esc_attr($field_data['name']); ?>" value="<?php echo isset($field_data['value']) ? $field_data['value'] : (!empty($invoice['user_data'][$field_slug]) ? $invoice['user_data'][$field_slug] : ''); ?>" />
                      </div>
                    </div>
                  </li>

                  <?php
                  $html = ob_get_clean();

                  break;

                case self::SELECT_INPUT_TYPE:
                  ?>

                  <li class="wpi_checkout_row">
                    <label for="<?php echo esc_attr($field_slug); ?>"><?php _e($field_data['label'], ud_get_wp_invoice()->domain); ?></label>
                    <?php echo WPI_UI::select("name={$field_data['name']}&values={$field_data['values']}&id={$field_slug}&class={$field_data['class']}"); ?>
                  </li>

                  <?php
                  $html = ob_get_clean();

                  break;

                default:
                  break;
              }

              echo $html;
            }
            echo '</ul>';
          }
        }
      }
  }

  /**
   * Merchant CB handler
   */
  static function server_callback() {}

  /**
   * Payment Processor
   */
  static function process_payment() {
    global $invoice;

    $wp_users_id = $invoice['user_data']['ID'];

    //** update user data */
    update_user_meta($wp_users_id, 'last_name', !empty($_REQUEST['last_name'])?$_REQUEST['last_name']:'' );
    update_user_meta($wp_users_id, 'first_name', !empty($_REQUEST['first_name'])?$_REQUEST['first_name']:'' );
    update_user_meta($wp_users_id, 'city', !empty($_REQUEST['city'])?$_REQUEST['city']:'' );
    update_user_meta($wp_users_id, 'state', !empty($_REQUEST['state'])?$_REQUEST['state']:'' );
    update_user_meta($wp_users_id, 'zip', !empty($_REQUEST['zip'])?$_REQUEST['zip']:'' );
    update_user_meta($wp_users_id, 'streetaddress', !empty($_REQUEST['address1'])?$_REQUEST['address1']:'' );
    update_user_meta($wp_users_id, 'phonenumber', !empty($_REQUEST['phonenumber'])?$_REQUEST['phonenumber']:'' );
    update_user_meta($wp_users_id, 'country', !empty($_REQUEST['country'])?$_REQUEST['country']:'' );

    if (!empty($_REQUEST['crm_data'])) {
      self::user_meta_updated($_REQUEST['crm_data']);
    }

    echo json_encode(
      array('success' => 1)
    );
  }
}