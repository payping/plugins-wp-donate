<?php 

defined('ABSPATH') or die('Access denied!');

if ( $_POST ) {
	
	if ( isset($_POST['payPingDonate_MerchantID']) ) {
		update_option( 'payPingDonate_MerchantID', $_POST['payPingDonate_MerchantID'] );
	}
	
	if ( isset($_POST['payPingDonate_IsOK']) ) {
		update_option( 'payPingDonate_IsOK', $_POST['payPingDonate_IsOK'] );
	}
  
	if ( isset($_POST['payPingDonate_IsError']) ) {
		update_option( 'payPingDonate_IsError', $_POST['payPingDonate_IsError'] );
	}
	
  if ( isset($_POST['payPingDonate_Unit']) ) {
		update_option( 'payPingDonate_Unit', $_POST['payPingDonate_Unit'] );
	}
  
  if ( isset($_POST['payPingDonate_UseCustomStyle']) ) {
		update_option( 'payPingDonate_UseCustomStyle', 'true' );
    
    if ( isset($_POST['payPingDonate_CustomStyle']) )
    {
      update_option( 'payPingDonate_CustomStyle', strip_tags($_POST['payPingDonate_CustomStyle']) );
    }
    
	}
  else
  {
    update_option( 'payPingDonate_UseCustomStyle', 'false' );
  }
  
	echo '<div class="updated" id="message"><p><strong>تنظیمات ذخیره شد</strong>.</p></div>';
	
}

?>
<h2 id="add-new-user">تنظیمات افزونه حمایت مالی - پی‌پینگ</h2>
<h2 id="add-new-user">جمع تمام پرداخت ها : <?php echo get_option("payPingDonate_TotalAmount"); ?>  تومان</h2>
<h2 id="add-new-user">برای استفاده تنها کافی است کد زیر را درون بخشی از برگه یا نوشته خود قرار دهید  [PayPingDonate]</h2>
<form method="post">
  <table class="form-table">
    <tbody>
      <tr class="user-first-name-wrap">
        <th><label for="payPingDonate_MerchantID">توکن</label></th>
        <td>
          <input type="text" class="regular-text" value="<?php echo get_option( 'payPingDonate_MerchantID'); ?>" id="payPingDonate_MerchantID" name="payPingDonate_MerchantID">
          <p class="description indicator-hint">توکن درگاه پی‌پینگ</p>
        </td>
      </tr>
      <tr>
        <th><label for="payPingDonate_IsOK">متن پرداخت موفق</label></th>
        <td><input type="text" class="regular-text" value="<?php echo get_option( 'payPingDonate_IsOK'); ?>" id="payPingDonate_IsOK" name="payPingDonate_IsOK"></td>
      </tr>
      <tr>
        <th><label for="payPingDonate_IsError">متن خطا در پرداخت</label></th>
        <td><input type="text" class="regular-text" value="<?php echo get_option( 'payPingDonate_IsError'); ?>" id="payPingDonate_IsError" name="payPingDonate_IsError"></td>
      </tr>
      
      <tr class="user-display-name-wrap">
        <th><label for="payPingDonate_Unit">واحد پول</label></th>
        <td>
          <?php $payPingDonate_Unit = get_option( 'payPingDonate_Unit'); ?>
          <select id="payPingDonate_Unit" name="payPingDonate_Unit">
            <option <?php if($payPingDonate_Unit == 'تومان' ) echo 'selected="selected"' ?>>تومان</option>
            <option <?php if($payPingDonate_Unit == 'ریال' ) echo 'selected="selected"' ?>>ریال</option>
          </select>
        </td>
      </tr>
      
      <tr class="user-display-name-wrap">
        <th>استفاده از استایل سفارشی</th>
        <td>
          <?php $payPingDonate_UseCustomStyle = get_option('payPingDonate_UseCustomStyle') == 'true' ? 'checked="checked"' : ''; ?>
          <input type="checkbox" name="payPingDonate_UseCustomStyle" id="payPingDonate_UseCustomStyle" value="true" <?php echo $payPingDonate_UseCustomStyle ?> /><label for="payPingDonate_UseCustomStyle">استفاده از استایل سفارشی برای فرم</label><br>
        </td>
      </tr>
      
      
      <tr class="user-display-name-wrap" id="payPingDonate_CustomStyleBox" <?php if(get_option('payPingDonate_UseCustomStyle') != 'true') echo 'style="display:none"'; ?>>
        <th>استایل سفارشی</th>
        <td>
          <textarea style="width: 90%;min-height: 400px;direction:ltr;" name="payPingDonate_CustomStyle" id="payPingDonate_CustomStyle"><?php echo get_option('payPingDonate_CustomStyle') ?></textarea><br>
        </td>
      </tr>
      
    </tbody>
  </table>
  <p class="submit"><input type="submit" value="به روز رسانی تنظیمات" class="button button-primary" id="submit" name="submit"></p>
</form>

<script>
  if(typeof jQuery == 'function')
  {
    jQuery("#payPingDonate_UseCustomStyle").change(function(){
      if(jQuery("#payPingDonate_UseCustomStyle").prop('checked') == true)
        jQuery("#payPingDonate_CustomStyleBox").show(500);
      else
        jQuery("#payPingDonate_CustomStyleBox").hide(500);
    });
  }
</script>

