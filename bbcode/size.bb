if(is_numeric($parm) && $parm > 0 && $parm < 38)
{
	return "<span style='font-size:{$parm}px'>$code_text</span>";
}
else
{
	return $code_text;
}
