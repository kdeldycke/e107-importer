if($sep != '' || $parm != '') { return $full_text; }
$search = array("&quot;", "&#039;", "&#036;", '<br />', E_NL, "-&gt;");
$replace = array('"', "'", "$", "\n", "\n", "->");
$code_text = str_replace($search, $replace, $code_text);
return eval($code_text);
