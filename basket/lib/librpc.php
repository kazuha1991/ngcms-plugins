<?php

//
// Shipping cart RPC manipulations
//


function basket_rpc_manage($params){
	global $userROW, $DSlist, $mysql, $twig;

	LoadPluginLibrary('xfields', 'common');

	if (!is_array($params) || !isset($params['action']))
		return array('status' => 0, 'errorCode' => 1, 'errorText' => 'Activity mode is not set');

	$params = arrayCharsetConvert(1, $params);

	switch ($params['action']) {
		// **** ADD NEW ITEM INTO BASKET ****
		case 'add':
			$linked_ds = intval($params['ds']);
			$linked_id = intval($params['id']);
			$count     = intval($params['count']);

			// Check available DataSources
			if (!(in_array($linked_ds, array($DSlist['news'], $DSlist['#xfields:tdata'])))) {
				return array('status' => 0, 'errorCode' => 2, 'errorText' => 'Basket can be used only for NEWS');
			}

			// Check available DataSources
			if ($count < 1) {
				return array('status' => 0, 'errorCode' => 2, 'errorText' => 'Count should be positive');
			}

			// Check if linked item is available
			switch ($linked_ds) {
				case $DSlist['news']:
					// Retrieve news record
					$rec = $mysql->record("select * from ".prefix."_news where id = ".db_squote($linked_id));
					if (!is_array($rec)) {
						return array('status' => 0, 'errorCode' => 3, 'errorText' => 'Item [news] with ID ('.$linked_id.') is not found');
					}

					// DO ADD

					break;
				case $DSlist['#xfields:tdata']:
					// Retrieve XFields record
					$rec = $mysql->record("select * from ".prefix."_xfields where id = ".db_squote($linked_id));
					if (!is_array($rec)) {
						return array('status' => 0, 'errorCode' => 4, 'errorText' => 'Item [tdata] with ID ('.$linked_id.') is not found');
					}

					// Retrieve joined record (assume that it can be only news
					if ($rec['linked_ds'] != $DSlist['news']) {
						return array('status' => 0, 'errorCode' => 5, 'errorText' => 'Sorry, only news related XFields tables are supported now');
					}

					$nrec = $mysql->record("select * from ".prefix."_news where id = ".db_squote($rec['linked_id']));
					if (!is_array($nrec)) {
						return array('status' => 0, 'errorCode' => 6, 'errorText' => 'Item found, but related [news] is lost');
					}

					// DO ADD
					// * Generate title
					$btitle = pluginGetVariable('basket', 'ntable_itemname');

					// Get price
					if (pluginGetVariable('basket', 'ntable_price') && isset($rec['xfields_'.pluginGetVariable('basket', 'ntable_price')])) {
						$price = $rec['xfields_'.pluginGetVariable('basket', 'ntable_price')];
					} else {
						$price = 0;
					}

					$replace = array();
					$replace[0][]= '{title}';
					$replace[1][]= $nrec['title'];

					$xc = xf_configLoad();

					foreach ($xc['tdata'] as $k => $v) {
						$replace[0][]= '{x:'.$k.'}';
						$replace[1][]= $rec['xfields_'.$k];
					}

					$btitle = str_replace($replace[0], $replace[1], $btitle);

					// Check if now we're logged in and earlier we started filling basket before logging in
					if (is_array($userROW)) {
						$mysql->query("update ".prefix."_basket set user_id = ".db_squote($userROW['id'])." where (user_id = 0) and (cookie = ".db_squote($_COOKIE['ngTrackID']).")");
					}

					$mysql->query("insert into ".prefix."_basket (user_id, cookie, linked_ds, linked_id, title, price, count) values (".(is_array($userROW)?db_squote($userROW['id']):0).", ".db_squote($_COOKIE['ngTrackID']).", ".db_squote($DSlist['#xfields:tdata']).", ".db_squote($linked_id).", ".db_squote($btitle).", ".db_squote($price).", ".db_squote($count).") on duplicate key update price=".db_squote($price).", count = count+".db_squote($count));

					// ======== Prepare update of totals informer ========
					$filter = array();
					if (is_array($userROW)) {												$filter []= '(user_id = '.db_squote($userROW['id']).')';		}
					if (isset($_COOKIE['ngTrackID']) && ($_COOKIE['ngTrackID'] != '')) {	$filter []= '(cookie = '.db_squote($_COOKIE['ngTrackID']).')';	}

					$tCount = 0;
					$tPrice = 0;

					if (count($filter) && is_array($res = $mysql->record("select count(*) as count, sum(price*count) as price from ".prefix."_basket where ".join(" or ", $filter), 1))) {
						$tCount = $res['count'];
						$tPrice = $res['price'];
					}

					// ������� ����������
					$tVars = array(
						'count' 		=> $tCount,
						'price' 		=> $tPrice,
						'ajaxUpdate'	=> 1,
					);

					// ������� ������ � ����� ������
					$xt = $twig->loadTemplate('plugins/basket/total.tpl');
					return array('status' => 1, 'errorCode' => 0, 'data' => 'Item added into basket', 'update' => arrayCharsetConvert(0, $xt->render($tVars)));

					break;
			}
			break;
	}
	return array('status' => 1, 'errorCode' => 0, 'data'	 => 'OK, '.var_export($params, true));

}


function basket_rpc_demo($params) {
	return array('status' => 1, 'errorCode' => 0, 'data' => var_export($params, true));
}

//rpcRegisterFunction('plugin.cart.demo', 'cart_rpc_demo');
rpcRegisterFunction('plugin.basket.manage', 'basket_rpc_manage');

