
global $pref;


if($pref['make_clickable'])
{

	if($parm)
	{
		list($p1,$p2) = explode("@",$parm);
		return "<a rel='external' href='javascript:window.location=\"mai\"+\"lto:\"+\"".$p1."\"+\"@\"+\"".$p2."\";self.close();' onmouseover='window.status=\"mai\"+\"lto:\"+\"".$p1."\"+\"@\"+\"".$p2."\"; return true;' onmouseout='window.status=\"\";return true;'>".$code_text."</a>";
	}
	else
	{
		list($p1,$p2) = explode("@",$code_text);
		$email_text = (CHARSET != "utf-8" && CHARSET != "UTF-8") ? $p1."&copy;".$p2 : $p1."©".$p2;
		return "<a rel='external' href='javascript:window.location=\"mai\"+\"lto:\"+\"".$p1."\"+\"@\"+\"".$p2."\";self.close();' onmouseover='window.status=\"mai\"+\"lto:\"+\"".$p1."\"+\"@\"+\"".$p2."\"; return true;' onmouseout='window.status=\"\";return true;'>".$email_text."</a>";
	}
}
// Old method that attracts SPAM.
if ($parm) {
  	return "<a href='mailto:".$tp -> toAttribute($parm)."'>".$code_text."</a>";
} else {
  	return "<a href='mailto:".$tp -> toAttribute($code_text)."'>".$code_text."</a>";
}