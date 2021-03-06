<?php
/**
 * Invoice List Table class.
 *
 * @package WP-Invoice
 * @since 3.0
 * @access private
 */
require_once( ud_get_wp_invoice()->path( "lib/class_list_table.php", 'dir' ) );

class WPI_Object_List_Table extends WPI_List_Table {

  function __construct($args = '') {
    $args = wp_parse_args( $args, array(
      'plural' => '',
      'iColumns' => 3,
      'per_page' => 20,
      'iDisplayStart' => 0,
      'ajax_action' => 'wpi_list_table',
      'current_screen' => '',
      'table_scope' => 'wpi_overview',
      'singular' => '',
      'ajax' => false
    ) );

    parent::__construct($args);
  }

  /**
   * Get a list of sortable columns.
   *
   * @since 3.1.0
   * @access protected
   *
   * @return array
   */
  function get_sortable_columns() {
    global $wpi_settings;

    $columns['post_title'] = 'post_title';
    $columns['post_status'] = 'post_status';
    $columns['post_modified'] = 'post_modified';

    if(!empty($wpi_settings['ui']) && $wpi_settings['ui']['overview_columns']) {
      foreach($wpi_settings['ui']['overview_columns'] as $slug => $title)
        $columns[$slug] = $slug;
    }

    $columns = apply_filters('wpi_admin_sortable_columns', $columns);

    return $columns;
  }

  /**
   * Set Bulk Actions
   *
   * @since 3.1.0
   *
   * @return array
   */
  public function get_bulk_actions() {
    $actions = array();

    $actions['untrash'] = __( 'Restore', ud_get_wp_invoice()->domain );
    $actions['archive'] = __( 'Archive', ud_get_wp_invoice()->domain );
    $actions['delete'] = __( 'Delete Permanently', ud_get_wp_invoice()->domain );
    $actions['trash'] = __( 'Move to Trash', ud_get_wp_invoice()->domain );
    $actions['unarchive'] = __( 'Un-Archive', ud_get_wp_invoice()->domain );

    return $actions;
  }

  /**
   * Generate HTML for a single row on the users.php admin panel.
   *
   */
  function single_row( $object ) {
    global $wpi_settings, $post;

    $object = (array) $object;

    $post = new WPI_Invoice();
    $post->load_invoice("id={$object['ID']}");
    $post = (object)$post->data;

    $post_owner = ( get_current_user_id() == $post->post_author ? 'self' : 'other' );
    $edit_link = admin_url("admin.php?page=wpi_page_manage_invoice&wpi[existing_invoice][invoice_id]={$post->ID}");
    $title = _draft_or_post_title($post->ID);
    $post_type_object = get_post_type_object( $post->post_type );
    $can_edit_post = current_user_can( $post_type_object->cap->edit_post, $post->ID );

    $result = "<tr id='object-{$object['ID']}' class='wpi_parent_element'>";

    list( $columns, $hidden ) = $this->get_column_info();

    foreach ( $columns as $column => $column_display_name ) {
      $class = "class=\"$column column-$column\"";
      $style = '';

      if ( in_array( $column, $hidden ) ) {
        $style = ' style="display:none;"';
      }

      $attributes = "$class$style";

      $result .= "<td {$attributes}>";

      $r = "";
      switch($column) {

        case 'cb':
          if ( $can_edit_post ) {
            $r .= '<input type="checkbox" name="post[]" value="'. get_the_ID() . '"/>';
          } else {
            $r .= '&nbsp;';
          }
        break;

        case 'post_title':
          $attributes = 'class="post-title page-title column-title"' . $style;
          if ( $can_edit_post && $post->post_status != 'trash' && $post->post_status != 'archived' ) {
            $r .= '<a class="row-title" href="' . $edit_link . '" title="' . esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', ud_get_wp_invoice()->domain ), $title ) ) . '">' . $title . '</a>';
          } else {
            $r .= $title;
          }
          $r .= (isset( $parent_name ) ? ' | ' . $post_type_object->labels->parent_item_colon . ' ' . esc_html( $parent_name ) : '');

          $actions = array();
          if ( $can_edit_post && 'trash' != $post->post_status && 'archived' != $post->post_status ) {
           $actions['edit'] = '<a href="' . get_edit_post_link( $post->ID, true ) . '" title="' . esc_attr( __( 'Edit this item', ud_get_wp_invoice()->domain ) ) . '">' . __( 'Edit', ud_get_wp_invoice()->domain ) . '</a>';
          }

          if ( 'archived' == $post->post_status ) {
            $actions['unarchive'] = '<a href="' . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=unarchive', $post->ID ) ), 'unarchive-' . $post->post_type . '_' . $post->ID ) . '" title="' . esc_attr( __( 'Un-Archive this item', ud_get_wp_invoice()->domain ) ) . '">' . __( 'Un-Archive', ud_get_wp_invoice()->domain ) . '</a>';
          } else if ( 'trash' != $post->post_status && 'pending' != $post->post_status ) {
            $actions['archive'] = '<a href="' . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=archive', $post->ID ) ), 'archive-' . $post->post_type . '_' . $post->ID ) . '" title="' . esc_attr( __( 'Archive this item', ud_get_wp_invoice()->domain ) ) . '">' . __( 'Archive', ud_get_wp_invoice()->domain ) . '</a>';
          }

          if ( current_user_can( $post_type_object->cap->delete_post, $post->ID ) ) {
            if ( 'trash' == $post->post_status ) {
              $actions['untrash'] = "<a title='" . esc_attr( __( 'Restore this item from the Trash', ud_get_wp_invoice()->domain ) ) . "' href='" . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-' . $post->post_type . '_' . $post->ID ) . "'>" . __( 'Restore', ud_get_wp_invoice()->domain ) . "</a>";
            } elseif ( EMPTY_TRASH_DAYS && 'pending' != $post->post_status ) {
              $actions['trash'] = "<a class='submitdelete' title='" . esc_attr( __( 'Move this item to the Trash', ud_get_wp_invoice()->domain ) ) . "' href='" . get_delete_post_link( $post->ID ) . "'>" . __( 'Trash', ud_get_wp_invoice()->domain ) . "</a>";
            }

            if ( 'trash' == $post->post_status || !EMPTY_TRASH_DAYS ) {
              $actions['delete'] = "<a class='submitdelete permanently' title='" . esc_attr( __( 'Delete this item permanently', ud_get_wp_invoice()->domain ) ) . "' href='" . get_delete_post_link( $post->ID, '', true ) . "'>" . __( 'Delete Permanently', ud_get_wp_invoice()->domain ) . "</a>";
            }
          }

          if ( 'trash' != $post->post_status && 'archived' != $post->post_status ) {
            $actions['view'] = '<a target="_blank" href="' . get_invoice_permalink( $post->invoice_id ) . '" title="' . esc_attr( sprintf( __( 'View &#8220;%s&#8221;', ud_get_wp_invoice()->domain ), $title ) ) . '" rel="permalink">' . __( 'View', ud_get_wp_invoice()->domain ) . '</a>';
          }

          $actions = apply_filters( is_post_type_hierarchical( $post->post_type ) ? 'page_row_actions' : 'post_row_actions', $actions, $post );
          $r .= $this->row_actions( $actions );
        break;

        case 'post_modified':
          if ( !empty( $post->post_status ) ) {
            if ( $post->post_status == 'paid' ) {
              $r .= get_post_status_object($post->post_status)->label.' '.human_time_diff(strtotime($post->post_modified), (time() + get_option('gmt_offset')*60*60)).__(' ago', ud_get_wp_invoice()->domain);
            } else {
              $r .= human_time_diff(strtotime($post->post_modified), (time() + get_option('gmt_offset')*60*60)).__(' ago', ud_get_wp_invoice()->domain);
            }
          } else {
            $r .= date(get_option('date_format'), strtotime($post->post_date));
          }
        break;

        case 'invoice_id':
          $invoice_id = $post->{$column};
          /* If custom_id exists we use it as invoice_id */
          if(!empty($post->custom_id)) {
            $invoice_id = $post->custom_id;
          }
          $r .= '<a href="' . get_invoice_permalink($post->{$column}) . '" target="_blank">'.apply_filters("wpi_attribute_{$column}", $invoice_id, $post).'</a>';
        break;

        case 'post_status':
          $r .= get_post_status_object($post->post_status)->label;
        break;

        case 'user_email':

          //** Get User Edit Link */
          if(class_exists('WP_CRM_Core')) {
          $edit_user_url = admin_url("admin.php?page=wp_crm_add_new&user_id={$post->user_data['ID']}");
          } else {
          $edit_user_url =  admin_url("user-edit.php?user_id={$post->user_data['ID']}");
          }

          $r .= '<ul>';
          $r .= '<li><a href="'.$edit_user_url.'">' . $post->user_data['display_name'] . '</a></li>';
          $r .= '<li>' . $post->user_data['user_email'] . '</li>';
          $r .= '</ul>';
        break;

        case 'type':
          $r .= $wpi_settings['types'][$post->type]['label'];
        break;

        case 'total':
          if ( !empty( $post->subtotal ) ) {
            if ( $post->type == 'single_payment' ) {
              $r .= (!empty($wpi_settings['currency']['symbol'][$post->default_currency_code])?$wpi_settings['currency']['symbol'][$post->default_currency_code]:'$') . wp_invoice_currency_format( !empty( $post->total_payments )?$post->total_payments:0 );
            } elseif ( $post->type == 'recurring' ) {
              $r .= (!empty($wpi_settings['currency']['symbol'][$post->default_currency_code])?$wpi_settings['currency']['symbol'][$post->default_currency_code]:'$') . wp_invoice_currency_format( !empty( $post->total_payments )?$post->total_payments:0 );
            } else {
              $r .= (!empty($wpi_settings['currency']['symbol'][$post->default_currency_code])?$wpi_settings['currency']['symbol'][$post->default_currency_code]:'$') . wp_invoice_currency_format( !empty( $post->adjustments )?abs($post->adjustments):0 )
                    ." <span style='color:#aaaaaa;'>" . __('of', ud_get_wp_invoice()->domain) ." ".
                    (!empty($wpi_settings['currency']['symbol'][$post->default_currency_code])?$wpi_settings['currency']['symbol'][$post->default_currency_code]:'$') . wp_invoice_currency_format($post->subtotal-(!empty($post->total_discount)?$post->total_discount:0)+(!empty($post->total_tax)?$post->total_tax:0))
                    ."</span>";
            }
          } else {
            $r .= (!empty($wpi_settings['currency']['symbol'][$post->default_currency_code])?$wpi_settings['currency']['symbol'][$post->default_currency_code]:'$') . wp_invoice_currency_format(0);
          }

        break;

        default:
          $r .= apply_filters("wpi_attribute_{$column}", $post->{$column}, $post);
        break;
      }

      //** Need to insert some sort of space in there to avoid DataTable error that occures when "null" is returned */
      $ajax_cells[] = $r;

      $result .= $r;
      $result .= "</td>";
    }

    $result .= '</tr>';

    if($this->_args['ajax']) {
      return $ajax_cells;
    }

    return $result;
  }



}