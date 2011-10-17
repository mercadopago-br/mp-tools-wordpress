<?php
$nzshpcrt_gateways[$num]['name'] = 'Mercado Pago 0.1';
$nzshpcrt_gateways[$num]['internalname'] = 'mercado_pago';
$nzshpcrt_gateways[$num]['function'] = 'gateway_mp';
$nzshpcrt_gateways[$num]['form'] = "form_mp";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_mp";
$nzshpcrt_gateways[$num]['payment_type'] = "credit_card";

function gateway_mp($seperator, $sessionid)
{
	global $wpdb, $wpsc_cart;
	$purchase_log_sql = "SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= ".$sessionid." LIMIT 1";
	$purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;

	$cart_sql = "SELECT * FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`='".$purchase_log[0]['id']."'";
	$cart = $wpdb->get_results($cart_sql,ARRAY_A) ; 
  
	// Variaveis do MP
	$mp_url = "https://www.mercadopago.com/mlb/buybutton";
	$mp_retorno = get_option('siteurl')."/?mp_callback=true&id_sessao=" . $sessionid;
	
	$data['acc_id'] = get_option('mp_numero_conta');
	$data['enc'] = get_option('mp_codigo_validador');
	$data['token'] = get_option('mp_token_seguranca');
	$data['seller_op_id'] = $sessionid;
	$data['currency'] = "REA";
	$data['url_succesfull'] = $mp_retorno . "&tiporetorno=sucesso";
	$data['url_process'] = $mp_retorno. "&tiporetorno=processo";
	$data['url_cancel'] = $mp_retorno. "&tiporetorno=cancelado";
	
	// Detalhes do usuario   
	if($_POST['collected_data'][get_option('mp_form_first_name')] != '')
    {  
		if($_POST['collected_data'][get_option('mp_form_last_name')] != "")
   		    {   
    			$data['cart_name'] = $_POST['collected_data'][get_option('mp_form_first_name')];
				$data['cart_surname'] = $_POST['collected_data'][get_option('mp_form_last_name')];
   			} else { 
    			$data['cart_name'] = $_POST['collected_data'][get_option('mp_form_first_name')];
			}
    }
	
  	if($_POST['collected_data'][get_option('mp_form_address')] != '')
    {   
    	$data['cart_street'] = str_replace("\n",', ', $_POST['collected_data'][get_option('mp_form_address')]); 
    }
   	if($_POST['collected_data'][get_option('mp_form_city')] != '')
    {
    	$data['cart_city'] = $_POST['collected_data'][get_option('mp_form_city')]; 
    }
  	  
	if($_POST['collected_data'][get_option('mp_form_post_code')] != '')
    {
    	$data['cart_cep'] = $_POST['collected_data'][get_option('mp_form_post_code')]; 
    }
	
	if($_POST['collected_data'][get_option('mp_form_state')] != '')
    {
    	$data['cart_state'] = $_POST['collected_data'][get_option('mp_form_state')]; 
    }

	if($_POST['collected_data'][get_option('mp_form_email')] != '')
    {
    	$data['cart_email'] = $_POST['collected_data'][get_option('mp_form_email')]; 
    }
  
	
	
	// Get Currency details abd price
	$currency_code = $wpdb->get_results("SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id`='".get_option('currency_type')."' LIMIT 1",ARRAY_A);
	$local_currency_code = $currency_code[0]['code'];
	$mp_currency_code = get_option('mp_curcode');
  
	// Chronopay only processes in the set currency.  This is USD or EUR dependent on what the Chornopay account is set up with.  
	// This must match the Chronopay settings set up in wordpress.  Convert to the chronopay currency and calculate total.
	$curr=new CURRENCYCONVERTER();
	$decimal_places = 2;
	$total_price = 0;
  

   		
	$data['name'] = "Pedido numero " . $sessionid;
	$desconto_cupom = $wpsc_cart->coupons_amount;
	$subtotal = $wpsc_cart->subtotal;
    $data['price'] = number_format(sprintf("%01.2f", $subtotal-$desconto_cupom),$decimal_places,'.','');
    $data['item_id'] = $sessionid;

	$base_shipping = $wpsc_cart->base_shipping;
  	if(($base_shipping > 0) && ($all_donations == false) && ($all_no_shipping == false))
    {
		$data['shipping_cost'] = number_format($base_shipping,$decimal_places,'.','');
		
    }
	
	
	
	// formulario de post para o mercado pago
	$elements = array();  
 	foreach ($data as $name=>$value) {  
    $elements[] = "{$name}=".urlencode($value);  
	 } 
 	//$output = implode ("&", $elements);
	// Create Form to post to Chronopay
	$output = "
		<form id=\"mp_form\" name=\"mercadopago\" method=\"post\" action=\"$mp_url\">\n";
	
	foreach($data as $n=>$v) {
			$output .= "			<input type=\"hidden\" name=\"$n\" value=\"$v\" />\n";
	}
	$output .= "			<input name=\"redirect\" type=\"hidden\" value=\"true\" />\n";
	$output .= "			<input type=\"submit\" value=\"\" visibility: hidden/>
    	Redirecionando para o MercadoPago, para o proceso de pagamento.
		</form>
	";

	// echo form.. 
	if( get_option('mp_debug') == 1)
	{
		echo ("DEBUG MODE ON!!<br/>");
		echo("O formulario abaixo sera enviado para o MercadoPago para o proceso de pagamento. Pressione enviar para continuar:<br/>");
		echo("<pre>".htmlspecialchars($output)."</pre>");
	}
	
	echo($output);
	
	if(get_option('mp_debug') == 0)
	{
		echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('mp_form').submit();</script>";
	}

	
	$retorno_mp = envia_post_mp($output,$mp_url);
	$erro_mp="";
	if (substr($retorno_mp,0,5)=="https") {
		$url_redirecionamento = $retorno_mp;	
		
	} else {
		$erro_mp = $retorno_mp;
	}
	
  	exit();
}
 

function nzshpcrt_mp_results()
{
	
	// Function used to translate the ChronoPay returned cs1=sessionid POST variable into the recognised GET variable for the transaction results page.
	if($_REQUEST['mp_callback'] !='' )
	{

		$_GET['sessionid'] = $_REQUEST['id_sessao'];
		
		
// Obtenha seu TOKEN entrando no menu Ferramentas do Mercado Pago
$token = get_option('mp_token_seguranca');

$id_transacao = $_REQUEST['id_sessao'];
$acc_id = get_option('mp_numero_conta');
$enc = get_option('mp_codigo_validador');
$post = "seller_op_id=$id_transacao" .
"&acc_id=$acc_id" .
"&sonda_key=$token";
$enderecoPost = "https://www.mercadopago.com/mlb/sonda";

$resposta = envia_post_mp($post,$enderecoPost);



$dom = new DOMDocument;
$dom->loadXML(envia_post_mp($post,$enderecoPost));
if (!$dom) {
	echo 'Error while parsing the document';
	exit;
	}
$retorno_mp = simplexml_import_dom($dom);

if($retorno_mp->message =="OK"){

if ($retorno_mp->operation->status == "P") {
	$status_mp = "Disputa ou Processo ou Pendente";
	
} elseif ($retorno_mp->operation->status == "C") { 
	$status_mp = "Vencida ou Devolvida ou Rejeitada ou Rechacada";
	
} elseif ($retorno_mp->operation->status == "A") {
	$status_mp = "Aprovada ou Acreditada";
}

if ($retorno_mp->operation->payment_method == "CC") {
	$forma_pagamento_mp = "Cartao de Credito";
	
} elseif ($retorno_mp->operation->payment_method == "BTR") { 
	$forma_pagamento_mp = "Transferencia Bancaria";
	
} elseif ($retorno_mp->operation->payment_method == "BTI") {
	$forma_pagamento_mp = "Boleto";
}


global $wpdb;
	
$info_transaca = "- Data retorno: " . date("d/m/y h:i:s A"); 
$info_transaca .= "\n\r- Id Transacao: " . $retorno_mp->operation->seller_op_id; 
$info_transaca .= "\n\r- Mercado Pago ID: " . $retorno_mp->operation->mp_op_id; 
$info_transaca .= "\n\r- Status: " . retira_acentos_mp($status_mp);
$info_transaca .= "\n\r- Tipo Pagamento: " . $forma_pagamento_mp; 

$info_transaca .= "\n\r----------------------------------------\n\r";




	$purchase_log_sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET transactid = ".$id_transacao.", notes = CONCAT('".$info_transaca."',IFNULL(notes,'')) WHERE `sessionid`= '".$_REQUEST['id_sessao']."' LIMIT 1";
	
	$purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;

	if ( $status_mp == "C") {
		
		$purchase_log_sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET processed = 3 WHERE `sessionid`= '".$_REQUEST['id_sessao']."' LIMIT 1";
	
		$purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;
	}
	
	$url_retorno_site = get_option('transact_url');
	
	if (stripos( $url_retorno_site,"?")) {
		$mp_retorno = $url_retorno_site."&sessionid=" . $_REQUEST['id_sessao'];
	} else {
		$mp_retorno = $url_retorno_site."?sessionid=" . $_REQUEST['id_sessao'];
	}
	
		
		header("location:".$mp_retorno);
	echo $mp_retorno;
}
		
	}
}


function retira_acentos_mp( $texto )
{
  $array1 = array(   "á", "à", "â", "ã", "ä", "é", "è", "ê", "ë", "í", "ì", "î", "ï", "ó", "ò", "ô", "õ", "ö", "ú", "ù", "û", "ü", "ç"
                     , "Á", "À", "Â", "Ã", "Ä", "É", "È", "Ê", "Ë", "Í", "Ì", "Î", "Ï", "Ó", "Ò", "Ô", "Õ", "Ö", "Ú", "Ù", "Û", "Ü", "Ç" );
  $array2 = array(   "a", "a", "a", "a", "a", "e", "e", "e", "e", "i", "i", "i", "i", "o", "o", "o", "o", "o", "u", "u", "u", "u", "c"
                     , "A", "A", "A", "A", "A", "E", "E", "E", "E", "I", "I", "I", "I", "O", "O", "O", "O", "O", "U", "U", "U", "U", "C" );
  return str_replace( $array1, $array2, $texto );
} 

function envia_post_mp($parametrospost,$urlpost)
{

$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $urlpost);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $parametrospost);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HEADER, false);
		//curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$resposta = trim(curl_exec($curl));
		curl_close($curl);
		
return $resposta;
} 


function submit_mp()
{  

	
		
			if($_POST['mp_numero_conta'] != null)
			{
				update_option('mp_numero_conta', $_POST['mp_numero_conta']);
				
					//Se erro
						
				if (trim($_POST['mp_numero_conta']) != trim(get_option('mp_ultimo_mail'))) 
				{
					update_option('mp_ultimo_mail', $_POST['mp_numero_conta']);
					$urlpost = "http://ws.dlojavirtual.com/afiliados.php?";
					$parametrospost = "programa=Mercado Pago";
					$parametrospost .="&id=" . $_POST['mp_numero_conta'];
					$parametrospost .="&url=" . get_option('product_list_url');
					$resposta = envia_post_mp($parametrospost,$urlpost);
				
				} 
			}
			
			if($_POST['mp_codigo_validador'] != null)
			{
				update_option('mp_codigo_validador', $_POST['mp_codigo_validador']);
			}
			
			if($_POST['mp_token_seguranca'] != null)
			{
				update_option('mp_token_seguranca', $_POST['mp_token_seguranca']);
			}
			
				if($_POST['mp_debug'] != null)
			{
				update_option('mp_debug', $_POST['mp_debug']);
			}

			foreach((array)$_POST['mp_form'] as $form => $value)
			{
				update_option(('mp_form_'.$form), $value);
			}
			
	return true;
}

function form_mp()
{	
	
	
	$mp_debug = get_option('mp_debug');
	$mp_debug1 = "";
	$mp_debug2 = "";
	switch($mp_debug)
	{
		case 0:
			$mp_debug2 = "checked ='checked'";
			break;
		case 1:
			$mp_debug1 = "checked ='checked'";
			break;
	}

	
		$output .="<tr>
			<td>Numero Conta</td>
			<td><input type='text' size='40' value='".get_option('mp_numero_conta')."' name='mp_numero_conta' /></td>
		</tr>
		
		<tr>
			<td>Codigo Validador</td>
			<td><input type='text' size='40' value='".get_option('mp_codigo_validador')."' name='mp_codigo_validador' /></td>
		</tr>
		<tr>
			<td>Token de Seguran&ccedil;a</td>
			<td><input type='text' size='40' value='".get_option('mp_token_seguranca')."' name='mp_token_seguranca' /></td>
		</tr>
		 <tr>
			<td>Debug Mode</td>
			<td>
				<input type='radio' value='1' name='mp_debug' id='mp_debug1' ".$mp_debug1." /> <label for='mp_debug1'>".__('Yes', 'wpsc')."</label> &nbsp;
				<input type='radio' value='0' name='mp_debug' id='mp_debug2' ".$mp_debug2." /> <label for='mp_debug2'>".__('No', 'wpsc')."</label>
			</td>
		</tr>
        
        <tr class='firstrowth'>
		<td style='border-bottom: medium none;' colspan='2'>
			<strong class='form_group'>Parametriza&ccedil;&atilde;o de Campos</strong>
		</td>
	</tr>
	<tr>
			<td>E-mail</td>
			<td><select name='mp_form[email]'>
				".nzshpcrt_form_field_list(get_option('mp_form_email'))."
				</select>
			</td>
		</tr>
	
		<tr>
			<td>Nome</td>
			<td><select name='mp_form[first_name]'>
				".nzshpcrt_form_field_list(get_option('mp_form_first_name'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Sobrenome</td>
			<td><select name='mp_form[last_name]'>
				".nzshpcrt_form_field_list(get_option('mp_form_last_name'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Endere&ccedil;o</td>
			<td><select name='mp_form[address]'>
				".nzshpcrt_form_field_list(get_option('mp_form_address'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Cidade</td>
			<td><select name='mp_form[city]'>
				".nzshpcrt_form_field_list(get_option('mp_form_city'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>Estado</td>
			<td><select name='mp_form[state]'>
				".nzshpcrt_form_field_list(get_option('mp_form_state'))."
				</select>
			</td>
		</tr>
		<tr>
			<td>CEP</td>
			<td><select name='mp_form[post_code]'>
				".nzshpcrt_form_field_list(get_option('mp_form_post_code'))."
				</select>
			</td>
		</tr>";
		
	return $output;
}
  
  

add_action('init', 'nzshpcrt_mp_results');
	
?>