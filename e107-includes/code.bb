global $pref, $e107cache, $tp;

if($pref['smiley_activate']) 
{
	if (!is_object($tp->e_emote))
	{
		require_once(e_HANDLER.'emote_filter.php');
		$tp->e_emote = new e_emoteFilter;
	}
	$code_text = $tp->e_emote->filterEmotesRev($code_text);
}

$search = array(E_NL,'&#092;','&#036;', '&lt;');
$replace = array("\r\n","\\",'$', '<');
$code_text = str_replace($search, $replace, $code_text);

if(varset($pref['useGeshi']) && file_exists(e_PLUGIN."geshi/geshi.php")) {

	$code_md5 = md5($code_text);
	if(!$CodeCache = $e107cache->retrieve('GeshiParsed_'.$code_md5)) {
		require_once(e_PLUGIN."geshi/geshi.php");
		if($parm) {
			$geshi = new GeSHi($code_text, $parm, e_PLUGIN."geshi/geshi/");
		} else {
			$geshi = new GeSHi($code_text, ($pref['defaultLanGeshi'] ? $pref['defaultLanGeshi'] : 'php'), e_PLUGIN."geshi/geshi/");
		}
		$geshi->line_style1 = "font-family: 'Courier New', Courier, monospace; font-weight: normal; font-style: normal;";
		$geshi->set_encoding(CHARSET);
		$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
		$geshi->set_header_type(GESHI_HEADER_DIV);
		$CodeCache = $geshi->parse_code();
		$e107cache->set('GeshiParsed_'.$code_md5, $CodeCache);
	}
	$ret = "<div class='code_highlight code-box' style='unicode-bidi: embed; direction: ltr'>".str_replace("&amp;", "&", $CodeCache)."</div>";
}
else
{
	$code_text = html_entity_decode($code_text, ENT_QUOTES, CHARSET);
	$highlighted_text = highlight_string($code_text, TRUE);
	$divClass = ($parm) ? $parm : 'code_highlight';
	$ret = "<div class='".$tp -> toAttribute($divClass)." code-box' style='unicode-bidi: embed; direction: ltr'>{$highlighted_text}</div>";
}
$ret = str_replace("[", "&#091;", $ret);
return $ret;