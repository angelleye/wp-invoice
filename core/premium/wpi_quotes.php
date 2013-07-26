<?php
/**
  Name: Invoice Quotes
  Class: wpi_quotes
  Global Variable: wpi_quotes
  Internal Slug: wpi_quotes
  JS Slug: wpi_quotes
  Version: 1.3.1
  Feature ID: 16
  Minimum Core Version: 3.07.0
  Description: Lets user create Quotes.
 */

class wpi_quotes {

  /**
   * Init feature filters and actions
   * @global object $wpi_settings
   * @global object $wpi_quotes
   */
  function wpi_premium_loaded() {
    global $wpi_settings, $wpi_quotes;

    // Filter existing WPI Object types
    add_action('comment_post', array(__CLASS__, 'wpi_save_comment_meta_data'));
    add_action('comment_post_redirect', array(__CLASS__, 'wpi_redirect_to_invoice'));
    add_action('wpi_add_comments_box', array(__CLASS__, 'wpi_add_comments_box'));
    add_action('wpi_ui_admin_scripts_invoice_editor', array('wpi_quotes', 'load_invoice_editor_scripts'));
    add_action("wpi_publish_options", array(__CLASS__, 'wpi_publish_options'));

    add_filter('wpi_object_types', array(__CLASS__, 'wpi_object_types'));
    add_filter('wpi_closed_comments', array(__CLASS__, 'wpi_closed_comments_handler'));
    add_filter('comments_array', array(__CLASS__, 'wpi_filter_comments'));
    add_filter('get_comments_number', array(__CLASS__, 'wpi_count_comments'));

    add_filter('comment_form_defaults', array(__CLASS__, 'comment_form_defaults'));
    add_filter('comment_form_logged_in', array(__CLASS__, 'wpi_change_comment_form'));
    add_filter('comment_form_default_fields', array(__CLASS__, 'wpi_change_comment_form_default'));

    add_filter('comment_row_actions', array(__CLASS__, 'wpi_change_comment_actions'), 10, 2);

    add_filter('wpi_invoice_history_allow_types', array(__CLASS__, 'wpi_invoice_history_allow_types'));
  }

  /**
   * Add allowed invoice type to history widget.
   *
   * @param array $current
   * @return string
   */
  function wpi_invoice_history_allow_types( $current = array() ) {
    $current[] = 'quote';
    return $current;
  }

  /**
   * Change actions buttons for comments
   * @global object $wpi_settings
   * @param array $actions
   * @param object $comments
   * @return array
   */
  function wpi_change_comment_actions($actions, $comment){
      global $wpi_settings;
      if(isset($_REQUEST['invoice_id']) || (isset($_REQUEST['page']) && $_REQUEST['page']=='wpi_page_manage_invoice')){
        unset($actions);
        $actions['reply'] = '<a class="vim-r" href="#" title="'. __('Reply to this comment') .'" onclick="commentReply.open( \''. $comment->comment_ID .'\',\''. $wpi_settings['web_invoice_page'] .'\' );return false;">'. __('Reply') .'</a>';
        $actions['delete'] = "<a href='javascript:wpiDeleteComment(" . $comment->comment_ID . ", \"" . wp_create_nonce( "delete-comment_$comment->comment_ID" ) . "\")' class='delete:the-comment-list'>" . __('Delete') . '</a>';
      }
      return $actions;
  }

  /**
   * Modify default comment args for WPI Objects
   *
   * $args:
   * - fields - author,email,url
   * - comment_field
   * - must_log_in
   * - logged_in_as
   * - comment_notes_before
   * - comment_notes_after
   * - id_form
   * - id_submit
   * - title_reply
   * - title_reply_to
   * - cancel_reply_link
   * - label_submit
   *
   * @author potanin@UD
   * @since 3.0.3
   */
  function comment_form_defaults($args) {
    global $invoice;

    if(empty($invoice['is_quote']) || !$invoice['is_quote']) {
      return $args;
    }

    $user = $invoice['user_data'];

    $args['fields']['author'] = '<input type="hidden" name="author" value="' . recipients_name(array('return'=>true)) . '" />';
    $args['fields']['email'] = '<input type="hidden" name="email" value="' . $user['user_email'] . '" />';
    $args['fields']['url'] = '';

    $args['logged_in_as'] = '';

    $args['comment_field'] = '<p class="comment-form-comment"><textarea id="comment" name="comment" cols="45" rows="8" aria-required="true"></textarea></p>';
    $args['comment_field'] .= '<input name="invoice_id" type="hidden" value="' . $invoice['hash'] . '">';

    if(is_user_logged_in()) {
      $args['title_reply'] = __('Response:', WPI);
      $args['label_submit'] = __('Send Response', WPI);
    } else {
      $args['title_reply'] = __('Have a question?', WPI);
      $args['label_submit'] = __('Send Question', WPI);
    }

    $args['comment_notes_before'] = '';
    $args['comment_notes_after'] = '';

    return $args;
  }

  /**
   * PHP function to echoing a message to JS console
   *
   * @author potanin@UD
   * @since 3.0.3
   */
  function load_invoice_editor_scripts() {
    wp_enqueue_script('admin-comments');
  }

  /**
   * Filter invoice comments
   * @global object $wpi_settings
   * @param type $comments
   * @return array|false
   */
  function wpi_filter_comments($comments) {
    global $wpi_settings;
    if (isset($_REQUEST['invoice_id']) && !empty($_REQUEST['invoice_id'])) {
      $invoice_id = $_REQUEST['invoice_id'];
    } else {
      return $comments;
    }

    add_filter('comments_clauses', array(__CLASS__, 'wpi_comments_clauses'));
    $comments = get_comments('post_id=' . (!empty($wpi_settings['web_invoce_page'])?$wpi_settings['web_invoce_page']:'') );
    $invoice_comments = array();
    foreach ($comments as $key => $comment) {
      if ($invoice_id == get_comment_meta($comment->comment_ID, 'invoice_id', true)) {
        $invoice_comments[] = $comment;
      }
    }
    return $invoice_comments;
  }

  /**
   * Count filtered invoice comments
   * @global object $wpi_settings
   * @param type $comments
   * @return int|false
   */
  function wpi_count_comments($comments) {
    global $wpi_settings;
    if (isset($_REQUEST['invoice_id']) && !empty($_REQUEST['invoice_id'])) {
      $invoice_id = $_REQUEST['invoice_id'];
    } else {
      return $comments;
    }

    add_filter('comments_clauses', array(__CLASS__, 'wpi_comments_clauses'));
    $comments = get_comments('post_id=' . $wpi_settings['web_invoce_page']);
    $invoice_comments = array();
    foreach ($comments as $key => $comment) {
      if ($invoice_id == get_comment_meta($comment->comment_ID, 'invoice_id', true)) {
        $invoice_comments[] = $comment;
      }
    }

    return count($invoice_comments);
  }

  /**
   * approved comment
   * @param array $arr
   * @return string
   */
  function wpi_comments_clauses($arr) {
    $arr['where'] = 'comment_approved=2';
    return $arr;
  }

  /**
   * Redirect to invoice after commenting
   * @param string $location
   * @return string
   */
  function wpi_redirect_to_invoice($location) {
    if (isset($_REQUEST['invoice_id']) && !empty($_REQUEST['invoice_id'])) {
      $location = str_replace('?', '?invoice_id=' . $_REQUEST['invoice_id'] . '&', $location);
      if(!strstr($location, 'invoice_id=')) $location = str_replace('#', '?invoice_id=' . $_REQUEST['invoice_id'] . '#', $location);
    }
    return $location;
  }

  /**
   * Adding invoice id to comment form
   * @global type $invoice
   * @param type $comment
   * @return string
   */
  function wpi_change_comment_form($comment) {
    global $invoice;
    $comment = '<input name="invoice_id" type="hidden" value="' . $invoice['hash'] . '">';
    return $comment;
  }
  /**
   * Adding invoice id to comment form
   * @global type $invoice
   * @param type $comment
   * @return string
   */
  function wpi_change_comment_form_default($comment) {
    global $invoice;
    $comment['url'] .= '<input name="invoice_id" type="hidden" value="' . $invoice['hash'] . '">';
    return $comment;
  }
  /**
   * Save comment meta data
   * @param int $comment_id
   */
  function wpi_save_comment_meta_data($comment_id) {
    global $wpdb;
    if (isset($_REQUEST['invoice_id']) && !empty($_REQUEST['invoice_id'])) {
      add_comment_meta($comment_id, 'invoice_id', $_REQUEST['invoice_id']);
      $wpdb->update($wpdb->comments, array('comment_approved' => 2), array('comment_ID' => $comment_id));
    }
  }

  /**
   * Add hadler to remove comments
   */
  function wpi_closed_comments_handler() {
    add_filter('comments_open', create_function("", " return false; "));
  }

  /**
   * Add new wpi_object type
   * @param array $types
   * @return array
   */
  function wpi_object_types($types) {
    $types['quote'] = array('label' => 'Quote');
    return $types;
  }


  /**
   * Quote checkbox
   *
   * @param type $this_invoice
   */
  static public function wpi_publish_options($this_invoice) {
    ?>
    <li class="wpi_quote_option wpi_not_for_recurring wpi_not_for_deposit"><?php echo WPI_UI::checkbox("name=wpi_invoice[quote]&value=true&label=Quote", ((!empty($this_invoice['status']) && $this_invoice['status'] == 'quote') ? true : false)); ?></li>
    <?php
  }

  /**
   * Draw wp_editor if it exists
   *
   * @return null
   * @author korotkov@ud
   */
  static function load_custom_wp_editor() {
    if (function_exists('wp_editor')) {
      $quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,spell,close' );
      wp_editor( '', 'replycontent', array( 'media_buttons' => false, 'tinymce' => false, 'quicktags' => $quicktags_settings, 'tabindex' => 104 ) );
      return;
    }
    ?>
    <textarea style="width:100%;" id="replycontent" name="replycontent"></textarea>
    <?php
  }

  /**
   * Adding comments to WP-admin
   * @global type $this_invoice
   * @global object $wpi_settings
   */
  function wpi_add_comments_box() {
    global $this_invoice, $wpi_settings;

    add_filter('comments_clauses', array(__CLASS__, 'wpi_comments_clauses'));
    $comments = get_comments('post_id=' . $wpi_settings['web_invoice_page']);
    $invoice_comments = array();
    foreach ($comments as $key => $comment) {
      if (!empty($this_invoice->data['hash']) && $this_invoice->data['hash'] == get_comment_meta($comment->comment_ID, 'invoice_id', true)) {
        $invoice_comments[] = $comment;
      }
    }
    ?>
    <div id="wpi_comments_box" class="postbox hidden">
      <div class="handlediv" title="Click to toggle"><br/></div>
      <h3 class="hndle"><span><?php _e('Comments', WPI); ?></span></h3>
      <div class="inside">
        <table class="widefat fixed comments" cellspacing="0">
          <thead>
            <tr>
              <th scope="col" id="author" class="manage-column column-author" style=""><?php _e('Author', WPI); ?></th>
              <th scope="col" id="comment" class="manage-column column-comment" style=""><?php _e('Comment', WPI); ?></th>
            </tr>
          </thead>
          <tbody id="the-comment-list" class="list:comment">
            <?php
            if(!empty($invoice_comments)) {
              foreach ($invoice_comments as $comment) {
                $in_reply = false;
                if ($comment->comment_parent != 0) {
                  $in_reply = get_comment($comment->comment_parent);
                }
                $wp_list_table = _get_list_table('WP_Post_Comments_List_Table');
                $wp_list_table->single_row( $comment );
              }
            } else {
              ?>
              <tr>
                <td colspan="2"><?php _e('There are no comments for this quote.', WPI); ?></td>
              </tr>
              <?php
            }
            ?>
          </tbody>
          <tbody id="the-extra-comment-list" class="list:comment" style="display: none;">
          </tbody>
        </table>
        <script type='text/javascript'>
          /* <![CDATA[ */
          var commonL10n = {
            warnDelete: "You are about to permanently delete the selected items.\n  \'Cancel\' to stop, \'OK\' to delete."
          };

          try{convertEntities(commonL10n);}catch(e){};

          var wpAjax = {
            noPerm: "You do not have permission to do that.",
            broken: "An unidentified error has occurred."
          };

          try{convertEntities(wpAjax);}catch(e){};

          var quicktagsL10n = {
            quickLinks: "(Quick Links)",
            wordLookup: "Enter a word to look up:",
            dictionaryLookup: "Dictionary lookup",
            lookup: "lookup",
            closeAllOpenTags: "Close all open tags",
            closeTags: "close tags",
            enterURL: "Enter the URL",
            enterImageURL: "Enter the URL of the image",
            enterImageDescription: "Enter a description of the image",
            fullscreen: "fullscreen",
            toggleFullscreen: "Toggle fullscreen mode"
          };

          try{convertEntities(quicktagsL10n);}catch(e){};

          var adminCommentsL10n = {
            hotkeys_highlight_first: "",
            hotkeys_highlight_last: "",
            replyApprove: "Approve and Reply",
            reply: "Reply"
          };

          function displayBox(obj) {
            var el = document.getElementById(obj);
            if ( el.style.display != 'none' ) {
              el.style.display = 'none';
            } else {
              el.style.display = 'block';
            }
          }

          jQuery.noConflict();
          jQuery(document).ready(function() {
            if(jQuery("#wpi_wpi_invoice_quote_").is(':checked')) {
              jQuery("#postbox_payment_methods").hide();
              jQuery("#wpi_comments_box").show();
              jQuery('#wpi_invoice_type_quote').val('quote');
            } else {
              jQuery("#postbox_payment_methods").show();
              jQuery("#wpi_comments_box").hide();
              jQuery('#wpi_invoice_type_quote').val('');
            }
          });

          jQuery("#wpi_wpi_invoice_quote_").live('click', function() {
            if(jQuery("#wpi_wpi_invoice_quote_").is(':checked')) {
              jQuery("#postbox_payment_methods").hide();
              jQuery("#wpi_comments_box").show();
              jQuery('#wpi_invoice_type_quote').val('quote');
            } else {
              jQuery("#postbox_payment_methods").show();
              jQuery("#wpi_comments_box").hide();
              jQuery('#wpi_invoice_type_quote').val('');
            }
          });

          function wpiDeleteComment(comment, nonce){
            jQuery.post('<?php echo get_site_url(); ?>/wp-admin/admin-ajax.php', {
              id: comment,
              action: 'delete-comment',
              trash: 1,
              _wpnonce: nonce
            });
            jQuery("#comment-"+comment).hide();
          }
          /* ]]> */
        </script>
        <div id="ajax-response"></div>
        <table style="display:none;">
          <tbody id="com-reply">
            <tr id="replyrow" style="display:none;">
              <td colspan="2" class="colspanchange">
                <div id="replyhead" style="display:none;">
                  <h5><?php _e('Reply to Comment', WPI); ?></h5>
                </div>
                <div id="edithead" style="display:none;">
                  <div class="inside">
                    <label for="author"><?php _e('Name', WPI); ?></label>
                    <input type="text" name="newcomment_author" size="50" value="" tabindex="101" id="author" />
                  </div>
                  <div class="inside">
                    <label for="author-email"><?php _e('E-mail', WPI); ?></label>
                    <input type="text" name="newcomment_author_email" size="50" value="" tabindex="102" id="author-email" />
                  </div>
                  <div class="inside">
                    <label for="author-url"><?php _e('URL') ?></label>
                    <input type="text" id="author-url" name="newcomment_author_url" size="103" value="" tabindex="103" />
                  </div>
                  <div style="clear:both;"></div>
                </div>
                <div id="replycontainer">
                <?php
                  self::load_custom_wp_editor();
                ?>
                </div>
                <p id="replysubmit" class="submit">
                  <a href="#comments-form" class="cancel button-secondary alignleft btn" tabindex="106"><?php _e('Cancel', WPI); ?></a>
                  <a href="#comments-form" class="save button-primary alignright btn" tabindex="104">
                    <span id="savebtn" class="btn" style="display:none;"><?php _e('Update Comment', WPI); ?></span>
                    <span id="replybtn" class="btn" style="display:none;"><?php _e('Submit Reply', WPI); ?></span>
                  </a>
                  <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
                  <span class="error" style="display:none;"></span>
                  <br class="clear" />
                </p>
                <input type="hidden" name="user_ID" id="user_ID" value="<?php echo get_current_user_id(); ?>" />
                <input type="hidden" name="action" id="action" value="" />
                <input type="hidden" name="comment_ID" id="comment_ID" value="" />
                <input type="hidden" name="comment_post_ID" id="comment_post_ID" value="" />
                <input type="hidden" name="status" id="status" value="" />
                <input type="hidden" name="invoice_id" id="invoice_id" value="<?php echo $this_invoice->data['hash']; ?>" />
                <input type="hidden" name="position" id="position" value="-1" />
                <input type="hidden" name="checkbox" id="checkbox" value="1" />
                <input type="hidden" name="mode" id="mode" value="single" />
                <?php
                  wp_nonce_field( 'replyto-comment', '_ajax_nonce-replyto-comment', false );
                  if ( current_user_can( 'unfiltered_html' ) )
                    wp_nonce_field( 'unfiltered-html-comment', '_wp_unfiltered_html_comment', false );
                ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <?php
  }

}

// Init Quotes premium feature
add_action('wpi_premium_loaded', array('wpi_quotes', 'wpi_premium_loaded'));