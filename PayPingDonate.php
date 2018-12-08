<?php
/*
Plugin Name: افزونه حمایت مالی پی‌پینگ برای وردپرس
Version: 1.1
Description: افزونه حمایت مالی از وبسایت ها -- برای استفاده تنها کافی است کد زیر را درون بخشی از برگه یا نوشته خود قرار دهید  [PayPingDonate]
Plugin URI: https://www.payping.ir/
Author: Erfan Ebrahimi
Author URI: http://erfanebrahimi.ir/
*/

defined('ABSPATH') or die('Access denied!');
define ('TABLE_DONATE'  , 'payping_donate');

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if ( is_admin() )
{
	add_action('admin_menu', 'payPingDonate_AdminMenuItem');
	function payPingDonate_AdminMenuItem()
	{
		add_menu_page( 'تنظیمات افزونه حمایت مالی - پی‌پینگ', 'حمات مالی', 'administrator', 'payPingDonate_MenuItem', 'payPingDonate_MainPageHTML', '', 6 );
		add_submenu_page('payPingDonate_MenuItem','نمایش حامیان مالی','نمایش حامیان مالی', 'administrator','payPingDonate_Hamian','payPingDonate_HamianHTML');
	}
}

function payPingDonate_MainPageHTML()
{
	include('payPingDonate_AdminPage.php');
}

function payPingDonate_HamianHTML()
{
	include('payPingDonate_Hamian.php');
}


add_action( 'init', 'PayPingDonateShortcode');
function PayPingDonateShortcode(){
	add_shortcode('PayPingDonate', 'PayPingDonateForm');
}

function PayPingDonateForm() {
	$out = '';
	$error = '';
	$message = '';

	$MerchantID = get_option( 'payPingDonate_MerchantID');
	$payPingDonate_IsOK = get_option( 'payPingDonate_IsOK');
	$payPingDonate_IsError = get_option( 'payPingDonate_IsError');
	$payPingDonate_Unit = get_option( 'payPingDonate_Unit');

	$Amount = '';
	$Description = '';
	$Name = '';
	$Mobile = '';
	$Email = '';

	//////////////////////////////////////////////////////////
	//            REQUEST
	if(isset($_POST['submit']) && $_POST['submit'] == 'پرداخت')
	{


		if($MerchantID == '')
		{
			$error = 'کد دروازه پرداخت وارد نشده است' . "<br>\r\n";
		}


		$Amount = filter_input(INPUT_POST, 'payPingDonate_Amount', FILTER_SANITIZE_SPECIAL_CHARS);

		if(is_numeric($Amount) != false)
		{
			//Amount will be based on Toman  - Required
			if($payPingDonate_Unit == 'ریال')
				$SendAmount =  $Amount / 10;
			else
				$SendAmount =  $Amount;
		}
		else
		{
			$error .= 'مبلغ به درستی وارد نشده است' . "<br>\r\n";
		}

		$Description =    filter_input(INPUT_POST, 'payPingDonate_Description', FILTER_SANITIZE_SPECIAL_CHARS);  // Required
		$Name =           filter_input(INPUT_POST, 'payPingDonate_Name', FILTER_SANITIZE_SPECIAL_CHARS);  // Required
		$Mobile =         filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_SPECIAL_CHARS); // Optional
		$Email =          filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS); // Optional

		$SendDescription = $Name . ' | ' . $Mobile . ' | ' . $Email . ' | ' . $Description ;

		if($error == '') // اگر خطایی نباشد
		{


			$code = payPingDonate_AddDonate(array(
				'Name'          => $Name,
				'AmountTomaan'  => $SendAmount,
				'Mobile'        => $Mobile,
				'Email'         => $Email,
				'InputDate'     => current_time( 'mysql' ),
				'Description'   => $Description,
				'Status'        => 'SEND'
			),array(
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s'
			));
			$CallbackURL = payPingDonate_GetCallBackURL();  // Required
			$data = array('payerName'=>$Name, 'Amount' => $SendAmount,'payerIdentity'=> $Mobile , 'returnUrl' => $CallbackURL, 'Description' => $SendDescription , 'clientRefId' => $code  );
			try {
				$curl = curl_init();
				curl_setopt_array($curl, array(
						CURLOPT_URL => "https://api.payping.ir/v1/pay",
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => "",
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => 30,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => "POST",
						CURLOPT_POSTFIELDS => json_encode($data),
						CURLOPT_HTTPHEADER => array(
							"accept: application/json",
							"authorization: Bearer " .$MerchantID,
							"cache-control: no-cache",
							"content-type: application/json"),
					)
				);
				$response = curl_exec($curl);
				$header = curl_getinfo($curl);
				$err = curl_error($curl);
				curl_close($curl);
				if ($err) {
					echo "cURL Error #:" . $err;
				} else {
					if ($header['http_code'] == 200) {
						$response = json_decode($response, true);
						if (isset($response["code"]) and $response["code"] != '') {
							$url = sprintf('https://api.payping.ir/v1/pay/gotoipg/%s', $response["code"]) ;

								echo '<meta http-equiv="refresh" content="0;url='.$url.'"><script>window.location.replace("'.$url.'");</script>';
								exit;

						} else {
							$error .= ' تراکنش ناموفق بود- شرح خطا : عدم وجود کد ارجاع '. "<br>\r\n";
						}
					} elseif ($header['http_code'] == 400) {
						$error .= ' تراکنش ناموفق بود- شرح خطا : ' . implode('. ',array_values (json_decode($response,true))). "<br>\r\n" ;
					} else {
						$error .= ' تراکنش ناموفق بود- شرح خطا : ' . payPingDonate_GetResaultStatusString($header['http_code']) . '(' . $header['http_code'] . ')'. "<br>\r\n";
					}
				}
			} catch (Exception $e){
				$error .= ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage(). "<br>\r\n";
			}
		}
	}
	//// END REQUEST


	////////////////////////////////////////////////////
	///             RESPONSE
	if(isset($_GET['clientrefid']))
	{
		$id = $_GET['clientrefid'] ;
		$refid = $_GET['refid'] ;



		$Record = payPingDonate_GetDonate($id);
		if( $Record  === false)
		{
			$error .= 'چنین تراکنشی در سایت ثبت نشده است' . "<br>\r\n";
		}
		else
		{


			$data = array('refId' => $refid, 'amount' => $Record['AmountTomaan']);
			try {
				$curl = curl_init();
				curl_setopt_array($curl, array(
					CURLOPT_URL => "https://api.payping.ir/v1/pay/verify",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($data),
					CURLOPT_HTTPHEADER => array(
						"accept: application/json",
						"authorization: Bearer ".$MerchantID,
						"cache-control: no-cache",
						"content-type: application/json",
					),
				));
				$response = curl_exec($curl);
				$err = curl_error($curl);
				$header = curl_getinfo($curl);
				curl_close($curl);
				if ($err) {
					payPingDonate_ChangeStatus($id, 'ERROR');
					$error .= get_option( 'payPingDonate_IsError') . "<br>\r\n";
					$error .= 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$err. "<br>\r\n";
					payPingDonate_SetAuthority($id, $refid);
				} else {
					if ($header['http_code'] == 200) {
						$response = json_decode($response, true);
						if (isset($_GET["refid"]) and $_GET["refid"] != '') {
							payPingDonate_ChangeStatus($id, 'OK');
							payPingDonate_SetAuthority($id, $refid);
							$message .= get_option( 'payPingDonate_IsOk') . "<br>\r\n";
							$message .= 'کد پیگیری تراکنش:'. $refid . "<br>\r\n";
							$payPingDonate_TotalAmount = get_option("payPingDonate_TotalAmount");
							update_option("payPingDonate_TotalAmount" , $payPingDonate_TotalAmount + $Record['AmountTomaan']);
						} else {
							payPingDonate_ChangeStatus($id, 'ERROR');
							$error .= get_option( 'payPingDonate_IsError') . "<br>\r\n";
							payPingDonate_SetAuthority($id, $refid);
							$error .= 'متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' . payPingDonate_GetResaultStatusString($header['http_code']) . '(' . $header['http_code'] . ')' . "<br>\r\n";
						}
					} elseif ($header['http_code'] == 400) {
						payPingDonate_ChangeStatus($id, 'ERROR');
						$error .= get_option( 'payPingDonate_IsError') . "<br>\r\n";
						payPingDonate_SetAuthority($id, $refid);
						$error .= 'تراکنش ناموفق بود- شرح خطا : ' .  implode('. ',array_values (json_decode($response,true))) . "<br>\r\n";
					}  else {
						payPingDonate_ChangeStatus($id, 'ERROR');
						$error .= get_option( 'payPingDonate_IsError') . "<br>\r\n";
						payPingDonate_SetAuthority($id, $refid);
						$error .= ' تراکنش ناموفق بود- شرح خطا : ' . payPingDonate_GetResaultStatusString($header['http_code']) . '(' . $header['http_code'] . ')'. "<br>\r\n";
					}
				}
			} catch (Exception $e){
				payPingDonate_ChangeStatus($id, 'ERROR');
				$error .= get_option( 'payPingDonate_IsError') . "<br>\r\n";
				payPingDonate_SetAuthority($id, $refid);
				$error .= ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage(). "<br>\r\n";
			}

		}

	}
	///     END RESPONSE

	$style = '';

	if(get_option('payPingDonate_UseCustomStyle') == 'true')
	{
		$style = get_option('payPingDonate_CustomStyle');
	}
	else
	{
		$style = '#payPingDonate_MainForm {  width: 400px;  height: auto;  margin: 0 auto;  direction: rtl; }  #payPingDonate_Form {  width: 96%;  height: auto;  float: right;  padding: 10px 2%; }  #payPingDonate_Message,#payPingDonate_Error {  width: 90%;  margin-top: 10px;  margin-right: 2%;  float: right;  padding: 5px 2%;  border-right: 2px solid #006704;  background-color: #e7ffc5;  color: #00581f; }  #payPingDonate_Error {  border-right: 2px solid #790000;  background-color: #ffc9c5;  color: #580a00; }  .payPingDonate_FormItem {  width: 90%;  margin-top: 10px;  margin-right: 2%;  float: right;  padding: 5px 2%; }    .payPingDonate_FormLabel {  width: 35%;  float: right;  padding: 3px 0; }  .payPingDonate_ItemInput {  width: 64%;  float: left; }  .payPingDonate_ItemInput input {  width: 90%;  float: right;  border-radius: 3px;  box-shadow: 0 0 2px #00c4ff;  border: 0px solid #c0fff0;  font-family: inherit;  font-size: inherit;  padding: 3px 5px; }  .payPingDonate_ItemInput input:focus {  box-shadow: 0 0 4px #0099d1; }  .payPingDonate_ItemInput input.error {  box-shadow: 0 0 4px #ef0d1e; }  input.payPingDonate_Submit {  background: none repeat scroll 0 0 #2ea2cc;  border-color: #0074a2;  box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset, 0 1px 0 rgba(0, 0, 0, 0.15);  color: #fff;  text-decoration: none;  border-radius: 3px;  border-style: solid;  border-width: 1px;  box-sizing: border-box;  cursor: pointer;  display: inline-block;  font-size: 13px;  line-height: 26px;  margin: 0;  padding: 0 10px 1px;  margin: 10px auto;  width: 50%;  font: inherit;  float: right;  margin-right: 24%; }';
	}


	$out = '
  <style>
    '. $style . '
  </style>
      <div style="clear:both;width:100%;float:right;">
	        <div id="payPingDonate_MainForm">
          <div id="payPingDonate_Form">';

	if($message != '')
	{
		$out .= "<div id=\"payPingDonate_Message\">
    ${message}
            </div>";
	}

	if($error != '')
	{
		$out .= "<div id=\"payPingDonate_Error\">
    ${error}
            </div>";
	}

	$out .=      '<form method="post">
              <div class="payPingDonate_FormItem">
                <label class="payPingDonate_FormLabel">مبلغ :</label>
                <div class="payPingDonate_ItemInput">
                  <input style="width:60%" type="text" name="payPingDonate_Amount" value="'. $Amount .'" />
                  <span style="margin-right:10px;">'. $payPingDonate_Unit .'</span>
                </div>
              </div>
              
              <div class="payPingDonate_FormItem">
                <label class="payPingDonate_FormLabel">نام و نام خانوادگی :</label>
                <div class="payPingDonate_ItemInput"><input type="text" name="payPingDonate_Name" value="'. $Name .'" /></div>
              </div>
              
              <div class="payPingDonate_FormItem">
                <label class="payPingDonate_FormLabel">تلفن همراه :</label>
                <div class="payPingDonate_ItemInput"><input type="text" name="mobile" value="'. $Mobile .'" /></div>
              </div>
              
              <div class="payPingDonate_FormItem">
                <label class="payPingDonate_FormLabel">ایمیل :</label>
                <div class="payPingDonate_ItemInput"><input type="text" name="email" style="direction:ltr;text-align:left;" value="'. $Email .'" /></div>
              </div>
              
              <div class="payPingDonate_FormItem">
                <label class="payPingDonate_FormLabel">توضیحات :</label>
                <div class="payPingDonate_ItemInput"><input type="text" name="payPingDonate_Description" value="'. $Description .'" /></div>
              </div>
              
              <div class="payPingDonate_FormItem">
                <input type="submit" name="submit" value="پرداخت" class="payPingDonate_Submit" />
              </div>              
            </form>
          </div>
        </div>
      </div>
	';

	return $out;
}

/////////////////////////////////////////////////

register_activation_hook(__FILE__,'payPingDonate_install');
function payPingDonate_install()
{
	payPingDonate_CreateDatabaseTables();
}
function payPingDonate_CreateDatabaseTables()
{
	global $wpdb;
	$DonateTable = $wpdb->prefix . TABLE_DONATE;
	// Creat table
	$nazrezohoor = "CREATE TABLE IF NOT EXISTS `$DonateTable` (
					  `DonateID` int(11) NOT NULL AUTO_INCREMENT,
					  `Authority` varchar(50) NOT NULL,
					  `Name` varchar(50) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
					  `AmountTomaan` int(11) NOT NULL,
					  `Mobile` varchar(11) ,
					  `Email` varchar(50),
					  `InputDate` varchar(20),
					  `Description` varchar(100) CHARACTER SET utf8 COLLATE utf8_persian_ci,
					  `Status` varchar(5),
					  PRIMARY KEY (`DonateID`),
					  KEY `DonateID` (`DonateID`)
					) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
	dbDelta($nazrezohoor);
	// Other Options
	add_option("payPingDonate_TotalAmount", 0, '', 'yes');
	add_option("payPingDonate_TotalPayment", 0, '', 'yes');
	add_option("payPingDonate_IsOK", 'با تشکر پرداخت شما به درستی انجام شد.', '', 'yes');
	add_option("payPingDonate_IsError", 'متاسفانه پرداخت انجام نشد.', '', 'yes');

	$style = '#payPingDonate_MainForm {
  width: 400px;
  height: auto;
  margin: 0 auto;
  direction: rtl;
}

#payPingDonate_Form {
  width: 96%;
  height: auto;
  float: right;
  padding: 10px 2%;
}

#payPingDonate_Message,#payPingDonate_Error {
  width: 90%;
  margin-top: 10px;
  margin-right: 2%;
  float: right;
  padding: 5px 2%;
  border-right: 2px solid #006704;
  background-color: #e7ffc5;
  color: #00581f;
}

#payPingDonate_Error {
  border-right: 2px solid #790000;
  background-color: #ffc9c5;
  color: #580a00;
}

.payPingDonate_FormItem {
  width: 90%;
  margin-top: 10px;
  margin-right: 2%;
  float: right;
  padding: 5px 2%;
}

.payPingDonate_FormLabel {
  width: 35%;
  float: right;
  padding: 3px 0;
}

.payPingDonate_ItemInput {
  width: 64%;
  float: left;
}

.payPingDonate_ItemInput input {
  width: 90%;
  float: right;
  border-radius: 3px;
  box-shadow: 0 0 2px #00c4ff;
  border: 0px solid #c0fff0;
  font-family: inherit;
  font-size: inherit;
  padding: 3px 5px;
}

.payPingDonate_ItemInput input:focus {
  box-shadow: 0 0 4px #0099d1;
}

.payPingDonate_ItemInput input.error {
  box-shadow: 0 0 4px #ef0d1e;
}

input.payPingDonate_Submit {
  background: none repeat scroll 0 0 #2ea2cc;
  border-color: #0074a2;
  box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset, 0 1px 0 rgba(0, 0, 0, 0.15);
  color: #fff;
  text-decoration: none;
  border-radius: 3px;
  border-style: solid;
  border-width: 1px;
  box-sizing: border-box;
  cursor: pointer;
  display: inline-block;
  font-size: 13px;
  line-height: 26px;
  margin: 0;
  padding: 0 10px 1px;
  margin: 10px auto;
  width: 50%;
  font: inherit;
  float: right;
  margin-right: 24%;
}';
	add_option("payPingDonate_CustomStyle", $style, '', 'yes');
	add_option("payPingDonate_UseCustomStyle", 'false', '', 'yes');
}

function payPingDonate_GetDonate($id)
{
	global $wpdb;
	$id = strip_tags($wpdb->escape($id));


	if($id == '')
		return false;

	$DonateTable = $wpdb->prefix . TABLE_DONATE;

	$res = $wpdb->get_results( "SELECT * FROM ".$DonateTable." WHERE `DonateID` = ".$id." LIMIT 1",ARRAY_A);

	if(count($res) == 0)
		return false;
	return $res[0];
}

function payPingDonate_AddDonate($Data, $Format)
{
	global $wpdb;

	if(!is_array($Data))
		return false;

	$DonateTable = $wpdb->prefix . TABLE_DONATE;

	$res = $wpdb->insert( $DonateTable , $Data, $Format);

	if($res == 1)
	{
		$totalPay = get_option('payPingDonate_TotalPayment');
		$totalPay += 1;
		update_option('payPingDonate_TotalPayment', $totalPay);
	}

	return $wpdb->insert_id;
}

function payPingDonate_ChangeStatus($id,$Status)
{
	global $wpdb;
	$id = strip_tags($wpdb->escape($id));
	$Status = strip_tags($wpdb->escape($Status));

	if($id == '' || $Status == '')
		return false;

	$DonateTable = $wpdb->prefix . TABLE_DONATE;

	$res = $wpdb->query( "UPDATE ".$DonateTable." SET `Status` = '".$Status."' WHERE `DonateID` = '".$id."'");

	return $res;
}
function payPingDonate_SetAuthority($id,$Authority)
{
	global $wpdb;
	$id = strip_tags($wpdb->escape($id));
	$Authority = strip_tags($wpdb->escape($Authority));

	if($id == '' || $Authority == '')
		return false;

	$DonateTable = $wpdb->prefix . TABLE_DONATE;

	$res = $wpdb->query( "UPDATE ".$DonateTable." SET `Authority` = '".$Authority."' WHERE `DonateID` = '".$id."'");

	return $res;
}

function payPingDonate_GetResaultStatusString($StatusNumber)
{
	switch($StatusNumber) {
		case 200 :
			return 'عملیات با موفقیت انجام شد';
			break ;
		case 400 :
			return 'مشکلی در ارسال درخواست وجود دارد';
			break ;
		case 500 :
			return 'مشکلی در سرور رخ داده است';
			break;
		case 503 :
			return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
			break;
		case 401 :
			return 'عدم دسترسی';
			break;
		case 403 :
			return 'دسترسی غیر مجاز';
			break;
		case 404 :
			return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
			break;
	}

	return '';
}

function payPingDonate_GetCallBackURL()
{
	$pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";

	$ServerName = htmlspecialchars($_SERVER["SERVER_NAME"], ENT_QUOTES, "utf-8");
	$ServerPort = htmlspecialchars($_SERVER["SERVER_PORT"], ENT_QUOTES, "utf-8");
	$ServerRequestUri = htmlspecialchars($_SERVER["REQUEST_URI"], ENT_QUOTES, "utf-8");

	if ($_SERVER["SERVER_PORT"] != "80")
	{
		$pageURL .= $ServerName .":". $ServerPort . $_SERVER["REQUEST_URI"];
	}
	else
	{
		$pageURL .= $ServerName . $ServerRequestUri;
	}
	return $pageURL;
}

?>
