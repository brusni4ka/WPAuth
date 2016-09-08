<?php
/**
 * Created by PhpStorm.
 * User: kate
 * Date: 03/06/16
 * Time: 23:23
 */


function auth_token_form( $username, $user_data, $redirect, $remember_me ) {?>
  <html>
 <?php echo auth_header();?>
    <body class='login wp-core-ui'>
      <div id="login">
        <h1>
          <a href="http://wordpress.org/" title="Powered by WordPress"><?php echo get_bloginfo( 'name' ); ?></a>
        </h1>
        <h3 style="text-align: center; margin-bottom:10px;">Two-Factor Authentication</h3>
        <p class="message">
           <?php printf("We've automatically sent you a token via text-message to your email address: <strong>%s</strong>.", $user_data['email'] );?>
          <strong>
            <?php
           // var_dump($user_data);
            ?>
          </strong>
        </p>

        <form method="POST" id="auth" action="<?php echo wp_login_url(); ?>">
          <label for="auth_token">
            OTP Token
            <br>
            <input type="text" name="auth_token" id="auth-token" class="input" value="" size="20" autofocus="true" />
          </label>
          <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>"/>
          <input type="hidden" name="username" value="<?php echo esc_attr( $username ); ?>"/>
          <input type="hidden" name="rememberme" value="<?php echo esc_attr( $remember_me ); ?>"/>

          <p class="submit">
            <input type="submit" value="Login" id="wp_submit" class="button button-primary button-large" />
          </p>
        </form>
      </div>
    </body>
  </html>
<?php }


function checkbox_for_admin_disable_auth($users_key, $meta)
{ ?>

    <tr>
        <th>Force enable 2FA</th>
        <td>
            <label for="force-enable">
                <input name="<?php echo esc_attr($users_key); ?>[force_by_admin]" type="checkbox"
                       value="true" <?php if ($meta['force_by_admin'] == 'true') echo 'checked="checked"'; ?> />
                Force this user to enable Two-Factor Authentication on the next login.
            </label>
            <?php wp_nonce_field( $users_key . '_force_by_admin', "_{$users_key}_wpnonce" ); ?>
        </td>
    </tr>

<?php }

/**
 * Form enable auth on profile
 * @param string $users_key
 * @return string
 */
function enable_form_on_profile($users_meta_key, $user_data)
{ ?>
    <h2>Two Factor Auth</h2>
    <table class="form-table" id="<?php echo esc_attr($users_meta_key); ?>">
        <tr>
            <th><label for="<?php echo esc_attr($users_meta_key); ?>_disable"> Enable/Disable 2FA ?</label>
            </th>
            <td>
                <input type="checkbox" id="<?php echo esc_attr($users_meta_key); ?>_enable"
                       name="<?php echo esc_attr($users_meta_key); ?>[enable_own]"
                    <?php if ($user_data['enable_own'] != false && $user_data['token_id'] ) {
                        ; ?> checked="checked" <?php } ?> />
                <label for="<?php echo esc_attr($users_meta_key); ?>_enable">Yes, enable 2FA for your account.</label>
                <?php wp_nonce_field($users_meta_key .'enable_own', $users_meta_key . '[nonce]'); ?>
            </td>
        </tr>
    </table>
<?php }

function form_enable_on_modal( $users_key, $username) {
      ?>
   <p class="message">
        <?php printf("Congratulations, <strong>%s</strong>!", $username );?>
         <p>Now you can use two factor auth!</p>
   </p>

   <div class="image">
         <img src="<?php echo plugins_url( '/assets/images/happiness.png', __FILE__ ); ?>" class="happiness" alt="happiness">
   </div>

   <!-- <p>You can check and edit your account through this link:
        <a href="https://www.protectimus.com/ru" target="_blank"><br>www.protectimus com</a>
    </p>-->
    <?php wp_nonce_field( $users_key . '_ajax_auth_enabled',$users_key . '[nonce]' ); ?>

    <p class="submit">
        <p><a class="button button-primary" href="#" onClick="self.parent.tb_remove();return false;">
        Return to your profile</a></p>
    </p>
<?php }

function render_confirmation_authy_disabled(  ) { ?>
    <div class="messages">
        <p>Two factor authentication was disabled!<br>
           From now your account is under risk of hacking.</p>
    </div>
    <div class="image">
         <img src="<?php echo plugins_url( '/assets/images/sadness.png', __FILE__ ); ?>" class="sadness" alt="sadness">
    </div>
     <p class="submit">
                <a class="button button-primary" href="#" onClick="self.parent.tb_remove();return false;">
      Return to your profile</a>
     </p>

<?php }

function render_disable_auth_on_modal( $users_key, $username ) { ?>
    <div class="message">
         <p>Two factor auth  is enabled for this account. </p>
         <p><?php printf( 'Click the button below to disable Two-Factor Authentication for <strong>%s</strong>', $username ); ?></p>
    </div>
    <div class="image">
     <img src="<?php echo plugins_url( '/assets/images/scare2.png', __FILE__ ); ?>" class="scare" alt="scare">
    </div>

    <p class="submit">
        <input name="Disable" type="submit" value="Disable" class="button-primary">
    </p>

    <?php wp_nonce_field( $users_key . '_ajax_auth_disable' );
}

function render_otp_page($user_data){?>
        <p class="message">
       <?php printf("We've automatically sent you a token via text-message to your email address: <strong>%s</strong>", $user_data['email'] );?>
        </p>

        <form method="POST" id="auth" action="<?php echo wp_login_url(); ?>">
            <label for="auth_token">
                Enter OTP
                <br>
                <input type="text" name="auth_token" id="auth-token" class="input" value="" size="20" autofocus="true" />
            </label>
             <p class="submit">
                <input type="submit" value="Confirm" id="wp_submit" class="button button-primary button-large" />
            </p>
        </form>
<?php
}

function render_force_auth(){
?>
    <p class="message">
    <?php printf("Please accept our apologises but you can not change this setting.
    You was forced by administrator to use 2fa!"); ?>

       <p class="submit">
            <a class="button button-primary" href="#" onClick="self.parent.tb_remove();return false;">
            <?php _e( 'Return to your profile', 'authy' ); ?></a>
       </p>
     <img src="<?php echo plugins_url( '/assets/images/angry.png', __FILE__ ); ?>" class="angry" alt="angry">

   </p>

<?php}





function ajax_head()
{ ?>
    <head>
        <?php
        wp_print_scripts(array('jquery', 'auth'));
        wp_print_styles(array('colors', 'auth'));
        ?>

        <style type="text/css">
            body {
                width: 450px;
                height: 380px;
                overflow: hidden;
                padding: 0 10px 10px 10px;
            }

            div.wrap {
                width: 450px;
                height: 380px;
                overflow: hidden;
            }

            table th label {
                font-size: 12px;
            }
        </style>
    </head>
    <?php
}

function auth_header( $step = '' ) { ?>
  <head>
    <?php
      global $wp_version;
      if ( version_compare( $wp_version, '3.3', '<=' ) ) {?>
        <link rel="stylesheet" type="text/css" href="<?php echo admin_url( 'css/login.css' ); ?>" />
        <link rel="stylesheet" type="text/css" href="<?php echo admin_url( 'css/colors-fresh.css' ); ?>" />
        <?php
      } elseif ( version_compare( $wp_version, '3.8', '<=' ) ) {
        wp_admin_css("wp-admin", true);
        wp_admin_css("colors-fresh", true);
        wp_admin_css("ie", true);
      } else{
        wp_admin_css("login", true);
      }
    ?>
    <?php if ( $step == 'verify_installation' ) { ?>
        <link href="<?php echo plugins_url( 'assets/authy.css', __FILE__ ); ?>" media="screen" rel="stylesheet" type="text/css">
        <script type="text/javascript">
         <?php echo admin_url( 'admin-ajax.php' ); ?>
        </script>
    <?php } ?>
  </head>
<?php }
